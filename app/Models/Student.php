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

        // Sorting student_id_display as a STRING breaks the moment two
        // different digit-widths exist side by side (e.g. an older
        // 4-digit id like STU20260229 next to a 5-digit one like
        // STU202600230) -- "0229" sorts above "00230" as text, so the
        // real highest sequence number is missed and a freshly computed
        // "next" id can collide with one that already exists. Casting
        // the numeric tail and taking MAX() is immune to the padding
        // width, so this keeps working even on data seeded inconsistently.
        $sql = "SELECT MAX(CAST(SUBSTRING(student_id_display, 8) AS UNSIGNED)) AS max_seq
                FROM {$this->table}
                WHERE student_id_display LIKE :pattern";
        $stmt = $this->query($sql, [':pattern' => $pattern]);
        $row = $stmt->fetch();

        $seq = ((int) ($row['max_seq'] ?? 0)) + 1;

        return 'STU' . $year . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a student with a freshly generated student_id_display,
     * retrying with the next id if a concurrent request already took the
     * one just generated.
     *
     * generateStudentId() reads MAX(sequence) and adds one, with no lock
     * held in between -- two enrolment approvals (or a manual add)
     * running at nearly the same moment can both read the same MAX and
     * both compute the same "next" id, so the second INSERT hits the
     * uk_student_id_display unique key and fails (seen in production as
     * "Enrolment approval failed: ... Duplicate entry 'STU...' for key
     * 'uk_student_id_display'", the same id every retry since nothing
     * had advanced MAX() in between). Catching exactly that duplicate
     * and generating again -- MAX() now reflects the row that just won
     * the race -- clears it without needing a table lock around every
     * student insert.
     *
     * @param array $data attributes for create(), WITHOUT student_id_display (this sets it)
     * @return array{id: int, student_id_display: string}
     * @throws \PDOException any insert failure other than a student_id_display collision,
     *                        or a collision that still hasn't cleared after $maxAttempts
     */
    public function createWithGeneratedId(array $data, int $maxAttempts = 5): array
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $studentIdDisplay = $this->generateStudentId();
            $data['student_id_display'] = $studentIdDisplay;
            try {
                $id = $this->create($data);
                return ['id' => $id, 'student_id_display' => $studentIdDisplay];
            } catch (\PDOException $e) {
                $isIdCollision = $e->getCode() === '23000' && stripos($e->getMessage(), 'uk_student_id_display') !== false;
                if (!$isIdCollision || $attempt === $maxAttempts) {
                    throw $e;
                }
                // Another request just took this id -- loop and generate the next one.
            }
        }

        // Unreachable (the loop always returns or throws), kept for static analysis.
        throw new \RuntimeException('Could not generate a unique student id.');
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
            // One placeholder per use: with native prepares (EMULATE_PREPARES
            // off) a repeated named placeholder fails with SQLSTATE HY093,
            // which made every search return an error page.
            $where[] = "(s.full_name LIKE :term1 OR s.student_id_display LIKE :term2
                         OR s.email LIKE :term3 OR s.phone1 LIKE :term4)";
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $params[':term1'] = $like;
            $params[':term2'] = $like;
            $params[':term3'] = $like;
            $params[':term4'] = $like;
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

        // Branch restriction, built by App\Services\BranchScope from ids
        // it read from the database (never from the request).
        if (!empty($filters['scope_sql'])) {
            $where[] = '1 = 1' . $filters['scope_sql'];
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
