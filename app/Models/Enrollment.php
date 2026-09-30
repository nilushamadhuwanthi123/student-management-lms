<?php
/**
 * Enrollment Model
 */

require_once __DIR__ . '/BaseModel.php';

class Enrollment extends BaseModel
{
    protected string $table = 'enrollments';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'scholarship_type',
        'status', 'enrolled_at', 'unenrolled_at',
    ];

    // ─── Enroll / Unenroll ───────────────────────────────────────────

    /**
     * Enroll a student in a class.
     *
     * @param  int         $studentId
     * @param  int         $classId
     * @param  string|null $scholarshipType  'none', 'half', 'free'
     * @return int  New enrollment ID
     */
    public function enrollStudent(int $studentId, int $classId, ?string $scholarshipType = 'none'): int
    {
        return $this->create([
            'student_id'       => $studentId,
            'class_id'         => $classId,
            'scholarship_type' => $scholarshipType ?? 'none',
            'status'           => 'active',
            'enrolled_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Unenroll a student from a class (soft — sets status + timestamp).
     */
    public function unenroll(int $studentId, int $classId): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'unenrolled', unenrolled_at = NOW()
                WHERE student_id = :sid AND class_id = :cid AND status = 'active'";
        return $this->query($sql, [
            ':sid' => $studentId,
            ':cid' => $classId,
        ])->rowCount() > 0;
    }

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get all enrollments for a student with class details.
     */
    public function getByStudent(int $studentId): array
    {
        $sql = "SELECT e.*, c.name AS class_name, c.monthly_fee,
                       c.schedule, c.code AS class_code,
                       i.name AS institute_name
                FROM {$this->table} e
                INNER JOIN classes c ON e.class_id = c.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                WHERE e.student_id = :sid
                ORDER BY e.enrolled_at DESC";
        return $this->query($sql, [':sid' => $studentId])->fetchAll();
    }

    /**
     * Get all enrollments for a class with student details.
     */
    public function getByClass(int $classId): array
    {
        $sql = "SELECT e.*, s.student_id AS display_student_id,
                       s.first_name, s.last_name, s.phone, s.email, s.status AS student_status
                FROM {$this->table} e
                INNER JOIN students s ON e.student_id = s.id
                WHERE e.class_id = :cid
                ORDER BY s.first_name ASC";
        return $this->query($sql, [':cid' => $classId])->fetchAll();
    }
}
