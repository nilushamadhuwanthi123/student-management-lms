<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Tells a class's families when something new has been put up for them.
 *
 * The teacher's complaint about every system like this is the same: she
 * uploads Saturday's recording and nobody watches it, because nobody
 * knew it was there. So publishing material or a quiz raises a
 * notification to every enrolled student and to their guardians.
 *
 * The teacher herself is not told -- she is the one who just pressed
 * the button, and a system that congratulates you on your own actions
 * teaches people to ignore its notifications.
 *
 * Draft material raises nothing. A draft is work in progress, and the
 * announcement is deliberately tied to publishing rather than to saving
 * so the teacher can upload a half-finished set on Friday without
 * waking anyone.
 *
 * Nothing here consults the fee gate. Knowing that this week's recording
 * exists is not the recording; a student behind on the fee is told it is
 * up, and meets the paywall when they open it. Hiding its existence
 * would just make the unlock prompt arrive in a vacuum.
 */
class AnnouncementService
{
    private PDO $db;
    private ReminderService $reminders;

    public function __construct()
    {
        $this->db = \Database::connect();
        $this->reminders = new ReminderService();
    }

    /**
     * Announce a newly published material to its class.
     *
     * @return array{sent: int, skipped: int}
     */
    public function materialPublished(int $materialId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.class_id, m.title, m.material_type, m.status,
                    m.lesson_date, m.duration_minutes, m.created_at,
                    c.class_name
               FROM class_materials m
               JOIN classes c ON c.id = m.class_id
              WHERE m.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $materialId]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($material === false || $material['status'] !== 'published') {
            return ['sent' => 0, 'skipped' => 0];
        }

        $kind = $this->materialLabel($material['material_type']);

        $detail = [];
        if (!empty($material['lesson_date'])) {
            $detail[] = date('j F', strtotime($material['lesson_date'])) . ' lesson';
        }
        if (!empty($material['duration_minutes'])) {
            $detail[] = $material['duration_minutes'] . ' min';
        }

        $body = $material['class_name'] . ' - ' . $material['title']
            . ($detail !== [] ? ' (' . implode(', ', $detail) . ')' : '')
            . '. Open it from your class page.';

        return $this->reminders->notifyOnce(
            'material_published',
            $materialId,
            (string) $material['created_at'],
            0,
            $this->familiesOf((int) $material['class_id']),
            "New {$kind}: {$material['title']}",
            $body
        );
    }

    /**
     * Announce a newly published class recording to its class.
     *
     * Same one-shot rule as material and quizzes: only a published
     * recording announces, and only once, however many times the
     * publish action is retried.
     *
     * @return array{sent: int, skipped: int}
     */
    public function recordingPublished(int $recordingId): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.id, r.class_id, r.title, r.status, r.recording_date, r.created_at,
                    c.class_name
               FROM recordings r
               JOIN classes c ON c.id = r.class_id
              WHERE r.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $recordingId]);
        $recording = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recording === false || $recording['status'] !== 'published') {
            return ['sent' => 0, 'skipped' => 0];
        }

        $detail = !empty($recording['recording_date'])
            ? date('j F', strtotime($recording['recording_date'])) . ' lesson'
            : null;

        $body = $recording['class_name'] . ' - ' . $recording['title']
            . ($detail !== null ? " ({$detail})" : '')
            . '. Open it from Class Recordings.';

        return $this->reminders->notifyOnce(
            'recording_published',
            $recordingId,
            (string) $recording['created_at'],
            0,
            $this->familiesOf((int) $recording['class_id']),
            "New recording: {$recording['title']}",
            $body
        );
    }

    /**
     * Announce a newly published quiz to its class.
     *
     * The closing time is in the body because that is the part people
     * act on. "A quiz is up" with no deadline gets read and forgotten.
     *
     * @return array{sent: int, skipped: int}
     */
    public function quizPublished(int $quizId): array
    {
        $stmt = $this->db->prepare(
            "SELECT q.id, q.class_id, q.title, q.opens_at, q.closes_at,
                    q.time_limit_minutes, q.quiz_status, q.created_at,
                    c.class_name
               FROM quizzes q
               JOIN classes c ON c.id = q.class_id
              WHERE q.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $quizId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($quiz === false || $quiz['quiz_status'] !== 'published') {
            return ['sent' => 0, 'skipped' => 0];
        }

        $body = $quiz['class_name'] . ' - opens '
            . date('j M, g:ia', strtotime((string) $quiz['opens_at']))
            . ', closes ' . date('j M, g:ia', strtotime((string) $quiz['closes_at']));

        if (!empty($quiz['time_limit_minutes'])) {
            $body .= '. You get ' . $quiz['time_limit_minutes'] . ' minutes once you start';
        }

        return $this->reminders->notifyOnce(
            'quiz_published',
            $quizId,
            (string) $quiz['created_at'],
            0,
            $this->familiesOf((int) $quiz['class_id']),
            "New quiz: {$quiz['title']}",
            $body . '.'
        );
    }

    /**
     * The students in a class and the guardians attached to them.
     *
     * @return array<int, array{type: string, id: int}>
     */
    private function familiesOf(int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id
               FROM enrollments e
               JOIN students s ON s.id = e.student_id AND s.status = 'active'
              WHERE e.class_id = :class_id
                AND e.enrollment_status = 'active'"
        );
        $stmt->execute([':class_id' => $classId]);
        $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($studentIds === []) {
            return [];
        }

        $recipients = [];

        foreach ($studentIds as $studentId) {
            $recipients[] = ['type' => 'student', 'id' => $studentId];
        }

        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id FROM parents WHERE student_id IN ({$placeholders}) AND status = 'active'"
        );
        $stmt->execute($studentIds);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentId) {
            $recipients[] = ['type' => 'parent', 'id' => (int) $parentId];
        }

        return $recipients;
    }

    // ------------------------------------------------------------------
    // Used by homework and the notice board
    // ------------------------------------------------------------------

    /**
     * Students (and their guardians) of one class, one branch, or every
     * branch. The same people the class announcements above reach.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public function familiesFor(?int $classId, ?int $instituteId, bool $students = true, bool $parents = true): array
    {
        if ($classId !== null) {
            $where = 'e.class_id = :id';
            $param = [':id' => $classId];
        } elseif ($instituteId !== null) {
            $where = 'c.institute_id = :id';
            $param = [':id' => $instituteId];
        } else {
            $where = '1 = 1';
            $param = [];
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT s.id
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id AND c.status = 'active'
               JOIN students s ON s.id = e.student_id AND s.status = 'active'
              WHERE e.enrollment_status = 'active' AND {$where}"
        );
        $stmt->execute($param);
        $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $out = [];
        if ($students) {
            foreach ($studentIds as $id) {
                $out[] = ['type' => 'student', 'id' => $id];
            }
        }
        if ($parents && $studentIds !== []) {
            $in = implode(',', $studentIds);
            foreach ($this->db->query("SELECT id FROM parents WHERE status = 'active' AND student_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $out[] = ['type' => 'parent', 'id' => (int) $pid];
            }
        }

        return $out;
    }

    /**
     * Families of every student in one grade -- across every branch
     * when $instituteId is null, or narrowed to just that branch when
     * it is set. The one targeting shape familiesFor() cannot already
     * do, so seminars get their own small method instead of overloading
     * that one's parameters.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public function familiesForGrade(string $grade, ?int $instituteId, bool $students = true, bool $parents = true): array
    {
        $where = 'c.grade = :grade';
        $param = [':grade' => $grade];
        if ($instituteId !== null) {
            $where .= ' AND c.institute_id = :iid';
            $param[':iid'] = $instituteId;
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT s.id
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id AND c.status = 'active'
               JOIN students s ON s.id = e.student_id AND s.status = 'active'
              WHERE e.enrollment_status = 'active' AND {$where}"
        );
        $stmt->execute($param);
        $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $out = [];
        if ($students) {
            foreach ($studentIds as $id) {
                $out[] = ['type' => 'student', 'id' => $id];
            }
        }
        if ($parents && $studentIds !== []) {
            $in = implode(',', $studentIds);
            foreach ($this->db->query("SELECT id FROM parents WHERE status = 'active' AND student_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $out[] = ['type' => 'parent', 'id' => (int) $pid];
            }
        }

        return $out;
    }

    /**
     * One in-app notification to each recipient. Callers guard against
     * sending twice (each notice/assignment records when it announced).
     *
     * @param array<int, array{type: string, id: int}> $recipients
     */
    public function notifyAll(array $recipients, string $title, string $body, string $entityType, int $entityId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (recipient_id, recipient_type, title, body, notification_type, is_read, related_entity_type, related_entity_id, created_at)
             VALUES (:rid, :rtype, :title, :body, 'info', 0, :etype, :eid, NOW())"
        );
        $n = 0;
        foreach ($recipients as $r) {
            $stmt->execute([
                ':rid' => $r['id'], ':rtype' => $r['type'], ':title' => mb_substr($title, 0, 500),
                ':body' => $body, ':etype' => $entityType, ':eid' => $entityId,
            ]);
            $n++;
        }

        return $n;
    }

    private function materialLabel(string $type): string
    {
        return match ($type) {
            'recording' => 'class recording',
            'reading'   => 'reading material',
            'video'     => 'video',
            'link'      => 'resource',
            default     => 'material',
        };
    }
}
