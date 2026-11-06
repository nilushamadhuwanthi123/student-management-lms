<?php
/**
 * Setting Model — System settings, deactivation/deletion settings, dashboard widgets
 */

require_once __DIR__ . '/BaseModel.php';

class Setting extends BaseModel
{
    protected string $table = 'system_settings';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'setting_key', 'setting_value', 'updated_by', 'updated_at',
    ];

    // All 16 Dashboard Items / Widgets specified in Document 1 Page 1
    public const DASHBOARD_WIDGETS = [
        'active_students'            => 'Active Students',
        'today_attendance'           => "Today's Attendance",
        'active_classes'             => 'Active Classes',
        'today_revenue'              => "Today's Revenue",
        'monthly_revenue'            => 'Monthly Revenue',
        'pending_payments'           => 'Pending Payments',
        'today_attendance_overview'  => "Today's Attendance Overview",
        'active_teachers'            => 'Active Teachers',
        'attendance_trend_7d'        => 'Attendance Trend (7 Days) [Daily present vs total]',
        'students_by_class'          => 'Students by Class [Active student distribution]',
        'all_active_classes'         => 'All Active Classes',
        'today_classes'              => 'Today Classes',
        'today_new_students'         => 'Today New Students',
        'this_month_new_students'    => 'This Month New Students',
        'upcoming_classes'           => 'Upcoming Classes',
        'recent_messages_approvals'  => 'Recent Messages / Workflow Approvals',
        'this_month_income'          => 'This Month Income (Institutes & Teachers)',
    ];

    // All role-based, hide-only-from-the-nav feature toggles. Each key
    // maps to one logical feature; the controller/view side decides which
    // nav links it gates. The "Settings" area itself is deliberately NOT
    // in this list -- it is shown to super_admin only, by role, in the
    // layout, so there is no row here that could ever hide the page that
    // manages this table and lock Super Admin out.
    public const FEATURE_MODULES = [
        'homework'     => 'Homework / Assignments',
        'recordings'   => 'Class Recordings',
        'past_papers'  => 'Past Papers',
        'payments'     => 'Payments (student/parent view)',
        'notices'      => 'Notices',
        'help'         => 'Help & Questions',
        'my_progress'  => 'My Progress (motivation)',
        'calendar'     => 'Calendar',
        'ai_assistant' => 'AI Assistant (floating helper)',
        'seminars'     => 'Seminars & Events',
    ];

    // Roles each feature is even meaningful for; the admin UI only
    // offers a toggle for a role where it would do something.
    public const FEATURE_ROLES = [
        'homework'     => ['student', 'parent'],
        'recordings'   => ['student', 'parent'],
        'past_papers'  => ['student', 'teacher', 'staff', 'coordinator', 'admin'],
        'payments'     => ['student', 'parent'],
        'notices'      => ['student', 'parent', 'teacher', 'staff', 'coordinator', 'admin'],
        'help'         => ['student', 'parent', 'teacher', 'staff', 'coordinator', 'admin'],
        'my_progress'  => ['student'],
        'calendar'     => ['student', 'parent', 'teacher'],
        'ai_assistant' => ['student', 'parent', 'teacher', 'staff', 'coordinator', 'admin'],
        'seminars'     => ['student', 'parent', 'teacher', 'staff', 'coordinator', 'admin'],
    ];

    // ─── Key-value access ────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        $sql = "SELECT setting_value FROM {$this->table} WHERE setting_key = :key LIMIT 1";
        $row = $this->query($sql, [':key' => $key])->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    public function set(string $key, mixed $value, int $updatedBy = 1): bool
    {
        $existing = $this->findBy('setting_key', $key);

        if ($existing) {
            $sql = "UPDATE {$this->table}
                    SET setting_value = :val, updated_by = :ub, updated_at = NOW()
                    WHERE setting_key = :key";
            $this->query($sql, [':val' => (string) $value, ':ub' => $updatedBy, ':key' => $key]);
        } else {
            $sql = "INSERT INTO {$this->table}
                    (setting_key, setting_value, updated_by, updated_at)
                    VALUES (:key, :val, :ub, NOW())";
            $this->query($sql, [':key' => $key, ':val' => (string) $value, ':ub' => $updatedBy]);
        }

        return true;
    }

    // ─── Deactivation settings ───────────────────────────────────────

    public function getDeactivationSettings(): array
    {
        try {
            $sql = "SELECT * FROM deactivation_settings ORDER BY id ASC";
            return $this->query($sql)->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function updateDeactivationSettings(array $data, int $updatedBy = 1): bool
    {
        foreach ($data as $key => $val) {
            $sql = "INSERT INTO deactivation_settings (setting_key, setting_value, updated_by, updated_at)
                    VALUES (:k, :v, :ub, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :v2, updated_by = :ub2, updated_at = NOW()";
            $this->query($sql, [
                ':k' => $key,
                ':v' => (string) $val,
                ':ub' => $updatedBy,
                ':v2' => (string) $val,
                ':ub2' => $updatedBy,
            ]);
        }
        return true;
    }

    // ─── Deletion settings ───────────────────────────────────────────

    public function getDeletionSettings(): array
    {
        try {
            $sql = "SELECT * FROM deletion_settings ORDER BY id ASC LIMIT 1";
            $row = $this->query($sql)->fetch();
            return $row ?: ['teacher_deletion_option' => 'option1'];
        } catch (\Throwable $e) {
            return ['teacher_deletion_option' => 'option1'];
        }
    }

    public function updateDeletionSettings(array $data, int $updatedBy = 1): bool
    {
        $opt = $data['teacher_deletion_option'] ?? 'option1';
        $sql = "INSERT INTO deletion_settings (id, teacher_deletion_option, updated_by, updated_at)
                VALUES (1, :opt, :ub, NOW())
                ON DUPLICATE KEY UPDATE teacher_deletion_option = :opt2, updated_by = :ub2, updated_at = NOW()";
        $this->query($sql, [
            ':opt' => $opt,
            ':ub' => $updatedBy,
            ':opt2' => $opt,
            ':ub2' => $updatedBy,
        ]);
        return true;
    }

    // ─── Feature visibility (nav-only, never an auth check) ─────────

    /**
     * Visibility map for every feature that applies to this role.
     * Default (no row yet) is visible, same convention as the
     * dashboard widgets above.
     *
     * @return array<string, bool>
     */
    public function getFeatureVisibility(string $role): array
    {
        $result = [];
        foreach (self::FEATURE_MODULES as $key => $label) {
            if (in_array($role, self::FEATURE_ROLES[$key] ?? [], true)) {
                $result[$key] = true;
            }
        }

        try {
            $sql = "SELECT feature_key, is_visible FROM feature_settings WHERE role = :role";
            $rows = $this->query($sql, [':role' => $role])->fetchAll();
            foreach ($rows as $row) {
                if (array_key_exists($row['feature_key'], $result)) {
                    $result[$row['feature_key']] = (bool) $row['is_visible'];
                }
            }
        } catch (\Throwable $e) {
            // Table might not exist yet; fall back to "everything visible".
        }

        return $result;
    }

    /**
     * Whether one feature's nav link should render for this role.
     * This is UI only -- it must never be used as an authorization
     * check. The route behind it keeps its own independent guard.
     */
    public function isFeatureVisible(string $role, string $featureKey): bool
    {
        if (!in_array($role, self::FEATURE_ROLES[$featureKey] ?? [], true)) {
            return true; // not a feature this role has an opinion on -- never hide by accident
        }
        $map = $this->getFeatureVisibility($role);
        return $map[$featureKey] ?? true;
    }

    public function updateFeatureVisibility(
        string $role,
        string $featureKey,
        bool $isVisible,
        int $updatedBy = 1
    ): bool {
        if (!array_key_exists($featureKey, self::FEATURE_MODULES)) {
            return false;
        }

        $sql = "INSERT INTO feature_settings (role, feature_key, is_visible, updated_by, updated_at)
                VALUES (:role, :fk, :vis, :ub, NOW())
                ON DUPLICATE KEY UPDATE is_visible = :vis2, updated_by = :ub2, updated_at = NOW()";

        $this->query($sql, [
            ':role' => $role,
            ':fk'   => $featureKey,
            ':vis'  => $isVisible ? 1 : 0,
            ':ub'   => $updatedBy,
            ':vis2' => $isVisible ? 1 : 0,
            ':ub2'  => $updatedBy,
        ]);

        return true;
    }

    // ─── Dashboard widgets (16 items) per role ───────────────────────

    /**
     * Get dashboard widget visibility map for a role.
     * Returns array<widget_key => is_visible (bool)>
     */
    public function getDashboardWidgets(string $role): array
    {
        $result = [];
        // Default: all widgets visible
        foreach (self::DASHBOARD_WIDGETS as $key => $label) {
            $result[$key] = true;
        }

        try {
            $sql = "SELECT widget_key, is_visible FROM dashboard_settings WHERE role = :role";
            $rows = $this->query($sql, [':role' => $role])->fetchAll();
            foreach ($rows as $row) {
                $result[$row['widget_key']] = (bool) $row['is_visible'];
            }
        } catch (\Throwable $e) {
            // Table might not exist or error, fallback to defaults
        }

        return $result;
    }

    /**
     * Check if a specific widget is visible for a role.
     */
    public function isWidgetVisible(string $role, string $widgetKey): bool
    {
        $widgets = $this->getDashboardWidgets($role);
        return $widgets[$widgetKey] ?? true;
    }

    /**
     * Set dashboard widget visibility for a role.
     */
    public function updateDashboardWidget(
        string $role,
        string $widgetKey,
        bool $isVisible,
        int $updatedBy = 1
    ): bool {
        $sql = "INSERT INTO dashboard_settings (role, widget_key, is_visible, updated_by, updated_at)
                VALUES (:role, :wk, :vis, :ub, NOW())
                ON DUPLICATE KEY UPDATE is_visible = :vis2, updated_by = :ub2, updated_at = NOW()";

        $this->query($sql, [
            ':role' => $role,
            ':wk'   => $widgetKey,
            ':vis'  => $isVisible ? 1 : 0,
            ':ub'   => $updatedBy,
            ':vis2' => $isVisible ? 1 : 0,
            ':ub2'  => $updatedBy,
        ]);

        return true;
    }
}
