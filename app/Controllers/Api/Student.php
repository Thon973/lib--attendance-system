<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\AttendanceModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class Student extends BaseController
{
    protected $studentModel;
    protected $attendanceModel;

    public function __construct()
    {
        $this->studentModel = new StudentModel();
        $this->attendanceModel = new AttendanceModel();
    }

    /**
     * Student login endpoint
     * POST /api/student/login
     */
    public function login()
    {
        try {
            $studentNumber = $this->request->getPost('student_number');
            $password = $this->request->getPost('password');

            if (empty($studentNumber) || empty($password)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student number and password are required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            $student = $this->studentModel->where('student_number', $studentNumber)->first();

            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid student number or password'
                ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
            }

            if (!password_verify($password, $student['password'])) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid student number or password'
                ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
            }

            if ($student['status'] !== 'Active') {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Account is inactive. Please contact administrator.'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            // Check if this is the student's first login
            // We'll consider it first login if the password is the same as the student number
            $isFirstLogin = password_verify($studentNumber, $student['password']);

            // Log student login to audit log
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'LOGIN',
                'Student',
                $student['student_id'],
                "Student logged in: {$student['first_name']} {$student['last_name']} (#{$student['student_number']})",
                null,
                null,
                $student['student_id'],
                'Student'
            );

            // Return student data without sensitive information
            unset($student['password']);
            
            // Convert profile picture to base64 if exists
            if (!empty($student['profile_picture'])) {
                $student['profile_picture_base64'] = 'data:image/png;base64,' . base64_encode($student['profile_picture']);
            }
            
            // Remove binary data from response
            unset($student['profile_picture']);
            unset($student['qr_code']);
            
            // Ensure all IDs are strings for JSON
            if (isset($student['student_id'])) {
                $student['student_id'] = (string) $student['student_id'];
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Login successful',
                'student' => $student,
                'first_login' => $isFirstLogin // Add first login flag
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred during login: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get student profile
     * GET /api/student/profile/{student_id}
     */
    public function profile($studentId)
    {
        try {
            $student = $this->studentModel->find($studentId);

            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            // Remove sensitive information
            unset($student['password']);
            
            // Convert profile picture to base64 if exists
            if (!empty($student['profile_picture'])) {
                $student['profile_picture_base64'] = 'data:image/png;base64,' . base64_encode($student['profile_picture']);
            }
            
            // Remove binary data
            unset($student['profile_picture']);
            unset($student['qr_code']);
            
            // Ensure ID is string
            if (isset($student['student_id'])) {
                $student['student_id'] = (string) $student['student_id'];
            }

            return $this->response->setJSON([
                'status' => 'success',
                'student' => $student
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while fetching profile: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get student QR code
     * GET /api/student/qrcode/{student_id}
     * 
     * Returns QR code as binary PNG image data
     */
    public function getQrCode($studentId)
    {
        try {
            $db = \Config\Database::connect();
            
            // Direct query to retrieve LONGBLOB data
            $builder = $db->table('students');
            $builder->select('qr_code');
            $builder->where('student_id', $studentId);
            $query = $builder->get();
            
            if (!$query || $query->getNumRows() === 0) {
                // Return a proper JSON error
                return $this->response
                    ->setHeader('Content-Type', 'application/json')
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Student not found'
                    ])
                    ->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }
            
            $result = $query->getRowArray();
            
            if (empty($result['qr_code'])) {
                // Return a proper JSON error
                return $this->response
                    ->setHeader('Content-Type', 'application/json')
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'QR code not found for this student'
                    ])
                    ->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            // Get raw binary image data from LONGBLOB
            $imageData = $result['qr_code'];
            
            // Verify it's valid image data
            if (empty($imageData)) {
                // Return a proper JSON error
                return $this->response
                    ->setHeader('Content-Type', 'application/json')
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'QR code data is empty'
                    ])
                    ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Set appropriate headers for PNG image
            return $this->response
                ->setHeader('Content-Type', 'image/png')
                ->setHeader('Content-Length', strlen($imageData))
                ->setHeader('Cache-Control', 'public, max-age=3600')
                ->setBody($imageData);

        } catch (\Exception $e) {
            log_message('error', 'API QR Code retrieval error: ' . $e->getMessage());
            // Return JSON error instead
            return $this->response
                ->setHeader('Content-Type', 'application/json')
                ->setJSON([
                    'status' => 'error',
                    'message' => 'An error occurred while fetching QR code'
                ])
                ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get student attendance history
     * GET /api/student/history/{student_id}
     */
    public function attendanceHistory($studentId)
    {
        try {
            // Set timezone to app default
            date_default_timezone_set(config('App')->appTimezone);
            
            // Verify student exists
            $student = $this->studentModel->find($studentId);
            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            // Get attendance history with student information
            $history = $this->attendanceModel
                ->select('attendance.*, sections.section_name, courses.course_code, colleges.college_name, students.first_name, students.last_name, students.middle_initial, students.sex')
                ->join('sections', 'sections.section_id = attendance.section_id', 'left')
                ->join('courses', 'courses.course_id = attendance.course_id', 'left')
                ->join('colleges', 'colleges.college_id = attendance.college_id', 'left')
                ->join('students', 'students.student_id = attendance.student_id', 'left')
                ->where('attendance.student_id', $studentId)
                ->orderBy('scan_datetime', 'DESC')
                ->findAll();

            return $this->response->setJSON([
                'status' => 'success',
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while fetching attendance history: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update student profile
     * POST /api/student/update
     */
    public function updateProfile()
    {
        try {
            $input = $this->request->getJSON(true);
            
            $studentId = $input['student_id'] ?? null;
            $firstName = $input['first_name'] ?? null;
            $lastName = $input['last_name'] ?? null;
            $address = $input['address'] ?? null;
            $yearLevel = $input['year_level'] ?? null;
            $profilePicture = $input['profile_picture'] ?? null; // Base64 encoded image
            
            if (!$studentId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student ID is required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            // Verify student exists
            $student = $this->studentModel->find($studentId);
            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }

            // Prepare update data
            $updateData = [];
            if ($firstName) $updateData['first_name'] = $firstName;
            if ($lastName) $updateData['last_name'] = $lastName;
            if ($address !== null) $updateData['address'] = $address;
            if ($yearLevel) $updateData['year_level'] = $yearLevel;
            
            // Handle middle initial and sex if provided
            $middleInitial = $input['middle_initial'] ?? null;
            $sex = $input['sex'] ?? null;
            if ($middleInitial !== null) $updateData['middle_initial'] = $middleInitial;
            if ($sex !== null) $updateData['sex'] = $sex;
            
            // Handle password update if provided
            $password = $input['password'] ?? null;
            $confirmPassword = $input['confirm_password'] ?? null;
            if (!empty($password)) {
                if ($password !== $confirmPassword) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'message' => 'Password and confirmation do not match'
                    ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
                }
                if (strlen($password) < 6) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'message' => 'Password must be at least 6 characters.'
                    ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
                }
                $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            // Handle profile picture if provided
            if ($profilePicture) {
                // Decode base64 image (remove data:image prefix if present)
                $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $profilePicture);
                $imageData = base64_decode($base64Data);
                if ($imageData !== false) {
                    $updateData['profile_picture'] = $imageData;
                }
            }

            // Store old values for audit log
            $oldValues = [];
            foreach ($updateData as $key => $value) {
                $oldValues[$key] = $student[$key] ?? null;
            }

            // Update student record
            $this->studentModel->update($studentId, $updateData);

            // Log the action
            $auditLogModel = new \App\Models\AuditLogModel();
            $auditLogModel->logAction(
                'UPDATE_PROFILE',
                'Student',
                $studentId,
                "Student profile updated: {$student['student_number']} - {$student['first_name']} {$student['last_name']}",
                $oldValues,
                $updateData,
                $studentId, // user_id (student updating their own profile)
                'Student'  // user_type
            );

            // Fetch updated student data
            $updatedStudent = $this->studentModel->find($studentId);
            unset($updatedStudent['password']);
            
            // Convert profile picture to base64 if exists
            if (!empty($updatedStudent['profile_picture'])) {
                $updatedStudent['profile_picture_base64'] = 'data:image/png;base64,' . base64_encode($updatedStudent['profile_picture']);
            }
            
            // Remove binary data
            unset($updatedStudent['profile_picture']);
            unset($updatedStudent['qr_code']);
            
            // Ensure ID is string
            if (isset($updatedStudent['student_id'])) {
                $updatedStudent['student_id'] = (string) $updatedStudent['student_id'];
            }

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'student' => $updatedStudent
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'An error occurred while updating profile: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Student logout endpoint
     * POST /api/student/logout
     */
    public function logout()
    {
        try {
            $input = $this->request->getJSON(true);
            $studentId = $input['student_id'] ?? null;
            
            if (!$studentId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student ID is required'
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }
            
            // Get student info for logging
            $student = $this->studentModel->find($studentId);
            if (!$student) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Student not found'
                ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
            }
            
            // Log student logout to audit log
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'LOGOUT',
                'Student',
                $studentId,
                "Student logged out: {$student['first_name']} {$student['last_name']} (#{$student['student_number']})",
                null,
                null,
                $studentId,
                'Student'
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