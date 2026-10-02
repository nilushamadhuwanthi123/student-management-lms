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
        'student_id_display', 'full_name', 'email', 'phone1', 'phone2',
        'whatsapp', 'home_number', 'date_of_birth', 'address', 'province',
        'district', 'notes', 'profile_photo', 'password_hash', 'status',
        'created_by', 'created_at', 'updated_at',
    ];

    /**
     * Helper to decorate student rows with alias keys for view compatibility.
     */
    public function decorateStudent(?array $student): ?array
    {
        if (!$student) {
            return null;
        }

        $student['student_id'] = $student['student_id'] ?? $student['student_id_display'] ?? '';
        $student['phone'] = $student['phone'] ?? $student['phone1'] ?? '';
        $student['dob'] = $student['dob'] ?? $student['date_of_birth'] ?? '';
        $student['photo'] = $student['photo'] ?? $student['profile_photo'] ?? '';

        if (!isset($student['first_name']) || !isset($student['last_name'])) {
            $parts = explode(' ', trim((string) ($student['full_name'] ?? '')));
            $student['first_name'] = $student['first_name'] ?? ($parts[0] ?? '');
            $student['last_name'] = $student['last_name'] ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '');
        }

        return $student;
    }

    public function find(int $id): ?array
    {
        $row = parent::find($id);
        return $this->decorateStudent($row);
    }

    public function all(string $orderBy = 'full_name ASC'): array
    {
        $rows = parent::all($orderBy);
        return array_map([$this, 'decorateStudent'], $rows);
    }

    public function paginate(int $page = 1, int $perPage = 15, array $conditions = [], string $orderBy = 'full_name ASC'): array
    {
        $result = parent::paginate($page, $perPage, $conditions, $orderBy);
        $result['data'] = array_map([$this, 'decorateStudent'], $result['data']);
        return $result;
    }

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

        return 'STU' . $year . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
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
                       c.schedule_days, c.start_time, c.end_time, c.institute_id AS class_institute_id,
                       i.name AS institute_name
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                LEFT JOIN institutes i ON c.institute_id = i.id
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
        $rows = $this->query($sql, [':cid' => $classId])->fetchAll();
        return array_map([$this, 'decorateStudent'], $rows);
    }

    public function getByInstitute(int $instituteId): array
    {
        $sql = "SELECT DISTINCT s.* FROM {$this->table} s
                INNER JOIN enrollments e ON s.id = e.student_id
                INNER JOIN classes c ON e.class_id = c.id
                WHERE c.institute_id = :iid
                ORDER BY s.full_name ASC";
        $rows = $this->query($sql, [':iid' => $instituteId])->fetchAll();
        return array_map([$this, 'decorateStudent'], $rows);
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Search students with optional filters.
     *
     * @param  string $term     Matched against name, student_id_display, email, phone1
     * @param  array  $filters  Optional: class_id, institute_id, status
     */
    public function search(string $term, array $filters = []): array
    {
        $params = [];
        $where  = [];

        if ($term !== '') {
            $where[] = "(s.full_name LIKE :term OR s.student_id_display LIKE :term
                         OR s.email LIKE :term OR s.phone1 LIKE :term)";
            $params[':term'] = "%{$term}%";
        }

        if (!empty($filters['status'])) {
            $where[] = "s.status = :status";
            $params[':status'] = $filters['status'];
        }

        $joinClass = '';
        if (!empty($filters['institute_id'])) {
            $joinClass .= " INNER JOIN enrollments ei ON s.id = ei.student_id
                            INNER JOIN classes ci ON ei.class_id = ci.id AND ci.institute_id = :inst";
            $params[':inst'] = $filters['institute_id'];
        }

        if (!empty($filters['class_id'])) {
            $joinClass .= " INNER JOIN enrollments e ON s.id = e.student_id AND e.class_id = :cid AND e.enrollment_status = 'active'";
            $params[':cid'] = $filters['class_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT s.* FROM {$this->table} s
                {$joinClass}
                {$whereClause}
                ORDER BY s.full_name ASC";
        $rows = $this->query($sql, $params)->fetchAll();
        return array_map([$this, 'decorateStudent'], $rows);
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
                WHERE p.month_year LIKE :my AND p.payment_status = 'paid'";
        $paid = (int) ($this->query($sql, [':my' => $monthYear . '%'])->fetch()['cnt'] ?? 0);

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
                WHERE student_id = :sid AND month_year LIKE :my
                LIMIT 1";
        $row = $this->query($sql, [':sid' => $studentId, ':my' => $monthYear . '%'])->fetch();
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
