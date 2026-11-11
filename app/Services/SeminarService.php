<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Seminar / event rules, mirroring NoticeService's shape.
 *
 * A seminar reaches: everyone (super admin only), one branch, one
 * grade (every branch, or narrowed to one), or one class -- and within
 * that, students, parents and/or staff as ticked. Registration (RSVP)
 * is optional per seminar and tracked in seminar_registrations.
 */
class SeminarService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * The address to put in <img src> for a seminar's poster: an uploaded
     * poster (served from this site) or an http(s) link. Anything else --
     * an old value that is neither -- gives '' so no poster is shown.
     */
    public static function coverSrc(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^/uploads/seminars/[A-Za-z0-9_]+\.(jpg|png|webp)$#', $value)) {
            return BASE_URL . $value;
        }
        return preg_match('#^https?://#i', $value) ? $value : '';
    }

    /**
     * Seminars a student (or a parent, about that student) should see,
     * most recently announced first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId, bool $asParent = false, int $limit = 50): array
    {
        $flag = $asParent ? 's.for_parents' : 's.for_students';
        $stmt = $this->db->prepare(
            "SELECT s.*, c.class_name, i.name AS institute_name
               FROM seminars s
               LEFT JOIN classes c ON c.id = s.class_id
               LEFT JOIN institutes i ON i.id = s.institute_id
              WHERE s.status = 'published'
                AND {$flag} = 1
                AND (s.target_scope = 'all'
                  OR (s.target_scope = 'class' AND s.class_id IN
                        (SELECT e.class_id FROM enrollments e WHERE e.student_id = :s1 AND e.enrollment_status = 'active'))
                  OR (s.target_scope = 'branch' AND s.institute_id IN
                        (SELECT c2.institute_id FROM enrollments e2 JOIN classes c2 ON c2.id = e2.class_id
                          WHERE e2.student_id = :s2 AND e2.enrollment_status = 'active'))
                  OR (s.target_scope = 'grade' AND s.grade IN
                        (SELECT c3.grade FROM enrollments e3 JOIN classes c3 ON c3.id = e3.class_id
                          WHERE e3.student_id = :s3 AND e3.enrollment_status = 'active')
                      AND (s.institute_id IS NULL OR s.institute_id IN
                        (SELECT c4.institute_id FROM enrollments e4 JOIN classes c4 ON c4.id = e4.class_id
                          WHERE e4.student_id = :s4 AND e4.enrollment_status = 'active'))))
              ORDER BY s.event_date DESC, s.id DESC
              LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute([':s1' => $studentId, ':s2' => $studentId, ':s3' => $studentId, ':s4' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Announce every published seminar that has not been announced yet.
     * Safe to run repeatedly: notified_at is set with a guarded UPDATE
     * before anyone is told.
     */
    public function announceDue(): int
    {
        $ids = $this->db->query(
            "SELECT id FROM seminars WHERE status = 'published' AND notified_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($ids as $id) {
            $count += $this->announce((int) $id);
        }

        return $count;
    }

    /** @return int notifications created */
    public function announce(int $seminarId): int
    {
        $claim = $this->db->prepare(
            "UPDATE seminars SET notified_at = NOW()
              WHERE id = :id AND notified_at IS NULL AND status = 'published'"
        );
        $claim->execute([':id' => $seminarId]);
        if ($claim->rowCount() === 0) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT * FROM seminars WHERE id = :id");
        $stmt->execute([':id' => $seminarId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        $ann = new AnnouncementService();
        $recipients = $this->recipients($s);

        $when = date('j M', strtotime((string) $s['event_date']));
        return $ann->notifyAll($recipients, 'Seminar: ' . $s['title'], $when . ' - ' . mb_substr((string) $s['description'], 0, 200), 'seminar', $seminarId);
    }

    /**
     * Everyone a seminar is announced to: the students and/or parents of
     * its class, branch, grade (in one branch or all) or of everyone, and
     * -- when ticked -- the staff of that same class or branch. Staff of a
     * grade-in-one-branch seminar are that branch's staff only.
     *
     * @param array<string, mixed> $s a seminars row
     * @return list<array{type: string, id: int}>
     */
    public function recipients(array $s): array
    {
        $ann = new AnnouncementService();
        $recipients = match ($s['target_scope']) {
            'class'  => $ann->familiesFor((int) $s['class_id'], null, (bool) $s['for_students'], (bool) $s['for_parents']),
            'branch' => $ann->familiesFor(null, (int) $s['institute_id'], (bool) $s['for_students'], (bool) $s['for_parents']),
            'grade'  => $ann->familiesForGrade((string) $s['grade'], $s['institute_id'] !== null ? (int) $s['institute_id'] : null, (bool) $s['for_students'], (bool) $s['for_parents']),
            default  => $ann->familiesFor(null, null, (bool) $s['for_students'], (bool) $s['for_parents']),
        };

        if ((int) $s['for_staff'] === 1) {
            $classId = $s['target_scope'] === 'class' ? (int) $s['class_id'] : null;
            $instituteId = in_array($s['target_scope'], ['branch', 'grade'], true) && $s['institute_id'] !== null
                ? (int) $s['institute_id'] : null;
            if ($classId !== null) {
                $q = $this->db->prepare("SELECT teacher_id FROM classes WHERE id = :c AND teacher_id IS NOT NULL");
                $q->execute([':c' => $classId]);
                $staff = $q->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($instituteId !== null) {
                $q = $this->db->prepare(
                    "SELECT ui.user_id FROM user_institutes ui JOIN users u ON u.id = ui.user_id AND u.status = 'active' WHERE ui.institute_id = :i
                     UNION SELECT teacher_id FROM classes WHERE institute_id = :i2 AND teacher_id IS NOT NULL"
                );
                $q->execute([':i' => $instituteId, ':i2' => $instituteId]);
                $staff = $q->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $staff = $this->db->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            }
            foreach (array_unique(array_map('intval', $staff)) as $uid) {
                $recipients[] = ['type' => 'user', 'id' => $uid];
            }
        }

        return $recipients;
    }

    /**
     * How many students, parents and staff a seminar reaches -- the same
     * people recipients() would notify, counted.
     *
     * @param array<string, mixed> $s a seminars row
     * @return array{student: int, parent: int, user: int}
     */
    public function audienceCounts(array $s): array
    {
        $out = ['student' => 0, 'parent' => 0, 'user' => 0];
        $seen = [];
        foreach ($this->recipients($s) as $r) {
            $k = $r['type'] . ':' . $r['id'];
            if (isset($seen[$k]) || !isset($out[$r['type']])) {
                continue;
            }
            $seen[$k] = true;
            $out[$r['type']]++;
        }
        return $out;
    }

    // ─── Registration (RSVP), capacity and waitlist ──────────────────

    /**
     * Register a person for a seminar. When the seminar has a
     * max_capacity and it is already full, the registration is recorded
     * as 'waitlisted' instead of refused outright -- it is promoted
     * automatically if a spot frees up (see unregister()).
     *
     * @return string 'registered'|'waitlisted'|'already' (already held
     *                that or a better status; nothing changed)
     */
    public function register(int $seminarId, string $userType, int $userId): string
    {
        $existing = $this->db->prepare(
            "SELECT status FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid"
        );
        $existing->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);
        $existingStatus = $existing->fetchColumn();
        if ($existingStatus !== false) {
            return 'already';
        }

        $status = $this->hasFreeCapacity($seminarId) ? 'registered' : 'waitlisted';
        $this->db->prepare(
            "INSERT INTO seminar_registrations (seminar_id, user_type, user_id, status, registered_at)
             VALUES (:sid, :ut, :uid, :status, NOW())
             ON DUPLICATE KEY UPDATE status = status"
        )->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId, ':status' => $status]);

        return $status;
    }

    /** Unregister, then promote the longest-waiting waitlisted person into the freed spot, if any. */
    public function unregister(int $seminarId, string $userType, int $userId): bool
    {
        $del = $this->db->prepare(
            "DELETE FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid AND status = 'registered'"
        );
        $del->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);
        $freedASpot = $del->rowCount() > 0;

        if (!$freedASpot) {
            // Also allow a waitlisted person to cancel outright.
            $this->db->prepare(
                "DELETE FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid AND status = 'waitlisted'"
            )->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);
            return true;
        }

        if ($this->hasFreeCapacity($seminarId)) {
            $this->db->prepare(
                "UPDATE seminar_registrations SET status = 'registered'
                  WHERE id = (
                      SELECT id FROM (
                          SELECT id FROM seminar_registrations
                           WHERE seminar_id = :sid AND status = 'waitlisted'
                        ORDER BY registered_at ASC LIMIT 1
                      ) AS next_up
                  )"
            )->execute([':sid' => $seminarId]);
        }

        return true;
    }

    /** Whether registering one more person would still fit under max_capacity (always true when unlimited). */
    private function hasFreeCapacity(int $seminarId): bool
    {
        $stmt = $this->db->prepare("SELECT max_capacity FROM seminars WHERE id = :id");
        $stmt->execute([':id' => $seminarId]);
        $capacity = $stmt->fetchColumn();
        if ($capacity === false || $capacity === null) {
            return true;
        }

        return $this->registrationCount($seminarId) < (int) $capacity;
    }

    /** @return string|null 'registered'|'waitlisted', or null if not registered at all */
    public function registrationStatus(int $seminarId, string $userType, int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT status FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid LIMIT 1"
        );
        $stmt->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);
        $status = $stmt->fetchColumn();

        return $status === false ? null : (string) $status;
    }

    public function isRegistered(int $seminarId, string $userType, int $userId): bool
    {
        return $this->registrationStatus($seminarId, $userType, $userId) !== null;
    }

    /** Only counts confirmed registrations, not the waitlist. */
    public function registrationCount(int $seminarId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id = :sid AND status = 'registered'");
        $stmt->execute([':sid' => $seminarId]);

        return (int) $stmt->fetchColumn();
    }

    public function waitlistCount(int $seminarId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id = :sid AND status = 'waitlisted'");
        $stmt->execute([':sid' => $seminarId]);

        return (int) $stmt->fetchColumn();
    }

    public function attendedCount(int $seminarId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id = :sid AND attended = 1");
        $stmt->execute([':sid' => $seminarId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Everyone registered or waitlisted for a seminar, with a
     * best-effort display name resolved per user_type, for the
     * organizer's registrants/attendance page.
     *
     * @return array<int, array{id:int,user_type:string,user_id:int,status:string,registered_at:string,attended:bool,attended_at:?string,name:string,detail:string}>
     */
    public function registrants(int $seminarId): array
    {
        $rows = $this->db->prepare(
            "SELECT id, user_type, user_id, status, registered_at, attended, attended_at
               FROM seminar_registrations WHERE seminar_id = :sid
           ORDER BY status = 'waitlisted', registered_at ASC"
        );
        $rows->execute([':sid' => $seminarId]);
        $regs = $rows->fetchAll(PDO::FETCH_ASSOC);

        $byType = ['student' => [], 'parent' => [], 'user' => []];
        foreach ($regs as $r) {
            $byType[$r['user_type']][] = (int) $r['user_id'];
        }

        $students = $byType['student'] === [] ? [] : $this->fetchNames(
            "SELECT id, full_name AS name, student_id_display AS detail FROM students WHERE id IN (" . implode(',', $byType['student']) . ")"
        );
        $parents = $byType['parent'] === [] ? [] : $this->fetchNames(
            "SELECT p.id, s.full_name AS name, p.phone1 AS detail FROM parents p LEFT JOIN students s ON s.id = p.student_id WHERE p.id IN (" . implode(',', $byType['parent']) . ")"
        );
        $users = $byType['user'] === [] ? [] : $this->fetchNames(
            "SELECT id, full_name AS name, role AS detail FROM users WHERE id IN (" . implode(',', $byType['user']) . ")"
        );
        $names = ['student' => $students, 'parent' => $parents, 'user' => $users];

        foreach ($regs as &$r) {
            $found = $names[$r['user_type']][(int) $r['user_id']] ?? null;
            $r['name'] = $found['name'] ?? ('#' . $r['user_id']);
            $r['detail'] = $found['detail'] ?? '';
            $r['attended'] = (bool) $r['attended'];
        }

        return $regs;
    }

    /** @return array<int, array{name:string, detail:string}> keyed by id */
    private function fetchNames(string $sql): array
    {
        $out = [];
        foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = ['name' => (string) $row['name'], 'detail' => (string) ($row['detail'] ?? '')];
        }
        return $out;
    }

    public function setAttendance(int $registrationId, int $seminarId, bool $attended): void
    {
        $this->db->prepare(
            "UPDATE seminar_registrations SET attended = :a, attended_at = :at
              WHERE id = :id AND seminar_id = :sid"
        )->execute([
            ':a' => $attended ? 1 : 0,
            ':at' => $attended ? date('Y-m-d H:i:s') : null,
            ':id' => $registrationId,
            ':sid' => $seminarId,
        ]);
    }
}
