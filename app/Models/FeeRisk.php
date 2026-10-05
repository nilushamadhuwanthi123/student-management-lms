<?php
/**
 * FeeRisk Model — Tracks fee-risk / deactivation state per student+class
 *
 * DB column reference (fee_risk_status table):
 *   id, student_id, class_id,
 *   risk_state ENUM('safe','grace','high_risk','deactivated','temporary_access'),
 *   grace_start_date DATE, deactivation_date DATE,
 *   temp_access_until DATE, temp_granted_by INT,
 *   updated_at TIMESTAMP
 *
 *   NO: months_overdue, warning_sent_at, escalated_at, deactivated_at, created_at
 *
 * deactivation_settings table: key-value format (setting_key / setting_value)
 */

require_once __DIR__ . '/BaseModel.php';

class FeeRisk extends BaseModel
{
    protected string $table = 'fee_risk_status';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'risk_state',
        'grace_start_date', 'deactivation_date',
        'temp_access_until', 'temp_granted_by',
        'updated_at',
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
     * @param  string $riskState  'safe', 'grace', 'high_risk', 'deactivated', 'temporary_access'
     * @param  array  $extraData  Additional columns (grace_start_date, deactivation_date, etc.)
     */
    public function updateState(int $studentId, int $classId, string $riskState, array $extraData = []): bool
    {
        $existing = $this->getByStudentAndClass($studentId, $classId);

        $data = array_merge($extraData, [
            'student_id' => $studentId,
            'class_id'   => $classId,
            'risk_state' => $riskState,
        ]);

        // Set date columns based on state transition
        $today = date('Y-m-d');
        if ($riskState === 'grace' && empty($data['grace_start_date'])) {
            $data['grace_start_date'] = $today;
        }
        if ($riskState === 'deactivated' && empty($data['deactivation_date'])) {
            $data['deactivation_date'] = $today;
        }
        // Clear dates when returning to safe
        if ($riskState === 'safe') {
            $data['grace_start_date']  = null;
            $data['deactivation_date'] = null;
            $data['temp_access_until'] = null;
            $data['temp_granted_by']   = null;
        }

        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->update($existing['id'], $data);
        } else {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->create($data) > 0;
        }
    }

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get all students in a given risk state, optionally filtered by class.
     */
    public function getStudentsInState(string $riskState, ?int $classId = null): array
    {
        $params = [':state' => $riskState];
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
                WHERE fr.risk_state = :state {$classFilter}
                ORDER BY s.full_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Temporary access ────────────────────────────────────────────

    /**
     * Grant temporary access to a deactivated student.
     *
     * @param  int    $studentId
     * @param  int    $classId
     * @param  string $until       Date until which access is granted (Y-m-d)
     * @param  int    $grantedBy   User ID who granted the access
     */
    public function grantTemporaryAccess(int $studentId, int $classId, string $until, int $grantedBy): bool
    {
        return $this->updateState($studentId, $classId, 'temporary_access', [
            'temp_access_until' => $until,
            'temp_granted_by'   => $grantedBy,
        ]);
    }

    // ─── Cron-like evaluation ────────────────────────────────────────

    /**
     * Evaluate all active students against deactivation settings
     * and update risk states accordingly.
     *
     * deactivation_settings is a key-value table (setting_key / setting_value):
     *   - grace_period_months
     *   - high_risk_months
     *   - auto_deactivation_months
     *
     * @return array{grace: int, high_risk: int, deactivations: int}
     */
    public function checkAndUpdateRiskStates(): array
    {
        // Load thresholds from deactivation_settings (key-value table)
        $settingsRows = $this->query("SELECT setting_key, setting_value FROM deactivation_settings")->fetchAll();
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $graceThreshold        = (int) ($settings['grace_period_months'] ?? 1);
        $highRiskThreshold     = (int) ($settings['high_risk_months'] ?? 2);
        $deactivationThreshold = (int) ($settings['auto_deactivation_months'] ?? 3);

        $currentMonth = date('Y-m-01');
        $counters = ['grace' => 0, 'high_risk' => 0, 'deactivations' => 0];

        // Get all active enrollments
        $sql = "SELECT e.student_id, e.class_id, c.monthly_fee, e.scholarship_type
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                INNER JOIN students s ON e.student_id = s.id
                WHERE e.enrollment_status = 'active' AND s.status = 'active'";
        $enrollments = $this->query($sql)->fetchAll();

        foreach ($enrollments as $enr) {
            // Count consecutive unpaid months working backwards
            $monthsOverdue = 0;
            $checkDate = new \DateTime($currentMonth);

            for ($m = 0; $m < $deactivationThreshold + 1; $m++) {
                $my = $checkDate->format('Y-m-01');
                $paid = $this->query(
                    "SELECT id FROM payments
                     WHERE student_id = :sid AND class_id = :cid
                       AND month_year = :my AND payment_status = 'paid'
                     LIMIT 1",
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
            } elseif ($monthsOverdue >= $highRiskThreshold) {
                $newState = 'high_risk';
            } elseif ($monthsOverdue >= $graceThreshold) {
                $newState = 'grace';
            } else {
                $newState = 'safe';
            }

            // Get current state to detect transitions
            $current = $this->getByStudentAndClass($enr['student_id'], $enr['class_id']);
            $currentState = $current['risk_state'] ?? 'safe';

            if ($newState !== $currentState) {
                $this->updateState($enr['student_id'], $enr['class_id'], $newState);

                if ($newState === 'grace') {
                    $counters['grace']++;
                } elseif ($newState === 'high_risk') {
                    $counters['high_risk']++;
                } elseif ($newState === 'deactivated') {
                    $counters['deactivations']++;
                    // Also deactivate the student record
                    $this->query(
                        "UPDATE students SET status = 'deactivated', updated_at = NOW() WHERE id = :id",
                        [':id' => $enr['student_id']]
                    );
                }
            }
        }

        return $counters;
    }
}
