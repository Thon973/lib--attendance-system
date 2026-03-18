<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\SectionModel;
use App\Models\CollegeModel;
use CodeIgniter\HTTP\ResponseInterface;

class StudentAttendance extends BaseController
{
    public function studentAttendance()
    {
        $sectionModel = new SectionModel();
        $collegeModel = new CollegeModel();
        
        $sections = $sectionModel->orderBy('section_name', 'ASC')->findAll();
        $colleges = $collegeModel->select('college_id, college_name, college_code')
                                 ->orderBy('college_code', 'ASC')
                                 ->findAll();
        
        return view('superadmin/student_attendance', [
            'sections' => $sections,
            'colleges' => $colleges,
        ]);
    }

    public function getAttendanceData()
    {
        try {
            $sectionId = $this->request->getGet('section_id');
            $collegeId = $this->request->getGet('college_id');
            $courseId = $this->request->getGet('course_id');
            $yearLevel = $this->request->getGet('year_level');
            $sex = $this->request->getGet('sex');
            
            // Normalize empty values
            $sectionId = (!empty($sectionId) && $sectionId !== '') ? (int)$sectionId : null;
            $collegeId = (!empty($collegeId) && $collegeId !== '') ? (int)$collegeId : null;
            $courseId = (!empty($courseId) && $courseId !== '') ? (int)$courseId : null;
            $yearLevel = (!empty($yearLevel) && $yearLevel !== '') ? $yearLevel : null;
            $sex = (!empty($sex) && $sex !== '') ? $sex : null;
            
            $db = \Config\Database::connect();
            
            // Get today's date range (start and end of today)
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            
            // Build the query with proper table prefixes - filter by today's date
            $builder = $db->table('attendance');
            $builder->select('attendance.*, students.student_number, students.first_name, students.last_name, students.middle_initial, students.sex, students.college_id, students.course_id, students.year_level, courses.course_code, colleges.college_name, sections.section_name');
            $builder->join('students', 'students.student_id = attendance.student_id', 'left');
            $builder->join('courses', 'courses.course_id = students.course_id', 'left');
            $builder->join('colleges', 'colleges.college_id = students.college_id', 'left');
            $builder->join('sections', 'sections.section_id = attendance.section_id', 'left');
            
            // Filter by today's date only
            $builder->where('attendance.scan_datetime >=', $todayStart);
            $builder->where('attendance.scan_datetime <=', $todayEnd);
            
            if ($sectionId !== null) {
                $builder->where('attendance.section_id', $sectionId);
            }
            
            if ($collegeId !== null) {
                $builder->where('students.college_id', $collegeId);
            }
            
            if ($courseId !== null) {
                $builder->where('students.course_id', $courseId);
            }
            
            if ($yearLevel !== null) {
                $builder->where('students.year_level', $yearLevel);
            }
            
            // Apply sex filter if provided
            if ($sex !== null) {
                $builder->where('students.sex', $sex);
            }
            
            $builder->orderBy('attendance.scan_datetime', 'DESC');
            $query = $builder->get();
            $rows = $query ? $query->getResultArray() : [];
            
            // Get visit counts per student per section - only for today
            $countBuilder = $db->table('attendance');
            $countBuilder->select('attendance.student_id, attendance.section_id, COUNT(*) as total_visits');
            $countBuilder->join('students', 'students.student_id = attendance.student_id', 'left');
            $countBuilder->where('attendance.scan_datetime >=', $todayStart);
            $countBuilder->where('attendance.scan_datetime <=', $todayEnd);
            
            if ($sectionId !== null) {
                $countBuilder->where('attendance.section_id', $sectionId);
            }
            
            if ($collegeId !== null) {
                $countBuilder->where('students.college_id', $collegeId);
            }
            
            if ($courseId !== null) {
                $countBuilder->where('students.course_id', $courseId);
            }
            
            if ($yearLevel !== null) {
                $countBuilder->where('students.year_level', $yearLevel);
            }
            
            // Apply sex filter if provided
            if ($sex !== null) {
                $countBuilder->where('students.sex', $sex);
            }
            
            $countBuilder->groupBy('attendance.student_id', 'attendance.section_id');
            
            $countQuery = $countBuilder->get();
            $counts = $countQuery ? $countQuery->getResultArray() : [];
            
            $visitCounts = [];
            foreach ($counts as $row) {
                $key = $row['student_id'] . '_' . $row['section_id'];
                $visitCounts[$key] = (int) $row['total_visits'];
            }
            
            // Get latest record per student per section
            $latestByStudent = [];
            foreach ($rows as $row) {
                $key = ($row['student_id'] ?? '') . '_' . ($row['section_id'] ?? '');
                if ($key && $key !== '_' && !isset($latestByStudent[$key])) {
                    $latestByStudent[$key] = $row;
                    $latestByStudent[$key]['total_visits'] = $visitCounts[$key] ?? 1;
                }
            }
            
            return $this->response->setJSON([
                'success' => true,
                'attendance' => array_values($latestByStudent),
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Attendance data error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading attendance data: ' . $e->getMessage(),
                'attendance' => [],
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $e) {
            log_message('error', 'Attendance data fatal error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Fatal error: ' . $e->getMessage(),
                'attendance' => [],
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

