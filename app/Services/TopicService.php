<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Syllabus topics. A topic belongs to a grade and subject (not to one branch):
 * the same Grade 8 unit is taught in every branch.
 */
final class TopicService
{
    /** Tables that may carry a topic_id, and nothing else. */
    private const TAGGABLE = ['class_materials', 'quizzes', 'quiz_questions', 'assignments', 'recordings'];

    private PDO $db;

    /** @var array<int, ?array<string, mixed>> */
    private array $classCache = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /** "Grade 8" -> 8; anything without a number -> null. */
    public static function gradeNumber(?string $grade): ?int
    {
        if ($grade === null || preg_match('/(\d{1,2})/', $grade, $m) !== 1) {
            return null;
        }
        $n = (int) $m[1];
        return $n >= 1 && $n <= 13 ? $n : null;
    }

    /** @return array{grade: ?int, subject: string}|null */
    public function classKey(int $classId): ?array
    {
        if (!array_key_exists($classId, $this->classCache)) {
            $stmt = $this->db->prepare("SELECT grade, subject FROM classes WHERE id = :id");
            $stmt->execute([':id' => $classId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->classCache[$classId] = $row === false ? null
                : ['grade' => self::gradeNumber($row['grade']), 'subject' => (string) $row['subject']];
        }
        return $this->classCache[$classId];
    }

    /** @return list<array<string, mixed>> */
    public function forGrade(int $grade, string $subject = 'Science', bool $withArchived = false): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM topics WHERE grade = :g AND subject = :s" . ($withArchived ? '' : " AND status = 'active'") . "
              ORDER BY sort_order, unit_no IS NULL, unit_no, title"
        );
        $stmt->execute([':g' => $grade, ':s' => $subject]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Active topics that fit a class (its grade and subject). @return list<array<string, mixed>> */
    public function forClass(int $classId): array
    {
        $key = $this->classKey($classId);
        if ($key === null || $key['grade'] === null) {
            return [];
        }
        return $this->forGrade($key['grade'], $key['subject']);
    }

    /** The topic id if it really belongs to this class's grade and subject, else null. */
    public function validForClass(int $classId, mixed $topicId): ?int
    {
        $topicId = (int) $topicId;
        if ($topicId <= 0) {
            return null;
        }
        foreach ($this->forClass($classId) as $t) {
            if ((int) $t['id'] === $topicId) {
                return $topicId;
            }
        }
        return null;
    }

    /** Set (or clear) the topic on a material, quiz, question or homework of this class. */
    public function tag(string $table, int $rowId, int $classId, mixed $topicId): void
    {
        if (!in_array($table, self::TAGGABLE, true) || $rowId <= 0) {
            return;
        }
        $this->db->prepare("UPDATE $table SET topic_id = :t WHERE id = :id")
            ->execute([':t' => $this->validForClass($classId, $topicId), ':id' => $rowId]);
    }

    public static function label(array $topic): string
    {
        $title = (string) $topic['title'];
        if (function_exists('lang_current') && lang_current() === 'si' && !empty($topic['title_si'])) {
            $title = (string) $topic['title_si'];
        }
        return ($topic['unit_no'] !== null && $topic['unit_no'] !== '' ? $topic['unit_no'] . '. ' : '') . $title;
    }
}
