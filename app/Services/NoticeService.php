<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Notice board rules, shared by the web pages and the scheduler.
 *
 * A notice reaches: everyone (super admin only), one branch, or one
 * class -- and within that, students, parents and/or staff as ticked.
 * A future publish_at keeps a published notice hidden, and unannounced,
 * until that time.
 */
class NoticeService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * Live notices a student (or a parent, about that student) should see.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId, bool $asParent = false, int $limit = 50): array
    {
        $flag = $asParent ? 'n.for_parents' : 'n.for_students';
        $stmt = $this->db->prepare(
            "SELECT n.*, c.class_name, i.name AS institute_name
               FROM notices n
               LEFT JOIN classes c ON c.id = n.class_id
               LEFT JOIN institutes i ON i.id = n.institute_id
              WHERE n.status = 'published'
                AND (n.publish_at IS NULL OR n.publish_at <= NOW())
                AND {$flag} = 1
                AND (n.audience = 'all'
                  OR (n.audience = 'class' AND n.class_id IN
                        (SELECT e.class_id FROM enrollments e WHERE e.student_id = :s1 AND e.enrollment_status = 'active'))
                  OR (n.audience = 'branch' AND n.institute_id IN
                        (SELECT c2.institute_id FROM enrollments e2 JOIN classes c2 ON c2.id = e2.class_id
                          WHERE e2.student_id = :s2 AND e2.enrollment_status = 'active')))
              ORDER BY COALESCE(n.publish_at, n.created_at) DESC
              LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute([':s1' => $studentId, ':s2' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Announce every published notice whose time has come and that has
     * not been announced yet. Safe to run repeatedly: notified_at is set
     * with a guarded UPDATE before anyone is told.
     */
    public function announceDue(): int
    {
        $ids = $this->db->query(
            "SELECT id FROM notices
              WHERE status = 'published' AND notified_at IS NULL
                AND (publish_at IS NULL OR publish_at <= NOW())"
        )->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($ids as $id) {
            $count += $this->announce((int) $id);
        }

        return $count;
    }

    /** @return int notifications created */
    public function announce(int $noticeId): int
    {
        $claim = $this->db->prepare(
            "UPDATE notices SET notified_at = NOW()
              WHERE id = :id AND notified_at IS NULL AND status = 'published'
                AND (publish_at IS NULL OR publish_at <= NOW())"
        );
        $claim->execute([':id' => $noticeId]);
        if ($claim->rowCount() === 0) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT * FROM notices WHERE id = :id");
        $stmt->execute([':id' => $noticeId]);
        $n = $stmt->fetch(PDO::FETCH_ASSOC);

        $classId = $n['audience'] === 'class' ? (int) $n['class_id'] : null;
        $instituteId = $n['audience'] === 'branch' ? (int) $n['institute_id'] : null;

        $ann = new AnnouncementService();
        $recipients = $ann->familiesFor($classId, $instituteId, (bool) $n['for_students'], (bool) $n['for_parents']);

        if ((int) $n['for_staff'] === 1) {
            if ($classId !== null) {
                $s = $this->db->prepare("SELECT teacher_id FROM classes WHERE id = :c AND teacher_id IS NOT NULL");
                $s->execute([':c' => $classId]);
                $staff = $s->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($instituteId !== null) {
                $s = $this->db->prepare(
                    "SELECT user_id FROM user_institutes WHERE institute_id = :i
                     UNION SELECT teacher_id FROM classes WHERE institute_id = :i2 AND teacher_id IS NOT NULL"
                );
                $s->execute([':i' => $instituteId, ':i2' => $instituteId]);
                $staff = $s->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $staff = $this->db->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            }
            foreach (array_unique(array_map('intval', $staff)) as $uid) {
                $recipients[] = ['type' => 'user', 'id' => $uid];
            }
        }

        $sent = $ann->notifyAll($recipients, 'Notice: ' . $n['title'], mb_substr((string) $n['body'], 0, 300), 'notice', $noticeId);
        $this->notifyMentions($ann, (string) $n['body'], $noticeId, (string) $n['title'], (int) $n['created_by']);

        return $sent;
    }

    /**
     * "@Full Name" inside a notice's body notifies that colleague
     * personally, on top of whoever the notice's audience already
     * reaches -- through the same notifyAll() path, one extra row per
     * mention. Runs exactly once, at the same moment the notice itself
     * is actually announced (immediately, or later via announceDue() for
     * a scheduled one) -- never for a draft, and never twice.
     */
    private function notifyMentions(AnnouncementService $ann, string $body, int $noticeId, string $title, int $authorUserId): void
    {
        $ids = (new MentionService($this->db))->mentionedStaffIds($body, $authorUserId);
        if ($ids === []) {
            return;
        }
        $ann->notifyAll(
            array_map(static fn (int $id): array => ['type' => 'user', 'id' => $id], $ids),
            t('You were mentioned') . ': ' . $title,
            mb_substr($body, 0, 200),
            'notice',
            $noticeId
        );
    }
}
