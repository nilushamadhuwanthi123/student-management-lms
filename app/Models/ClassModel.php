<?php
/**
 * ClassModel — LMS Class Management Model
 */

require_once __DIR__ . '/BaseModel.php';

class ClassModel extends BaseModel
{
    protected string $table = 'classes';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'class_name', 'subject', 'medium', 'grade', 'institute_id',
        'room', 'teacher_id', 'staff_id', 'schedule_days', 'start_time',
        'end_time', 'monthly_fee', 'teacher_revenue_split', 'notes',
        'delivery_mode', 'meeting_url', 'capacity',
        'status', 'created_at', 'updated_at',
    ];

    /**
     * Get all classes formatted with all 13 grid columns as specified in Document 1 Page 6:
     * CLASS NAME + Subject + Grade, TEACHER, INSTITUTE, COORDINATOR, ROOM, STAFF, 
     * SCHEDULE, TIME, FEE, STUDENTS (Count), STATUS, SPLIT, ACTIONS
     */
    public function getGridList(array $filters = []): array
    {
        $params = [];
        $where = [];

        if (!empty($filters['search'])) {
            // One placeholder per use: with native prepares (see
            // config/database.php) one name reused across five
            // conditions is rejected, and searching 500s.
            $where[] = "(c.class_name LIKE :search1 OR c.subject LIKE :search2"
                . " OR c.grade LIKE :search3 OR t.full_name LIKE :search4"
                . " OR i.name LIKE :search5)";
            $needle = '%' . trim($filters['search']) . '%';
            foreach (['search1', 'search2', 'search3', 'search4', 'search5'] as $ph) {
                $params[':' . $ph] = $needle;
            }
        }

        if (!empty($filters['institute_id'])) {
            $where[] = "c.institute_id = :inst_id";
            $params[':inst_id'] = (int) $filters['institute_id'];
        }

        if (!empty($filters['medium'])) {
            $where[] = "c.medium = :medium";
            $params[':medium'] = $filters['medium'];
        }

        if (!empty($filters['grade'])) {
            $where[] = "c.grade = :grade";
            $params[':grade'] = $filters['grade'];
        }

        if (!empty($filters['status'])) {
            $where[] = "c.status = :status";
            $params[':status'] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT 
                    c.*,
                    t.full_name AS teacher_name,
                    t.user_id_display AS teacher_display_id,
                    sf.full_name AS staff_name,
                    sf.user_id_display AS staff_display_id,
                    i.name AS institute_name,
                    coord.full_name AS coordinator_name,
                    coord.user_id_display AS coordinator_display_id,
                    (SELECT COUNT(*) FROM enrollments e 
                     WHERE e.class_id = c.id AND e.enrollment_status = 'active') AS student_count
                FROM {$this->table} c
                LEFT JOIN users t ON c.teacher_id = t.id
                LEFT JOIN users sf ON c.staff_id = sf.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                LEFT JOIN users coord ON i.coordinator_id = coord.id
                {$whereClause}
                ORDER BY c.id DESC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get single class with all joined details.
     */
    public function getWithDetails(int $id): ?array
    {
        $sql = "SELECT 
                    c.*,
                    t.full_name AS teacher_name,
                    t.user_id_display AS teacher_display_id,
                    sf.full_name AS staff_name,
                    sf.user_id_display AS staff_display_id,
                    i.name AS institute_name,
                    coord.full_name AS coordinator_name,
                    coord.user_id_display AS coordinator_display_id,
                    (SELECT COUNT(*) FROM enrollments e 
                     WHERE e.class_id = c.id AND e.enrollment_status = 'active') AS student_count
                FROM {$this->table} c
                LEFT JOIN users t ON c.teacher_id = t.id
                LEFT JOIN users sf ON c.staff_id = sf.id
                LEFT JOIN institutes i ON c.institute_id = i.id
                LEFT JOIN users coord ON i.coordinator_id = coord.id
                WHERE c.id = :id
                LIMIT 1";

        $row = $this->query($sql, [':id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Get active classes for dropdowns.
     */
    public function getActiveClasses(?int $instituteId = null): array
    {
        if ($instituteId !== null) {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE status = 'active' AND institute_id = :iid 
                    ORDER BY class_name ASC";
            return $this->query($sql, [':iid' => $instituteId])->fetchAll();
        }

        return $this->where(['status' => 'active'], 'class_name ASC');
    }

    /**
     * Toggle class status.
     */
    public function toggleStatus(int $id): string
    {
        $class = $this->find($id);
        if (!$class) {
            return 'inactive';
        }

        $newStatus = ($class['status'] === 'active') ? 'inactive' : 'active';
        $sql = "UPDATE {$this->table} SET status = :st, updated_at = NOW() WHERE id = :id";
        $this->query($sql, [':st' => $newStatus, ':id' => $id]);
        return $newStatus;
    }
}
