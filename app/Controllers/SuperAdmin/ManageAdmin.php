<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\SectionModel;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class ManageAdmin extends BaseController
{
    protected $adminModel;
    protected $sectionModel;

    public function __construct()
    {
        $this->adminModel = new AdminModel();
        $this->sectionModel = new SectionModel();
        helper('url');
    }

    // Display Manage Admin Page
    public function manageAdmin()
    {
        $admins = $this->adminModel->findAll();

        // Reindex sections by ID
        $sections = [];
        foreach ($this->sectionModel->findAll() as $s) {
            $sections[$s['section_id']] = $s;
        }

        return view('superadmin/manage-admin', [
            'admins' => $admins,
            'sections' => $sections
        ]);
    }

    // Add new Admin via AJAX
    public function addAdminAjax()
    {
        $request = $this->request;

        $fullName   = trim($request->getPost('full_name'));
        $email      = trim($request->getPost('email'));
        $password   = trim($request->getPost('password'));
        $role       = trim($request->getPost('role'));
        $section_id = $request->getPost('section_id');
        $status     = trim($request->getPost('status')) ?: 'Active';

        // Validation
        if (!$fullName || !$email || !$password || !$role || ($role === 'Admin' && !$section_id)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please fill in all required fields.'
            ]);
        }

        // Check for duplicate email
        if ($this->adminModel->where('email', $email)->first()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Email already exists.'
            ]);
        }

        // Ensure section_id null for SuperAdmin
        if ($role === 'SuperAdmin') {
            $section_id = null;
        }

        $adminId = $this->adminModel->insert([
            'full_name'  => $fullName,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => $role,
            'section_id' => $section_id ?: null,
            'status'     => $status,
            'created_by' => session()->get('admin_id'),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Admin added successfully.',
            'admin_id' => $adminId
        ]);
    }

    // Update Admin via AJAX
    public function updateAdminAjax($admin_id)
    {
        $request = $this->request;

        $admin = $this->adminModel->find($admin_id);
        if (!$admin) {
            return $this->response->setJSON(['success' => false, 'message' => 'Admin not found.']);
        }

        $fullName   = trim($request->getPost('full_name'));
        $email      = trim($request->getPost('email'));
        $role       = trim($request->getPost('role'));
        $section_id = $request->getPost('section_id');
        $status     = trim($request->getPost('status')) ?: 'Active';
        $password   = $request->getPost('password');
        $confirmPassword = $request->getPost('confirm_password');

        if (!$fullName || !$email || !$role || ($role === 'Admin' && !$section_id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please fill in all required fields.']);
        }

        // Validate password if provided
        if (!empty($password)) {
            if (strlen($password) < 6) {
                return $this->response->setJSON(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            }
            if ($password !== $confirmPassword) {
                return $this->response->setJSON(['success' => false, 'message' => 'Password and confirmation do not match.']);
            }
        }

        // Ensure section_id null for SuperAdmin
        if ($role === 'SuperAdmin') {
            $section_id = null;
        }

        // Check for email duplicates
        $existing = $this->adminModel->where('email', $email)->where('admin_id !=', $admin_id)->first();
        if ($existing) {
            return $this->response->setJSON(['success' => false, 'message' => 'Email already exists.']);
        }

        $updateData = [
            'full_name'  => $fullName,
            'email'      => $email,
            'role'       => $role,
            'section_id' => $section_id ?: null,
            'status'     => $status
        ];

        // Only update password if provided
        if (!empty($password)) {
            $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        // Store old values for audit log
        $oldValues = [
            'full_name' => $admin['full_name'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'section_id' => $admin['section_id'],
            'status' => $admin['status']
        ];

        $this->adminModel->update($admin_id, $updateData);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'UPDATE_ADMIN',
            'Admin',
            $admin_id,
            "Admin updated: {$updateData['full_name']} ({$updateData['email']})",
            $oldValues,
            $updateData
        );

        return $this->response->setJSON(['success' => true, 'message' => 'Admin updated successfully.']);
    }

    public function updateAdminStatus($admin_id)
    {
        $admin = $this->adminModel->find($admin_id);
        if (!$admin) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Admin not found.',
            ])->setStatusCode(ResponseInterface::HTTP_NOT_FOUND);
        }

        $currentAdminId = session()->get('admin_id');
        if ((string) $admin_id === (string) $currentAdminId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'You cannot change your own status.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        $input = $this->request->getJSON(true);
        $status = $input['status'] ?? null;

        if (!$status || !in_array($status, ['Active', 'Inactive'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid status value.',
            ])->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
        }

        // Store old status for audit log
        $oldStatus = $admin['status'] ?? 'Active';

        try {
            $this->adminModel->update($admin_id, ['status' => $status]);
            
            // Log the action
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'UPDATE_ADMIN_STATUS',
                'Admin',
                $admin_id,
                "Admin status updated: {$admin['full_name']} ({$admin['email']}) ({$oldStatus} → {$status})",
                ['status' => $oldStatus],
                ['status' => $status]
            );
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Status updated successfully.',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to update admin status: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unable to update status right now.',
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Delete Admin via AJAX
    public function deleteAdminAjax($admin_id)
    {
        if ($admin_id == session()->get('admin_id')) {
            return $this->response->setJSON(['success' => false, 'message' => 'You cannot delete your own account.']);
        }

        $admin = $this->adminModel->find($admin_id);
        if (!$admin) {
            return $this->response->setJSON(['success' => false, 'message' => 'Admin not found.']);
        }

        // Store admin info for audit log before deletion
        $adminInfo = [
            'full_name' => $admin['full_name'],
            'email' => $admin['email'],
            'role' => $admin['role']
        ];

        $this->adminModel->delete($admin_id);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'DELETE_ADMIN',
            'Admin',
            $admin_id,
            "Admin deleted: {$admin['full_name']} ({$admin['email']})",
            $adminInfo,
            null
        );

        return $this->response->setJSON(['success' => true, 'message' => 'Admin deleted successfully.']);
    }
}
