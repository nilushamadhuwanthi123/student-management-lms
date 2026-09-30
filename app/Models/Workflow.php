<?php
/**
 * Workflow Model — Approval workflows
 */

require_once __DIR__ . '/BaseModel.php';

class Workflow extends BaseModel
{
    protected string $table = 'workflows';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'type', 'entity_id', 'entity_type', 'initiated_by',
        'initiated_by_role', 'status', 'reason',
        'created_at', 'updated_at',
    ];

    // ─── Create workflow with steps ──────────────────────────────────

    /**
     * Create a new workflow with its approval steps.
     *
     * @param  string $type            Workflow type (e.g. 'student_deactivation')
     * @param  int    $entityId        Related entity ID
     * @param  int    $initiatedBy     User who started the workflow
     * @param  string $initiatedByRole Role of the initiator
     * @param  array  $steps           Array of ['approver_id' => int, 'approver_role' => string, 'order' => int]
     * @param  string $reason          Reason for workflow
     * @return int    Workflow ID
     */
    public function createWorkflow(
        string $type,
        int $entityId,
        int $initiatedBy,
        string $initiatedByRole,
        array $steps,
        string $reason = ''
    ): int {
        $this->db->beginTransaction();
        try {
            $workflowId = $this->create([
                'type'              => $type,
                'entity_id'         => $entityId,
                'entity_type'       => $type,
                'initiated_by'      => $initiatedBy,
                'initiated_by_role' => $initiatedByRole,
                'status'            => 'pending',
                'reason'            => $reason,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            $stepSql = "INSERT INTO workflow_steps
                        (workflow_id, approver_id, approver_role, step_order, status, created_at)
                        VALUES (:wid, :aid, :arole, :ord, :status, NOW())";

            foreach ($steps as $i => $step) {
                $this->query($stepSql, [
                    ':wid'    => $workflowId,
                    ':aid'    => $step['approver_id'],
                    ':arole'  => $step['approver_role'],
                    ':ord'    => $step['order'] ?? ($i + 1),
                    ':status' => ($i === 0) ? 'pending' : 'waiting',
                ]);
            }

            $this->db->commit();
            return $workflowId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ─── Step queries ────────────────────────────────────────────────

    /**
     * Get the current pending step for a workflow.
     */
    public function getCurrentStep(int $workflowId): ?array
    {
        $sql = "SELECT * FROM workflow_steps
                WHERE workflow_id = :wid AND status = 'pending'
                ORDER BY step_order ASC LIMIT 1";
        $row = $this->query($sql, [':wid' => $workflowId])->fetch();
        return $row ?: null;
    }

    // ─── Approve / Reject ────────────────────────────────────────────

    /**
     * Approve the current step. If it's the last step, mark workflow as approved.
     */
    public function approve(int $workflowId, int $approverId, string $reason = ''): bool
    {
        $currentStep = $this->getCurrentStep($workflowId);
        if (!$currentStep || (int) $currentStep['approver_id'] !== $approverId) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // Mark current step as approved
            $sql = "UPDATE workflow_steps
                    SET status = 'approved', remarks = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            // Check if there's a next step
            $nextSql = "SELECT id FROM workflow_steps
                        WHERE workflow_id = :wid AND status = 'waiting'
                        ORDER BY step_order ASC LIMIT 1";
            $next = $this->query($nextSql, [':wid' => $workflowId])->fetch();

            if ($next) {
                // Advance next step to pending
                $this->query(
                    "UPDATE workflow_steps SET status = 'pending' WHERE id = :id",
                    [':id' => $next['id']]
                );
                $this->query(
                    "UPDATE {$this->table} SET updated_at = NOW() WHERE id = :id",
                    [':id' => $workflowId]
                );
            } else {
                // All steps approved — mark workflow as approved
                $this->query(
                    "UPDATE {$this->table} SET status = 'approved', updated_at = NOW() WHERE id = :id",
                    [':id' => $workflowId]
                );
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Reject the workflow at the current step.
     */
    public function reject(int $workflowId, int $approverId, string $reason = ''): bool
    {
        $currentStep = $this->getCurrentStep($workflowId);
        if (!$currentStep || (int) $currentStep['approver_id'] !== $approverId) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $sql = "UPDATE workflow_steps
                    SET status = 'rejected', remarks = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            $this->query(
                "UPDATE {$this->table} SET status = 'rejected', updated_at = NOW() WHERE id = :id",
                [':id' => $workflowId]
            );

            // Cancel remaining steps
            $this->query(
                "UPDATE workflow_steps SET status = 'cancelled' WHERE workflow_id = :wid AND status = 'waiting'",
                [':wid' => $workflowId]
            );

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ─── Listing ─────────────────────────────────────────────────────

    /**
     * Get workflows pending approval by a specific user.
     */
    public function getByApprover(int $userId): array
    {
        $sql = "SELECT w.*, ws.step_order, ws.id AS step_id
                FROM {$this->table} w
                INNER JOIN workflow_steps ws ON w.id = ws.workflow_id
                WHERE ws.approver_id = :uid AND ws.status = 'pending'
                ORDER BY w.created_at DESC";
        return $this->query($sql, [':uid' => $userId])->fetchAll();
    }

    /**
     * Get workflow history for a given entity.
     */
    public function getHistory(int $entityId, string $entityType): array
    {
        $sql = "SELECT w.*,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id) AS total_steps,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id AND ws.status = 'approved') AS approved_steps
                FROM {$this->table} w
                WHERE w.entity_id = :eid AND w.entity_type = :etype
                ORDER BY w.created_at DESC";
        return $this->query($sql, [':eid' => $entityId, ':etype' => $entityType])->fetchAll();
    }

    /**
     * Get all pending workflows, optionally for a specific user.
     */
    public function getPending(?int $userId = null): array
    {
        if ($userId !== null) {
            return $this->getByApprover($userId);
        }

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'pending'
                ORDER BY created_at DESC";
        return $this->query($sql)->fetchAll();
    }
}
