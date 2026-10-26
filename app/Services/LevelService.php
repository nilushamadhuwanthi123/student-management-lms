<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Learning levels, read straight from the official progress score (0-100)
 * that ProgressService already calculates. No separate points are invented:
 * the level is simply a friendly name for a band of that score, and the
 * "points to next level" is the gap to the next band.
 *
 * The first five lower bounds are the same as ProgressService::LEVELS
 * (0/40/55/70/85), so a teacher's label and the student's level never
 * disagree; Science Champion adds a top band at 95.
 *
 * These levels are for encouragement inside this class only. They are not
 * school grades and say nothing official about a student.
 */
final class LevelService
{
    /** level => [minimum score, English name] */
    public const LEVELS = [
        1 => [0,  'Starter'],
        2 => [40, 'Explorer'],
        3 => [55, 'Learner'],
        4 => [70, 'Achiever'],
        5 => [85, 'Science Star'],
        6 => [95, 'Science Champion'],
    ];

    /**
     * @return array{level: int, name: string, score: int, floor: int,
     *               next_level: ?int, next_name: ?string, next_at: ?int,
     *               points_to_next: int, band_progress: int}
     */
    public static function forScore(?float $score): array
    {
        $score = (int) round(max(0, min(100, (float) ($score ?? 0))));
        $level = 1;
        foreach (self::LEVELS as $n => [$min]) {
            if ($score >= $min) {
                $level = $n;
            }
        }
        $floor = self::LEVELS[$level][0];
        $next = self::LEVELS[$level + 1] ?? null;
        $nextAt = $next[0] ?? null;

        return [
            'level'          => $level,
            'name'           => self::LEVELS[$level][1],
            'score'          => $score,
            'floor'          => $floor,
            'next_level'     => $next ? $level + 1 : null,
            'next_name'      => $next[1] ?? null,
            'next_at'        => $nextAt,
            'points_to_next' => $nextAt !== null ? max(0, $nextAt - $score) : 0,
            // How far through the current band (0-100), for a small bar.
            'band_progress'  => $nextAt !== null && $nextAt > $floor
                ? (int) round(($score - $floor) / ($nextAt - $floor) * 100) : 100,
        ];
    }
}
