<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Which branches, classes and students the signed-in staff member may see.
 *
 * One place answers the question for every controller, so a branch admin
 * in Galle cannot read Kandy's students by changing an id in the URL, and
 * a list page cannot forget the filter that its detail page applies.
 *
 *   super_admin              everything
 *   admin/coordinator/staff  the branches in `user_institutes` (plus the
 *                            branch that names them as coordinator)
 *   teacher                  the classes they teach
 *
 * Anyone with no branch gets nothing: deny by default. A null return
 * from instituteIds()/classIds() means "no restriction"; an empty array
 * means "nothing at all".
 */
class BranchScope
{
    private PDO $db;
    private int $userId;
    private string $role;

    /** @var array<int, int>|null */
    private ?array $institutes = null;
    /** @var array<int, int>|null */
    private ?array $classes = null;
    private bool $loadedInstitutes = false;
    private bool $loadedClasses = false;

    private static ?self $current = null;

    public function __construct(int $userId, string $role, ?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
        $this->userId = $userId;
        $this->role = $role;
    }

    /** The scope of whoever is signed in. */
    public static function current(): self
    {
        if (self::$current === null) {
            $type = (string) session_get('user_type');
            // Students and parents never reach staff screens; if one does,
            // give them an empty scope rather than borrowing a user's.
            $role = $type === 'user' || $type === '' ? (string) session_get('user_role') : 'none';
            self::$current = new self((int) session_get('user_id'), $role);
        }

        return self::$current;
    }

    public function isGlobal(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /** @return array<int, int>|null  null = all branches */
    public function instituteIds(): ?array
    {
        if ($this->isGlobal()) {
            return null;
        }
        if (!$this->loadedInstitutes) {
            $this->loadedInstitutes = true;
            if (in_array($this->role, ['admin', 'coordinator', 'staff'], true)) {
                $stmt = $this->db->prepare(
                    "SELECT institute_id FROM user_institutes WHERE user_id = :u1
                     UNION
                     SELECT id FROM institutes WHERE coordinator_id = :u2"
                );
                $stmt->execute([':u1' => $this->userId, ':u2' => $this->userId]);
                $this->institutes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } elseif ($this->isTeacher()) {
                // A teacher's branches are wherever their classes are.
                $ids = $this->classIds() ?? [];
                if ($ids === []) {
                    $this->institutes = [];
                } else {
                    $in = implode(',', array_map('intval', $ids));
                    $this->institutes = array_map(
                        'intval',
                        $this->db->query("SELECT DISTINCT institute_id FROM classes WHERE id IN ($in)")->fetchAll(PDO::FETCH_COLUMN)
                    );
                }
            } else {
                $this->institutes = [];
            }
        }

        return $this->institutes;
    }

    /** @return array<int, int>|null  null = all classes */
    public function classIds(): ?array
    {
        if ($this->isGlobal()) {
            return null;
        }
        if (!$this->loadedClasses) {
            $this->loadedClasses = true;
            if ($this->isTeacher()) {
                $stmt = $this->db->prepare(
                    "SELECT id FROM classes WHERE teacher_id = :u1
                     UNION
                     SELECT class_id FROM user_classes WHERE user_id = :u2"
                );
                $stmt->execute([':u1' => $this->userId, ':u2' => $this->userId]);
                $this->classes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } else {
                $inst = $this->instituteIds() ?? [];
                if ($inst === []) {
                    $this->classes = [];
                } else {
                    $in = implode(',', array_map('intval', $inst));
                    $this->classes = array_map(
                        'intval',
                        $this->db->query("SELECT id FROM classes WHERE institute_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN)
                    );
                }
            }
        }

        return $this->classes;
    }

    public function allowsInstitute(?int $instituteId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }
        return $instituteId !== null && in_array($instituteId, $this->instituteIds() ?? [], true);
    }

    public function allowsClass(?int $classId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }
        return $classId !== null && in_array($classId, $this->classIds() ?? [], true);
    }

    /**
     * A student is in scope when they are (or were) enrolled in one of the
     * scope's classes. A student with no enrolment at all -- just
     * registered, not yet placed -- is visible to branch office staff so
     * they can be enrolled, but not to teachers.
     */
    public function allowsStudent(int $studentId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        $classes = $this->classIds() ?? [];
        if ($classes !== []) {
            $in = implode(',', array_map('intval', $classes));
            $stmt = $this->db->prepare("SELECT 1 FROM enrollments WHERE student_id = :s AND class_id IN ($in) LIMIT 1");
            $stmt->execute([':s' => $studentId]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        if (!$this->isTeacher() && ($this->instituteIds() ?? []) !== []) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :s");
            $stmt->execute([':s' => $studentId]);
            return (int) $stmt->fetchColumn() === 0;
        }

        return false;
    }

    public function allowsPayment(int $paymentId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }
        $stmt = $this->db->prepare("SELECT class_id FROM payments WHERE id = :p");
        $stmt->execute([':p' => $paymentId]);
        $classId = $stmt->fetchColumn();

        return $classId !== false && $this->allowsClass((int) $classId);
    }

    /**
     * Another staff account is in scope when it belongs to one of this
     * user's branches, or teaches a class there. Super admins are only
     * ever managed by the super admin.
     */
    public function allowsUser(int $targetUserId): bool
    {
        if ($this->isGlobal()) {
            return true;
        }
        if ($targetUserId === $this->userId) {
            return true;
        }
        $inst = $this->instituteIds() ?? [];
        if ($inst === [] || $this->isTeacher()) {
            return false;
        }
        $in = implode(',', array_map('intval', $inst));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users u
              WHERE u.id = :t AND u.role <> 'super_admin'
                AND (EXISTS (SELECT 1 FROM user_institutes ui WHERE ui.user_id = u.id AND ui.institute_id IN ($in))
                  OR EXISTS (SELECT 1 FROM classes c WHERE c.teacher_id = u.id AND c.institute_id IN ($in)))"
        );
        $stmt->execute([':t' => $targetUserId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Whether the signed-in user may CHANGE another staff account: edit
     * it, switch it on or off, or reset its password. Stricter than
     * allowsUser() (which is about seeing it): a branch admin manages only
     * people who belong to their branch alone. Someone who also works in
     * another branch -- the teacher who teaches in every city, say -- is
     * shared, so only the super admin may change them; otherwise one
     * city's admin could reset the shared teacher's password, sign in as
     * her and read every other city's students. Nobody manages their own
     * account from here (that is what the profile page is for), so an
     * admin cannot switch themselves off by mistake either.
     */
    public function allowsManageUser(int $targetUserId): bool
    {
        if ($targetUserId === $this->userId) {
            return false;
        }
        if ($this->isGlobal()) {
            return true;
        }
        $inst = $this->instituteIds() ?? [];
        if ($inst === [] || $this->isTeacher() || $this->role !== 'admin') {
            return false;
        }
        $in = implode(',', array_map('intval', $inst));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users u
              WHERE u.id = :t AND u.role NOT IN ('super_admin', 'admin')
                AND (EXISTS (SELECT 1 FROM user_institutes ui WHERE ui.user_id = u.id AND ui.institute_id IN ($in))
                  OR EXISTS (SELECT 1 FROM classes c WHERE c.teacher_id = u.id AND c.institute_id IN ($in)))
                AND NOT EXISTS (SELECT 1 FROM user_institutes ui WHERE ui.user_id = u.id AND ui.institute_id NOT IN ($in))
                AND NOT EXISTS (SELECT 1 FROM classes c WHERE c.teacher_id = u.id AND c.institute_id NOT IN ($in))"
        );
        $stmt->execute([':t' => $targetUserId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * " AND <column> IN (1,2,3)" for a query, or "" when unrestricted, or
     * " AND 1 = 0" when the scope is empty. Ids are integers from the
     * database, so they are inlined safely.
     */
    public function classSql(string $column): string
    {
        return self::inSql($column, $this->classIds());
    }

    public function instituteSql(string $column): string
    {
        return self::inSql($column, $this->instituteIds());
    }

    /** Student filter for list queries, matching allowsStudent(). */
    public function studentSql(string $studentIdColumn): string
    {
        if ($this->isGlobal()) {
            return '';
        }
        $classes = $this->classIds() ?? [];
        $parts = [];
        if ($classes !== []) {
            $parts[] = "EXISTS (SELECT 1 FROM enrollments se WHERE se.student_id = {$studentIdColumn} AND se.class_id IN ("
                . implode(',', array_map('intval', $classes)) . '))';
        }
        if (!$this->isTeacher() && ($this->instituteIds() ?? []) !== []) {
            $parts[] = "NOT EXISTS (SELECT 1 FROM enrollments se2 WHERE se2.student_id = {$studentIdColumn})";
        }

        return $parts === [] ? ' AND 1 = 0' : ' AND (' . implode(' OR ', $parts) . ')';
    }

    /** @param array<int, int>|null $ids */
    private static function inSql(string $column, ?array $ids): string
    {
        if ($ids === null) {
            return '';
        }
        if ($ids === []) {
            return ' AND 1 = 0';
        }

        return " AND {$column} IN (" . implode(',', array_map('intval', $ids)) . ')';
    }
}
