<?php
/**
 * Parent_ Model — Parent / Guardian
 * Named with underscore to avoid the PHP reserved word "Parent".
 */

require_once __DIR__ . '/BaseModel.php';

class Parent_ extends BaseModel
{
    protected string $table = 'parents';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'student_id', 'first_name', 'last_name', 'relationship',
        'phone', 'email', 'password', 'address',
        'status', 'created_at', 'updated_at',
    ];

    // ─── Finders ─────────────────────────────────────────────────────

    /**
     * Get all parents linked to a student.
     */
    public function findByStudent(int $studentId): array
    {
        return $this->where(['student_id' => $studentId], 'first_name ASC');
    }

    // ─── Authentication ──────────────────────────────────────────────

    /**
     * Authenticate a parent by phone + password.
     */
    public function authenticate(string $phone, string $password): ?array
    {
        $parent = $this->findBy('phone', $phone);
        if (!$parent) {
            return null;
        }
        if (!password_verify($password, $parent['password'])) {
            return null;
        }
        return $parent;
    }

    // ─── Status management ───────────────────────────────────────────

    public function deactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'deactivated', updated_at = NOW()
                WHERE id = :id";
        return $this->query($sql, [':id' => $id])->rowCount() > 0;
    }

    public function reactivate(int $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET status = 'active', updated_at = NOW()
                WHERE id = :id";
        return $this->query($sql, [':id' => $id])->rowCount() > 0;
    }
}
