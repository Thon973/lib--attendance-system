<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';
    protected $allowedFields = [
        'user_id',
        'user_type',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    protected $useTimestamps = false; // We manually set created_at
    protected $createdField = 'created_at';
    protected $updatedField = null;
    protected $deletedField = null;

    /**
     * Log an action to the audit log
     * 
     * @param string $action Action name (e.g., 'CREATE_STUDENT', 'UPDATE_ADMIN')
     * @param string|null $entityType Type of entity (e.g., 'Student', 'Admin')
     * @param int|null $entityId ID of the entity
     * @param string|null $description Description of the action
     * @param array|null $oldValues Old values (for updates)
     * @param array|null $newValues New values (for updates)
     * @param int|null $userId Optional user ID (for student self-updates, etc.)
     * @param string|null $userType Optional user type (for student self-updates, etc.)
     * @return int|false Log ID on success, false on failure
     */
    public function logAction(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $userType = null
    ) {
        $session = session();
        $request = \Config\Services::request();

        // Use provided userId/userType, or fall back to session data
        $loggedUserId = $userId ?? $session->get('admin_id');
        $loggedUserType = $userType ?? $session->get('role');
        
        // If we still don't have user info, try to get it from other session keys
        if (!$loggedUserId) {
            $loggedUserId = $session->get('student_id');
            if ($loggedUserId) {
                $loggedUserType = 'Student';
            }
        }
        
        // Final fallback - if we still don't have user info, don't log user details
        if (!$loggedUserId) {
            $loggedUserId = null;
            $loggedUserType = null;
        }

        $data = [
            'user_id' => $loggedUserId,
            'user_type' => $loggedUserType,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getUserAgent()->getAgentString(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }
}

