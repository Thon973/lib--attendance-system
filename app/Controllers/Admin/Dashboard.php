<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AttendanceModel;
use App\Models\SectionModel;

class Dashboard extends BaseController
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

        $attendance = [];

        if ($sectionId) {
            // Get today's date
            $today = date('Y-m-d');
            
            log_message('debug', 'Admin Dashboard - Checking for date: ' . $today);
            
            $attendanceModel = new AttendanceModel();

            // First, let's get ALL records to see what we're working with
            $allRows = $attendanceModel
                ->select('attendance.*, students.student_number, students.first_name, students.last_name, students.middle_initial, students.sex, courses.course_code, colleges.college_name')
                ->join('students', 'students.student_id = attendance.student_id', 'left')
                ->join('courses', 'courses.course_id = attendance.course_id', 'left')
                ->join('colleges', 'colleges.college_id = attendance.college_id', 'left')
                ->where('attendance.section_id', $sectionId)
                ->orderBy('attendance.scan_datetime', 'DESC')
                ->findAll(20); // Limit to 20 records for debugging
                
            log_message('debug', 'Admin Dashboard - All recent records: ' . json_encode($allRows));

            // Now filter by today's date
            $rows = $attendanceModel
                ->select('attendance.*, students.student_number, students.first_name, students.last_name, students.middle_initial, students.sex, courses.course_code, colleges.college_name')
                ->join('students', 'students.student_id = attendance.student_id', 'left')
                ->join('courses', 'courses.course_id = attendance.course_id', 'left')
                ->join('colleges', 'colleges.college_id = attendance.college_id', 'left')
                ->where('attendance.section_id', $sectionId)
                ->where('DATE(attendance.scan_datetime)', $today)
                ->orderBy('attendance.scan_datetime', 'DESC')
                ->findAll();
                
            log_message('debug', 'Admin Dashboard - Today\'s records count: ' . count($rows));
            log_message('debug', 'Admin Dashboard - Today\'s records: ' . json_encode($rows));

            // Get visit counts per student - only for today
            $counts = $attendanceModel
                ->select('student_id, COUNT(*) as total_visits')
                ->where('section_id', $sectionId)
                ->where('DATE(scan_datetime)', $today)
                ->groupBy('student_id')
                ->findAll();

            log_message('debug', 'Admin Dashboard - Today\'s visit counts: ' . json_encode($counts));

            $visitCounts = [];
            foreach ($counts as $row) {
                $visitCounts[$row['student_id']] = (int) $row['total_visits'];
            }

            // Get the LATEST record for each student today
            $latestByStudent = [];
            foreach ($rows as $row) {
                $studentId = $row['student_id'];
                // Only keep the latest record for each student (based on scan_datetime)
                if (!isset($latestByStudent[$studentId]) || 
                    strtotime($row['scan_datetime']) > strtotime($latestByStudent[$studentId]['scan_datetime'])) {
                    $latestByStudent[$studentId] = $row;
                }
            }

            // Apply visit counts to the latest records
            $attendance = array_values(array_map(function ($row) use ($visitCounts) {
                $row['total_visits'] = $visitCounts[$row['student_id']] ?? 1;
                return $row;
            }, $latestByStudent));
            
            log_message('debug', 'Admin Dashboard - Final attendance data: ' . json_encode($attendance));
        }

        return [
            'sectionName' => $sectionName,
            'fullName'    => $session->get('full_name'),
            'sectionId'   => $sectionId,
            'attendance'  => $attendance,
        ];
    }

    public function dashboard()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        return view('admin/ad-dashboard', $this->buildViewData());
    }

    public function getAttendanceData()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        // Log the request for debugging
        log_message('debug', 'Admin Dashboard - getAttendanceData called');
        
        $data = $this->buildViewData();
        
        log_message('debug', 'Admin Dashboard - Returning ' . count($data['attendance']) . ' attendance records');
        
        // Make sure we're returning the data in the correct format for the frontend
        $attendanceData = [];
        if (!empty($data['attendance']) && is_array($data['attendance'])) {
            foreach ($data['attendance'] as $entry) {
                $attendanceData[] = [
                    'student_number' => $entry['student_number'] ?? 'N/A',
                    'first_name' => $entry['first_name'] ?? '',
                    'last_name' => $entry['last_name'] ?? '',
                    'middle_initial' => $entry['middle_initial'] ?? '',
                    'sex' => $entry['sex'] ?? 'N/A',
                    'course_code' => $entry['course_code'] ?? 'N/A',
                    'college_name' => $entry['college_name'] ?? 'N/A',
                    'scan_datetime' => $entry['scan_datetime'] ?? $entry['created_at'] ?? '',
                    'total_visits' => $entry['total_visits'] ?? 1
                ];
            }
        }
        
        return $this->response->setJSON([
            'success' => true,
            'attendance' => $attendanceData,
        ]);
    }
}

