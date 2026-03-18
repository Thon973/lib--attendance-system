<?php

namespace App\Controllers\SuperAdmin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class AuditLogs extends BaseController
{
    public function auditLogs()
    {
        return view('superadmin/audit-logs');
    }

    public function getAuditLogs()
    {
        try {
            $auditLogModel = new \App\Models\AuditLogModel();
            $db = \Config\Database::connect();

            // Get filter parameters
            $startDate = $this->request->getGet('start_date');
            $endDate = $this->request->getGet('end_date');
            $action = $this->request->getGet('action');
            $entityType = $this->request->getGet('entity_type');
            $userId = $this->request->getGet('user_id');
            $page = (int)($this->request->getGet('page') ?? 1);
            $limit = (int)($this->request->getGet('limit') ?? 50);
            $offset = ($page - 1) * $limit;

            // Build query
            $builder = $db->table('audit_logs');
            $builder->select("audit_logs.*, 
                             COALESCE(admins.full_name, CONCAT(students.first_name, ' ', students.last_name)) as full_name, 
                             COALESCE(admins.email, students.student_number) as email");
            $builder->join('admins', "admins.admin_id = audit_logs.user_id AND audit_logs.user_type = 'Admin'", 'left');
            $builder->join('students', "students.student_id = audit_logs.user_id AND audit_logs.user_type = 'Student'", 'left');
            $builder->orderBy('audit_logs.created_at', 'DESC');

            // Apply filters
            if (!empty($startDate)) {
                $builder->where('DATE(audit_logs.created_at) >=', $startDate);
            }
            if (!empty($endDate)) {
                $builder->where('DATE(audit_logs.created_at) <=', $endDate);
            }
            if (!empty($action)) {
                $builder->where('audit_logs.action', $action);
            }
            if (!empty($entityType)) {
                $builder->where('audit_logs.entity_type', $entityType);
            }
            if (!empty($userId)) {
                $builder->where('audit_logs.user_id', $userId);
            }

            // Clone the builder to get count without affecting the main query
            $countBuilder = clone $builder;
            $totalCount = $countBuilder->countAllResults(false);

            // Get paginated results
            $logs = $builder->limit($limit, $offset)->get()->getResultArray();

            // Format logs
            $formattedLogs = [];
            foreach ($logs as $log) {
                $oldValues = !empty($log['old_values']) ? json_decode($log['old_values'], true) : null;
                $newValues = !empty($log['new_values']) ? json_decode($log['new_values'], true) : null;

                $formattedLogs[] = [
                    'log_id' => $log['log_id'],
                    'user_name' => !empty($log['full_name']) ? $log['full_name'] : ($log['user_type'] === 'Student' ? 'Unknown Student' : 'System'),
                    'user_email' => $log['email'] ?? 'N/A',
                    'user_type' => $log['user_type'] ?? 'N/A',
                    'action' => $log['action'],
                    'entity_type' => $log['entity_type'] ?? 'N/A',
                    'entity_id' => $log['entity_id'],
                    'description' => $log['description'],
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'ip_address' => $log['ip_address'] ?? 'N/A',
                    'created_at' => $log['created_at'],
                    'formatted_date' => date('M d, Y h:i A', strtotime($log['created_at']))
                ];
            }

            return $this->response->setJSON([
                'success' => true,
                'logs' => $formattedLogs,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Audit logs error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading audit logs: ' . $e->getMessage(),
                'logs' => [],
                'total' => 0
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAuditLogFilters()
    {
        try {
            $db = \Config\Database::connect();

            // Get unique actions
            $actions = $db->table('audit_logs')
                ->select('action')
                ->distinct()
                ->orderBy('action', 'ASC')
                ->get()
                ->getResultArray();

            // Get unique entity types
            $entityTypes = $db->table('audit_logs')
                ->select('entity_type')
                ->distinct()
                ->where('entity_type IS NOT NULL')
                ->orderBy('entity_type', 'ASC')
                ->get()
                ->getResultArray();

            // Get users who have performed actions (both admins and students)
            $users = $db->table('audit_logs')
                ->select("audit_logs.user_id, 
                         audit_logs.user_type,
                         COALESCE(admins.full_name, CONCAT(students.first_name, ' ', students.last_name)) as full_name, 
                         COALESCE(admins.email, students.student_number) as email")
                ->join('admins', "admins.admin_id = audit_logs.user_id AND audit_logs.user_type = 'Admin'", 'left')
                ->join('students', "students.student_id = audit_logs.user_id AND audit_logs.user_type = 'Student'", 'left')
                ->where('audit_logs.user_id IS NOT NULL')
                ->distinct()
                ->orderBy('full_name', 'ASC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'success' => true,
                'actions' => array_column($actions, 'action'),
                'entity_types' => array_column($entityTypes, 'entity_type'),
                'users' => $users
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading filters: ' . $e->getMessage()
            ])->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
