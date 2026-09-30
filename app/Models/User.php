<?php
/**
 * User Model — admin / coordinator / staff / teacher (NOT students)
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'first_name', 'last_name', 'email', 'phone',
        'password', 'role', 'institute_id', 'status',
        'profile_picture', 'created_at', 'updated_at',
    ];

    // Role → display-ID prefix map
    private const ROLE_PREFIX = [
        'admin'       => 'ADM',
        'coordinator' => 'COD',
        'staff'       => 'STF',
        'teacher'     => 'TCH',
    ];

    // ─── Finders ─────────────────────────────────────────────────────

    /**
     * Find by display ID (e.g. ADM20260001).
     */
    public function findByUserId(string $displayId): ?array
    {
        return $this->findBy('user_id', $displayId);
    }

    /**
     * Find by email address.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    // ─── ID generation ───────────────────────────────────────────────

    /**
     * Generate the next display ID for a given role.
     * Format: PREFIX + 4-digit year + 4-digit auto-increment
     * e.g. ADM20260001
     */
    public function generateDisplayId(string $role): string
    {
        $prefix = self::ROLE_PREFIX[strtolower($role)] ?? 'USR';
        $year = date('Y');
        $pattern = $prefix . $year . '%';

        $sql = "SELECT user_id FROM {$this->table}
                WHERE user_id LIKE :pattern
                ORDER BY user_id DESC LIMIT 1";
        $stmt = $this->query($sql, [':pattern' => $pattern]);
        $last = $stmt->fetch();

        if ($last) {
            $seq = (int) substr($last['user_id'], strlen($prefix) + 4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ─── Role / institute / class queries ────────────────────────────

    public function getByRole(string $role): array
    {
        return $this->where(['role' => $role], 'first_name ASC');
    }

    public function getByInstitute(int $instituteId): array
    {
        return $this->where(['institute_id' => $instituteId], 'first_name ASC');
    }

    public function getByClass(int $classId): array
    {
        $sql = "SELECT u.* FROM {$this->table} u
                INNER JOIN user_classes uc ON u.id = uc.user_id
                WHERE uc.class_id = :class_id
                ORDER BY u.first_name ASC";
        return $this->query($sql, [':class_id' => $classId])->fetchAll();
    }

    // ─── Authentication ──────────────────────────────────────────────

    /**
     * Verify credentials. Returns user row on success, null on failure.
     */
    public function authenticate(string $userId, string $password): ?array
    {
        $user = $this->findByUserId($userId);
        if (!$user) {
            return null;
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }
        return $user;
    }

    /**
     * Store a new password hash.
     */
    public function updatePassword(int $id, string $hash): bool
    {
        $sql = "UPDATE {$this->table} SET password = :hash, updated_at = NOW() WHERE id = :id";
        $stmt = $this->query($sql, [':hash' => $hash, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ─── Class assignments ───────────────────────────────────────────

    /**
     * Get classes assigned to a user via the user_classes pivot table.
     */
    public function getAssignedClasses(int $userId): array
    {
        $sql = "SELECT c.* FROM classes c
                INNER JOIN user_classes uc ON c.id = uc.class_id
                WHERE uc.user_id = :user_id
                ORDER BY c.name ASC";
        return $this->query($sql, [':user_id' => $userId])->fetchAll();
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Full-text search with optional filters.
     *
     * @param  string $term     Search term (matched against name, email, user_id)
     * @param  array  $filters  Optional keys: role, institute_id, class_id, status
     * @return array
     */
    public function search(string $term, array $filters = []): array
    {
        $params = [];
        $where = [];

        if ($term !== '') {
            $where[] = "(u.first_name LIKE :term OR u.last_name LIKE :term
                         OR u.email LIKE :term OR u.user_id LIKE :term)";
            $params[':term'] = "%{$term}%";
        }

        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params[':role'] = $filters['role'];
        }
        if (!empty($filters['institute_id'])) {
            $where[] = "u.institute_id = :inst";
            $params[':inst'] = $filters['institute_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = "u.status = :status";
            $params[':status'] = $filters['status'];
        }

        $joinClass = '';
        if (!empty($filters['class_id'])) {
            $joinClass = "INNER JOIN user_classes uc ON u.id = uc.user_id";
            $where[] = "uc.class_id = :class_id";
            $params[':class_id'] = $filters['class_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT u.* FROM {$this->table} u
                {$joinClass}
                {$whereClause}
                ORDER BY u.first_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }
}
