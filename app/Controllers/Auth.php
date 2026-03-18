<?php

namespace App\Controllers;

use App\Models\AdminModel;
use CodeIgniter\Controller;
use App\Models\AuditLogModel;

class Auth extends Controller
{
    public function login()
    {
        helper(['form', 'url']);
        $session = session();

        // If already logged in, redirect to dashboard
        if ($session->get('isLoggedIn')) {
            return redirect()->to(
                $session->get('role') === 'SuperAdmin' 
                ? '/superadmin/dashboard' 
                : '/admin/ad-dashboard'
            );
        }

        return view('login');
    }

    public function checkLogin()
    {
        helper(['form', 'url']);
        $session = session();
        $adminModel = new AdminModel();

        $email = trim($this->request->getPost('username'));
        $password = trim($this->request->getPost('password'));

        // Validate empty fields
        if (empty($email) || empty($password)) {
            $session->setFlashdata('error', 'Please fill in all fields.');
            return redirect()->to('/login');
        }

        // Find admin by email
        $admin = $adminModel->where('email', $email)->first();
        if (!$admin) {
            $session->setFlashdata('error', 'Email not found.');
            return redirect()->to('/login');
        }

        // Verify password
        if (!password_verify($password, $admin['password'])) {
            $session->setFlashdata('error', 'Invalid password.');
            return redirect()->to('/login');
        }

        // Check if account is active
        if ($admin['status'] !== 'Active') {
            $session->setFlashdata('error', 'Account is inactive. Please contact administrator.');
            return redirect()->to('/login');
        }

        // Update last login timestamp
        $adminModel->update($admin['admin_id'], [
            'last_login' => date('Y-m-d H:i:s')
        ]);

        // Set session data FIRST
        $sessionData = [
            'admin_id'   => $admin['admin_id'],
            'full_name'  => $admin['full_name'],
            'email'      => $admin['email'],
            'role'       => $admin['role'],
            'section_id' => $admin['section_id'],
            'isLoggedIn' => true
        ];
        $session->set($sessionData);

        // Log login action AFTER session is set
        $auditLogModel = new AuditLogModel();
        $auditLogModel->logAction(
            'LOGIN',
            'Admin',
            $admin['admin_id'],
            "User logged in: {$admin['full_name']} ({$admin['email']})",
            null,
            ['role' => $admin['role']]
        );

        // Redirect to dashboard based on role
        return redirect()->to(
            $admin['role'] === 'SuperAdmin' 
            ? '/superadmin/dashboard' 
            : '/admin/ad-dashboard'
        );
    }

    public function logout()
    {
        $session = session();
        
        // Log logout action before destroying session
        $adminId = $session->get('admin_id');
        $adminName = $session->get('full_name');
        $adminEmail = $session->get('email');
        
        if ($adminId) {
            $auditLogModel = new AuditLogModel();
            $auditLogModel->logAction(
                'LOGOUT',
                'Admin',
                $adminId,
                "User logged out: {$adminName} ({$adminEmail})",
                null,
                null
            );
        }
        
        $session->destroy(); // Destroy all session data
        return redirect()->to('/login');
    }
}