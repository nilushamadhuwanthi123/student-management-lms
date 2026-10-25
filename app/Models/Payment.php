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
        'payment_status', 'paid_at', 'marked_by', 'notes',
        'created_at', 'updated_at',
    ];

    /**
     * Helper to decorate payment rows with alias keys for view compatibility.
     */
    public function decoratePayment(?array $row): ?array
    {
        if (!$row) {
            return null;
        }

        $row['status'] = $row['status'] ?? $row['payment_status'] ?? 'pending';
        $row['payment_status'] = $row['status'];
        $row['amount'] = $row['amount'] ?? $row['amount_due'] ?? 0;
        $row['month'] = $row['month'] ?? (!empty($row['month_year']) ? substr((string)$row['month_year'], 0, 7) : date('Y-m'));

        if (!empty($row['full_name'])) {
            $row['student_name'] = $row['student_name'] ?? $row['full_name'];
            $parts = explode(' ', trim((string)$row['full_name']));
            $row['first_name'] = $row['first_name'] ?? ($parts[0] ?? '');
            $row['last_name']  = $row['last_name'] ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '');
        }

        return $row;
    }

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get all payment records with filters for overview grid.
     */
    public function getAllPayments(?string $monthYear = null, ?int $classId = null, ?string $status = null, ?array $classIds = null): array
    {
        $where = [];
        $params = [];

        // Branch restriction: null = every class, [] = none.
        if ($classIds !== null) {
            $where[] = $classIds === [] ? '1 = 0' : 'p.class_id IN (' . implode(',', array_map('intval', $classIds)) . ')';
        }

        if (!empty($monthYear)) {
            $monthLike = substr($monthYear, 0, 7) . '%';
            $where[] = "p.month_year LIKE :my";
            $params[':my'] = $monthLike;
        }

        if (!empty($classId)) {
            $where[] = "p.class_id = :cid";
            $params[':cid'] = $classId;
        }

        if (!empty($status)) {
            $where[] = "p.payment_status = :st";
            $params[':st'] = $status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT p.*,
                       p.payment_status AS status,
                       p.amount_due AS amount,
                       DATE_FORMAT(p.month_year, '%Y-%m') AS month,
                       s.full_name AS student_name,
                       s.full_name,
                       s.student_id_display AS student_id,
                       s.student_id_display AS display_student_id,
                       c.class_name,
                       c.class_name AS name
                FROM {$this->table} p
                INNER JOIN students s ON p.student_id = s.id
                INNER JOIN classes c ON p.class_id = c.id
                {$whereClause}
                ORDER BY p.id DESC";

        $rows = $this->query($sql, $params)->fetchAll();
        return array_map([$this, 'decoratePayment'], $rows);
    }

    /**
     * Get payments for a student, optionally for a specific month.
     */
    public function getByStudent(int $studentId, ?string $monthYear = null): array
    {
        $params = [':sid' => $studentId];
        $monthFilter = '';
        if ($monthYear !== null) {
            $monthFilter = 'AND p.month_year LIKE :my';
            $params[':my'] = substr($monthYear, 0, 7) . '%';
        }

        $sql = "SELECT p.*,
                       p.payment_status AS status,
                       c.class_name,
                       c.class_name AS name
                FROM {$this->table} p
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.student_id = :sid {$monthFilter}
                ORDER BY p.month_year DESC, c.class_name ASC";
        $rows = $this->query($sql, $params)->fetchAll();
        return array_map([$this, 'decoratePayment'], $rows);
    }

    /**
     * Get payments for a class in a given month.
     */
    public function getByClass(int $classId, string $monthYear): array
    {
        $monthLike = substr($monthYear, 0, 7) . '%';
        $sql = "SELECT p.*,
                       p.payment_status AS status,
                       s.student_id_display AS display_student_id,
                       s.student_id_display AS student_id,
                       s.full_name,
                       c.class_name
                FROM {$this->table} p
                INNER JOIN students s ON p.student_id = s.id
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.class_id = :cid AND p.month_year LIKE :my
                ORDER BY s.full_name ASC";
        $rows = $this->query($sql, [':cid' => $classId, ':my' => $monthLike])->fetchAll();
        return array_map([$this, 'decoratePayment'], $rows);
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
                SET payment_status = 'paid',
                    amount_paid = amount_due,
                    paid_at = NOW(),
                    marked_by = :mb,
                    updated_at = NOW()
                WHERE id = :id AND payment_status != 'paid'";
        return $this->query($sql, [':mb' => $markedBy, ':id' => $paymentId])->rowCount() > 0;
    }

    // ─── Bulk generation ─────────────────────────────────────────────

    /**
     * Generate pending payment records for all active enrollments for a given month.
     *
     * @param  string $monthYear  e.g. "2026-09"
     * @return int  Number of records created
     */
    public function generateMonthlyPayments(string $monthYear, ?array $classIds = null): int
    {
        $monthClean = substr($monthYear, 0, 7);
        $monthDate  = $monthClean . '-01';
        $monthLike  = $monthClean . '%';

        // Get all active enrollments that don't already have a payment for this month
        $sql = "SELECT e.id AS enrollment_id, e.student_id, e.class_id,
                       e.scholarship_type, c.monthly_fee
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                WHERE e.enrollment_status = 'active'
                  {$this->classIdSql('e.class_id', $classIds)}
                  AND NOT EXISTS (
                      SELECT 1 FROM {$this->table} p
                      WHERE p.enrollment_id = e.id AND (p.month_year = :mdate OR p.month_year LIKE :mlike)
                  )";
        $enrollments = $this->query($sql, [':mdate' => $monthDate, ':mlike' => $monthLike])->fetchAll();

        $count = 0;
        $insertSql = "INSERT INTO {$this->table}
                      (student_id, class_id, enrollment_id, month_year, amount_due, amount_paid,
                       scholarship_type, payment_status, created_at, updated_at)
                      VALUES (:sid, :cid, :eid, :my, :amount, 0.00, :stype, 'pending', NOW(), NOW())";

        foreach ($enrollments as $e) {
            $amount = $this->calculateAmountDue((float) $e['monthly_fee'], (string) ($e['scholarship_type'] ?? 'full_payment'));
            $this->query($insertSql, [
                ':sid'    => $e['student_id'],
                ':cid'    => $e['class_id'],
                ':eid'    => $e['enrollment_id'],
                ':my'     => $monthDate,
                ':amount' => $amount,
                ':stype'  => $e['scholarship_type'] ?? 'full_payment',
            ]);
            $count++;
        }

        return $count;
    }

    // ─── Overdue ─────────────────────────────────────────────────────

    /**
     * Get overdue (pending) payments, optionally filtered by class.
     */
    public function getOverdue(?int $classId = null, ?array $classIds = null): array
    {
        $currentMonthDate = date('Y-m-01');
        $params = [':my' => $currentMonthDate];
        $classFilter = '';
        if ($classId !== null) {
            $classFilter = 'AND p.class_id = :cid';
            $params[':cid'] = $classId;
        }

        $sql = "SELECT p.*,
                       p.payment_status AS status,
                       s.student_id_display AS display_student_id,
                       s.student_id_display AS student_id,
                       s.full_name,
                       c.class_name,
                       c.class_name AS name
                FROM {$this->table} p
                INNER JOIN students s ON p.student_id = s.id
                INNER JOIN classes c ON p.class_id = c.id
                WHERE p.payment_status IN ('pending', 'overdue')
                  AND p.month_year < :my
                  {$classFilter}
                  {$this->classIdSql('p.class_id', $classIds)}
                ORDER BY p.month_year ASC, s.full_name ASC";
        $rows = $this->query($sql, $params)->fetchAll();
        return array_map([$this, 'decoratePayment'], $rows);
    }

    // ─── Revenue stats ───────────────────────────────────────────────

    /**
     * Revenue summary for a given month.
     *
     * @return array{total_collected: float, total_pending: float, by_class: array}
     */
    public function getRevenueStats(string $monthYear, ?array $classIds = null): array
    {
        $monthClean = substr($monthYear, 0, 7);
        $monthLike  = $monthClean . '%';
        $params = [':my' => $monthLike, ':my_raw' => $monthClean];

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount_paid ELSE 0 END), 0) AS total_collected,
                    COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'overdue') THEN (amount_due - amount_paid) ELSE 0 END), 0) AS total_pending
                FROM {$this->table}
                WHERE (month_year LIKE :my OR DATE_FORMAT(month_year, '%Y-%m') = :my_raw)
                {$this->classIdSql('class_id', $classIds)}";
        $totals = $this->query($sql, $params)->fetch();

        $sql = "SELECT c.id AS class_id, c.class_name,
                    COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount_paid ELSE 0 END), 0) AS collected,
                    COALESCE(SUM(CASE WHEN p.payment_status IN ('pending', 'overdue') THEN (p.amount_due - p.amount_paid) ELSE 0 END), 0) AS pending,
                    COUNT(p.id) AS payment_count
                FROM {$this->table} p
                INNER JOIN classes c ON p.class_id = c.id
                WHERE (p.month_year LIKE :my OR DATE_FORMAT(p.month_year, '%Y-%m') = :my_raw)
                {$this->classIdSql('p.class_id', $classIds)}
                GROUP BY c.id, c.class_name
                ORDER BY c.class_name ASC";
        $byClass = $this->query($sql, $params)->fetchAll();

        return [
            'total_collected' => (float) ($totals['total_collected'] ?? 0),
            'total_pending'   => (float) ($totals['total_pending'] ?? 0),
            'by_class'        => $byClass ?: [],
        ];
    }

    // ─── Fee calculation ─────────────────────────────────────────────

    /**
     * Apply scholarship discount to monthly fee.
     *
     * @param  float  $monthlyFee
     * @param  string $scholarshipType  'full_payment', 'half_payment', 'free_card'
     * @return float
     */
    public function calculateAmountDue(float $monthlyFee, string $scholarshipType): float
    {
        return match ($scholarshipType) {
            'half_payment', 'half' => round($monthlyFee / 2, 2),
            'free_card', 'free'    => 0.00,
            default                => $monthlyFee,
        };
    }

    /**
     * " AND col IN (...)" for a branch's classes; "" when unrestricted
     * (null), and a clause matching nothing for an empty list.
     *
     * @param array<int, int>|null $classIds
     */
    private function classIdSql(string $column, ?array $classIds): string
    {
        if ($classIds === null) {
            return '';
        }

        return $classIds === []
            ? ' AND 1 = 0'
            : " AND {$column} IN (" . implode(',', array_map('intval', $classIds)) . ')';
    }
}
