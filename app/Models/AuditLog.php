<?php
/**
 * AuditLog Model
 */

require_once __DIR__ . '/BaseModel.php';

class AuditLog extends BaseModel
{
    protected string $table = 'audit_log';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'user_type', 'user_role', 'action',
        'entity_type', 'entity_id', 'details',
        'ip_address', 'created_at',
    ];

    // ─── Log ─────────────────────────────────────────────────────────

    /**
     * Record an audit entry.
     *
     * @param  int         $userId
     * @param  string      $userType    'user', 'student', 'parent'
     * @param  string      $userRole    e.g. 'admin', 'teacher'
     * @param  string      $action      e.g. 'create_student', 'approve_workflow'
     * @param  string      $entityType  e.g. 'student', 'payment'
     * @param  int|null    $entityId
     * @param  array|null  $details     Extra data stored as JSON
     * @return int         Log entry ID
     */
    public function log(
        int $userId,
        string $userType,
        string $userRole,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $details = null
    ): int {
        $data = [
            'user_id'     => $userId,
            'user_type'   => $userType,
            'user_role'   => $userRole,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details ? json_encode($details) : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        return $this->create($data);
    }

    // ─── Queries ─────────────────────────────────────────────────────

    /**
     * Get audit entries for a specific entity.
     */
    public function getByEntity(string $entityType, int $entityId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE entity_type = :etype AND entity_id = :eid
                ORDER BY created_at DESC";
        return $this->query($sql, [':etype' => $entityType, ':eid' => $entityId])->fetchAll();
    }

    /**
     * Get audit entries by a specific user.
     */
    public function getByUser(int $userId, string $userType): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :uid AND user_type = :utype
                ORDER BY created_at DESC";
        return $this->query($sql, [':uid' => $userId, ':utype' => $userType])->fetchAll();
    }

    /**
     * Search audit log with filters.
     *
     * @param  array $filters  Optional: date_from, date_to, action, entity_type, entity_id, user_id, user_type
     */
    public function search(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['action'])) {
            $where[] = "a.action = :action";
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = "a.entity_type = :etype";
            $params[':etype'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[] = "a.entity_id = :eid";
            $params[':eid'] = $filters['entity_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = :uid";
            $params[':uid'] = $filters['user_id'];
        }
        if (!empty($filters['user_type'])) {
            $where[] = "a.user_type = :utype";
            $params[':utype'] = $filters['user_type'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT a.*, COALESCE(u.full_name, 'System') AS user_name
                FROM {$this->table} a
                LEFT JOIN users u ON a.user_id = u.id AND a.user_type = 'user'
                {$whereClause}
                ORDER BY a.created_at DESC";
        return $this->query($sql, $params)->fetchAll();
    }
}
