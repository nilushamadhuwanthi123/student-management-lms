<?php
/**
 * Enrollment Model
 * Matches DB schema: enrollments table with enrollment_status ENUM('active','inactive')
 * and scholarship_type ENUM('full_payment','half_payment','free_card').
 */

require_once __DIR__ . '/BaseModel.php';

class Enrollment extends BaseModel
{
    protected string $table = 'enrollments';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'class_id', 'scholarship_type',
        'enrollment_status', 'enrolled_at',
    ];

    // ─── Enroll / Unenroll ───────────────────────────────────────────

    /**
     * Enroll a student in a class.
     *
     * @param  int         $studentId
     * @param  int         $classId
     * @param  string      $scholarshipType  'full_payment', 'half_payment', 'free_card'
     * @return int  New enrollment ID
     */
    public function enrollStudent(int $studentId, int $classId, string $scholarshipType = 'full_payment'): int
    {
        return $this->create([
            'student_id'        => $studentId,
            'class_id'          => $classId,
            'scholarship_type'  => $scholarshipType,
            'enrollment_status' => 'active',
            'enrolled_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Unenroll a student from a class (sets enrollment_status to 'inactive').
     */
    public function unenroll(int $studentId, int $classId): bool
    {
        $sql = "UPDATE {$this->table}
                SET enrollment_status = 'inactive', updated_at = NOW()
                WHERE student_id = :sid AND class_id = :cid AND enrollment_status = 'active'";
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
        $sql = "SELECT e.*, c.class_name, c.subject, c.grade, c.medium,
                       c.monthly_fee, c.schedule_days, c.start_time, c.end_time,
                       c.status AS class_status,
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
        $sql = "SELECT e.*, s.student_id_display,
                       s.full_name, s.phone1, s.email, s.status AS student_status
                FROM {$this->table} e
                INNER JOIN students s ON e.student_id = s.id
                WHERE e.class_id = :cid
                ORDER BY s.full_name ASC";
        return $this->query($sql, [':cid' => $classId])->fetchAll();
    }

    /**
     * Get active enrollment count for a class.
     */
    public function countActiveByClass(int $classId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}
                WHERE class_id = :cid AND enrollment_status = 'active'";
        $row = $this->query($sql, [':cid' => $classId])->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get active enrollment count for a student.
     */
    public function countActiveByStudent(int $studentId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}
                WHERE student_id = :sid AND enrollment_status = 'active'";
        $row = $this->query($sql, [':sid' => $studentId])->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Check if a student is enrolled in a specific class.
     */
    public function isEnrolled(int $studentId, int $classId): bool
    {
        $sql = "SELECT id FROM {$this->table}
                WHERE student_id = :sid AND class_id = :cid AND enrollment_status = 'active'
                LIMIT 1";
        $row = $this->query($sql, [':sid' => $studentId, ':cid' => $classId])->fetch();
        return $row !== false;
    }
}
