<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class WorkflowController extends BaseController
{
    private \Workflow $workflowModel;

    public function __construct()
    {
        parent::__construct();
        $this->workflowModel = new \Workflow();
    }

    /**
     * Show pending approvals inbox for the current user.
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);

        $userId = (int) session_get('user_id');
        $user = $this->getCurrentUser();

        // Super admins/admins see all pending; others see only their own
        $pendingWorkflows = [];
        try {
            if (in_array($user['role'], ['super_admin', 'admin'], true)) {
                $pendingWorkflows = $this->workflowModel->getPending();
            } else {
                $pendingWorkflows = $this->workflowModel->getByApprover($userId);
            }
        } catch (\Throwable $e) {
            error_log('Workflow index error: ' . $e->getMessage());
        }

        $this->view('workflows/index', [
            'pageTitle' => 'Pending Approvals',
            'workflows' => $pendingWorkflows,
        ]);
    }

    /**
     * Approve a workflow step (POST).
     */
    public function approve(string $id): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $reason = trim($_POST['reason'] ?? '');

        try {
            $result = $this->workflowModel->approve((int) $id, $userId, $reason);

            if ($result) {
                $this->logAudit('approve_workflow', 'workflow', (int) $id, [
                    'reason' => $reason,
                ]);
                $this->flash('success', 'Workflow step approved successfully.');
            } else {
                $this->flash('error', 'Unable to approve. You may not be the current approver for this workflow.');
            }
        } catch (\Throwable $e) {
            error_log('Workflow approve error: ' . $e->getMessage());
            $this->flash('error', 'An error occurred while processing the approval.');
        }

        $this->redirect(BASE_URL . '/workflows');
    }

    /**
     * Reject a workflow (POST).
     */
    public function reject(string $id): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '') {
            $this->flash('error', 'A reason is required when rejecting a workflow.');
            $this->redirect(BASE_URL . '/workflows');
        }

        try {
            $result = $this->workflowModel->reject((int) $id, $userId, $reason);

            if ($result) {
                $this->logAudit('reject_workflow', 'workflow', (int) $id, [
                    'reason' => $reason,
                ]);
                $this->flash('success', 'Workflow rejected.');
            } else {
                $this->flash('error', 'Unable to reject. You may not be the current approver for this workflow.');
            }
        } catch (\Throwable $e) {
            error_log('Workflow reject error: ' . $e->getMessage());
            $this->flash('error', 'An error occurred while processing the rejection.');
        }

        $this->redirect(BASE_URL . '/workflows');
    }

    /**
     * Workflow history view with filters.
     */
    public function history(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $filters = [
            'type'      => $_GET['type'] ?? '',
            'status'    => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        // Remove empty filters for the query
        $activeFilters = array_filter($filters, fn($v) => $v !== '');

        $workflows = [];
        try {
            $workflows = $this->workflowModel->getFilteredHistory($activeFilters);
        } catch (\Throwable $e) {
            error_log('Workflow history error: ' . $e->getMessage());
        }

        $this->view('workflows/history', [
            'pageTitle'  => 'Workflow History',
            'workflows'  => $workflows,
            'filters'    => $filters,
        ]);
    }

    /**
     * Log an audit entry.
     */
    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'unknown',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
