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
        'student_id', 'first_name', 'last_name', 'date_of_birth',
        'gender', 'address', 'phone', 'email',
        'institute_id', 'status', 'profile_picture',
        'created_at', 'updated_at',
    ];

    // ─── ID generation ───────────────────────────────────────────────

    /**
     * Generate next student display ID: STD + year + 5-digit sequence.
     * e.g. STD202600001
     */
    public function generateStudentId(): string
    {
        $year = date('Y');
        $pattern = "STD{$year}%";

        $sql = "SELECT student_id FROM {$this->table}
                WHERE student_id LIKE :pattern
                ORDER BY student_id DESC LIMIT 1";
        $stmt = $this->query($sql, [':pattern' => $pattern]);
        $last = $stmt->fetch();

        $seq = $last ? (int) substr($last['student_id'], 7) + 1 : 1;

        return 'STD' . $year . str_pad($seq, 5, '0', STR_PAD_LEFT);
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

        $sql = "SELECT e.*, c.name AS class_name, c.monthly_fee,
                       c.schedule, c.institute_id AS class_institute_id
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
                WHERE e.class_id = :cid AND e.status = 'active'
                ORDER BY s.first_name ASC";
        return $this->query($sql, [':cid' => $classId])->fetchAll();
    }

    public function getByInstitute(int $instituteId): array
    {
        return $this->where(['institute_id' => $instituteId], 'first_name ASC');
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Search students with optional filters.
     *
     * @param  string $term     Matched against name, student_id, email, phone
     * @param  array  $filters  Optional: class_id, institute_id, status
     */
    public function search(string $term, array $filters = []): array
    {
        $params = [];
        $where  = [];

        if ($term !== '') {
            $where[] = "(s.first_name LIKE :term OR s.last_name LIKE :term
                         OR s.student_id LIKE :term OR s.email LIKE :term
                         OR s.phone LIKE :term)";
            $params[':term'] = "%{$term}%";
        }

        if (!empty($filters['status'])) {
            $where[] = "s.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['institute_id'])) {
            $where[] = "s.institute_id = :inst";
            $params[':inst'] = $filters['institute_id'];
        }

        $joinClass = '';
        if (!empty($filters['class_id'])) {
            $joinClass = "INNER JOIN enrollments e ON s.id = e.student_id";
            $where[] = "e.class_id = :cid AND e.status = 'active'";
            $params[':cid'] = $filters['class_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT s.* FROM {$this->table} s
                {$joinClass}
                {$whereClause}
                ORDER BY s.first_name ASC";
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

        $monthYear = date('Y-m');
        $sql = "SELECT COUNT(DISTINCT p.student_id) AS cnt
                FROM payments p
                WHERE p.month_year = :my AND p.status = 'paid'";
        $paid = (int) $this->query($sql, [':my' => $monthYear])->fetch()['cnt'];

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
        $sql = "SELECT * FROM payments
                WHERE student_id = :sid AND month_year = :my
                LIMIT 1";
        $row = $this->query($sql, [':sid' => $studentId, ':my' => $monthYear])->fetch();
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
