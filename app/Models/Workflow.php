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
        'initiated_by_role', 'current_step', 'total_steps',
        'current_approver_id', 'current_approver_role',
        'status', 'reason', 'combined_reasons',
        'created_at', 'updated_at',
    ];

    // ─── Create workflow with steps ──────────────────────────────────

    /**
     * Create a new workflow with its approval steps.
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
            $totalSteps = count($steps);
            $firstApprover = $steps[0] ?? [];

            $workflowId = $this->create([
                'type'                  => $type,
                'entity_id'             => $entityId,
                'entity_type'           => 'student',
                'initiated_by'          => $initiatedBy,
                'initiated_by_role'     => $initiatedByRole,
                'current_step'          => 1,
                'total_steps'           => $totalSteps,
                'current_approver_id'   => (int) ($firstApprover['approver_id'] ?? 0),
                'current_approver_role' => (string) ($firstApprover['approver_role'] ?? ''),
                'status'                => 'pending',
                'reason'                => $reason,
                'combined_reasons'      => $reason,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            $stepSql = "INSERT INTO workflow_steps
                        (workflow_id, step_number, approver_id, approver_role, action, reason, acted_at)
                        VALUES (:wid, :step_num, :aid, :arole, :action, NULL, NULL)";

            foreach ($steps as $i => $step) {
                $this->query($stepSql, [
                    ':wid'      => $workflowId,
                    ':step_num' => $i + 1,
                    ':aid'      => (int) $step['approver_id'],
                    ':arole'    => (string) $step['approver_role'],
                    ':action'   => 'pending',
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
        $sql = "SELECT *, action AS status, step_number AS step_order
                FROM workflow_steps
                WHERE workflow_id = :wid AND action = 'pending'
                ORDER BY step_number ASC LIMIT 1";
        $row = $this->query($sql, [':wid' => $workflowId])->fetch();
        return $row ?: null;
    }

    // ─── Approve / Reject ────────────────────────────────────────────

    /**
     * Approve the current step. If it's the last step, mark workflow as completed.
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
                    SET action = 'approved', reason = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            // Check if there's a next step
            $nextSql = "SELECT *, step_number AS step_order FROM workflow_steps
                        WHERE workflow_id = :wid AND step_number > :curr
                        ORDER BY step_number ASC LIMIT 1";
            $next = $this->query($nextSql, [
                ':wid'  => $workflowId,
                ':curr' => (int) $currentStep['step_number'],
            ])->fetch();

            if ($next) {
                // Update current approver on workflow
                $this->query(
                    "UPDATE {$this->table}
                     SET current_step = :cstep,
                         current_approver_id = :aid,
                         current_approver_role = :arole,
                         updated_at = NOW()
                     WHERE id = :id",
                    [
                        ':cstep' => $next['step_number'],
                        ':aid'   => $next['approver_id'],
                        ':arole' => $next['approver_role'],
                        ':id'    => $workflowId,
                    ]
                );
            } else {
                // All steps approved — mark workflow as completed / approved
                $this->query(
                    "UPDATE {$this->table}
                     SET status = 'completed', updated_at = NOW()
                     WHERE id = :id",
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
                    SET action = 'rejected', reason = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            $this->query(
                "UPDATE {$this->table} SET status = 'rejected', updated_at = NOW() WHERE id = :id",
                [':id' => $workflowId]
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
        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       u.full_name AS initiated_by_name,
                       ws.step_number AS step_order,
                       ws.id AS step_id
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                INNER JOIN workflow_steps ws ON w.id = ws.workflow_id
                WHERE ws.approver_id = :uid AND ws.action = 'pending' AND w.status = 'pending'
                ORDER BY w.created_at DESC";
        return $this->query($sql, [':uid' => $userId])->fetchAll();
    }

    /**
     * Get workflow history for a given entity.
     */
    public function getHistory(int $entityId, string $entityType = 'student'): array
    {
        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       u.full_name AS initiated_by_name,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id) AS total_steps,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id AND ws.action = 'approved') AS completed_steps
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                WHERE w.entity_id = :eid AND w.entity_type = :etype
                ORDER BY w.created_at DESC";
        $workflows = $this->query($sql, [':eid' => $entityId, ':etype' => $entityType])->fetchAll();

        foreach ($workflows as &$wf) {
            $stepSql = "SELECT ws.*, ws.action AS status, ws.step_number AS step_order, u.full_name AS approver_name
                        FROM workflow_steps ws
                        LEFT JOIN users u ON ws.approver_id = u.id
                        WHERE ws.workflow_id = :wid
                        ORDER BY ws.step_number ASC";
            $wf['steps'] = $this->query($stepSql, [':wid' => $wf['id']])->fetchAll();
        }

        return $workflows;
    }

    /**
     * Get all workflows with optional filters and step details for History page.
     */
    public function getAllHistory(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = "w.type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[] = "w.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "w.created_at >= :dfrom";
            $params[':dfrom'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "w.created_at <= :dto";
            $params[':dto'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       u.full_name AS initiated_by_name,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id) AS total_steps,
                       (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id AND ws.action = 'approved') AS completed_steps
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                {$whereClause}
                ORDER BY w.id DESC";

        $workflows = $this->query($sql, $params)->fetchAll();

        foreach ($workflows as &$wf) {
            $stepSql = "SELECT ws.*, ws.action AS status, ws.step_number AS step_order, u.full_name AS approver_name
                        FROM workflow_steps ws
                        LEFT JOIN users u ON ws.approver_id = u.id
                        WHERE ws.workflow_id = :wid
                        ORDER BY ws.step_number ASC";
            $wf['steps'] = $this->query($stepSql, [':wid' => $wf['id']])->fetchAll();
        }

        return $workflows;
    }

    /**
     * Get all pending workflows, optionally for a specific user.
     */
    public function getPending(?int $userId = null): array
    {
        if ($userId !== null) {
            return $this->getByApprover($userId);
        }

        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       u.full_name AS initiated_by_name
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                WHERE w.status = 'pending'
                ORDER BY w.created_at DESC";
        return $this->query($sql)->fetchAll();
    }
}
