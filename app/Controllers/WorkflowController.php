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


        // A branch admin sees requests about their own branch's students.
        $scope = \App\Services\BranchScope::current();
        if (!$scope->isGlobal()) {
            $pendingWorkflows = array_values(array_filter($pendingWorkflows, static fn (array $w): bool =>
                ($w['entity_type'] ?? 'student') !== 'student' || $scope->allowsStudent((int) ($w['entity_id'] ?? 0))));
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
        // access: role-checked -- same as the Workflows inbox; ids from students and users can collide
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);
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

        $this->redirect('/workflows');
    }

    /**
     * Reject a workflow (POST).
     */
    public function reject(string $id): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Workflows inbox; ids from students and users can collide
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '') {
            $this->flash('error', 'A reason is required when rejecting a workflow.');
            $this->redirect('/workflows');
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

        $this->redirect('/workflows');
    }

    /**
     * Workflow history view.
     */
    public function history(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Workflows inbox
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $filters = [
            'type'        => trim($_GET['type'] ?? ''),
            'status'      => trim($_GET['status'] ?? ''),
            'date_from'   => trim($_GET['date_from'] ?? ''),
            'date_to'     => trim($_GET['date_to'] ?? ''),
            'entity_id'   => !empty($_GET['entity_id']) ? (int) $_GET['entity_id'] : null,
            'entity_type' => trim($_GET['entity_type'] ?? ''),
        ];

        $workflows = [];
        try {
            if (!empty($filters['entity_id']) && !empty($filters['entity_type'])) {
                $workflows = $this->workflowModel->getHistory((int) $filters['entity_id'], $filters['entity_type']);
            } else {
                $workflows = $this->workflowModel->getAllHistory(array_filter($filters));
            }
        } catch (\Throwable $e) {
            error_log('Workflow history error: ' . $e->getMessage());
        }


        // A branch admin sees requests about their own branch's students.
        $scope = \App\Services\BranchScope::current();
        if (!$scope->isGlobal()) {
            $workflows = array_values(array_filter($workflows, static fn (array $w): bool =>
                ($w['entity_type'] ?? 'student') !== 'student' || $scope->allowsStudent((int) ($w['entity_id'] ?? 0))));
        }

        $this->view('workflows/history', [
            'pageTitle'   => 'Workflow History',
            'workflows'   => $workflows,
            'history'     => $workflows,
            'filters'     => $filters,
            'currentPage' => 1,
            'totalPages'  => 1,
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
