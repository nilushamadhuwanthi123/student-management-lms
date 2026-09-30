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

        $this->redirect('/workflows');
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

        $entityId   = (int) ($_GET['entity_id'] ?? 0);
        $entityType = $_GET['entity_type'] ?? '';

        $history = [];
        if ($entityId > 0 && $entityType !== '') {
            try {
                $history = $this->workflowModel->getHistory($entityId, $entityType);
            } catch (\Throwable $e) {
                error_log('Workflow history error: ' . $e->getMessage());
            }
        }

        $this->view('workflows/history', [
            'pageTitle'  => 'Workflow History',
            'history'    => $history,
            'entityId'   => $entityId,
            'entityType' => $entityType,
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
