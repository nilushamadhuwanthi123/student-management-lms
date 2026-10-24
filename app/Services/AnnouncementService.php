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
