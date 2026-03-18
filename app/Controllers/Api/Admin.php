<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AttendanceModel;
use App\Models\StudentModel;
use App\Models\AuditLogModel;
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

            // Get admin with section information by joining with sections table
            $admin = $this->adminModel
                ->select('admins.*, sections.section_name')
                ->join('sections', 'sections.section_id = admins.section_id', 'left')
                ->where('email', $email)
                ->first();

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

            // Update last login timestamp
            $this->adminModel->update($admin['admin_id'], [
                'last_login' => date('Y-m-d H:i:s')
            ]);

            // Log admin login to audit log
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'LOGIN',
                'Admin',
                $admin['admin_id'],
                "Admin logged in: {$admin['full_name']} ({$admin['email']})",
                null,
                ['role' => $admin['role']]
            );

            // Return admin data without sensitive information
            unset($admin['password']);
            
            // Split full_name into first_name and last_name for frontend compatibility
            $fullName = $admin['full_name'] ?? '';
            $nameParts = explode(' ', $fullName, 2);
            $admin['first_name'] = $nameParts[0] ?? '';
            $admin['last_name'] = $nameParts[1] ?? '';
            
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
     * Scan QR code and record attendance with 10-minute restriction
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
                ->where('student_number', $studentNumber)
                ->first();

            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found with ID: ' . $studentNumber
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            if ($student['status'] !== 'Active') {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student account is inactive'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            // Set timezone to app default
            date_default_timezone_set(config('App')->appTimezone);
            
            // Check if student has scanned within the last 10 minutes for THIS SECTION
            $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
            
            $recentAttendance = $this->attendanceModel
                ->where('student_id', $student['student_id'])
                ->where('section_id', $sectionId)
                ->where('scan_datetime >=', $tenMinutesAgo)
                ->orderBy('scan_datetime', 'DESC')
                ->first();

            if ($recentAttendance) {
                $lastScan = strtotime($recentAttendance['scan_datetime']);
                $currentTime = time();
                $minutesPassed = floor(($currentTime - $lastScan) / 60);
                $minutesLeft = 10 - $minutesPassed;
                
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student has already scanned in this section. Please wait ' . $minutesLeft . ' minutes before scanning again.'
                ])->setStatusCode(ResponseInterface::HTTP_TOO_MANY_REQUESTS);
            }

            // Record attendance
            // Set timezone to app default
            date_default_timezone_set(config('App')->appTimezone);
            $attendanceData = [
                'student_id'   => $student['student_id'],
                'admin_id'     => $adminId,
                'section_id'   => $sectionId,
                'course_id'    => $student['course_id'] ?? null,
                'college_id'   => $student['college_id'] ?? null,
                'scan_datetime' => date('Y-m-d H:i:s')
            ];

            $attendanceId = $this->attendanceModel->insert($attendanceData);

            if (!$attendanceId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to record attendance'
                ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Get the recorded attendance
            $recordedAttendance = $this->attendanceModel
                ->select('attendance.*, sections.section_name')
                ->join('sections', 'sections.section_id = attendance.section_id', 'left')
                ->find($attendanceId);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Attendance recorded successfully',
                'attendance_id' => $attendanceId,
                'scan_time' => $attendanceData['scan_datetime'],
                'student' => [
                    'student_id' => $student['student_id'],
                    'student_number' => $student['student_number'],
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'middle_initial' => $student['middle_initial'] ?? null,
                    'sex' => $student['sex'] ?? null,
                    'course' => $student['course'] ?? null,
                    'year_level' => $student['year_level'] ?? null
                ],
                'attendance' => $recordedAttendance
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while recording attendance: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Get attendance statistics (optional - for dashboard)
     * GET /api/admin/stats/{admin_id}
     */
    public function getStats($adminId)
    {
        try {
            // Set timezone to app default
            date_default_timezone_set(config('App')->appTimezone);
            
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            $today = date('Y-m-d');
            $sectionId = $admin['section_id'];

            // Get today's attendance count
            $todayCount = $this->attendanceModel
                ->where('admin_id', $adminId)
                ->where('DATE(scan_datetime)', $today)
                ->countAllResults();

            // Get total attendance count
            $totalCount = $this->attendanceModel
                ->where('admin_id', $adminId)
                ->countAllResults();

            // Get unique students scanned today
            $uniqueStudents = $this->attendanceModel
                ->select('COUNT(DISTINCT student_id) as unique_count')
                ->where('admin_id', $adminId)
                ->where('DATE(scan_datetime)', $today)
                ->first();

            return $this->response->setJSON([
                'status' => 'success',
                'stats' => [
                    'today_scans' => $todayCount,
                    'total_scans' => $totalCount,
                    'unique_students_today' => $uniqueStudents['unique_count'] ?? 0,
                    'section_id' => $sectionId
                ]
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while fetching stats: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Admin logout endpoint
     * POST /api/admin/logout
     */
    public function logout()
    {
        try {
            $input = $this->request->getJSON(true);
            $adminId = $input['admin_id'] ?? null;
            
            if (!$adminId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin ID is required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }
            
            // Get admin info for logging
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Admin not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }
            
            // Log admin logout to audit log
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'LOGOUT',
                'Admin',
                $adminId,
                "Admin logged out: {$admin['full_name']} ({$admin['email']})",
                null,
                null
            );
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Logout successful'
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred during logout: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}