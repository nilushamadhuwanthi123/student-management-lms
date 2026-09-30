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
        'name', 'code', 'institute_id', 'teacher_id', 'staff_id',
        'monthly_fee', 'schedule', 'description', 'status',
        'created_at', 'updated_at',
    ];

    // ─── Filtered queries ────────────────────────────────────────────

    public function getByInstitute(int $instituteId): array
    {
        return $this->where(['institute_id' => $instituteId], 'name ASC');
    }

    /**
     * Get class with teacher, staff, and institute details joined.
     */
    public function getWithDetails(int $id): ?array
    {
        $sql = "SELECT c.*,
                       t.first_name  AS teacher_first_name,
                       t.last_name   AS teacher_last_name,
                       t.user_id     AS teacher_user_id,
                       sf.first_name AS staff_first_name,
                       sf.last_name  AS staff_last_name,
                       sf.user_id    AS staff_user_id,
                       i.name        AS institute_name,
                       i.code        AS institute_code
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
                WHERE class_id = :cid AND status = 'active'";
        return (int) $this->query($sql, [':cid' => $classId])->fetch()['cnt'];
    }

    /**
     * All classes with status = 'active'.
     */
    public function getActiveClasses(): array
    {
        return $this->where(['status' => 'active'], 'name ASC');
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
                WHERE c.name LIKE :term OR c.code LIKE :term
                ORDER BY c.name ASC";
        return $this->query($sql, [':term' => "%{$term}%"])->fetchAll();
    }
}
