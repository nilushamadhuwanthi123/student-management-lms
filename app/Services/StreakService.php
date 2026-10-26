<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Learning streak: days on which the student actually learned something.
 *
 * A learning day is a day with at least one of: a class attended (present or
 * late), a quiz handed in, homework handed in, or class material opened for
 * the first time that day. Reloading a page does not count.
 *
 * Fair to holidays: one rest day between learning days does not break the
 * streak (classes are weekly, and school holidays happen), so the streak
 * counts learning days in a row allowing a single empty day between them.
 */
final class StreakService
{
    public const MAX_GAP_DAYS = 2; // learning on Mon and Wed keeps the streak

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /** @return list<string> distinct Y-m-d learning days, oldest first */
    public function learningDays(int $studentId, int $lookbackDays = 400): array
    {
        $since = date('Y-m-d', strtotime("-{$lookbackDays} days"));
        $parts = [
            "SELECT DATE(submitted_at) d FROM quiz_attempts WHERE student_id = :s1 AND submitted_at >= :f1
                AND attempt_status IN ('submitted','auto_submitted','graded')",
            "SELECT DATE(submitted_at) d FROM assignment_submissions WHERE student_id = :s2 AND submitted_at >= :f2",
            "SELECT date d FROM attendance WHERE student_id = :s3 AND date >= :f3 AND status IN ('present','late')",
        ];
        $params = [':s1' => $studentId, ':s2' => $studentId, ':s3' => $studentId, ':f1' => $since, ':f2' => $since, ':f3' => $since];

        $viewCol = self::materialViewDateColumn($this->db);
        if ($viewCol !== null) {
            $parts[] = "SELECT DATE($viewCol) d FROM material_views WHERE student_id = :s4 AND $viewCol >= :f4";
            $params[':s4'] = $studentId;
            $params[':f4'] = $since;
        }

        $stmt = $this->db->prepare("SELECT DISTINCT d FROM (" . implode(' UNION ALL ', $parts) . ") x WHERE d IS NOT NULL ORDER BY d");
        $stmt->execute($params);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array{current: int, longest: int, last_day: ?string, alive: bool, next_milestone: int, to_next: int}
     */
    public function summary(int $studentId, ?string $today = null): array
    {
        return self::fromDays($this->learningDays($studentId), $today ?? date('Y-m-d'));
    }

    /**
     * Pure calculation, so it can be tested without a database.
     *
     * @param list<string> $days sorted Y-m-d
     * @return array{current: int, longest: int, last_day: ?string, alive: bool, next_milestone: int, to_next: int}
     */
    public static function fromDays(array $days, string $today): array
    {
        $days = array_values(array_unique($days));
        sort($days);
        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($days as $d) {
            if ($d > $today) {
                continue;
            }
            $run = ($prev !== null && self::gap($prev, $d) <= self::MAX_GAP_DAYS) ? $run + 1 : 1;
            $longest = max($longest, $run);
            $prev = $d;
        }
        $alive = $prev !== null && self::gap($prev, $today) <= self::MAX_GAP_DAYS;
        $current = $alive ? $run : 0;

        $next = 3;
        foreach ([3, 7, 14, 30, 60, 100] as $m) {
            if ($current < $m) {
                $next = $m;
                break;
            }
            $next = $m * 2;
        }

        return [
            'current'        => $current,
            'longest'        => $longest,
            'last_day'       => $prev,
            'alive'          => $alive,
            'next_milestone' => $next,
            'to_next'        => max(0, $next - $current),
        ];
    }

    private static function gap(string $a, string $b): int
    {
        return (int) round((strtotime($b . ' 12:00:00') - strtotime($a . ' 12:00:00')) / 86400);
    }

    /** material_views has existed for a while; find its timestamp column rather than guess. */
    public static function materialViewDateColumn(PDO $db): ?string
    {
        static $col = false;
        if ($col !== false) {
            return $col;
        }
        try {
            $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'material_views'")->fetchAll(PDO::FETCH_COLUMN);
            foreach (['viewed_at', 'opened_at', 'first_viewed_at', 'last_viewed_at', 'created_at'] as $c) {
                if (in_array($c, $cols, true) && in_array('student_id', $cols, true)) {
                    return $col = $c;
                }
            }
        } catch (\Throwable $e) {
            error_log('material_views not readable: ' . $e->getMessage());
        }
        return $col = null;
    }
}
