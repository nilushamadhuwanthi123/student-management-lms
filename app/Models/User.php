<?php
/**
 * User Model — Admin, Coordinator, Staff, Teacher (NOT students/parents)
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id_display', 'role', 'full_name', 'email', 'phone1', 'phone2',
        'whatsapp', 'home_number', 'date_of_birth', 'address', 'province',
        'district', 'notes', 'profile_photo', 'password_hash', 'status',
        'created_by', 'created_at', 'updated_at',
    ];

    // Role → display-ID prefix map as specified in Document 1
    public const ROLE_PREFIX = [
        'super_admin' => 'ADM',
        'admin'       => 'ADM',
        'coordinator' => 'COD',
        'staff'       => 'STF',
        'teacher'     => 'TCH',
    ];

    // Role theme colors as specified in Document 1 Page 2
    public const ROLE_COLORS = [
        'super_admin' => [
            'primary' => '#4f46e5',
            'bg'      => '#eef2ff',
            'border'  => '#c7d2fe',
            'label'   => 'Super Admin',
            'badge'   => 'badge--primary'
        ],
        'admin' => [
            'primary' => '#6366f1',
            'bg'      => '#f5f3ff',
            'border'  => '#ddd6fe',
            'label'   => 'Admin',
            'badge'   => 'badge--info'
        ],
        'coordinator' => [
            'primary' => '#0d9488',
            'bg'      => '#f0fdfa',
            'border'  => '#99f6e4',
            'label'   => 'Coordinator',
            'badge'   => 'badge--teal'
        ],
        'staff' => [
            'primary' => '#d97706',
            'bg'      => '#fffbeb',
            'border'  => '#fde68a',
            'label'   => 'Staff',
            'badge'   => 'badge--warning'
        ],
        'teacher' => [
            'primary' => '#2563eb',
            'bg'      => '#eff6ff',
            'border'  => '#bfdbfe',
            'label'   => 'Teacher',
            'badge'   => 'badge--blue'
        ],
    ];

    // ─── Finders ─────────────────────────────────────────────────────

    /**
     * Find user by display ID (e.g. ADM20260001, COD20260001).
     */
    public function findByUserId(string $displayId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id_display = :uid LIMIT 1";
        $stmt = $this->query($sql, [':uid' => $displayId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find by email.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Find by primary phone number.
     */
    public function findByPhone(string $phone): ?array
    {
        return $this->findBy('phone1', $phone);
    }

    // ─── ID Generation ───────────────────────────────────────────────

    /**
     * Generate next display ID for a given role (ADM<ID>, COD<ID>, STF<ID>, TCH<ID>).
     * Format: PREFIX + 4-digit year + 4-digit auto-increment
     * e.g. ADM20260001, COD20260001, STF20260001, TCH20260001
     */
    public function generateDisplayId(string $role): string
    {
        $prefix = self::ROLE_PREFIX[strtolower($role)] ?? 'ADM';
        $year = date('Y');
        $pattern = $prefix . $year . '%';

        $sql = "SELECT user_id_display FROM {$this->table}
                WHERE user_id_display LIKE :pattern
                ORDER BY user_id_display DESC LIMIT 1";
        $stmt = $this->query($sql, [':pattern' => $pattern]);
        $last = $stmt->fetch();

        if ($last && !empty($last['user_id_display'])) {
            $seq = (int) substr($last['user_id_display'], strlen($prefix) + 4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $year . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ─── Authentication & Status ─────────────────────────────────────

    /**
     * Verify credentials. Returns user row on success, null on failure.
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        // Allow login by user_id_display OR email
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id_display = :id_disp OR email = :email LIMIT 1";
        $stmt = $this->query($sql, [':id_disp' => $identifier, ':email' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $hash = $user['password_hash'] ?? '';
        if ($hash && password_verify($password, $hash)) {
            return $user;
        }

        return null;
    }

    /**
     * Update password hash.
     */
    public function updatePassword(int $id, string $plainPassword): bool
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE {$this->table} 
                SET password_hash = :hash, status = 'active', updated_at = NOW() 
                WHERE id = :id";
        $stmt = $this->query($sql, [':hash' => $hash, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleStatus(int $id): string
    {
        $user = $this->find($id);
        if (!$user) {
            return 'inactive';
        }

        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id";
        $this->query($sql, [':status' => $newStatus, ':id' => $id]);
        return $newStatus;
    }

    // ─── User Management Statistics (KPI Card) ───────────────────────

    /**
     * Get statistics for User Management KPI Card:
     * Total, Active Users, Admins [active/total], Coordinators [active/total],
     * Staff [active/total], Teachers [active/total].
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN role IN ('admin', 'super_admin') THEN 1 ELSE 0 END) AS admins_total,
                    SUM(CASE WHEN role IN ('admin', 'super_admin') AND status = 'active' THEN 1 ELSE 0 END) AS admins_active,
                    SUM(CASE WHEN role = 'coordinator' THEN 1 ELSE 0 END) AS coordinators_total,
                    SUM(CASE WHEN role = 'coordinator' AND status = 'active' THEN 1 ELSE 0 END) AS coordinators_active,
                    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) AS staff_total,
                    SUM(CASE WHEN role = 'staff' AND status = 'active' THEN 1 ELSE 0 END) AS staff_active,
                    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) AS teachers_total,
                    SUM(CASE WHEN role = 'teacher' AND status = 'active' THEN 1 ELSE 0 END) AS teachers_active
                FROM {$this->table}
                WHERE role IN ('admin', 'super_admin', 'coordinator', 'staff', 'teacher')";

        $res = $this->query($sql)->fetch();
        return [
            'total'         => (int) ($res['total_count'] ?? 0),
            'active_users'  => (int) ($res['active_count'] ?? 0),
            'admins'        => ['active' => (int) ($res['admins_active'] ?? 0), 'total' => (int) ($res['admins_total'] ?? 0)],
            'coordinators'  => ['active' => (int) ($res['coordinators_active'] ?? 0), 'total' => (int) ($res['coordinators_total'] ?? 0)],
            'staff'         => ['active' => (int) ($res['staff_active'] ?? 0), 'total' => (int) ($res['staff_total'] ?? 0)],
            'teachers'      => ['active' => (int) ($res['teachers_active'] ?? 0), 'total' => (int) ($res['teachers_total'] ?? 0)],
        ];
    }

    // ─── Filtered Users for Card Grid ────────────────────────────────

    /**
     * Get filtered users for User Management Cards:
     * Supports search (name/email), role filter, institute filter, class filter.
     * Excludes students.
     */
    public function getFilteredUsers(array $filters = []): array
    {
        $params = [];
        $where = ["u.role IN ('super_admin', 'admin', 'coordinator', 'staff', 'teacher')"];

        if (!empty($filters['search'])) {
            // One placeholder per use -- see ClassModel::getGridList.
            $where[] = "(u.full_name LIKE :search1 OR u.email LIKE :search2"
                . " OR u.user_id_display LIKE :search3 OR u.phone1 LIKE :search4)";
            $needle = '%' . trim($filters['search']) . '%';
            foreach (['search1', 'search2', 'search3', 'search4'] as $ph) {
                $params[':' . $ph] = $needle;
            }
        }

        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params[':role'] = $filters['role'];
        }

        $joinClasses = "LEFT JOIN user_classes uc ON u.id = uc.user_id
                        LEFT JOIN classes c ON uc.class_id = c.id";

        if (!empty($filters['institute_id'])) {
            $where[] = "(c.institute_id = :inst_id OR EXISTS(SELECT 1 FROM institutes inst WHERE inst.coordinator_id = u.id AND inst.id = :inst_id2))";
            $params[':inst_id'] = (int) $filters['institute_id'];
            $params[':inst_id2'] = (int) $filters['institute_id'];
        }

        if (!empty($filters['class_id'])) {
            $where[] = "uc.class_id = :class_id";
            $params[':class_id'] = (int) $filters['class_id'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT DISTINCT 
                    u.*,
                    creator.full_name AS creator_name,
                    creator.role AS creator_role,
                    (SELECT inst.name FROM institutes inst 
                     WHERE inst.coordinator_id = u.id LIMIT 1) AS coordinated_institute_name,
                    (SELECT GROUP_CONCAT(DISTINCT cl.class_name SEPARATOR ', ')
                     FROM user_classes ucl
                     JOIN classes cl ON ucl.class_id = cl.id
                     WHERE ucl.user_id = u.id) AS assigned_class_names,
                    (SELECT inst2.name FROM user_classes ucl2
                     JOIN classes cl2 ON ucl2.class_id = cl2.id
                     JOIN institutes inst2 ON cl2.institute_id = inst2.id
                     WHERE ucl2.user_id = u.id LIMIT 1) AS primary_institute_name
                FROM {$this->table} u
                LEFT JOIN users creator ON u.created_by = creator.id
                {$joinClasses}
                WHERE {$whereClause}
                ORDER BY u.id DESC";

        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Class Assignments ───────────────────────────────────────────

    /**
     * Get class IDs attached to user.
     */
    public function getAssignedClassIds(int $userId): array
    {
        $sql = "SELECT class_id FROM user_classes WHERE user_id = :uid";
        $stmt = $this->query($sql, [':uid' => $userId]);
        return array_column($stmt->fetchAll(), 'class_id');
    }

    /**
     * Get classes attached to user (full class details).
     */
    public function getAssignedClasses(int $userId): array
    {
        $sql = "SELECT c.* FROM classes c
                INNER JOIN user_classes uc ON c.id = uc.class_id
                WHERE uc.user_id = :user_id
                ORDER BY c.class_name ASC";
        return $this->query($sql, [':user_id' => $userId])->fetchAll();
    }

    /**
     * Sync assigned classes for user.
     */
    public function syncClasses(int $userId, array $classIds): void
    {
        // Delete existing
        $sql = "DELETE FROM user_classes WHERE user_id = :uid";
        $this->query($sql, [':uid' => $userId]);

        // Insert new
        if (!empty($classIds)) {
            $insertSql = "INSERT INTO user_classes (user_id, class_id) VALUES (:uid, :cid)";
            $stmt = $this->db->prepare($insertSql);
            foreach ($classIds as $cid) {
                if ((int) $cid > 0) {
                    $stmt->execute([':uid' => $userId, ':cid' => (int) $cid]);
                }
            }
        }
    }

    /**
     * Get users by role (e.g. 'coordinator', 'teacher', 'staff').
     */
    public function getByRole(string $role, string $status = 'active'): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE role = :role";
        $params = [':role' => $role];
        if ($status !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY full_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get users by institute.
     */
    public function getByInstitute(int $instituteId): array
    {
        $sql = "SELECT u.* FROM {$this->table} u
                INNER JOIN user_classes uc ON u.id = uc.user_id
                INNER JOIN classes c ON uc.class_id = c.id
                WHERE c.institute_id = :inst
                ORDER BY u.full_name ASC";
        return $this->query($sql, [':inst' => $instituteId])->fetchAll();
    }

    /**
     * Get users by class.
     */
    public function getByClass(int $classId): array
    {
        $sql = "SELECT u.* FROM {$this->table} u
                INNER JOIN user_classes uc ON u.id = uc.user_id
                WHERE uc.class_id = :class_id
                ORDER BY u.full_name ASC";
        return $this->query($sql, [':class_id' => $classId])->fetchAll();
    }

    // ─── Search ──────────────────────────────────────────────────────

    /**
     * Full-text search with optional filters.
     *
     * @param  string $term     Search term (matched against name, email, user_id_display, phone1)
     * @param  array  $filters  Optional keys: role, class_id, status
     * @return array
     */
    public function search(string $term, array $filters = []): array
    {
        $params = [];
        $where  = [];

        if ($term !== '') {
            // One placeholder per use -- see ClassModel::getGridList.
            $where[] = "(u.full_name LIKE :term1 OR u.email LIKE :term2"
                . " OR u.user_id_display LIKE :term3 OR u.phone1 LIKE :term4)";
            foreach (['term1', 'term2', 'term3', 'term4'] as $ph) {
                $params[':' . $ph] = "%{$term}%";
            }
        }

        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $where[] = "u.status = :status";
            $params[':status'] = $filters['status'];
        }

        $joinClass = '';
        if (!empty($filters['class_id'])) {
            $joinClass = "INNER JOIN user_classes uc ON u.id = uc.user_id";
            $where[] = "uc.class_id = :cid";
            $params[':cid'] = $filters['class_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT DISTINCT u.* FROM {$this->table} u
                {$joinClass}
                {$whereClause}
                ORDER BY u.full_name ASC";
        return $this->query($sql, $params)->fetchAll();
    }
}
