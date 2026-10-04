<?php
/**
 * Student Model
 */

require_once __DIR__ . '/BaseModel.php';

class Student extends BaseModel
{
    protected string $table = 'students';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id_display', 'full_name', 'email',
        'phone1', 'phone2', 'whatsapp', 'home_number',
        'date_of_birth', 'address', 'province', 'district',
        'institute_id', 'notes', 'profile_photo', 'password_hash',
        'status', 'created_by', 'created_at', 'updated_at',
    ];

    // ─── ID generation ───────────────────────────────────────────────

    /**
     * Generate next student display ID: STU + year + 5-digit sequence.
     * e.g. STU202600001
     */
    public function generateStudentId(): string
    {
        $year = date('Y');
        $pattern = "STU{$year}%";

        $sql = "SELECT student_id_display FROM {$this->table}
                WHERE student_id_display LIKE :pattern
                ORDER BY student_id_display DESC LIMIT 1";
        $stmt = $this->query($sql, [':pattern' => $pattern]);
        $last = $stmt->fetch();

        $seq = $last ? (int) substr($last['student_id_display'], 7) + 1 : 1;

        return 'STU' . $year . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // ─── Authentication ──────────────────────────────────────────────

    /**
     * Authenticate a student by display ID + password.
     */
    public function authenticate(string $studentIdDisplay, string $password): ?array
    {
        $student = $this->findBy('student_id_display', $studentIdDisplay);
        if (!$student) {
            return null;
        }
        if (!password_verify($password, $student['password_hash'])) {
            return null;
        }
        return $student;
    }

    // ─── Relations ───────────────────────────────────────────────────

    /**
     * Get student with their enrollment + class details.
     */
    public function getWithEnrollments(int $id): ?array
    {
        $student = $this->find($id);
        if (!$student) {
            return null;
        }

        $sql = "SELECT e.*, c.class_name, c.monthly_fee,
                       c.schedule_days, c.start_time, c.end_time,
                       c.institute_id AS class_institute_id
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                WHERE e.student_id = :sid
                ORDER BY e.enrolled_at DESC";
        $student['enrollments'] = $this->query($sql, [':sid' => $id])->fetchAll();
        return $student;
    }

    // ─── Filtered queries ────────────────────────────────────────────

    public function getByClass(int $classId): array
    {
        $sql = "SELECT s.* FROM {$this->table} s
                INNER JOIN enrollments e ON s.id = e.student_id
                WHERE e.class_id = :cid AND e.enrollment_status = 'active'
                ORDER BY s.full_name ASC";
        return $this->query($sql, [':cid' => $classId])->fetchAll();
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Search students with optional filters.
     *
     * @param  string $term     Matched against name, student_id_display, email, phone1
     * @param  array  $filters  Optional: class_id, status
     */
    public function search(string $term, array $filters = []): array
    {
        $params = [];
        $where  = [];

        if ($term !== '') {
            $where[] = "(s.full_name LIKE :term
                         OR s.student_id_display LIKE :term OR s.email LIKE :term
                         OR s.phone1 LIKE :term)";
            $params[':term'] = "%{$term}%";
        }

        if (!empty($filters['status'])) {
            $where[] = "s.status = :status";
            $params[':status'] = $filters['status'];
        }

        $joinClass = '';
        if (!empty($filters['class_id'])) {
            $joinClass = "INNER JOIN enrollments e ON s.id = e.student_id";
            $where[] = "e.class_id = :cid AND e.enrollment_status = 'active'";
            $params[':cid'] = $filters['class_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT s.* FROM {$this->table} s
                {$joinClass}
                {$whereClause}
                ORDER BY s.full_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Statistics ──────────────────────────────────────────────────

    /**
     * Dashboard stats.
     *
     * @return array{total: int, active: int, paid_this_month: int, deactivated: int}
     */
    public function getStats(): array
    {
        $total = $this->count();
        $active = $this->count(['status' => 'active']);
        $deactivated = $this->count(['status' => 'deactivated']);

        $monthYear = date('Y-m-01'); // First day of current month
        $sql = "SELECT COUNT(DISTINCT p.student_id) AS cnt
                FROM payments p
                WHERE p.month_year = :my
                  AND p.payment_status = 'paid'";
        $paid = (int) $this->query($sql, [
            ':my' => $monthYear,
        ])->fetch()['cnt'];

        return [
            'total'           => $total,
            'active'          => $active,
            'paid_this_month' => $paid,
            'deactivated'     => $deactivated,
        ];
    }

    // ─── Payment status ──────────────────────────────────────────────

    /**
     * Check if a student has paid for a given month.
     *
     * @param  int    $studentId
     * @param  string $monthYear  e.g. "2026-09"
     * @return array|null  Payment row or null if none exists
     */
    public function getPaymentStatus(int $studentId, string $monthYear): ?array
    {
        $monthStart = $monthYear . '-01';
        $sql = "SELECT * FROM payments
                WHERE student_id = :sid
                  AND month_year = :my
                LIMIT 1";
        $row = $this->query($sql, [
            ':sid' => $studentId,
            ':my'  => $monthStart,
        ])->fetch();
        return $row ?: null;
    }

    // ─── Status management ───────────────────────────────────────────

    public function deactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'deactivated', updated_at = NOW()
                WHERE id = :id";
        return $this->query($sql, [':id' => $id])->rowCount() > 0;
    }

    public function reactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'active', updated_at = NOW()
                WHERE id = :id";
        return $this->query($sql, [':id' => $id])->rowCount() > 0;
    }
}
