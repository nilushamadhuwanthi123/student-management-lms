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

    // ─── Key-value access ────────────────────────────────────────────

    /**
     * Get a setting value by key, with optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $sql = "SELECT setting_value FROM {$this->table} WHERE setting_key = :key LIMIT 1";
        $row = $this->query($sql, [':key' => $key])->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    /**
     * Set (upsert) a setting value.
     */
    public function set(string $key, mixed $value, int $updatedBy): bool
    {
        $existing = $this->findBy('setting_key', $key);

        if ($existing) {
            $sql = "UPDATE {$this->table}
                    SET setting_value = :val, updated_by = :ub, updated_at = NOW()
                    WHERE setting_key = :key";
            $this->query($sql, [':val' => $value, ':ub' => $updatedBy, ':key' => $key]);
        } else {
            $sql = "INSERT INTO {$this->table}
                    (setting_key, setting_value, updated_by, updated_at)
                    VALUES (:key, :val, :ub, NOW())";
            $this->query($sql, [':key' => $key, ':val' => $value, ':ub' => $updatedBy]);
        }

        return true;
    }

    // ─── Deactivation settings ───────────────────────────────────────

    /**
     * Get all deactivation-related settings from the deactivation_settings table.
     */
    public function getDeactivationSettings(): array
    {
        $sql = "SELECT * FROM deactivation_settings ORDER BY id ASC";
        return $this->query($sql)->fetchAll();
    }

    /**
     * Update deactivation settings.
     *
     * @param  array $data       Associative array of column => value to update
     * @param  int   $updatedBy  User who made the change
     */
    public function updateDeactivationSettings(array $data, int $updatedBy): bool
    {
        $setParts = [];
        $params = [':ub' => $updatedBy];
        $i = 0;
        foreach ($data as $col => $val) {
            $col = $this->sanitizeColumn($col);
            $placeholder = ":ds{$i}";
            $setParts[] = "{$col} = {$placeholder}";
            $params[$placeholder] = $val;
            $i++;
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = "UPDATE deactivation_settings
                SET " . implode(', ', $setParts) . ", updated_by = :ub, updated_at = NOW()";
        $this->query($sql, $params);
        return true;
    }

    // ─── Deletion settings ───────────────────────────────────────────

    /**
     * Get all deletion-related settings.
     */
    public function getDeletionSettings(): array
    {
        $sql = "SELECT * FROM deletion_settings ORDER BY id ASC";
        return $this->query($sql)->fetchAll();
    }

    /**
     * Update deletion settings.
     */
    public function updateDeletionSettings(array $data, int $updatedBy): bool
    {
        $setParts = [];
        $params = [':ub' => $updatedBy];
        $i = 0;
        foreach ($data as $col => $val) {
            $col = $this->sanitizeColumn($col);
            $placeholder = ":dl{$i}";
            $setParts[] = "{$col} = {$placeholder}";
            $params[$placeholder] = $val;
            $i++;
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = "UPDATE deletion_settings
                SET " . implode(', ', $setParts) . ", updated_by = :ub, updated_at = NOW()";
        $this->query($sql, $params);
        return true;
    }

    // ─── Dashboard widgets ───────────────────────────────────────────

    /**
     * Get dashboard widget visibility for a role.
     */
    public function getDashboardWidgets(string $role): array
    {
        $sql = "SELECT * FROM dashboard_widgets
                WHERE role = :role
                ORDER BY widget_key ASC";
        return $this->query($sql, [':role' => $role])->fetchAll();
    }

    /**
     * Toggle a dashboard widget's visibility for a role.
     */
    public function updateDashboardWidget(
        string $role,
        string $widgetKey,
        bool $isVisible,
        int $updatedBy
    ): bool {
        // Try update first
        $sql = "UPDATE dashboard_widgets
                SET is_visible = :vis, updated_by = :ub, updated_at = NOW()
                WHERE role = :role AND widget_key = :wk";
        $stmt = $this->query($sql, [
            ':vis'  => $isVisible ? 1 : 0,
            ':ub'   => $updatedBy,
            ':role' => $role,
            ':wk'   => $widgetKey,
        ]);

        if ($stmt->rowCount() === 0) {
            // Insert if it doesn't exist
            $sql = "INSERT INTO dashboard_widgets
                    (role, widget_key, is_visible, updated_by, updated_at)
                    VALUES (:role, :wk, :vis, :ub, NOW())";
            $this->query($sql, [
                ':role' => $role,
                ':wk'   => $widgetKey,
                ':vis'  => $isVisible ? 1 : 0,
                ':ub'   => $updatedBy,
            ]);
        }

        return true;
    }
}
