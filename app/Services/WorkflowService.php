<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AuthMiddleware;

class WorkflowService
{
    private \Workflow $workflowModel;
    private \AuditLog $auditLog;

    public function __construct()
    {
        $this->workflowModel = new \Workflow();
        $this->auditLog = new \AuditLog();
    }

    /**
     * Create a student deletion workflow with the appropriate approval chain.
     *
     * @param  int    $studentId   The student to delete
     * @param  int    $initiatedBy User ID who initiated the request
     * @param  string $reason      Reason for deletion
     * @return int    Workflow ID
     */
    public function createDeletionWorkflow(int $studentId, int $initiatedBy, string $reason): int
    {
        $initiatorRole = session_get('user_role', 'unknown');
        $steps = $this->getApprovalChain('student_deletion', $initiatorRole);

        $workflowId = $this->workflowModel->createWorkflow(
            'student_deletion',
            $studentId,
            $initiatedBy,
            $initiatorRole,
            $steps,
            $reason
        );

        $this->auditLog->log(
            $initiatedBy,
            'user',
            $initiatorRole,
            'initiate_deletion_workflow',
            'student',
            $studentId,
            ['workflow_id' => $workflowId, 'reason' => $reason]
        );

        return $workflowId;
    }

    /**
     * Create a student deactivation workflow with the appropriate approval chain.
     *
     * @param  int    $studentId   The student to deactivate
     * @param  int    $initiatedBy User ID who initiated the request
     * @param  string $reason      Reason for deactivation
     * @return int    Workflow ID
     */
    public function createDeactivationWorkflow(int $studentId, int $initiatedBy, string $reason): int
    {
        $initiatorRole = session_get('user_role', 'unknown');
        $steps = $this->getApprovalChain('student_deactivation', $initiatorRole);

        $workflowId = $this->workflowModel->createWorkflow(
            'student_deactivation',
            $studentId,
            $initiatedBy,
            $initiatorRole,
            $steps,
            $reason
        );

        $this->auditLog->log(
            $initiatedBy,
            'user',
            $initiatorRole,
            'initiate_deactivation_workflow',
            'student',
            $studentId,
            ['workflow_id' => $workflowId, 'reason' => $reason]
        );

        return $workflowId;
    }

    /**
     * Process an approval on a workflow.
     *
     * @param  int    $workflowId
     * @param  int    $approverId
     * @param  string $reason
     * @return bool
     */
    public function processApproval(int $workflowId, int $approverId, string $reason): bool
    {
        $result = $this->workflowModel->approve($workflowId, $approverId, $reason);

        if ($result) {
            $approverRole = session_get('user_role', 'unknown');
            $this->auditLog->log(
                $approverId,
                'user',
                $approverRole,
                'approve_workflow_step',
                'workflow',
                $workflowId,
                ['reason' => $reason]
            );

            // Check if the workflow is now fully approved and execute the action
            $workflow = $this->workflowModel->find($workflowId);
            if ($workflow && $workflow['status'] === 'approved') {
                $this->executeWorkflowAction($workflow);
            }
        }

        return $result;
    }

    /**
     * Process a rejection on a workflow.
     *
     * @param  int    $workflowId
     * @param  int    $approverId
     * @param  string $reason
     * @return bool
     */
    public function processRejection(int $workflowId, int $approverId, string $reason): bool
    {
        $result = $this->workflowModel->reject($workflowId, $approverId, $reason);

        if ($result) {
            $approverRole = session_get('user_role', 'unknown');
            $this->auditLog->log(
                $approverId,
                'user',
                $approverRole,
                'reject_workflow',
                'workflow',
                $workflowId,
                ['reason' => $reason]
            );
        }

        return $result;
    }

    /**
     * Get the approval chain (list of required steps) based on workflow type
     * and the role of the person who initiated it.
     *
     * @param  string $type          Workflow type
     * @param  string $initiatorRole Role of the initiator
     * @return array<array{approver_id: int, approver_role: string, order: int}>
     */
    public function getApprovalChain(string $type, string $initiatorRole): array
    {
        // Define the approval hierarchy based on workflow type
        // In a production system, this would come from the database settings
        $chains = [
            'student_deletion' => [
                'staff'       => ['coordinator', 'admin', 'super_admin'],
                'coordinator' => ['admin', 'super_admin'],
                'admin'       => ['super_admin'],
                'super_admin' => [], // Super admin can delete directly
            ],
            'student_deactivation' => [
                'staff'       => ['coordinator', 'admin'],
                'coordinator' => ['admin'],
                'admin'       => [], // Admin can deactivate directly
                'super_admin' => [],
            ],
        ];

        $chain = $chains[$type][$initiatorRole] ?? ['admin', 'super_admin'];

        // Build steps — in a real system, we would look up specific user IDs
        // For now, we use role-based placeholders (approver_id = 0 means any user with that role)
        $steps = [];
        foreach ($chain as $order => $approverRole) {
            $steps[] = [
                'approver_id'   => 0, // Will be resolved to a specific user
                'approver_role' => $approverRole,
                'order'         => $order + 1,
            ];
        }

        return $steps;
    }

    /**
     * Execute the final action when a workflow is fully approved.
     */
    private function executeWorkflowAction(array $workflow): void
    {
        try {
            $studentModel = new \Student();

            switch ($workflow['type']) {
                case 'student_deletion':
                    $studentModel->delete((int) $workflow['entity_id']);
                    $this->auditLog->log(
                        (int) $workflow['initiated_by'],
                        'user',
                        $workflow['initiated_by_role'],
                        'delete_student_executed',
                        'student',
                        (int) $workflow['entity_id'],
                        ['workflow_id' => $workflow['id']]
                    );
                    break;

                case 'student_deactivation':
                    $studentModel->deactivate((int) $workflow['entity_id']);
                    $this->auditLog->log(
                        (int) $workflow['initiated_by'],
                        'user',
                        $workflow['initiated_by_role'],
                        'deactivate_student_executed',
                        'student',
                        (int) $workflow['entity_id'],
                        ['workflow_id' => $workflow['id']]
                    );
                    break;
            }
        } catch (\Throwable $e) {
            error_log('Workflow action execution failed: ' . $e->getMessage());
        }
    }
}
