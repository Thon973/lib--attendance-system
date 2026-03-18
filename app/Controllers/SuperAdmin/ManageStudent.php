<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\CourseModel;
use App\Models\CollegeModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ManageStudent extends BaseController
{
    // ===== MANAGE STUDENT VIEW =====
    public function manageStudent()
    {
        return view('superadmin/manage-student');
    }

    // ===== GET STUDENTS (with course & college info) =====
    public function getStudents()
    {
        $db = \Config\Database::connect();
        
        // Get filter parameters from GET request
        $collegeId = $this->request->getGet('college_id');
        $courseId = $this->request->getGet('course_id');
        $yearLevel = $this->request->getGet('year_level');
        $status = $this->request->getGet('status');
        $sex = $this->request->getGet('sex');
        
        // Normalize empty values
        $collegeId = (!empty($collegeId) && $collegeId !== '') ? (int)$collegeId : null;
        $courseId = (!empty($courseId) && $courseId !== '') ? (int)$courseId : null;
        $yearLevel = (!empty($yearLevel) && $yearLevel !== '') ? $yearLevel : null;
        $status = (!empty($status) && $status !== '') ? $status : null;
        $sex = (!empty($sex) && $sex !== '') ? $sex : null;
        
        // Exclude qr_code (LONGBLOB) from SELECT to avoid JSON encoding issues
        // We'll check if QR code exists separately
        $builder = $db->table('students');
        $builder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, students.course_id, students.college_id, students.year_level, students.address, students.status, students.created_by, students.created_at, courses.course_name, courses.course_code, colleges.college_name');
        $builder->join('courses', 'courses.course_id = students.course_id', 'left');
        $builder->join('colleges', 'colleges.college_id = students.college_id', 'left');
        
        // Apply filters if provided
        if ($collegeId !== null) {
            $builder->where('students.college_id', $collegeId);
        }
        
        if ($courseId !== null) {
            $builder->where('students.course_id', $courseId);
        }
        
        if ($yearLevel !== null) {
            $builder->where('students.year_level', $yearLevel);
        }
        
        if ($status !== null) {
            $builder->where('students.status', $status);
        }
        
        if ($sex !== null) {
            $builder->where('students.sex', $sex);
        }
        
        $builder->orderBy('students.student_id', 'DESC');
        $query = $builder->get();
        $students = $query ? $query->getResultArray() : [];

        // Check which students have QR codes (without loading the binary data)
        $studentIds = array_column($students, 'student_id');
        $qrCheckBuilder = $db->table('students');
        $qrCheckBuilder->select('student_id');
        $qrCheckBuilder->whereIn('student_id', $studentIds);
        $qrCheckBuilder->where('qr_code IS NOT NULL');
        $qrCheckQuery = $qrCheckBuilder->get();
        $hasQrCodes = [];
        if ($qrCheckQuery) {
            foreach ($qrCheckQuery->getResultArray() as $row) {
                $hasQrCodes[$row['student_id']] = true;
            }
        }

        // Add qr_code flag to each student
        foreach ($students as &$student) {
            $student['qr_code'] = isset($hasQrCodes[$student['student_id']]);
        }

        return $this->response->setJSON($students);
    }

    // ===== GET STUDENTS BY SEX FILTER =====
    public function getStudentsBySex($sex = null)
    {
        if ($sex === null) {
            return $this->getStudents();
        }
        
        $db = \Config\Database::connect();
        
        // Exclude qr_code (LONGBLOB) from SELECT to avoid JSON encoding issues
        // We'll check if QR code exists separately
        $builder = $db->table('students');
        $builder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, students.course_id, students.college_id, students.year_level, students.address, students.status, students.created_by, students.created_at, courses.course_name, courses.course_code, colleges.college_name');
        $builder->join('courses', 'courses.course_id = students.course_id', 'left');
        $builder->join('colleges', 'colleges.college_id = students.college_id', 'left');
        $builder->where('students.sex', $sex);
        $builder->orderBy('students.student_id', 'DESC');
        $query = $builder->get();
        $students = $query ? $query->getResultArray() : [];

        // Check which students have QR codes (without loading the binary data)
        $studentIds = array_column($students, 'student_id');
        $qrCheckBuilder = $db->table('students');
        $qrCheckBuilder->select('student_id');
        $qrCheckBuilder->whereIn('student_id', $studentIds);
        $qrCheckBuilder->where('qr_code IS NOT NULL');
        $qrCheckQuery = $qrCheckBuilder->get();
        $hasQrCodes = [];
        if ($qrCheckQuery) {
            foreach ($qrCheckQuery->getResultArray() as $row) {
                $hasQrCodes[$row['student_id']] = true;
            }
        }

        // Add qr_code flag to each student
        foreach ($students as &$student) {
            $student['qr_code'] = isset($hasQrCodes[$student['student_id']]);
        }

        return $this->response->setJSON($students);
    }

    public function stdColleges()
    {
        $collegeModel = new CollegeModel();
        $colleges = $collegeModel
            ->select('college_id, college_name, college_code')
            ->orderBy('college_code', 'ASC')
            ->findAll();

        return $this->response->setJSON($colleges);
    }

    public function stdCourses($collegeId = null)
    {
        if ($collegeId === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'College ID is required.',
            ]);
        }

        $courseModel = new CourseModel();
        $courses = $courseModel
            ->select('courses.course_id, courses.course_name, courses.course_code, colleges.college_name')
            ->join('colleges', 'colleges.college_id = courses.college_id', 'left')
            ->where('courses.college_id', $collegeId)
            ->orderBy('courses.course_code', 'ASC')
            ->findAll();

        return $this->response->setJSON($courses);
    }

    // ===== UPDATE STUDENT STATUS =====
    public function updateStudentStatus($id)
    {
        $studentModel = new StudentModel();

        // Check if student exists
        $student = $studentModel->find($id);
        if (!$student) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Student not found'
            ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
        }

        // Get input JSON
        $input = $this->request->getJSON(true);
        $status = $input['status'] ?? null;

        // Validate status
        if (!$status || !in_array($status, ['Active', 'Inactive'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid status'
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Store old status for audit log
        $oldStatus = $student['status'] ?? 'Active';

        // Attempt to update
        try {
            $studentModel->update($id, ['status' => $status]);
            
            // Log the action
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'UPDATE_STUDENT_STATUS',
                'Student',
                $id,
                "Student status updated: {$student['student_number']} - {$student['last_name']}, {$student['first_name']}" . ($student['middle_initial'] ? " {$student['middle_initial']}." : "") . " ({$oldStatus} → {$status})",
                ['status' => $oldStatus],
                ['status' => $status]
            );
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== ADD STUDENT + GENERATE QR CODE (Endroid v6 compatible) =====
    public function addStudent()
    {
        helper(['form', 'url']);
        $studentModel = new StudentModel();

        $adminId = session()->get('admin_id');
        if (!$adminId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Session expired. Please log in again.',
            ]);
        }

        $studentNumber = $this->request->getPost('student_number');
        $firstName = $this->request->getPost('first_name');
        $lastName = $this->request->getPost('last_name');
        $collegeId = $this->request->getPost('college_id');
        $courseId = $this->request->getPost('course_id');
        $yearLevel = $this->request->getPost('year_level');
        $address = trim($this->request->getPost('address'));

        if (empty($studentNumber) || empty($firstName) || empty($lastName)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Please fill in all required fields.',
            ]);
        }

        if ($studentModel->where('student_number', $studentNumber)->first()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Student number already exists.',
            ]);
        }

        // Prepare data for insert
        $data = [
            'student_number' => $studentNumber,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'sex'            => $this->request->getPost('sex') ?: null,
            'middle_initial' => $this->request->getPost('middle_initial') ?: null,
            'password'       => password_hash($studentNumber, PASSWORD_DEFAULT),
            'college_id'     => $collegeId ?: null,
            'course_id'      => $courseId ?: null,
            'year_level'     => $yearLevel,
            'address'        => $address ?: null,
            'status'         => 'Active',
            'created_by'     => $adminId,
            'qr_code'        => null,
        ];

        // Prepare QR content with ID, Student No, Name, Course
        $courseModel = new CourseModel();
        $course = $courseId ? $courseModel->find($courseId) : null;
        $courseCode = $course['course_code'] ?? 'N/A';
        if ($courseCode === '' || $courseCode === null) {
            $courseCode = 'N/A';
        }

        // Generate QR code as binary data
        $qrBinary = $this->generateStudentQr(0, $studentNumber, $firstName, $lastName, $courseCode);
        
        // Update college_id if not set but course has college_id
        if (!$data['college_id'] && !empty($course['college_id'])) {
            $data['college_id'] = $course['college_id'];
        }

        // Insert into DB & get ID (without qr_code first)
        $studentId = $studentModel->insert($data, true);
        
        // Update QR code separately using raw query to handle binary data properly
        if ($studentId && !empty($qrBinary)) {
            $db = \Config\Database::connect();
            $db->query("UPDATE students SET qr_code = ? WHERE student_id = ?", [$qrBinary, $studentId]);
        }

        if (!$studentId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to add student. Please try again.',
            ]);
        }

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'CREATE_STUDENT',
            'Student',
            $studentId,
            "Student added: {$studentNumber} - {$firstName} {$lastName}",
            null,
            ['student_number' => $studentNumber, 'name' => "{$firstName} {$lastName}", 'course_code' => $courseCode]
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Student added successfully with default password and QR code.',
        ]);
    }

    // Get single student (for Edit)
    public function get_student($id)
    {
        $db = \Config\Database::connect();
        
        // Select all fields except qr_code (LONGBLOB) to avoid JSON encoding issues
        $builder = $db->table('students');
        $builder->select('student_id, student_number, first_name, last_name, sex, middle_initial, course_id, college_id, year_level, address, status, created_by, created_at');
        $builder->where('student_id', $id);
        $query = $builder->get();
        $student = $query ? $query->getRowArray() : null;

        if ($student) {
            // Check if QR code exists (without loading binary data)
            $qrCheck = $db->table('students')
                ->select('student_id')
                ->where('student_id', $id)
                ->where('qr_code IS NOT NULL')
                ->get()
                ->getRowArray();
            
            $student['qr_code'] = !empty($qrCheck);
            
            return $this->response->setJSON([
                'status' => 'success',
                'student' => $student
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Student not found.'
            ]);
        }
    }

    // Update student info
    public function update_student($id)
    {
        $studentModel = new StudentModel();
        $student = $studentModel->find($id);

        if (!$student) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Student not found.'
            ]);
        }

        // Store old values for audit log
        $oldValues = [
            'student_number' => $student['student_number'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'sex' => $student['sex'] ?? null,
            'middle_initial' => $student['middle_initial'] ?? null,
            'course_id' => $student['course_id'],
            'college_id' => $student['college_id'],
            'year_level' => $student['year_level'],
            'address' => $student['address'] ?? null,
            'status' => $student['status'] ?? 'Active'
        ];

        $data = [
            'student_number' => $this->request->getPost('student_number'),
            'first_name'     => $this->request->getPost('first_name'),
            'last_name'      => $this->request->getPost('last_name'),
            'sex'            => $this->request->getPost('sex') ?: null,
            'middle_initial' => $this->request->getPost('middle_initial') ?: null,
            'college_id'     => $this->request->getPost('college_id') ?: null,
            'course_id'      => $this->request->getPost('course_id') ?: null,
            'year_level'     => $this->request->getPost('year_level'),
            'address'        => trim($this->request->getPost('address')) ?: null,
        ];

        $status = $this->request->getPost('status');
        if ($status !== null && $status !== '') {
            $data['status'] = $status;
        }

        // Handle password update if provided
        $password = $this->request->getPost('password');
        $confirmPassword = $this->request->getPost('confirm_password');
        
        if (!empty($password)) {
            if ($password !== $confirmPassword) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Password and confirmation do not match.'
                ]);
            }
            if (strlen($password) < 6) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Password must be at least 6 characters.'
                ]);
            }
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $courseModel = new CourseModel();
        $course = $data['course_id'] ? $courseModel->find($data['course_id']) : null;
        $courseCode = $course['course_code'] ?? 'N/A';
        if ($courseCode === '' || $courseCode === null) {
            $courseCode = 'N/A';
        }

        if (!$data['college_id'] && !empty($course['college_id'])) {
            $data['college_id'] = $course['college_id'];
        }

        // Generate QR code as binary data
        $newQrBinary = $this->generateStudentQr(
            (int) $id,
            $data['student_number'],
            $data['first_name'],
            $data['last_name'],
            $courseCode
        );

        // Update student data (without qr_code)
        $studentModel->update($id, $data);
        
        // Update QR code separately using raw query to handle binary data properly
        if (!empty($newQrBinary)) {
            $db = \Config\Database::connect();
            $db->query("UPDATE students SET qr_code = ? WHERE student_id = ?", [$newQrBinary, $id]);
        }

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'UPDATE_STUDENT',
            'Student',
            $id,
            "Student updated: {$data['student_number']} - {$data['last_name']}, {$data['first_name']}" . ($data['middle_initial'] ? " {$data['middle_initial']}.": ""),
            $oldValues,
            $data
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Student updated successfully.'
        ]);
    }

    // Delete student
    public function delete_student($id)
    {
        $studentModel = new StudentModel();
        $student = $studentModel->find($id);

        if (!$student) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Student not found.'
            ]);
        }

        // Store student info for audit log before deletion
        $studentInfo = [
            'student_number' => $student['student_number'],
            'name' => "{$student['last_name']}, {$student['first_name']}" . ($student['middle_initial'] ? " {$student['middle_initial']}.": "")
        ];

        // QR code is now stored in database, no file to delete
        $studentModel->delete($id);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'DELETE_STUDENT',
            'Student',
            $id,
            "Student deleted: {$student['student_number']} - {$student['last_name']}, {$student['first_name']}" . ($student['middle_initial'] ? " {$student['middle_initial']}.": ""),
            $studentInfo,
            null
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Student deleted successfully.'
        ]);
    }

    public function importStudents()
    {
        helper(['form', 'url']);

        $adminId = session()->get('admin_id');
        if (!$adminId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
            ])->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $file = $this->request->getFile('import_file');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No file uploaded or file is invalid.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->getClientExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv', 'pdf'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unsupported file type. Please upload an Excel (.xlsx, .xls), CSV, or PDF file.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Validate file size (max 30MB)
        $maxSize = 30 * 1024 * 1024; // 30MB in bytes
        if ($file->getSize() > $maxSize) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'File size exceeds the maximum limit of 30MB.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        $uploadDir = WRITEPATH . 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $tempName = $file->getRandomName();
        $file->move($uploadDir, $tempName);
        $filePath = $uploadDir . $tempName;

        $studentModel = new StudentModel();
        $courseModel = new CourseModel();

        try {
            // Handle PDF files (limited support - requires text extraction)
            if ($extension === 'pdf') {
                @unlink($filePath);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'PDF import is not currently supported. Please convert your PDF to Excel (.xlsx) or CSV format. PDF files are not structured data files and cannot be reliably parsed.',
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            // Check for ZipArchive extension (required for .xlsx files)
            if (in_array($extension, ['xlsx', 'xls'], true) && !class_exists('ZipArchive')) {
                @unlink($filePath);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'PHP ZipArchive extension is not enabled. Please enable the php_zip extension in your PHP configuration, or use CSV format instead. Contact your server administrator to enable the zip extension.',
                ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
            }

            // Load Excel/CSV file
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            @unlink($filePath);
            
            // Provide more helpful error messages
            $errorMessage = $e->getMessage();
            $userMessage = 'Unable to read the uploaded file. Please check the format.';
            
            if (strpos($errorMessage, 'ZipArchive') !== false || strpos($errorMessage, 'zip') !== false) {
                $userMessage = 'PHP ZipArchive extension is not enabled. Please enable the php_zip extension in your PHP configuration, or use CSV format instead. Contact your server administrator.';
            } elseif (strpos($errorMessage, 'XML') !== false) {
                $userMessage = 'The file appears to be corrupted or in an unsupported format. Please ensure it is a valid Excel (.xlsx, .xls) or CSV file.';
            }
            
            return $this->response->setJSON([
                'success' => false,
                'message' => $userMessage,
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            @unlink($filePath);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'The uploaded file does not contain any data rows.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Helper function to normalize column names for flexible matching
        $normalizeColumnName = function($name) {
            // Convert to lowercase, trim, and replace spaces/underscores/dashes with underscores
            $normalized = strtolower(trim((string)$name));
            $normalized = preg_replace('/[\s\-_]+/', '_', $normalized);
            $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized); // Remove special characters
            return $normalized;
        };

        // Helper function to find column by multiple possible names
        $findColumn = function($possibleNames, $columns) use ($normalizeColumnName) {
            foreach ($possibleNames as $name) {
                $normalized = $normalizeColumnName($name);
                // Direct match
                if (isset($columns[$normalized])) {
                    return $columns[$normalized];
                }
                // Try matching against all column keys
                foreach ($columns as $colKey => $colIndex) {
                    if ($normalizeColumnName($colKey) === $normalized) {
                        return $colIndex;
                    }
                }
            }
            return null;
        };

        // Read header row and map all column names with flexible matching
        // Extra columns beyond the required/optional ones will be automatically ignored
        $headerRow = array_shift($rows);
        $columns = [];
        foreach ($headerRow as $col => $value) {
            if ($value === null || trim((string)$value) === '') {
                continue;
            }
            $normalized = $normalizeColumnName($value);
            $columns[$normalized] = $col;
            // Also store original for reference
            $columns['_original_' . $col] = $value;
        }

        // Define required columns with multiple possible name variations
        // The system will try to match any of these variations
        $requiredColumnMappings = [
            'student_number' => ['student_number', 'student number', 'student id', 'student_id', 'id', 'student no', 'student_no', 'studentnum', 'student_num', 'studentnum', 'studentnum'],
            'first_name' => ['first_name', 'first name', 'fname', 'f_name', 'given name', 'given_name', 'firstname', 'first'],
            'last_name' => ['last_name', 'last name', 'lname', 'l_name', 'surname', 'family name', 'family_name', 'lastname', 'last'],
            'course_code' => ['course_code', 'course code', 'course', 'courseid', 'course_id', 'coursecode', 'program', 'program_code', 'program code', 'programcode', 'subject', 'subject_code', 'coursename', 'course name']
        ];

        // Map required columns to their actual column indices
        $requiredColumns = [];
        $missingColumns = [];
        foreach ($requiredColumnMappings as $key => $possibleNames) {
            $colIndex = $findColumn($possibleNames, $columns);
            if ($colIndex === null) {
                $missingColumns[] = $key . ' (or variations: ' . implode(', ', array_slice($possibleNames, 0, 3)) . '...)';
            } else {
                $requiredColumns[$key] = $colIndex;
            }
        }

        if (!empty($missingColumns)) {
            @unlink($filePath);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Missing required columns: ' . implode(', ', $missingColumns) . '. Please ensure your file contains these columns (names can vary with spaces/underscores).',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }
        
        // Define optional columns with multiple possible name variations
        $optionalColumnMappings = [
            'year_level' => ['year_level', 'year level', 'year', 'level', 'yr_level', 'yr level', 'grade', 'yearlevel'],
            'status' => ['status', 'account status', 'account_status', 'active', 'state'],
            'address' => ['address', 'location', 'home address', 'home_address', 'residence', 'residential address', 'residential_address'],
            'sex' => ['sex', 'gender', 'gender_identity', 'gender identity', 'sex_identity', 'sex identity'],
            'middle_initial' => ['middle_initial', 'middle initial', 'middle_initials', 'middle initials', 'middle_name', 'middle name', 'mi', 'm.i.']
        ];

        // Map optional columns to their actual column indices
        $optionalColumns = [];
        foreach ($optionalColumnMappings as $key => $possibleNames) {
            $colIndex = $findColumn($possibleNames, $columns);
            if ($colIndex !== null) {
                $optionalColumns[$key] = $colIndex;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for header row

            // Use mapped column indices for required columns
            $studentNumber = trim((string) ($row[$requiredColumns['student_number']] ?? ''));
            $firstName = trim((string) ($row[$requiredColumns['first_name']] ?? ''));
            $lastName = trim((string) ($row[$requiredColumns['last_name']] ?? ''));
            $courseCode = strtoupper(trim((string) ($row[$requiredColumns['course_code']] ?? '')));

            if ($studentNumber === '' && $firstName === '' && $lastName === '' && $courseCode === '') {
                continue;
            }

            if ($studentNumber === '' || $firstName === '' || $lastName === '' || $courseCode === '') {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Missing required values.";
                continue;
            }

            $course = $courseModel->where('course_code', $courseCode)->first();
            if (!$course) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Course code '{$courseCode}' not found.";
                continue;
            }

            // Get optional columns if they exist (using mapped indices)
            $yearLevel = '';
            if (isset($optionalColumns['year_level']) && isset($row[$optionalColumns['year_level']])) {
                $yearLevel = trim((string) $row[$optionalColumns['year_level']]);
            }
            if ($yearLevel === '') {
                $yearLevel = '1st Year';
            }

            $status = '';
            if (isset($optionalColumns['status']) && isset($row[$optionalColumns['status']])) {
                $status = trim((string) $row[$optionalColumns['status']]);
            }
            $status = ucfirst(strtolower($status));
            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $status = 'Active';
            }

            $address = '';
            if (isset($optionalColumns['address']) && isset($row[$optionalColumns['address']])) {
                $address = trim((string) $row[$optionalColumns['address']]);
            }

            $existing = $studentModel->where('student_number', $studentNumber)->first();

            if ($existing) {
                $updateData = [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'sex'        => $row[$optionalColumns['sex']] ?? null,
                    'middle_initial' => $row[$optionalColumns['middle_initial']] ?? null,
                    'course_id'  => $course['course_id'],
                    'college_id' => $course['college_id'] ?? null,
                    'year_level' => $yearLevel,
                    'status'     => $status,
                    'address'    => $address ?: null,
                ];

                // Generate QR code as binary data
                $newQrBinary = $this->generateStudentQr(
                    (int) $existing['student_id'],
                    $studentNumber,
                    $firstName,
                    $lastName,
                    $courseCode
                );

                // Update student data (without qr_code)
                $studentModel->update($existing['student_id'], $updateData);
                
                // Update QR code separately using raw query to handle binary data properly
                if (!empty($newQrBinary)) {
                    $db = \Config\Database::connect();
                    $db->query("UPDATE students SET qr_code = ? WHERE student_id = ?", [$newQrBinary, $existing['student_id']]);
                }

                $updated++;
                continue;
            }

            // Generate QR code as binary data
            $qrBinary = $this->generateStudentQr(
                0,
                $studentNumber,
                $firstName,
                $lastName,
                $courseCode
            );

            $insertData = [
                'student_number' => $studentNumber,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'sex'            => $row[$optionalColumns['sex']] ?? null,
                'middle_initial' => $row[$optionalColumns['middle_initial']] ?? null,
                'password'       => password_hash($studentNumber, PASSWORD_DEFAULT),
                'course_id'      => $course['course_id'],
                'college_id'     => $course['college_id'] ?? null,
                'year_level'     => $yearLevel,
                'status'         => $status,
                'address'        => $address ?: null,
                'created_by'     => $adminId,
            ];

            $studentId = $studentModel->insert($insertData, true);
            if (!$studentId) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Failed to insert student.";
                continue;
            }
            
            // Update QR code separately using raw query to handle binary data properly
            if (!empty($qrBinary)) {
                $db = \Config\Database::connect();
                $db->query("UPDATE students SET qr_code = ? WHERE student_id = ?", [$qrBinary, $studentId]);
            }

            $created++;
        }

        @unlink($filePath);

        // Log the import action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'IMPORT_STUDENTS',
            'Student',
            null,
            "Bulk import completed: {$created} created, {$updated} updated, {$skipped} skipped",
            null,
            ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'error_count' => count($errors)]
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Import completed.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    protected function generateStudentQr(int $studentId, string $studentNumber, string $firstName, string $lastName, ?string $courseCode = null): string
    {
        $courseCode = $courseCode ?: 'N/A';
        if ($courseCode === '') {
            $courseCode = 'N/A';
        }

        $qrData = implode("\n", [
            "Student No: {$studentNumber}",
            "Name: {$firstName} {$lastName}",
            "Course: {$courseCode}",
        ]);

        $qr = new QrCode(
            $qrData,
            new Encoding('UTF-8'),
            ErrorCorrectionLevel::High,
            300,
            10
        );
        $writer = new PngWriter();

        // Generate QR code and get image data as binary string
        $result = $writer->write($qr);
        $qrImageData = $result->getString();
        
        // Add logo to QR code if logo file exists
        $logoPath = FCPATH . 'assets/icons/logo1.png';
        if (file_exists($logoPath) && extension_loaded('gd')) {
            // Create GD images from QR code and logo
            $qrImage = imagecreatefromstring($qrImageData);
            $logoImage = imagecreatefrompng($logoPath);
            
            if ($qrImage !== false && $logoImage !== false) {
                // Get dimensions
                $qrWidth = imagesx($qrImage);
                $qrHeight = imagesy($qrImage);
                $logoWidth = imagesx($logoImage);
                $logoHeight = imagesy($logoImage);
                
                // Calculate position to center the logo
                $logoX = ($qrWidth - $logoWidth) / 2;
                $logoY = ($qrHeight - $logoHeight) / 2;
                
                // Resize logo to 1/5 of QR code size
                $newLogoWidth = $qrWidth / 5;
                $newLogoHeight = ($logoHeight / $logoWidth) * $newLogoWidth;
                
                // Create a temporary resized logo
                $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
                imagealphablending($resizedLogo, false);
                imagesavealpha($resizedLogo, true);
                imagecopyresampled($resizedLogo, $logoImage, 0, 0, 0, 0, $newLogoWidth, $newLogoHeight, $logoWidth, $logoHeight);
                
                // Calculate new position for resized logo
                $logoX = ($qrWidth - $newLogoWidth) / 2;
                $logoY = ($qrHeight - $newLogoHeight) / 2;
                
                // Copy the resized logo onto the QR code
                imagecopy($qrImage, $resizedLogo, $logoX, $logoY, 0, 0, $newLogoWidth, $newLogoHeight);
                
                // Capture the final image as a string
                ob_start();
                imagepng($qrImage);
                $imageData = ob_get_contents();
                ob_end_clean();
                
                // Free memory
                imagedestroy($qrImage);
                imagedestroy($logoImage);
                imagedestroy($resizedLogo);
                
                return $imageData;
            }
        }
        
        // Return QR code without logo if GD is not available or logo doesn't exist
        return $qrImageData;
    }
}
