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

    /**
     * Get all institutes with coordinator information and class counts.
     */
    public function getAllWithCoordinator(string $search = ''): array
    {
        $params = [];
        $where = '';

        if ($search !== '') {
            $where = "WHERE i.name LIKE :search OR i.location LIKE :search OR u.full_name LIKE :search";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql = "SELECT 
                    i.*,
                    u.full_name AS coordinator_name,
                    u.user_id_display AS coordinator_display_id,
                    u.email AS coordinator_email,
                    u.phone1 AS coordinator_phone,
                    (SELECT COUNT(*) FROM classes c WHERE c.institute_id = i.id) AS class_count,
                    (SELECT COUNT(DISTINCT e.student_id) 
                     FROM classes c 
                     JOIN enrollments e ON c.id = e.class_id 
                     WHERE c.institute_id = i.id) AS student_count
                FROM {$this->table} i
                LEFT JOIN users u ON i.coordinator_id = u.id
                {$where}
                ORDER BY i.name ASC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get single institute with coordinator details.
     */
    public function getWithCoordinator(int $id): ?array
    {
        $sql = "SELECT 
                    i.*,
                    u.full_name AS coordinator_name,
                    u.user_id_display AS coordinator_display_id,
                    u.email AS coordinator_email,
                    u.phone1 AS coordinator_phone
                FROM {$this->table} i
                LEFT JOIN users u ON i.coordinator_id = u.id
                WHERE i.id = :id
                LIMIT 1";
        $row = $this->query($sql, [':id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Get active institutes for DDL / dropdown selection.
     */
    public function getActiveInstitutes(): array
    {
        return $this->where(['status' => 'active'], 'name ASC');
    }

    /**
     * Get all classes belonging to an institute.
     */
    public function getClasses(int $instituteId): array
    {
        $sql = "SELECT * FROM classes WHERE institute_id = :iid ORDER BY class_name ASC";
        return $this->query($sql, [':iid' => $instituteId])->fetchAll();
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleStatus(int $id): string
    {
        $inst = $this->find($id);
        if (!$inst) {
            return 'inactive';
        }

        $newStatus = ($inst['status'] === 'active') ? 'inactive' : 'active';
        $sql = "UPDATE {$this->table} SET status = :st, updated_at = NOW() WHERE id = :id";
        $this->query($sql, [':st' => $newStatus, ':id' => $id]);
        return $newStatus;
    }
}
