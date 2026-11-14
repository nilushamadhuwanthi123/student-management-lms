<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class AuditController extends BaseController
{
    private \AuditLog $auditModel;

    public function __construct()
    {
        parent::__construct();
        $this->auditModel = new \AuditLog();
    }

    /**
     * Audit log listing with filters.
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        // The full system audit log covers every branch and every account,
        // so it is the super admin's. Branch admins can still open the
        // history of a person in their own branch (userHistory).
        AuthMiddleware::requireSuperAdmin();

        $filters = [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'action'      => $_GET['action'] ?? ($_GET['action_type'] ?? ''),
            'action_type' => $_GET['action_type'] ?? ($_GET['action'] ?? ''),
            'entity_type' => $_GET['entity_type'] ?? '',
            'user_id'     => $_GET['user_id'] ?? '',
        ];

        // Remove empty filters
        $activeFilters = array_filter($filters, fn($v) => $v !== '');

        $entries = [];
        try {
            $entries = $this->auditModel->search($activeFilters);
        } catch (\Throwable $e) {
            error_log('Audit log search error: ' . $e->getMessage());
        }

        $actionTypes = [
            'create_student', 'update_student', 'delete_student',
            'mark_payment_paid', 'generate_monthly_payments',
            'approve_workflow', 'reject_workflow',
            'create_user', 'update_user', 'login', 'update_settings',
        ];
        $entityTypes = ['student', 'user', 'payment', 'workflow', 'class', 'institute', 'settings'];

        $users = [];
        if ($this->db) {
            try {
                $userModel = new \User();
                $users = $userModel->all('full_name ASC');
            } catch (\Throwable $e) {}
        }

        $this->view('audit/index', [
            'pageTitle'   => 'Audit Log',
            'entries'     => $entries,
            'logs'        => $entries,
            'filters'     => $filters,
            'actionTypes' => $actionTypes,
            'entityTypes' => $entityTypes,
            'users'       => $users,
            'currentPage' => 1,
            'totalPages'  => 1,
        ]);
    }

    /**
     * Export the (filtered) audit log as a CSV file.
     * Reuses the same filters as index() so "Export CSV" exports exactly
     * what the admin is currently looking at.
     */
    public function export(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();

        $filters = [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'action'      => $_GET['action'] ?? ($_GET['action_type'] ?? ''),
            'entity_type' => $_GET['entity_type'] ?? '',
            'user_id'     => $_GET['user_id'] ?? '',
        ];
        $activeFilters = array_filter($filters, fn($v) => $v !== '');

        $entries = [];
        try {
            $entries = $this->auditModel->search($activeFilters);
        } catch (\Throwable $e) {
            error_log('Audit log export error: ' . $e->getMessage());
        }

        $filename = 'audit-log-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date/Time', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address', 'Details']);

        foreach ($entries as $row) {
            $details = $row['details'] ?? '';
            fputcsv($out, array_map(static function ($v) {
                if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($v)) {
                    return "'" . $v;
                }
                return $v;
            }, [
                $row['created_at'] ?? '',
                $row['user_name'] ?? 'System',
                $row['action'] ?? '',
                $row['entity_type'] ?? '',
                $row['entity_id'] ?? '',
                $row['ip_address'] ?? '',
                $details,
            ]));
        }

        try {
            $user = $this->getCurrentUser();
            $this->auditModel->log((int) ($user['id'] ?? 0), 'user', $user['role'] ?? 'unknown', 'export_report', 'audit_log', null, ['file' => $filename]);
        } catch (\Throwable $e) {
            error_log('Audit export log failed: ' . $e->getMessage());
        }

        fclose($out);
        exit;
    }

    /**
     * Show audit trail for a specific user.
     */
    public function userHistory(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $userType = $_GET['user_type'] ?? 'user';

        $scope = \App\Services\BranchScope::current();
        $allowed = $userType === 'student'
            ? $scope->allowsStudent((int) $id)
            : ($userType === 'user' ? $scope->allowsUser((int) $id) : $scope->isGlobal());
        if (!$allowed) {
            http_response_code(403);
            $this->flash('error', 'That account is not in your branch.');
            $this->redirect('/users');
        }

        $entries = [];
        try {
            $entries = $this->auditModel->getByUser((int) $id, $userType);
        } catch (\Throwable $e) {
            error_log('User audit history error: ' . $e->getMessage());
        }

        $this->view('audit/user-history', [
            'pageTitle' => 'User Audit History',
            'entries'   => $entries,
            'userId'    => (int) $id,
            'userType'  => $userType,
        ]);
    }

    /**
     * Workflow history view (referenced in routes as AuditController::workflowHistory).
     */
    public function workflowHistory(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $filters = [
            'type'        => trim($_GET['type'] ?? ''),
            'status'      => trim($_GET['status'] ?? ''),
            'date_from'   => trim($_GET['date_from'] ?? ''),
            'date_to'     => trim($_GET['date_to'] ?? ''),
            'entity_id'   => !empty($_GET['entity_id']) ? (int) $_GET['entity_id'] : 0,
            'entity_type' => trim($_GET['entity_type'] ?? ''),
        ];

        $history = [];
        try {
            $workflowModel = new \Workflow();
            if ($filters['entity_id'] > 0 && $filters['entity_type'] !== '') {
                $history = $workflowModel->getHistory($filters['entity_id'], $filters['entity_type']);
            } else {
                $history = $workflowModel->getAllHistory(array_filter($filters));
            }
        } catch (\Throwable $e) {
            error_log('Workflow history error: ' . $e->getMessage());
        }

        $scope = \App\Services\BranchScope::current();
        if (!$scope->isGlobal()) {
            $history = array_values(array_filter($history, static fn (array $w): bool =>
                ($w['entity_type'] ?? 'student') !== 'student' || $scope->allowsStudent((int) ($w['entity_id'] ?? 0))));
        }

        $this->view('audit/workflow-history', [
            'pageTitle'   => 'Workflow History',
            'history'     => $history,
            'workflows'   => $history,
            'filters'     => $filters,
            'entityId'    => $filters['entity_id'],
            'entityType'  => $filters['entity_type'],
            'currentPage' => 1,
            'totalPages'  => 1,
        ]);
    }
}
