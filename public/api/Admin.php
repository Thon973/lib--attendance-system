<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AttendanceModel;
use App\Models\StudentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Admin extends BaseController
{
    protected $adminModel;
    protected $attendanceModel;
    protected $studentModel;

    public function __construct()
    {
        $this->adminModel = new AdminModel();
        $this->attendanceModel = new AttendanceModel();
        $this->studentModel = new StudentModel();
    }

    /**
     * Admin login endpoint
     * POST /api/admin/login
     */
    public function login()
    {
        try {
            $email = $this->request->getPost('email');
            $password = $this->request->getPost('password');

            if (empty($email) || empty($password)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Email and password are required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            $admin = $this->adminModel->where('email', $email)->first();

            if (!$admin) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid email or password'
                ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
            }

            if (!password_verify($password, $admin['password'])) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid email or password'
                ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
            }

            if ($admin['status'] !== 'Active') {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Account is inactive. Please contact administrator.'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            // Update last login timestamp with app default timezone
            $appTimezone = config('App')->appTimezone;
            $localTime = new \DateTime('now', new \DateTimeZone($appTimezone));
            $this->adminModel->update($admin['admin_id'], [
                'last_login' => $localTime->format('Y-m-d H:i:s')
            ]);

            // Return admin data without sensitive information
            unset($admin['password']);
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Login successful',
                'admin' => $admin
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred during login: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Scan QR code and record attendance
     * POST /api/admin/scan
     */
    public function scanAttendance()
    {
        try {
            $input = $this->request->getJSON(true);
            
            $adminId = $input['admin_id'] ?? null;
            $studentNumber = $input['student_number'] ?? null;

            if (!$adminId || !$studentNumber) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin ID and student number are required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            // Verify admin exists and is active
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            if ($admin['status'] !== 'Active') {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin account is inactive'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            // Get admin's section
            $sectionId = $admin['section_id'];
            if (!$sectionId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin is not assigned to a section'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            // Find student by student number
            $student = $this->studentModel
                ->select('students.student_id, students.student_number, students.first_name, students.last_name, students.middle_initial, students.sex, students.course_id, students.college_id, students.year_level, students.address, students.status, courses.course_id, courses.college_id')
                ->join('courses', 'courses.course_id = students.course_id', 'left')
                ->where('student_number', $studentNumber)
                ->first();

            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            // Check if student has scanned within the last 10 minutes
            $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
            
            $recentAttendance = $this->attendanceModel
                ->where('student_id', $student['student_id'])
                ->where('section_id', $sectionId)
                ->where('scan_datetime >=', $tenMinutesAgo)
                ->orderBy('scan_datetime', 'DESC')
                ->first();

            if ($recentAttendance) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student has already scanned within the last 10 minutes'
                ])->setStatusCode(ResponseInterface::HTTP_TOO_MANY_REQUESTS);
            }

            // Record attendance with app default timezone
            $appTimezone = config('App')->appTimezone;
            $localTime = new \DateTime('now', new \DateTimeZone($appTimezone));
            $attendanceId = $this->attendanceModel->insert([
                'student_id'   => $student['student_id'],
                'admin_id'     => $adminId,
                'section_id'   => $sectionId,
                'course_id'    => $student['course_id'] ?? null,
                'college_id'   => $student['college_id'] ?? null,
                'scan_datetime' => $localTime->format('Y-m-d H:i:s')
            ]);

            if (!$attendanceId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to record attendance'
                ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Attendance recorded successfully',
                'attendance_id' => $attendanceId,
                'student' => [
                    'student_id' => $student['student_id'],
                    'student_number' => $student['student_number'],
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'middle_initial' => $student['middle_initial'] ?? null,
                    'sex' => $student['sex'] ?? null,
                    'course_id' => $student['course_id'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while recording attendance: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}