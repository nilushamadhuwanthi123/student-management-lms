<?php
/**
 * Institute Model
 */

require_once __DIR__ . '/BaseModel.php';

class Institute extends BaseModel
{
    protected string $table = 'institutes';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'coordinator_id', 'location', 'phone1', 'phone2',
        'notes', 'status', 'created_at', 'updated_at',
    ];

    // ─── Relations ───────────────────────────────────────────────────

    /**
     * Get institute with its coordinator's details joined.
     */
    public function getWithCoordinator(int $id): ?array
    {
        $sql = "SELECT i.*,
                       u.full_name       AS coordinator_name,
                       u.email           AS coordinator_email,
                       u.phone1          AS coordinator_phone,
                       u.user_id_display AS coordinator_user_id
                FROM {$this->table} i
                LEFT JOIN users u ON i.coordinator_id = u.id
                WHERE i.id = :id
                LIMIT 1";
        $row = $this->query($sql, [':id' => $id])->fetch();
        return $row ?: null;
    }

    // ─── Filtered queries ────────────────────────────────────────────

    /**
     * All institutes with status = 'active'.
     */
    public function getActiveInstitutes(): array
    {
        return $this->where(['status' => 'active'], 'name ASC');
    }

    // ─── Aggregate helpers ───────────────────────────────────────────

    /**
     * Number of classes belonging to an institute.
     */
    public function getClassCount(int $instituteId): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM classes WHERE institute_id = :iid";
        return (int) $this->query($sql, [':iid' => $instituteId])->fetch()['cnt'];
    }

    /**
     * Number of students enrolled in an institute's classes.
     * Students table has no institute_id; relationship is via enrollments→classes→institutes.
     */
    public function getStudentCount(int $instituteId): int
    {
        $sql = "SELECT COUNT(DISTINCT e.student_id) AS cnt
                FROM enrollments e
                INNER JOIN classes c ON e.class_id = c.id
                WHERE c.institute_id = :iid AND e.enrollment_status = 'active'";
        return (int) $this->query($sql, [':iid' => $instituteId])->fetch()['cnt'];
    }
}
