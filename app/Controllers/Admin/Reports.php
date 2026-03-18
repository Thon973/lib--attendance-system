<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SectionModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Reports extends BaseController
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

    public function reports()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        return view('admin/ad-reports', $this->buildViewData());
    }

    public function getReportsData()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        try {
            $session = session();
            $sectionId = $session->get('section_id');
            
            if (!$sectionId) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Your account is not assigned to a section.',
                    'attendance' => [],
                    'summary' => [],
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            $sex = $this->request->getGet('sex');
            
            // Normalize empty values
            $startDate = (!empty($startDate) && $startDate !== '') ? $startDate : null;
            $endDate = (!empty($endDate) && $endDate !== '') ? $endDate : null;
            $sex = (!empty($sex) && $sex !== '') ? $sex : null;
            
            $db = \Config\Database::connect();
            
            // Get ALL individual attendance records (no grouping) - each visit is a separate row
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
            
            // Always filter by admin's assigned section
            $detailBuilder->where('attendance.section_id', $sectionId);
            
            // Apply date filters if provided
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
                $studentBuilder->select('students.student_id, students.student_number, students.first_name, students.last_name, students.sex, students.middle_initial, courses.course_code, colleges.college_name');
                $studentBuilder->join('courses', 'courses.course_id = students.course_id', 'left');
                $studentBuilder->join('colleges', 'colleges.college_id = students.college_id', 'left');
                $studentBuilder->whereIn('students.student_id', $studentIds);
                
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
            
            // Calculate visit counts per student per section per day
            $visitCounts = [];
            foreach ($detailRows as $row) {
                $key = $row['student_id'] . '_' . $row['section_id'] . '_' . date('Y-m-d', strtotime($row['scan_datetime']));
                if (!isset($visitCounts[$key])) {
                    $visitCounts[$key] = 0;
                }
                $visitCounts[$key]++;
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
            
            // Calculate summary statistics for charts
            $summary = $this->calculateSummaryStats($rows, $sectionId, $startDate, $endDate);
            
            // Calculate most active student by gender
            $summary['by_gender'] = $this->calculateMostActiveByGender($rows);
            
            return $this->response->setJSON([
                'success' => true,
                'attendance' => $rows,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Admin reports data error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading reports data: ' . $e->getMessage(),
                'attendance' => [],
                'summary' => [],
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function calculateSummaryStats($rows, $sectionId, $startDate, $endDate)
    {
        $db = \Config\Database::connect();
        
        // Total attendance count
        $totalCount = count($rows);
        
        // Attendance by section (will only be one section for admin)
        $sectionBuilder = $db->table('attendance');
        $sectionBuilder->select('sections.section_name, COUNT(*) as count');
        $sectionBuilder->join('sections', 'sections.section_id = attendance.section_id', 'left');
        $sectionBuilder->where('attendance.section_id', $sectionId);
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
        $dateBuilder->where('section_id', $sectionId);
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
        $courseBuilder->where('attendance.section_id', $sectionId);
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
        $studentBuilder->where('attendance.section_id', $sectionId);
        
        // Apply date filters if provided
        if ($startDate !== null) {
            $studentBuilder->where('DATE(attendance.scan_datetime) >=', $startDate);
        }
        if ($endDate !== null) {
            $studentBuilder->where('DATE(attendance.scan_datetime) <=', $endDate);
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
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $format = $this->request->getGet('format') ?? 'pdf';
        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');
        $sex = $this->request->getGet('sex');
        
        // Normalize empty values
        $startDate = (!empty($startDate) && $startDate !== '') ? $startDate : null;
        $endDate = (!empty($endDate) && $endDate !== '') ? $endDate : null;
        $sex = (!empty($sex) && $sex !== '') ? $sex : null;
        
        try {
            $session = session();
            $sectionId = $session->get('section_id');
            
            if (!$sectionId) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Your account is not assigned to a section.'
                ])->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
            }

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
            $detailBuilder->where('attendance.section_id', $sectionId);
            
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
            
            // Get section name for description
            $sectionName = '';
            if ($sectionId) {
                $section = $sectionModel->find($sectionId);
                if ($section) {
                    $sectionName = $section['section_name'];
                }
            }
            
            // Build description with readable names
            $descriptionParts = [];
            if ($sectionName) {
                $descriptionParts[] = "from {$sectionName} section";
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
            if ($startDate) $filterInfo['start_date'] = $startDate;
            if ($endDate) $filterInfo['end_date'] = $endDate;
            if ($sex) $filterInfo['sex'] = $sex;
            
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
        // Return JSON data and let frontend handle PDF generation
        return $this->response->setJSON([
            'success' => true,
            'data' => $rows,
            'format' => 'pdf'
        ]);
    }
}

