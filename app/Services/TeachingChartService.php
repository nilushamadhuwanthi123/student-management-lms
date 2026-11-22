<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The two questions a teacher asks about a quiz once it has closed.
 *
 *   1. Did they actually sit it?  A quiz everyone skipped is not a hard
 *      quiz, it is a quiz nobody saw -- and the average says nothing.
 *   2. How spread out were the marks, grade by grade? An average of 60
 *      can be a class that all scored 60, or half at 95 and half at 25.
 *      Those are opposite problems and the mean hides both.
 *
 * Both are SQL, not PHP loops: a teacher with twelve classes should not
 * wait on twelve round trips, and the numbers belong next to the data.
 *
 * Everything is scoped by the classes passed in, so this can never show
 * a teacher another teacher's results. It returns empty arrays rather
 * than throwing -- a chart that cannot be drawn must not take the
 * teaching page down with it.
 */
final class TeachingChartService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * Per published quiz: how many were enrolled, how many sat it.
     *
     * "Enrolled" is counted at the class, not from the attempts table,
     * because the students who matter most are the ones with no row in
     * it at all.
     *
     * @param list<int> $classIds
     * @return list<array{quiz: string, class: string, grade: string, enrolled: int, sat: int, graded: int, rate: float}>
     */
    public function participation(array $classIds, string $month, int $limit = 12): array
    {
        $classIds = array_values(array_filter(array_map('intval', $classIds)));
        if ($classIds === []) {
            return [];
        }
        $in = implode(',', $classIds);

        try {
            $sql = "SELECT q.id, q.title, c.class_name, c.grade,
                           (SELECT COUNT(*) FROM enrollments e
                             WHERE e.class_id = q.class_id
                               AND e.enrollment_status = 'active') AS enrolled,
                           (SELECT COUNT(DISTINCT a.student_id) FROM quiz_attempts a
                             WHERE a.quiz_id = q.id
                               AND a.attempt_status <> 'in_progress') AS sat,
                           (SELECT COUNT(DISTINCT a.student_id) FROM quiz_attempts a
                             WHERE a.quiz_id = q.id
                               AND a.attempt_status = 'graded') AS graded
                      FROM quizzes q
                      JOIN classes c ON c.id = q.class_id
                     WHERE q.class_id IN ({$in})
                       AND q.quiz_status IN ('published', 'closed')
                       AND q.month_year = :month
                  ORDER BY q.opens_at DESC
                     LIMIT {$limit}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':month' => $month]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('Quiz participation chart failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $enrolled = (int) $r['enrolled'];
            $sat = (int) $r['sat'];
            $out[] = [
                'quiz'     => (string) $r['title'],
                'class'    => (string) $r['class_name'],
                // grade is free text in this database ("Grade 10", and on
                // one row "A/L"), not a number. Casting it to int turned
                // every class into "Grade 0" on screen.
                'grade'    => trim((string) ($r['grade'] ?? '')),
                'enrolled' => $enrolled,
                'sat'      => $sat,
                'graded'   => (int) $r['graded'],
                'rate'     => $enrolled > 0 ? round($sat / $enrolled * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Marks by grade: the spread, not just the average.
     *
     * Returns the lowest, the average and the highest, plus how many
     * fall in each band, so a teacher can see at a glance whether a
     * grade is together or split in two.
     *
     * Only graded attempts count. A paper still waiting to be marked has
     * no score worth plotting, and counting it as zero would make a
     * grade look like it failed because the teacher is behind.
     *
     * @param list<int> $classIds
     * @return list<array{grade: string, sat: int, lowest: float, average: float, highest: float, bands: array<string, int>}>
     */
    public function marksByGrade(array $classIds, string $month): array
    {
        $classIds = array_values(array_filter(array_map('intval', $classIds)));
        if ($classIds === []) {
            return [];
        }
        $in = implode(',', $classIds);

        try {
            $sql = "SELECT c.grade,
                           COUNT(*)            AS sat,
                           MIN(a.percentage)   AS lowest,
                           AVG(a.percentage)   AS average,
                           MAX(a.percentage)   AS highest,
                           SUM(a.percentage >= 75)                        AS band_top,
                           SUM(a.percentage >= 50 AND a.percentage < 75)  AS band_mid,
                           SUM(a.percentage >= 35 AND a.percentage < 50)  AS band_low,
                           SUM(a.percentage < 35)                         AS band_bottom
                      FROM quiz_attempts a
                      JOIN quizzes q ON q.id = a.quiz_id
                      JOIN classes c ON c.id = q.class_id
                     WHERE q.class_id IN ({$in})
                       AND q.month_year = :month
                       AND a.attempt_status = 'graded'
                       AND a.percentage IS NOT NULL
                  GROUP BY c.grade
                  ORDER BY c.grade ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':month' => $month]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('Marks-by-grade chart failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'grade'   => trim((string) ($r['grade'] ?? '')),
                'sat'     => (int) $r['sat'],
                'lowest'  => round((float) $r['lowest'], 1),
                'average' => round((float) $r['average'], 1),
                'highest' => round((float) $r['highest'], 1),
                'bands'   => [
                    'top'    => (int) $r['band_top'],
                    'mid'    => (int) $r['band_mid'],
                    'low'    => (int) $r['band_low'],
                    'bottom' => (int) $r['band_bottom'],
                ],
            ];
        }

        return $out;
    }
}
