<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AttendanceModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Scanner extends BaseController
{
    protected function ensureLoggedIn()
    {
        $session = session();
        if (!$session->get('isLoggedIn') || $session->get('role') !== 'Admin') {
            return redirect()->to('/login');
        }

        return null;
    }

    protected function buildViewData(): array
    {
        $session = session();
        $sectionId = $session->get('section_id');
        $sectionName = 'Unassigned Section';

        if ($sectionId) {
            $section = (new SectionModel())->find($sectionId);
            if (!empty($section['section_name'])) {
                $sectionName = $section['section_name'];
            }
        }

        return [
            'sectionName' => $sectionName,
            'fullName'    => $session->get('full_name'),
            'sectionId'   => $sectionId,
        ];
    }

    public function scanner()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        return view('admin/ad-scanner', $this->buildViewData());
    }

    public function scanAttendance()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $session = session();
        $sessionAdminId = $session->get('admin_id');
        $sectionId = $session->get('section_id');

        if (!$sectionId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Your account is not assigned to a section.',
            ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        $payload = $this->request->getJSON(true);
        if (!$payload || empty($payload['student_number'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid QR payload.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Use admin_id from payload if provided (for handheld scanner), otherwise use session
        $adminId = !empty($payload['admin_id']) ? $payload['admin_id'] : $sessionAdminId;
        $studentNumber = trim($payload['student_number']);

        $studentModel = new StudentModel();
        $student = $studentModel
            ->select('students.*, courses.course_id, courses.course_name, courses.course_code, courses.college_id')
            ->join('courses', 'courses.course_id = students.course_id', 'left')
            ->where('student_number', $studentNumber)
            ->first();

        if (!$student) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Student not found.',
            ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
        }

        // Prevent inactive students from being recorded
        if (isset($student['status']) && $student['status'] !== 'Active') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Student account is inactive. Attendance cannot be recorded.',
            ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        // Check if student has scanned within the last 10 minutes
        $attendanceModel = new AttendanceModel();
        $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        
        $recentAttendance = $attendanceModel
            ->where('student_id', $student['student_id'])
            ->where('section_id', $sectionId)
            ->where('scan_datetime >=', $tenMinutesAgo)
            ->orderBy('scan_datetime', 'DESC')
            ->first();

        if ($recentAttendance) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Student has already scanned within the last 10 minutes.',
            ])->setStatusCode(ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        // Set timezone to app default for accurate timestamp
        $appTimezone = config('App')->appTimezone;
        $localTime = new \DateTime('now', new \DateTimeZone($appTimezone));
        $scanTimestamp = $localTime->format('Y-m-d H:i:s');
        
        $attendanceModel->insert([
            'student_id'   => $student['student_id'],
            'admin_id'     => $adminId,
            'section_id'   => $sectionId,
            'course_id'    => $student['course_id'] ?? null,
            'college_id'   => $student['college_id'] ?? null,
            'scan_datetime' => $scanTimestamp
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Attendance recorded for ' . $student['last_name'] . ', ' . $student['first_name'] . ($student['middle_initial'] ? ' ' . $student['middle_initial'] . '.' : ''),
            'student' => [
                'number' => $studentNumber,
                'name'   => $student['last_name'] . ', ' . $student['first_name'] . ($student['middle_initial'] ? ' ' . $student['middle_initial'] . '.' : ''),
                'course' => $student['course_code'] ?? 'N/A',
            ],
        ]);
    }
}

