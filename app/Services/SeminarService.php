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
        $recipients = match ($s['target_scope']) {
            'class'  => $ann->familiesFor((int) $s['class_id'], null, (bool) $s['for_students'], (bool) $s['for_parents']),
            'branch' => $ann->familiesFor(null, (int) $s['institute_id'], (bool) $s['for_students'], (bool) $s['for_parents']),
            'grade'  => $ann->familiesForGrade((string) $s['grade'], $s['institute_id'] !== null ? (int) $s['institute_id'] : null, (bool) $s['for_students'], (bool) $s['for_parents']),
            default  => $ann->familiesFor(null, null, (bool) $s['for_students'], (bool) $s['for_parents']),
        };

        if ((int) $s['for_staff'] === 1) {
            $classId = $s['target_scope'] === 'class' ? (int) $s['class_id'] : null;
            $instituteId = $s['target_scope'] === 'branch' ? (int) $s['institute_id'] : null;
            if ($classId !== null) {
                $q = $this->db->prepare("SELECT teacher_id FROM classes WHERE id = :c AND teacher_id IS NOT NULL");
                $q->execute([':c' => $classId]);
                $staff = $q->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($instituteId !== null) {
                $q = $this->db->prepare(
                    "SELECT user_id FROM user_institutes WHERE institute_id = :i
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

        $when = date('j M', strtotime((string) $s['event_date']));
        return $ann->notifyAll($recipients, 'Seminar: ' . $s['title'], $when . ' - ' . mb_substr((string) $s['description'], 0, 200), 'seminar', $seminarId);
    }

    // ─── Registration (RSVP) ─────────────────────────────────────────

    public function register(int $seminarId, string $userType, int $userId): bool
    {
        $this->db->prepare(
            "INSERT INTO seminar_registrations (seminar_id, user_type, user_id, registered_at)
             VALUES (:sid, :ut, :uid, NOW())
             ON DUPLICATE KEY UPDATE registered_at = registered_at"
        )->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);

        return true;
    }

    public function unregister(int $seminarId, string $userType, int $userId): bool
    {
        $this->db->prepare(
            "DELETE FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid"
        )->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);

        return true;
    }

    public function isRegistered(int $seminarId, string $userType, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM seminar_registrations WHERE seminar_id = :sid AND user_type = :ut AND user_id = :uid LIMIT 1"
        );
        $stmt->execute([':sid' => $seminarId, ':ut' => $userType, ':uid' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    public function registrationCount(int $seminarId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id = :sid");
        $stmt->execute([':sid' => $seminarId]);

        return (int) $stmt->fetchColumn();
    }
}
