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
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $filters = [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'action'      => $_GET['action'] ?? '',
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

        $this->view('audit/index', [
            'pageTitle' => 'Audit Log',
            'entries'   => $entries,
            'filters'   => $filters,
        ]);
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

        $entityId   = (int) ($_GET['entity_id'] ?? 0);
        $entityType = $_GET['entity_type'] ?? '';

        $history = [];
        if ($entityId > 0 && $entityType !== '') {
            try {
                $workflowModel = new \Workflow();
                $history = $workflowModel->getHistory($entityId, $entityType);
            } catch (\Throwable $e) {
                error_log('Workflow history error: ' . $e->getMessage());
            }
        }

        $this->view('audit/workflow-history', [
            'pageTitle'  => 'Workflow History',
            'history'    => $history,
            'entityId'   => $entityId,
            'entityType' => $entityType,
        ]);
    }
}
