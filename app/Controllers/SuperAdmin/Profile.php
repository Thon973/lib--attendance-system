<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use App\Models\AdminModel;
use App\Models\AuditLogModel;

class Profile extends BaseController
{
    protected $adminModel;

    public function __construct()
    {
        $this->adminModel = new AdminModel();
    }

    public function profile()
    {
        $session = session();
        $adminId = $session->get('admin_id');

        if (!$adminId) {
            return redirect()->to('/login');
        }

        $admin = $this->adminModel->find($adminId);
        if (!$admin) {
            $session->setFlashdata('error', 'Unable to load profile information.');
            return redirect()->to('/login');
        }

        // Format profile picture URL if binary data exists
        if (!empty($admin['profile_picture'])) {
            // If profile picture is binary data (not a URL), use the image serving route
            if (!preg_match('/^https?:\/\//', $admin['profile_picture'])) {
                $admin['profile_picture'] = base_url("superadmin/profile/image/{$adminId}");
            }
        }
            
        // Update session with profile picture URL to persist after refresh
        if (!empty($admin['profile_picture'])) {
            $session->set('profile_picture', $admin['profile_picture']);
        }
    
        return view('superadmin/profile', [
            'admin' => $admin,
        ]);
    }

    public function updateProfile()
    {
        helper(['form', 'url']);

        $session = session();
        $adminId = $session->get('admin_id');

        if (!$adminId) {
            return redirect()->to('/login');
        }

        $admin = $this->adminModel->find($adminId);
        if (!$admin) {
            $session->setFlashdata('error', 'Account not found.');
            return redirect()->to('/login');
        }

        $fullName = trim($this->request->getPost('full_name'));
        $email    = trim($this->request->getPost('email'));
        $currentPassword = $this->request->getPost('current_password');
        $newPassword     = $this->request->getPost('new_password');
        $confirmPassword = $this->request->getPost('confirm_password');

        $errors = [];

        if ($fullName === '' || mb_strlen($fullName) < 3) {
            $errors[] = 'Full name must be at least 3 characters.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        $existingEmail = $this->adminModel
            ->where('email', $email)
            ->where('admin_id !=', $adminId)
            ->first();

        if ($existingEmail) {
            $errors[] = 'Email address is already in use by another account.';
        }

        $updateData = [
            'full_name' => $fullName,
            'email'     => $email,
        ];

        if ($newPassword !== null && $newPassword !== '') {
            if ($currentPassword === null || $currentPassword === '') {
                $errors[] = 'Current password is required to set a new password.';
            } elseif (!password_verify($currentPassword, $admin['password'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation do not match.';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } else {
                $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }

        if (!empty($errors)) {
            $session->setFlashdata('error', implode(' ', $errors));
            return redirect()->to('/superadmin/profile')->withInput();
        }

        // Store old values for audit log (before update)
        $oldValues = [
            'full_name' => $admin['full_name'],
            'email' => $admin['email']
        ];

        $this->adminModel->update($adminId, $updateData);

        // Log the action
        $auditLogModel = new AuditLogModel();
        $newValues = [
            'full_name' => $fullName,
            'email' => $email
        ];
        if (!empty($newPassword)) {
            $newValues['password_changed'] = true;
        }
        
        $auditLogModel->logAction(
            'UPDATE_PROFILE',
            'Admin',
            $adminId,
            "Profile updated: {$fullName} ({$email})",
            $oldValues,
            $newValues
        );

        $session->set('full_name', $fullName);
        $session->set('email', $email);
        
        // Also update profile picture in session if it exists
        $updatedAdmin = $this->adminModel->find($adminId);
        if (!empty($updatedAdmin['profile_picture'])) {
            if (!preg_match('/^https?:\/\//', $updatedAdmin['profile_picture'])) {
                $profilePictureUrl = base_url("superadmin/profile/image/{$adminId}");
                $session->set('profile_picture', $profilePictureUrl);
            } else {
                $session->set('profile_picture', $updatedAdmin['profile_picture']);
            }
        }

        $session->setFlashdata('success', 'Profile updated successfully.');

        return redirect()->to('/superadmin/profile');
    }


    public function updateProfilePicture()
    {
        $session = session();
        $adminId = $session->get('admin_id');

        if (!$adminId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'User not authenticated'
            ]);
        }

        $validation = $this->validate([
            'profile_picture' => [
                'uploaded[profile_picture]',
                'mime_in[profile_picture,image/jpg,image/jpeg,image/png,image/webp,image/gif]',
                'max_size[profile_picture,2048]', // 2MB max
            ]
        ]);

        if (!$validation) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid file. Please upload a JPG, PNG, or GIF image (max 2MB)'
            ]);
        }

        $file = $this->request->getFile('profile_picture');
        if ($file->isValid() && !$file->hasMoved()) {
            // Read the image file as binary data
            $imageData = file_get_contents($file->getTempName());
            
            // Update database with binary image data
            $this->adminModel->update($adminId, [
                'profile_picture' => $imageData
            ]);

            // Update session data - we'll need a URL to serve the image
            $session->set('profile_picture', base_url("superadmin/profile/image/{$adminId}"));

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'profile_picture' => base_url("superadmin/profile/image/{$adminId}")
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to upload file'
        ]);
    }
    
    /**
     * Serve profile image from database
     */
    public function getImage($adminId = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Direct query to retrieve LONGBLOB data
            $builder = $db->table('admins');
            $builder->select('profile_picture');
            $builder->where('admin_id', $adminId);
            $query = $builder->get();
            
            if (!$query || $query->getNumRows() === 0) {
                return $this->response
                    ->setStatusCode(404)
                    ->setBody('Admin not found');
            }
            
            $result = $query->getRowArray();
            
            if (empty($result['profile_picture'])) {
                return $this->response
                    ->setStatusCode(404)
                    ->setBody('Profile picture not found');
            }

            // Get raw binary image data from LONGBLOB
            $imageData = $result['profile_picture'];
            
            // Detect image type from the binary data
            $imageInfo = getimagesizefromstring($imageData);
            $mimeType = $imageInfo ? $imageInfo['mime'] : 'image/png';
            
            // Set appropriate headers for image
            return $this->response
                ->setHeader('Content-Type', $mimeType)
                ->setHeader('Content-Length', strlen($imageData))
                ->setHeader('Cache-Control', 'public, max-age=3600')
                ->setBody($imageData);

        } catch (\Exception $e) {
            log_message('error', 'Profile image retrieval error: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(500)
                ->setBody('Internal server error');
        }
    }
}

