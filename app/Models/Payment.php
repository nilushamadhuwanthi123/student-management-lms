<?php
/**
 * Payment Model
 */

require_once __DIR__ . '/BaseModel.php';

class Payment extends BaseModel
{
    protected string $table = 'payments';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'enrollment_id', 'month_year',
        'amount_due', 'amount_paid', 'scholarship_type',
        'status', 'paid_at', 'marked_by', 'notes',
        'created_at', 'updated_at',
    ];

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get payments for a student, optionally for a specific month.
     */
    public function getByStudent(int $studentId, ?string $monthYear = null): array
    {
        $params = [':sid' => $studentId];
        $monthFilter = '';
        if ($monthYear !== null) {
            $monthFilter = 'AND p.month_year = :my';
            $params[':my'] = $monthYear;
        }

        $sql = "SELECT p.*, c.name AS class_name, c.code AS class_code
                FROM {$this->table} p
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.student_id = :sid {$monthFilter}
                ORDER BY p.month_year DESC, c.name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get payments for a class in a given month.
     */
    public function getByClass(int $classId, string $monthYear): array
    {
        $sql = "SELECT p.*, s.student_id AS display_student_id,
                       s.first_name, s.last_name
                FROM {$this->table} p
                INNER JOIN students s ON p.student_id = s.id
                WHERE p.class_id = :cid AND p.month_year = :my
                ORDER BY s.first_name ASC";
        return $this->query($sql, [':cid' => $classId, ':my' => $monthYear])->fetchAll();
    }

    // ─── Mark paid ───────────────────────────────────────────────────

    /**
     * Mark a payment as paid.
     *
     * @param  int $paymentId
     * @param  int $markedBy   User ID who marked the payment
     */
    public function markPaid(int $paymentId, int $markedBy): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'paid',
                    amount_paid = amount_due,
                    paid_at = NOW(),
                    marked_by = :mb,
                    updated_at = NOW()
                WHERE id = :id AND status != 'paid'";
        return $this->query($sql, [':mb' => $markedBy, ':id' => $paymentId])->rowCount() > 0;
    }

    // ─── Bulk generation ─────────────────────────────────────────────

    /**
     * Generate pending payment records for all active enrollments for a given month.
     *
     * @param  string $monthYear  e.g. "2026-09"
     * @return int  Number of records created
     */
    public function generateMonthlyPayments(string $monthYear): int
    {
        // Get all active enrollments that don't already have a payment for this month
        $sql = "SELECT e.id AS enrollment_id, e.student_id, e.class_id,
                       e.scholarship_type, c.monthly_fee
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                WHERE e.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1 FROM {$this->table} p
                      WHERE p.enrollment_id = e.id AND p.month_year = :my
                  )";
        $enrollments = $this->query($sql, [':my' => $monthYear])->fetchAll();

        $count = 0;
        $insertSql = "INSERT INTO {$this->table}
                      (student_id, class_id, enrollment_id, month_year, amount_due,
                       scholarship_type, status, created_at, updated_at)
                      VALUES (:sid, :cid, :eid, :my, :amount, :stype, 'pending', NOW(), NOW())";

        foreach ($enrollments as $e) {
            $amount = $this->calculateAmountDue((float) $e['monthly_fee'], $e['scholarship_type']);
            $this->query($insertSql, [
                ':sid'    => $e['student_id'],
                ':cid'    => $e['class_id'],
                ':eid'    => $e['enrollment_id'],
                ':my'     => $monthYear,
                ':amount' => $amount,
                ':stype'  => $e['scholarship_type'],
            ]);
            $count++;
        }

        return $count;
    }

    // ─── Overdue ─────────────────────────────────────────────────────

    /**
     * Get overdue (pending) payments, optionally filtered by class.
     */
    public function getOverdue(?int $classId = null): array
    {
        $params = [':my' => date('Y-m')];
        $classFilter = '';
        if ($classId !== null) {
            $classFilter = 'AND p.class_id = :cid';
            $params[':cid'] = $classId;
        }

        $sql = "SELECT p.*, s.student_id AS display_student_id,
                       s.first_name, s.last_name,
                       c.name AS class_name
                FROM {$this->table} p
                INNER JOIN students s ON p.student_id = s.id
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.status = 'pending'
                  AND p.month_year < :my
                  {$classFilter}
                ORDER BY p.month_year ASC, s.first_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Revenue stats ───────────────────────────────────────────────

    /**
     * Revenue summary for a given month.
     *
     * @return array{total_collected: float, total_pending: float, by_class: array}
     */
    public function getRevenueStats(string $monthYear): array
    {
        $params = [':my' => $monthYear];

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END), 0) AS total_collected,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount_due ELSE 0 END), 0) AS total_pending
                FROM {$this->table}
                WHERE month_year = :my";
        $totals = $this->query($sql, $params)->fetch();

        $sql = "SELECT c.id AS class_id, c.name AS class_name,
                    COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount_paid ELSE 0 END), 0) AS collected,
                    COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount_due ELSE 0 END), 0) AS pending,
                    COUNT(p.id) AS payment_count
                FROM {$this->table} p
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.month_year = :my
                GROUP BY c.id, c.name
                ORDER BY c.name ASC";
        $byClass = $this->query($sql, $params)->fetchAll();

        return [
            'total_collected' => (float) $totals['total_collected'],
            'total_pending'   => (float) $totals['total_pending'],
            'by_class'        => $byClass,
        ];
    }

    // ─── Fee calculation ─────────────────────────────────────────────

    /**
     * Apply scholarship discount to monthly fee.
     *
     * @param  float  $monthlyFee
     * @param  string $scholarshipType  'none', 'half', 'free'
     * @return float
     */
    public function calculateAmountDue(float $monthlyFee, string $scholarshipType): float
    {
        return match ($scholarshipType) {
            'half' => round($monthlyFee / 2, 2),
            'free' => 0.00,
            default => $monthlyFee,
        };
    }
}
