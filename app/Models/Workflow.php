<?php
/**
 * Workflow Model — Approval workflows
 *
 * DB schema:
 * - workflows: type ENUM('deactivation','deletion','temp_activation'),
 *   entity_type ENUM('student'), current_step, total_steps,
 *   current_approver_id, current_approver_role,
 *   status ENUM('pending','approved','rejected','completed','cancelled'),
 *   reason, combined_reasons
 * - workflow_steps: step_number (NOT step_order), action ENUM('pending','approved','rejected')
 *   (NOT status), reason (NOT remarks), acted_at. No created_at column.
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
     *
     * @param  string $type            Workflow type: 'deactivation', 'deletion', or 'temp_activation'
     * @param  int    $entityId        Related entity ID (student ID)
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
            $totalSteps = count($steps);
            $firstStep  = $steps[0] ?? null;

            $workflowId = $this->create([
                'type'                  => $type,
                'entity_id'             => $entityId,
                'entity_type'           => 'student',
                'initiated_by'          => $initiatedBy,
                'initiated_by_role'     => $initiatedByRole,
                'status'                => 'pending',
                'reason'                => $reason,
                'current_step'          => 1,
                'total_steps'           => $totalSteps,
                'current_approver_id'   => $firstStep['approver_id'] ?? null,
                'current_approver_role' => $firstStep['approver_role'] ?? null,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            // workflow_steps columns: workflow_id, step_number, approver_id,
            // approver_role, action (ENUM: pending/approved/rejected), reason, acted_at
            $stepSql = "INSERT INTO workflow_steps
                        (workflow_id, step_number, approver_id, approver_role, action)
                        VALUES (:wid, :snum, :aid, :arole, 'pending')";

            foreach ($steps as $i => $step) {
                $this->query($stepSql, [
                    ':wid'   => $workflowId,
                    ':snum'  => $step['order'] ?? ($i + 1),
                    ':aid'   => $step['approver_id'],
                    ':arole' => $step['approver_role'],
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
     * Uses the workflow's current_step to identify which step is active.
     */
    public function getCurrentStep(int $workflowId): ?array
    {
        // Get the workflow's current_step, then find the matching step
        $wf = $this->find($workflowId);
        if (!$wf || $wf['status'] !== 'pending') {
            return null;
        }

        $sql = "SELECT * FROM workflow_steps
                WHERE workflow_id = :wid AND step_number = :snum AND action = 'pending'
                LIMIT 1";
        $row = $this->query($sql, [
            ':wid'  => $workflowId,
            ':snum' => (int) $wf['current_step'],
        ])->fetch();
        return $row ?: null;
    }

    // ─── Approve / Reject ────────────────────────────────────────────

    /**
     * Approve the current step. If it's the last step, mark workflow as approved/completed.
     */
    public function approve(int $workflowId, int $approverId, string $reason = ''): bool
    {
        $currentStep = $this->getCurrentStep($workflowId);
        if (!$currentStep || (int) $currentStep['approver_id'] !== $approverId) {
            return false;
        }

        $workflow = $this->find($workflowId);

        $this->db->beginTransaction();
        try {
            // Mark current step as approved
            $sql = "UPDATE workflow_steps
                    SET action = 'approved', reason = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            $currentStepNum = (int) $workflow['current_step'];
            $totalSteps     = (int) $workflow['total_steps'];

            if ($currentStepNum < $totalSteps) {
                // Advance to next step
                $nextStepNum = $currentStepNum + 1;

                // Find the next step's approver info
                $nextSql = "SELECT approver_id, approver_role FROM workflow_steps
                            WHERE workflow_id = :wid AND step_number = :snum LIMIT 1";
                $next = $this->query($nextSql, [
                    ':wid'  => $workflowId,
                    ':snum' => $nextStepNum,
                ])->fetch();

                $this->query(
                    "UPDATE {$this->table}
                     SET current_step = :cs, current_approver_id = :caid,
                         current_approver_role = :car, updated_at = NOW()
                     WHERE id = :id",
                    [
                        ':cs'   => $nextStepNum,
                        ':caid' => $next['approver_id'] ?? null,
                        ':car'  => $next['approver_role'] ?? null,
                        ':id'   => $workflowId,
                    ]
                );
            } else {
                // All steps approved — mark workflow as approved
                $this->query(
                    "UPDATE {$this->table}
                     SET status = 'approved', current_approver_id = NULL,
                         current_approver_role = NULL, updated_at = NOW()
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
            // Mark current step as rejected
            $sql = "UPDATE workflow_steps
                    SET action = 'rejected', reason = :reason, acted_at = NOW()
                    WHERE id = :id";
            $this->query($sql, [':reason' => $reason, ':id' => $currentStep['id']]);

            // Mark workflow as rejected
            $this->query(
                "UPDATE {$this->table}
                 SET status = 'rejected', current_approver_id = NULL,
                     current_approver_role = NULL, updated_at = NOW()
                 WHERE id = :id",
                [':id' => $workflowId]
            );

            // Note: remaining pending steps stay as 'pending' in the DB
            // since 'cancelled' is not a valid ENUM value for workflow_steps.action.
            // The workflow's 'rejected' status prevents further action.

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
     * Joins students and users tables to provide names for views.
     */
    public function getByApprover(int $userId): array
    {
        $sql = "SELECT w.*, ws.step_number, ws.id AS step_id,
                       s.full_name AS student_name,
                       s.student_id_display,
                       u.full_name AS initiated_by_name,
                       CONCAT(w.current_approver_role) AS current_approver
                FROM {$this->table} w
                INNER JOIN workflow_steps ws ON w.id = ws.workflow_id
                LEFT JOIN students s ON w.entity_type = 'student' AND w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                WHERE ws.approver_id = :uid AND ws.action = 'pending'
                  AND ws.step_number = w.current_step
                  AND w.status = 'pending'
                ORDER BY w.created_at DESC";
        return $this->query($sql, [':uid' => $userId])->fetchAll();
    }

    /**
     * Get workflow history for a given entity, including steps and names.
     */
    public function getHistory(int $entityId, string $entityType): array
    {
        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       s.student_id_display,
                       u.full_name AS initiated_by_name,
                       w.total_steps,
                       (SELECT COUNT(*) FROM workflow_steps ws
                        WHERE ws.workflow_id = w.id AND ws.action = 'approved') AS completed_steps
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_type = 'student' AND w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                WHERE w.entity_id = :eid AND w.entity_type = :etype
                ORDER BY w.created_at DESC";
        $workflows = $this->query($sql, [':eid' => $entityId, ':etype' => $entityType])->fetchAll();

        // Load steps for each workflow
        foreach ($workflows as &$wf) {
            $stepsSql = "SELECT ws.*, u.full_name AS approver_name
                         FROM workflow_steps ws
                         LEFT JOIN users u ON ws.approver_id = u.id
                         WHERE ws.workflow_id = :wid
                         ORDER BY ws.step_number ASC";
            $wf['steps'] = $this->query($stepsSql, [':wid' => $wf['id']])->fetchAll();

            // Determine completed_at from the last acted step
            $lastActed = null;
            foreach ($wf['steps'] as $step) {
                if (!empty($step['acted_at'])) {
                    $lastActed = $step['acted_at'];
                }
            }
            $wf['completed_at'] = ($wf['status'] !== 'pending') ? $lastActed : null;
        }
        unset($wf);

        return $workflows;
    }

    /**
     * Get all pending workflows with student and initiator names.
     * Used by admins/super_admins to see all pending workflows.
     */
    public function getPending(?int $userId = null): array
    {
        if ($userId !== null) {
            return $this->getByApprover($userId);
        }

        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       s.student_id_display,
                       u.full_name AS initiated_by_name,
                       CONCAT(w.current_approver_role) AS current_approver
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_type = 'student' AND w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                WHERE w.status = 'pending'
                ORDER BY w.created_at DESC";
        return $this->query($sql)->fetchAll();
    }

    /**
     * Get workflow history with filters and pagination (for history page).
     *
     * @param  array $filters  Optional: type, status, date_from, date_to
     * @return array
     */
    public function getFilteredHistory(array $filters = []): array
    {
        $where  = [];
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
            $where[] = "w.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "w.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT w.*,
                       s.full_name AS student_name,
                       s.student_id_display,
                       u.full_name AS initiated_by_name,
                       w.total_steps,
                       (SELECT COUNT(*) FROM workflow_steps ws
                        WHERE ws.workflow_id = w.id AND ws.action = 'approved') AS completed_steps
                FROM {$this->table} w
                LEFT JOIN students s ON w.entity_type = 'student' AND w.entity_id = s.id
                LEFT JOIN users u ON w.initiated_by = u.id
                {$whereClause}
                ORDER BY w.created_at DESC";
        $workflows = $this->query($sql, $params)->fetchAll();

        // Load steps for each workflow
        foreach ($workflows as &$wf) {
            $stepsSql = "SELECT ws.*, u.full_name AS approver_name
                         FROM workflow_steps ws
                         LEFT JOIN users u ON ws.approver_id = u.id
                         WHERE ws.workflow_id = :wid
                         ORDER BY ws.step_number ASC";
            $wf['steps'] = $this->query($stepsSql, [':wid' => $wf['id']])->fetchAll();

            $lastActed = null;
            foreach ($wf['steps'] as $step) {
                if (!empty($step['acted_at'])) {
                    $lastActed = $step['acted_at'];
                }
            }
            $wf['completed_at'] = ($wf['status'] !== 'pending') ? $lastActed : null;
        }
        unset($wf);

        return $workflows;
    }
}
