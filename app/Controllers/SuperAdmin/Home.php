<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use App\Models\CourseModel;
use App\Models\CollegeModel;
use App\Models\SectionModel;
use App\Models\AuditLogModel;

class Home extends BaseController
{
    // ===== DASHBOARD =====
    public function dashboard()
    {
        $studentModel = new StudentModel();
        $courseModel  = new CourseModel();
        $collegeModel = new CollegeModel();
        $sectionModel = new SectionModel();

        $data = [
            'totalStudents' => $studentModel->countAllResults(),
            'totalCourses'  => $courseModel->countAllResults(),
            'totalColleges' => $collegeModel->countAllResults(),
            'totalSections' => $sectionModel->countAllResults(),
            'courses'  => $courseModel->select('courses.*, colleges.college_name')
                                      ->join('colleges', 'colleges.college_id = courses.college_id', 'left')
                                      ->findAll(),
            'colleges' => $collegeModel->findAll(),
            'sections' => $sectionModel->findAll()
        ];

        return view('superadmin/dashboard', $data);
    }

    // ===== GET =====
    public function getColleges()
    {
        $model = new CollegeModel();
        return $this->response->setJSON($model->findAll());
    }

    public function getCourses()
    {
        $model = new CourseModel();
        $data = $model->select('courses.*, colleges.college_name')
                      ->join('colleges', 'colleges.college_id = courses.college_id', 'left')
                      ->findAll();
        return $this->response->setJSON($data);
    }

    public function getSections()
    {
        $model = new SectionModel();
        return $this->response->setJSON($model->findAll());
    }

    // ===== ADD =====
    public function addCollege()
    {
        $model = new CollegeModel();
        $name = trim($this->request->getPost('college_name'));
        $code = trim($this->request->getPost('college_code'));

        if (empty($name) || empty($code))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'College name and code are required']);

        $collegeId = $model->insert([
            'college_name' => $name,
            'college_code' => strtoupper($code)
        ]);

        // Log the action
        if ($collegeId) {
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'CREATE_COLLEGE',
                'College',
                $collegeId,
                "College created: {$name} ({$code})",
                null,
                ['college_name' => $name, 'college_code' => strtoupper($code)]
            );
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function addCourse()
    {
        $model = new CourseModel();
        $name = trim($this->request->getPost('course_name'));
        $code = trim($this->request->getPost('course_code'));
        $collegeId = $this->request->getPost('college_id');

        if (empty($name) || empty($code) || empty($collegeId))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'All fields are required']);

        $courseId = $model->insert([
            'course_name' => $name,
            'course_code' => strtoupper($code),
            'college_id'  => $collegeId
        ]);

        // Log the action
        if ($courseId) {
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'CREATE_COURSE',
                'Course',
                $courseId,
                "Course created: {$name} ({$code})",
                null,
                ['course_name' => $name, 'course_code' => strtoupper($code), 'college_id' => $collegeId]
            );
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function addSection()
    {
        $model = new SectionModel();
        $name = trim($this->request->getPost('section_name'));

        if (empty($name))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Section name is required']);

        $sectionId = $model->insert(['section_name' => $name]);
        
        // Log the action
        if ($sectionId) {
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'CREATE_SECTION',
                'Section',
                $sectionId,
                "Section created: {$name}",
                null,
                ['section_name' => $name]
            );
        }
        
        return $this->response->setJSON(['success' => true]);
    }

    // ===== UPDATE =====
    public function updateCollege($id)
    {
        $model = new CollegeModel();
        $college = $model->find($id);
        
        if (!$college) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'College not found']);
        }

        $name = trim($this->request->getPost('college_name'));
        $code = trim($this->request->getPost('college_code'));

        if (empty($name) || empty($code))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'College name and code are required']);

        // Store old values for audit log
        $oldValues = [
            'college_name' => $college['college_name'],
            'college_code' => $college['college_code']
        ];

        $model->update($id, [
            'college_name' => $name,
            'college_code' => strtoupper($code)
        ]);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'UPDATE_COLLEGE',
            'College',
            $id,
            "College updated: {$name} ({$code})",
            $oldValues,
            ['college_name' => $name, 'college_code' => strtoupper($code)]
        );

        return $this->response->setJSON(['success' => true]);
    }

    public function updateCourse($id)
    {
        $model = new CourseModel();
        $course = $model->find($id);
        
        if (!$course) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Course not found']);
        }

        $name = trim($this->request->getPost('course_name'));
        $code = trim($this->request->getPost('course_code'));
        $collegeId = $this->request->getPost('college_id');

        if (empty($name) || empty($code) || empty($collegeId))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'All fields are required']);

        // Store old values for audit log
        $oldValues = [
            'course_name' => $course['course_name'],
            'course_code' => $course['course_code'],
            'college_id' => $course['college_id']
        ];

        $model->update($id, [
            'course_name' => $name,
            'course_code' => strtoupper($code),
            'college_id'  => $collegeId
        ]);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'UPDATE_COURSE',
            'Course',
            $id,
            "Course updated: {$name} ({$code})",
            $oldValues,
            ['course_name' => $name, 'course_code' => strtoupper($code), 'college_id' => $collegeId]
        );

        return $this->response->setJSON(['success' => true]);
    }

    public function updateSection($id)
    {
        $model = new SectionModel();
        $section = $model->find($id);
        
        if (!$section) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Section not found']);
        }

        $name = trim($this->request->getPost('section_name'));

        if (empty($name))
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Section name is required']);

        // Store old values for audit log
        $oldValues = ['section_name' => $section['section_name']];

        $model->update($id, ['section_name' => $name]);
        
        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'UPDATE_SECTION',
            'Section',
            $id,
            "Section updated: {$name}",
            $oldValues,
            ['section_name' => $name]
        );
        
        return $this->response->setJSON(['success' => true]);
    }

    // ===== DELETE =====
    public function deleteCollege($id)
    {
        $model = new CollegeModel();
        $college = $model->find($id);
        
        if (!$college) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'College not found']);
        }

        $collegeInfo = [
            'college_name' => $college['college_name'],
            'college_code' => $college['college_code']
        ];

        $model->delete($id);
        
        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'DELETE_COLLEGE',
            'College',
            $id,
            "College deleted: {$college['college_name']} ({$college['college_code']})",
            $collegeInfo,
            null
        );
        
        return $this->response->setJSON(['success' => true]);
    }

    public function deleteCourse($id)
    {
        $model = new CourseModel();
        $course = $model->find($id);
        
        if (!$course) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Course not found']);
        }

        $courseInfo = [
            'course_name' => $course['course_name'],
            'course_code' => $course['course_code']
        ];

        $model->delete($id);
        
        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'DELETE_COURSE',
            'Course',
            $id,
            "Course deleted: {$course['course_name']} ({$course['course_code']})",
            $courseInfo,
            null
        );
        
        return $this->response->setJSON(['success' => true]);
    }

    public function deleteSection($id)
    {
        $model = new SectionModel();
        $section = $model->find($id);
        
        if (!$section) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Section not found']);
        }

        $sectionInfo = ['section_name' => $section['section_name']];

        $model->delete($id);
        
        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'DELETE_SECTION',
            'Section',
            $id,
            "Section deleted: {$section['section_name']}",
            $sectionInfo,
            null
        );
        
        return $this->response->setJSON(['success' => true]);
    }
}
