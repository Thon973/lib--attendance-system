<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\SectionModel;
use App\Models\CollegeModel;
use App\Models\CourseModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Reports extends BaseController
{
    public function reports()
    {
        $sectionModel = new SectionModel();
        $collegeModel = new CollegeModel();
        $courseModel = new CourseModel();
        
        $sections = $sectionModel->orderBy('section_name', 'ASC')->findAll();
        $colleges = $collegeModel->select('college_id, college_name, college_code')
                                 ->orderBy('college_code', 'ASC')
                                 ->findAll();
        $courses = $courseModel->select('courses.*, colleges.college_name')
                              ->join('colleges', 'colleges.college_id = courses.college_id', 'left')
                              ->orderBy('courses.course_code', 'ASC')
                              ->findAll();
        
        return view('superadmin/reports', [
            'sections' => $sections,
            'colleges' => $colleges,
            'courses' => $courses,
        ]);
    }

    public function getStudentAttendanceHistory($studentId)
    {
        try {
            $db = \Config\Database::connect();
            
            $builder = $db->table('attendance');
            $builder->select('attendance.*, sections.section_name, courses.course_code, colleges.college_name');
            $builder->join('sections', 'sections.section_id = attendance.section_id', 'left');
            $builder->join('courses', 'courses.course_id = attendance.course_id', 'left');
            $builder->join('colleges', 'colleges.college_id = attendance.college_id', 'left');
            $builder->where('attendance.student_id', $studentId);
            $builder->orderBy('attendance.scan_datetime', 'DESC');
            
            $query = $builder->get();
            $rows = $query ? $query->getResultArray() : [];
            
            // Get student info with course and college
            $studentBuilder = $db->table('students');
            // Select explicit fields to avoid returning binary/blob columns (e.g. qr_code)
            $studentBuilder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, students.course_id, students.college_id, students.year_level, students.address, students.status, students.created_by, students.created_at, courses.course_code, colleges.college_name');
            $studentBuilder->join('courses', 'courses.course_id = students.course_id', 'left');
            $studentBuilder->join('colleges', 'colleges.college_id = students.college_id', 'left');
            $studentBuilder->where('students.student_id', $studentId);
            $studentQuery = $studentBuilder->get();
            $student = $studentQuery ? $studentQuery->getRowArray() : null;
            
            return $this->response->setJSON([
                'success' => true,
                'student' => $student,
                'attendance' => $rows,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Student attendance history error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading attendance history: ' . $e->getMessage(),
                'attendance' => [],
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getReportsData()
    {
        try {
            $sectionId = $this->request->getGet('section_id');
            $collegeId = $this->request->getGet('college_id');
            $courseId = $this->request->getGet('course_id');
            $yearLevel = $this->request->getGet('year_level');
            $sex = $this->request->getGet('sex');
            $timePeriod = $this->request->getGet('time_period');
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            
            // Normalize empty values - ensure we properly handle empty strings and null
            $sectionId = (!empty($sectionId) && $sectionId !== '') ? (int)$sectionId : null;
            $collegeId = (!empty($collegeId) && $collegeId !== '') ? (int)$collegeId : null;
            $courseId = (!empty($courseId) && $courseId !== '') ? (int)$courseId : null;
            $yearLevel = (!empty($yearLevel) && $yearLevel !== '') ? $yearLevel : null;
            $sex = (!empty($sex) && $sex !== '') ? $sex : null;
            $timePeriod = (!empty($timePeriod) && $timePeriod !== '') ? $timePeriod : null;
            
            // Handle time period filter - automatically set start and end dates
            if ($timePeriod !== null && ($startDate === null || $endDate === null)) {
                $today = new \DateTime();
                switch ($timePeriod) {
                    case 'daily':
                        $startDate = $today->format('Y-m-d');
                        $endDate = $today->format('Y-m-d');
                        break;
                    case 'weekly':
                        $startDate = $today->modify('monday this week')->format('Y-m-d');
                        $endDate = $today->modify('sunday this week')->format('Y-m-d');
                        break;
                    case 'monthly':
                        $startDate = $today->modify('first day of this month')->format('Y-m-d');
                        $endDate = $today->modify('last day of this month')->format('Y-m-d');
                        break;
                }
            }
            
            $startDate = (!empty($startDate) && $startDate !== '') ? $startDate : null;
            $endDate = (!empty($endDate) && $endDate !== '') ? $endDate : null;
            
            // Debug: Log filter values
            log_message('debug', 'Reports: sectionId=' . var_export($sectionId, true) . ', collegeId=' . var_export($collegeId, true) . ', courseId=' . var_export($courseId, true) . ', startDate=' . var_export($startDate, true) . ', endDate=' . var_export($endDate, true));
            
            $db = \Config\Database::connect();
            
            // Get ALL individual attendance records (no grouping) - each visit is a separate row
            // When sectionId is null, it will load ALL sections
            $detailBuilder = $db->table('attendance');
            $detailBuilder->select('
                attendance.attendance_id,
                attendance.student_id,
                attendance.section_id,
                attendance.scan_datetime,
                DATE(attendance.scan_datetime) as visit_date,
                sections.section_name
            ');
            // Use LEFT JOIN to ensure all attendance records are included, even if section is missing
            $detailBuilder->join('sections', 'sections.section_id = attendance.section_id', 'left');
            
            // Only apply section filter if a specific section is selected
            // When null, no filter is applied - loads ALL sections from ALL attendance records
            if ($sectionId !== null) {
                $detailBuilder->where('attendance.section_id', $sectionId);
            }
            
            // Only apply date filters if dates are provided
            // When null, no filter is applied - loads ALL dates
            if ($startDate !== null) {
                $detailBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
            }
            
            if ($endDate !== null) {
                $detailBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
            }
            
            // Order by scan_datetime descending to show most recent first
            $detailBuilder->orderBy('attendance.scan_datetime', 'DESC');
            $detailQuery = $detailBuilder->get();
            $detailRows = $detailQuery ? $detailQuery->getResultArray() : [];
            
            // Get student IDs for lookup
            $studentIds = array_unique(array_column($detailRows, 'student_id'));
            $studentIds = array_filter($studentIds);
            
            // Get student, course, and college information
            $studentInfo = [];
            if (!empty($studentIds)) {
                $studentBuilder = $db->table('students');
                $studentBuilder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, students.college_id, students.course_id, students.year_level, students.status, courses.course_code, colleges.college_name');
                $studentBuilder->join('courses', 'courses.course_id = students.course_id', 'left');
                $studentBuilder->join('colleges', 'colleges.college_id = students.college_id', 'left');
                $studentBuilder->whereIn('students.student_id', $studentIds);
                
                // Apply college filter if provided
                if ($collegeId !== null) {
                    $studentBuilder->where('students.college_id', $collegeId);
                }
                
                // Apply course filter if provided
                if ($courseId !== null) {
                    $studentBuilder->where('students.course_id', $courseId);
                }
                
                // Apply year level filter if provided
                if ($yearLevel !== null) {
                    $studentBuilder->where('students.year_level', $yearLevel);
                }
                
                // Apply sex filter if provided
                if ($sex !== null) {
                    $studentBuilder->where('students.sex', $sex);
                }
                
                $studentQuery = $studentBuilder->get();
                $students = $studentQuery ? $studentQuery->getResultArray() : [];
                
                foreach ($students as $student) {
                    $studentInfo[$student['student_id']] = $student;
                }
            }
            
            // Filter detailRows to only include students that match the filters
            if ($collegeId !== null || $courseId !== null || $yearLevel !== null || $sex !== null) {
                $filteredStudentIds = array_keys($studentInfo);
                $detailRows = array_filter($detailRows, function($row) use ($filteredStudentIds) {
                    return in_array($row['student_id'], $filteredStudentIds);
                });
            }
            
            // Calculate visit counts per student per section per day
            $visitCounts = [];
            foreach ($detailRows as $row) {
                if (!empty($row['student_id']) && !empty($row['section_id']) && !empty($row['scan_datetime'])) {
                    $key = $row['student_id'] . '_' . $row['section_id'] . '_' . date('Y-m-d', strtotime($row['scan_datetime']));
                    if (!isset($visitCounts[$key])) {
                        $visitCounts[$key] = 0;
                    }
                    $visitCounts[$key]++;
                }
            }
            
            // Build final rows - each attendance record is a separate row
            $rows = [];
            foreach ($detailRows as $detail) {
                if (empty($detail['section_name']) || empty($detail['student_id'])) {
                    continue;
                }
                
                $studentId = $detail['student_id'];
                if (!isset($studentInfo[$studentId])) {
                    continue;
                }
                
                // Calculate visit count for this student in this section on this day
                $visitDate = date('Y-m-d', strtotime($detail['scan_datetime']));
                $countKey = $studentId . '_' . $detail['section_id'] . '_' . $visitDate;
                $visitCount = $visitCounts[$countKey] ?? 1;
                
                $rows[] = array_merge($studentInfo[$studentId], [
                    'visit_date' => $visitDate,
                    'scan_datetime' => $detail['scan_datetime'],
                    'total_visits' => $visitCount,
                    'sections_text' => trim($detail['section_name']),
                    'section_name' => trim($detail['section_name'])
                ]);
            }
            
            // Sort by date (descending), then scan_datetime (descending), then student number
            usort($rows, function($a, $b) {
                $dateCompare = strcmp($b['visit_date'], $a['visit_date']);
                if ($dateCompare !== 0) return $dateCompare;
                
                $timeCompare = strcmp($b['scan_datetime'] ?? '', $a['scan_datetime'] ?? '');
                if ($timeCompare !== 0) return $timeCompare;
                
                return strcmp($a['student_number'] ?? '', $b['student_number'] ?? '');
            });
            
            // Calculate summary statistics for charts (using all attendance records, not grouped)
            $summary = $this->calculateSummaryStats([], $sectionId, $collegeId, $courseId, $yearLevel, $sex, $startDate, $endDate);
            
            // Calculate most active student by gender
            $summary['by_gender'] = $this->calculateMostActiveByGender($rows);
            
            // Debug: Log what we're returning
            log_message('debug', 'Reports: Returning ' . count($rows) . ' attendance records (one row per section visit)');
            if (!empty($rows)) {
                $sectionsFound = [];
                foreach ($rows as $row) {
                    if (!empty($row['sections_text'])) {
                        // sections_text now contains a single section name per row
                        $sectionsFound[] = $row['sections_text'];
                    }
                }
                $uniqueSections = array_unique($sectionsFound);
                log_message('debug', 'Reports: Unique sections found in data: ' . implode(', ', $uniqueSections));
            }
            
            return $this->response->setJSON([
                'success' => true,
                'attendance' => $rows,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Reports data error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading reports data: ' . $e->getMessage(),
                'attendance' => [],
                'summary' => [],
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function calculateSummaryStats($rows, $sectionId, $collegeId, $courseId, $yearLevel, $sex, $startDate, $endDate)
    {
        $db = \Config\Database::connect();
        
        // Total attendance count
        $totalCount = count($rows);
        
        // Get student IDs that match filters for attendance filtering
        $filteredStudentIds = [];
        if ($collegeId !== null || $courseId !== null || $yearLevel !== null || $sex !== null) {
            $studentBuilder = $db->table('students');
            $studentBuilder->select('students.student_id');
            if ($collegeId !== null) {
                $studentBuilder->where('students.college_id', $collegeId);
            }
            if ($courseId !== null) {
                $studentBuilder->where('students.course_id', $courseId);
            }
            if ($yearLevel !== null) {
                $studentBuilder->where('students.year_level', $yearLevel);
            }
            
            // Apply sex filter if provided
            if ($sex !== null) {
                $studentBuilder->where('students.sex', $sex);
            }
            
            $studentQuery = $studentBuilder->get();
            $filteredStudentIds = array_column($studentQuery->getResultArray(), 'student_id');
        }
        
        // Attendance by section
        $sectionBuilder = $db->table('attendance');
        $sectionBuilder->select('sections.section_name, COUNT(*) as count');
        $sectionBuilder->join('sections', 'sections.section_id = attendance.section_id', 'left');
        if ($sectionId !== null) {
            $sectionBuilder->where('attendance.section_id', $sectionId);
        }
        if (!empty($filteredStudentIds)) {
            $sectionBuilder->whereIn('attendance.student_id', $filteredStudentIds);
        }
        if ($startDate !== null) {
            $sectionBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
        }
        if ($endDate !== null) {
            $sectionBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
        }
        $sectionBuilder->groupBy('sections.section_id', 'sections.section_name');
        $sectionBuilder->orderBy('count', 'DESC');
        $sectionQuery = $sectionBuilder->get();
        $sectionStats = $sectionQuery ? $sectionQuery->getResultArray() : [];
        
        // Attendance by date
        $dateBuilder = $db->table('attendance');
        $dateBuilder->select('DATE(scan_datetime) as date, COUNT(*) as count');
        if ($sectionId !== null) {
            $dateBuilder->where('section_id', $sectionId);
        }
        if (!empty($filteredStudentIds)) {
            $dateBuilder->whereIn('attendance.student_id', $filteredStudentIds);
        }
        if ($startDate !== null) {
            $dateBuilder->where('DATE(scan_datetime) >=', $startDate);
        }
        if ($endDate !== null) {
            $dateBuilder->where('DATE(scan_datetime) <=', $endDate);
        }
        $dateBuilder->groupBy('DATE(scan_datetime)');
        $dateBuilder->orderBy('date', 'ASC');
        $dateQuery = $dateBuilder->get();
        $dateStats = $dateQuery ? $dateQuery->getResultArray() : [];
        
        // Attendance by course
        $courseBuilder = $db->table('attendance');
        $courseBuilder->select('courses.course_code, COUNT(*) as count');
        $courseBuilder->join('courses', 'courses.course_id = attendance.course_id', 'left');
        if ($sectionId !== null) {
            $courseBuilder->where('attendance.section_id', $sectionId);
        }
        if ($courseId !== null) {
            $courseBuilder->where('attendance.course_id', $courseId);
        }
        if (!empty($filteredStudentIds)) {
            $courseBuilder->whereIn('attendance.student_id', $filteredStudentIds);
        }
        if ($startDate !== null) {
            $courseBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
        }
        if ($endDate !== null) {
            $courseBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
        }
        $courseBuilder->groupBy('courses.course_id', 'courses.course_code');
        $courseBuilder->orderBy('count', 'DESC');
        $courseBuilder->limit(10);
        $courseQuery = $courseBuilder->get();
        $courseStats = $courseQuery ? $courseQuery->getResultArray() : [];
        
        // Top 5 students by visit count
        $studentBuilder = $db->table('attendance');
        $studentBuilder->select('
            students.student_id, 
            students.student_number,
            students.first_name, 
            students.last_name, 
            students.middle_initial,
            COUNT(*) as visit_count
        ');
        $studentBuilder->join('students', 'students.student_id = attendance.student_id', 'inner');
        
        // Apply section filter if specified
        if ($sectionId !== null) {
            $studentBuilder->where('attendance.section_id', $sectionId);
        }
        
        // Apply date filters if provided
        if ($startDate !== null) {
            $studentBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
        }
        if ($endDate !== null) {
            $studentBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
        }
        
        // Apply college filter if provided
        if ($collegeId !== null) {
            $studentBuilder->where('students.college_id', $collegeId);
        }
        
        // Apply course filter if provided
        if ($courseId !== null) {
            $studentBuilder->where('students.course_id', $courseId);
        }
        
        // Apply year level filter if provided
        if ($yearLevel !== null) {
            $studentBuilder->where('students.year_level', $yearLevel);
        }
        
        // Apply sex filter if provided
        if ($sex !== null) {
            $studentBuilder->where('students.sex', $sex);
        }
        
        $studentBuilder->groupBy('students.student_id, students.student_number, students.first_name, students.last_name, students.middle_initial');
        $studentBuilder->orderBy('visit_count', 'DESC');
        $studentBuilder->limit(5); // Top 5 students
        $studentQuery = $studentBuilder->get();
        $studentStats = $studentQuery ? $studentQuery->getResultArray() : [];
        
        return [
            'total_count' => $totalCount,
            'by_section' => $sectionStats,
            'by_date' => $dateStats,
            'by_course' => $courseStats,
            'by_student' => $studentStats,
        ];
    }

    private function calculateMostActiveByGender($rows)
    {
        // Count total visits by gender
        $genderTotals = [
            'Male' => 0,
            'Female' => 0,
            'Other' => 0
        ];
        
        foreach ($rows as $row) {
            $sex = $row['sex'] ?? 'Other';
            $genderTotals[$sex]++;
        }
        
        // Find which gender has the most visits
        $maxGender = array_keys($genderTotals, max($genderTotals));
        $mostActiveGender = $maxGender[0];
        
        // Create result with the gender that has the most visits
        $result = [
            'most_active_gender' => [
                'gender' => $mostActiveGender,
                'total_visits' => $genderTotals[$mostActiveGender]
            ],
            'gender_totals' => $genderTotals
        ];
        
        return $result;
    }

    public function exportReports()
    {
        $format = $this->request->getGet('format') ?? 'pdf';
        $sectionId = $this->request->getGet('section_id');
        $collegeId = $this->request->getGet('college_id');
        $courseId = $this->request->getGet('course_id');
        $yearLevel = $this->request->getGet('year_level');
        $sex = $this->request->getGet('sex');
        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');
        $timePeriod = $this->request->getGet('time_period');
        
        // Normalize empty values
        $sectionId = (!empty($sectionId) && $sectionId !== '') ? (int)$sectionId : null;
        $collegeId = (!empty($collegeId) && $collegeId !== '') ? (int)$collegeId : null;
        $courseId = (!empty($courseId) && $courseId !== '') ? (int)$courseId : null;
        $yearLevel = (!empty($yearLevel) && $yearLevel !== '') ? $yearLevel : null;
        $sex = (!empty($sex) && $sex !== '') ? $sex : null;
        $timePeriod = (!empty($timePeriod) && $timePeriod !== '') ? $timePeriod : null;
        
        // Handle time period filter - automatically set start and end dates
        if ($timePeriod !== null && ($startDate === null || $endDate === null)) {
            $today = new \DateTime();
            switch ($timePeriod) {
                case 'daily':
                    $startDate = $today->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
                    break;
                case 'weekly':
                    $startDate = $today->modify('monday this week')->format('Y-m-d');
                    $endDate = $today->modify('sunday this week')->format('Y-m-d');
                    break;
                case 'monthly':
                    $startDate = $today->modify('first day of this month')->format('Y-m-d');
                    $endDate = $today->modify('last day of this month')->format('Y-m-d');
                    break;
            }
        }
        
        $startDate = (!empty($startDate) && $startDate !== '') ? $startDate : null;
        $endDate = (!empty($endDate) && $endDate !== '') ? $endDate : null;
        
        try {
            $db = \Config\Database::connect();
            
            // Get attendance data (same query as getReportsData)
            $detailBuilder = $db->table('attendance');
            $detailBuilder->select('
                attendance.attendance_id,
                attendance.student_id,
                attendance.section_id,
                attendance.scan_datetime,
                DATE(attendance.scan_datetime) as visit_date,
                sections.section_name
            ');
            $detailBuilder->join('sections', 'sections.section_id = attendance.section_id', 'left');
            
            if ($sectionId !== null) {
                $detailBuilder->where('attendance.section_id', $sectionId);
            }
            
            if ($startDate !== null) {
                $detailBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
            }
            
            if ($endDate !== null) {
                $detailBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
            }
            
            $detailBuilder->orderBy('attendance.scan_datetime', 'DESC');
            $detailQuery = $detailBuilder->get();
            $detailRows = $detailQuery ? $detailQuery->getResultArray() : [];
            
            // Get student information
            $studentIds = array_unique(array_column($detailRows, 'student_id'));
            $studentIds = array_filter($studentIds);
            
            $studentInfo = [];
            if (!empty($studentIds)) {
                $studentBuilder = $db->table('students');
                $studentBuilder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, courses.course_code, colleges.college_name');
                $studentBuilder->join('courses', 'courses.course_id = students.course_id', 'left');
                $studentBuilder->join('colleges', 'colleges.college_id = students.college_id', 'left');
                $studentBuilder->whereIn('students.student_id', $studentIds);
                
                // Apply college filter if provided
                if ($collegeId !== null) {
                    $studentBuilder->where('students.college_id', $collegeId);
                }
                
                // Apply course filter if provided
                if ($courseId !== null) {
                    $studentBuilder->where('students.course_id', $courseId);
                }
                
                // Apply year level filter if provided
                if ($yearLevel !== null) {
                    $studentBuilder->where('students.year_level', $yearLevel);
                }
                
                // Apply sex filter if provided
                if ($sex !== null) {
                    $studentBuilder->where('students.sex', $sex);
                }
                
                $studentQuery = $studentBuilder->get();
                $students = $studentQuery ? $studentQuery->getResultArray() : [];
                
                foreach ($students as $student) {
                    $studentInfo[$student['student_id']] = $student;
                }
            }
            
            // Filter detailRows to only include students that match the filters
            if ($collegeId !== null || $courseId !== null || $yearLevel !== null || $sex !== null) {
                $filteredStudentIds = array_keys($studentInfo);
                $detailRows = array_filter($detailRows, function($row) use ($filteredStudentIds) {
                    return in_array($row['student_id'], $filteredStudentIds);
                });
            }
            
            // Build final rows
            $rows = [];
            foreach ($detailRows as $detail) {
                if (empty($detail['section_name']) || empty($detail['student_id'])) {
                    continue;
                }
                
                $studentId = $detail['student_id'];
                if (!isset($studentInfo[$studentId])) {
                    continue;
                }
                
                $visitDate = date('Y-m-d', strtotime($detail['scan_datetime']));
                $timeIn = date('H:i A', strtotime($detail['scan_datetime']));
                
                $rows[] = [
                    'student_number' => $studentInfo[$studentId]['student_number'] ?? 'N/A',
                    'student_name' => ($studentInfo[$studentId]['last_name'] ?? 'N/A') . ', ' . ($studentInfo[$studentId]['first_name'] ?? 'N/A') . ($studentInfo[$studentId]['middle_initial'] ? ' ' . $studentInfo[$studentId]['middle_initial'] . '.' : ''),
                    'sex' => $studentInfo[$studentId]['sex'] ?? 'N/A',
                    'course_code' => $studentInfo[$studentId]['course_code'] ?? 'N/A',
                    'college_name' => $studentInfo[$studentId]['college_name'] ?? 'N/A',
                    'time_in' => $timeIn,
                    'section_name' => trim($detail['section_name']),
                    'date' => date('M d, Y', strtotime($visitDate))
                ];
            }
            
            // Log the export action before exporting
            $auditLogModel = new AuditLogModel();
            $sectionModel = new SectionModel();
            $courseModel = new CourseModel();
            $collegeModel = new CollegeModel();
            
            // Build description with readable names
            $descriptionParts = [];
            if ($sectionId) {
                $section = $sectionModel->find($sectionId);
                if ($section) {
                    $descriptionParts[] = "from {$section['section_name']} section";
                }
            }
            if ($collegeId) {
                $college = $collegeModel->find($collegeId);
                if ($college) {
                    $descriptionParts[] = "College: {$college['college_name']}";
                }
            }
            if ($courseId) {
                $course = $courseModel->find($courseId);
                if ($course) {
                    $descriptionParts[] = "Course: {$course['course_code']}";
                }
            }
            if ($sex) {
                $descriptionParts[] = "Sex: {$sex}";
            }
            if ($startDate && $endDate) {
                $descriptionParts[] = "Date: {$startDate} to {$endDate}";
            } elseif ($startDate) {
                $descriptionParts[] = "From: {$startDate}";
            } elseif ($endDate) {
                $descriptionParts[] = "Until: {$endDate}";
            }
            
            $recordCount = count($rows);
            $description = "Exported " . strtolower($format) . " report with {$recordCount} records";
            if (!empty($descriptionParts)) {
                $description .= " (" . implode(", ", $descriptionParts) . ")";
            }
            
            $filterInfo = [];
            if ($sectionId) $filterInfo['section_id'] = $sectionId;
            if ($collegeId) $filterInfo['college_id'] = $collegeId;
            if ($courseId) $filterInfo['course_id'] = $courseId;
            if ($sex) $filterInfo['sex'] = $sex;
            if ($startDate) $filterInfo['start_date'] = $startDate;
            if ($endDate) $filterInfo['end_date'] = $endDate;
            
            $auditLogModel->logAction(
                'EXPORT_REPORTS',
                'Report',
                null,
                $description,
                null,
                [
                    'format' => strtoupper($format),
                    'record_count' => $recordCount,
                    'filters' => $filterInfo
                ]
            );
            
            if ($format === 'excel') {
                return $this->exportToExcel($rows);
            } else {
                return $this->exportToPdf($rows);
            }
        } catch (\Exception $e) {
            log_message('error', 'Export error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error exporting data: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    private function exportToExcel($rows)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Student ID');
        $sheet->setCellValue('B1', 'Student Name');
        $sheet->setCellValue('C1', 'Sex');
        $sheet->setCellValue('D1', 'Course');
        $sheet->setCellValue('E1', 'Department');
        $sheet->setCellValue('F1', 'Time In');
        $sheet->setCellValue('G1', 'Section');
        $sheet->setCellValue('H1', 'Date');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1A1851']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        
        // Add data
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $rowNum, $row['student_number']);
            $sheet->setCellValue('B' . $rowNum, $row['student_name']);
            $sheet->setCellValue('C' . $rowNum, $row['sex']);
            $sheet->setCellValue('D' . $rowNum, $row['course_code']);
            $sheet->setCellValue('E' . $rowNum, $row['college_name']);
            $sheet->setCellValue('F' . $rowNum, $row['time_in']);
            $sheet->setCellValue('G' . $rowNum, $row['section_name']);
            $sheet->setCellValue('H' . $rowNum, $row['date']);
            $rowNum++;
        }
        
        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set filename
        $filename = 'attendance_report_' . date('Y-m-d_His') . '.xlsx';
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    private function exportToPdf($rows)
    {
        // For PDF, we'll use a simple HTML to PDF approach or redirect to client-side PDF generation
        // Since the frontend already has PDF generation, we can return JSON and let frontend handle it
        // Or we can use a server-side PDF library like TCPDF or DomPDF
        
        // For now, return JSON data and let frontend handle PDF generation
        return $this->response->setJSON([
            'success' => true,
            'data' => $rows,
            'format' => 'pdf'
        ]);
    }
}

