<?php
/**
 * ClassModel — Named to avoid the PHP reserved word "Class".
 */

require_once __DIR__ . '/BaseModel.php';

class ClassModel extends BaseModel
{
    protected string $table = 'classes';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'class_name', 'subject', 'medium', 'grade', 'institute_id',
        'room', 'teacher_id', 'staff_id', 'schedule_days',
        'start_time', 'end_time', 'monthly_fee', 'teacher_revenue_split',
        'notes', 'status', 'created_at', 'updated_at',
    ];

    // ─── Filtered queries ────────────────────────────────────────────

    public function getByInstitute(int $instituteId): array
    {
        return $this->where(['institute_id' => $instituteId], 'class_name ASC');
    }

    /**
     * Get class with teacher, staff, and institute details joined.
     */
    public function getWithDetails(int $id): ?array
    {
        $sql = "SELECT c.*,
                       t.full_name        AS teacher_name,
                       t.user_id_display  AS teacher_user_id,
                       sf.full_name       AS staff_name,
                       sf.user_id_display AS staff_user_id,
                       i.name             AS institute_name
                FROM {$this->table} c
                LEFT JOIN users t  ON c.teacher_id = t.id
                LEFT JOIN users sf ON c.staff_id   = sf.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                WHERE c.id = :id
                LIMIT 1";
        $row = $this->query($sql, [':id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Number of active students enrolled in a class.
     */
    public function getStudentCount(int $classId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM enrollments
                WHERE class_id = :cid AND enrollment_status = 'active'";
        return (int) $this->query($sql, [':cid' => $classId])->fetch()['cnt'];
    }

    /**
     * All classes with status = 'active'.
     */
    public function getActiveClasses(): array
    {
        return $this->where(['status' => 'active'], 'class_name ASC');
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Search classes by name or code.
     */
    public function search(string $term): array
    {
        $sql = "SELECT c.*, i.name AS institute_name
                FROM {$this->table} c
                LEFT JOIN institutes i ON c.institute_id = i.id
                WHERE c.class_name LIKE :term OR c.subject LIKE :term2
                ORDER BY c.class_name ASC";
        return $this->query($sql, [':term' => "%{$term}%", ':term2' => "%{$term}%"])->fetchAll();
    }
}
