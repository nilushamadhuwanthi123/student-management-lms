<?php
/**
 * FeeRisk Model — Tracks fee-risk / deactivation state per student+class
 */

require_once __DIR__ . '/BaseModel.php';

class FeeRisk extends BaseModel
{
    protected string $table = 'fee_risk_status';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'state',
        'months_overdue', 'last_paid_month', 'warning_sent_at',
        'escalated_at', 'deactivated_at',
        'created_at', 'updated_at',
    ];

    // ─── Finders ─────────────────────────────────────────────────────

    public function getByStudent(int $studentId): array
    {
        $sql = "SELECT fr.*, c.class_name
                FROM {$this->table} fr
                INNER JOIN classes c ON fr.class_id = c.id
                WHERE fr.student_id = :sid
                ORDER BY c.class_name ASC";
        return $this->query($sql, [':sid' => $studentId])->fetchAll();
    }

    public function getByStudentAndClass(int $studentId, int $classId): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE student_id = :sid AND class_id = :cid
                LIMIT 1";
        $row = $this->query($sql, [':sid' => $studentId, ':cid' => $classId])->fetch();
        return $row ?: null;
    }

    // ─── State management ────────────────────────────────────────────

    /**
     * Update or insert the risk state for a student+class.
     *
     * @param  int    $studentId
     * @param  int    $classId
     * @param  string $state      e.g. 'good', 'warning', 'escalated', 'deactivated'
     * @param  array  $extraData  Additional columns to set (months_overdue, warning_sent_at, etc.)
     */
    public function updateState(int $studentId, int $classId, string $state, array $extraData = []): bool
    {
        $existing = $this->getByStudentAndClass($studentId, $classId);

        $data = array_merge($extraData, [
            'student_id' => $studentId,
            'class_id'   => $classId,
            'state'      => $state,
        ]);

        // Set timestamp columns based on state transition
        $now = date('Y-m-d H:i:s');
        if ($state === 'warning' && empty($data['warning_sent_at'])) {
            $data['warning_sent_at'] = $now;
        }
        if ($state === 'escalated' && empty($data['escalated_at'])) {
            $data['escalated_at'] = $now;
        }
        if ($state === 'deactivated' && empty($data['deactivated_at'])) {
            $data['deactivated_at'] = $now;
        }

        if ($existing) {
            $data['updated_at'] = $now;
            return $this->update($existing['id'], $data);
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            return $this->create($data) > 0;
        }
    }

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get all students in a given risk state, optionally filtered by class.
     */
    public function getStudentsInState(string $state, ?int $classId = null): array
    {
        $params = [':state' => $state];
        $classFilter = '';
        if ($classId !== null) {
            $classFilter = 'AND fr.class_id = :cid';
            $params[':cid'] = $classId;
        }

        $sql = "SELECT fr.*, s.student_id_display,
                       s.full_name, s.phone1, s.email,
                       c.class_name
                FROM {$this->table} fr
                INNER JOIN students s ON fr.student_id = s.id
                INNER JOIN classes c ON fr.class_id = c.id
                WHERE fr.state = :state {$classFilter}
                ORDER BY fr.months_overdue DESC, s.full_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Cron-like evaluation ────────────────────────────────────────

    /**
     * Evaluate all active students against deactivation settings
     * and update risk states accordingly.
     *
     * This reads from deactivation_settings to determine thresholds:
     *   - warning_after_months: months overdue before warning
     *   - escalation_after_months: months overdue before escalation
     *   - deactivation_after_months: months overdue before auto-deactivation
     *
     * @return array{warnings: int, escalations: int, deactivations: int}
     */
    public function checkAndUpdateRiskStates(): array
    {
        // Load thresholds from deactivation_settings
        $settings = $this->query("SELECT * FROM deactivation_settings LIMIT 1")->fetch();
        $warningThreshold     = (int) ($settings['warning_after_months'] ?? 1);
        $escalationThreshold  = (int) ($settings['escalation_after_months'] ?? 2);
        $deactivationThreshold = (int) ($settings['deactivation_after_months'] ?? 3);

        $currentMonth = date('Y-m');
        $counters = ['warnings' => 0, 'escalations' => 0, 'deactivations' => 0];

        // Get all active enrollments
        $sql = "SELECT e.student_id, e.class_id, c.monthly_fee, e.scholarship_type
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                INNER JOIN students s ON e.student_id = s.id
                WHERE e.status = 'active' AND s.status = 'active'";
        $enrollments = $this->query($sql)->fetchAll();

        foreach ($enrollments as $enr) {
            // Count consecutive unpaid months working backwards from current month
            $monthsOverdue = 0;
            $checkDate = new DateTime($currentMonth . '-01');

            for ($m = 0; $m < $deactivationThreshold + 1; $m++) {
                $my = $checkDate->format('Y-m');
                $paid = $this->query(
                    "SELECT id FROM payments WHERE student_id = :sid AND class_id = :cid AND month_year = :my AND status = 'paid' LIMIT 1",
                    [':sid' => $enr['student_id'], ':cid' => $enr['class_id'], ':my' => $my]
                )->fetch();

                if (!$paid) {
                    $monthsOverdue++;
                } else {
                    break;
                }
                $checkDate->modify('-1 month');
            }

            // Determine new state
            if ($monthsOverdue >= $deactivationThreshold) {
                $newState = 'deactivated';
            } elseif ($monthsOverdue >= $escalationThreshold) {
                $newState = 'escalated';
            } elseif ($monthsOverdue >= $warningThreshold) {
                $newState = 'warning';
            } else {
                $newState = 'good';
            }

            // Get current state to detect transitions
            $current = $this->getByStudentAndClass($enr['student_id'], $enr['class_id']);
            $currentState = $current['state'] ?? 'good';

            if ($newState !== $currentState) {
                $this->updateState($enr['student_id'], $enr['class_id'], $newState, [
                    'months_overdue' => $monthsOverdue,
                ]);

                if ($newState === 'warning') {
                    $counters['warnings']++;
                } elseif ($newState === 'escalated') {
                    $counters['escalations']++;
                } elseif ($newState === 'deactivated') {
                    $counters['deactivations']++;
                    // Also deactivate the student record
                    $this->query(
                        "UPDATE students SET status = 'deactivated', updated_at = NOW() WHERE id = :id",
                        [':id' => $enr['student_id']]
                    );
                }
            } else {
                // Update months_overdue even if state didn't change
                if ($current) {
                    $this->update($current['id'], ['months_overdue' => $monthsOverdue]);
                }
            }
        }

        return $counters;
    }
}
