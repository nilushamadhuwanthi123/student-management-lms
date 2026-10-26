<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

/**
 * What keeps a twelve-year-old coming back: seeing that they are getting
 * somewhere, and knowing exactly what the next step is.
 *
 * Every figure here comes from the student's own record. There are no
 * invented points, no leaderboard and no comparison with classmates --
 * a child in the bottom third of a class does not need telling, and the
 * one at the top does not need it either. The comparison that motivates
 * is with themselves last month.
 *
 * Achievements that are not earned yet are returned too, with how to
 * earn them, because a locked badge with a clear route is the part that
 * makes a student want to try. The view decides the wording; this class
 * returns keys and numbers so both languages read naturally.
 */
class MotivationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * @param array<string, mixed> $progress ProgressService::forStudent() for the same student
     * @return array<string, mixed>
     */
    public function forStudent(int $studentId, array $progress): array
    {
        $history = $this->history($studentId);
        $streak = $this->streak($studentId);

        return [
            'next_level' => $this->nextLevel($progress),
            'trend'      => $this->trend($history),
            'history'    => array_slice($history, -12),
            'best'       => $history === [] ? null : max(array_column($history, 'percentage')),
            'streak'     => $streak,
            // Every achievement, earned or not, with the date it was first
            // earned (kept in student_achievements, so it never moves).
            'badges'     => (new AchievementService($this->db))->forStudent($studentId, $progress),
            'goals'      => $this->goals($studentId, $progress),
            // Named level from the same official score (never a school grade).
            'level'      => LevelService::forScore(!empty($progress['has_data']) ? (float) $progress['score'] : null) + ['has_data' => !empty($progress['has_data'])],
            // Days with real learning activity; one rest day does not break it.
            'learning_streak' => (new StreakService($this->db))->summary($studentId),
            // Goals the student or a teacher set (migration 015).
            'personal_goals'  => $this->personalGoals($studentId),
            'headline'   => $this->headline($progress, $this->trend($history)),
        ];
    }

    /**
     * Graded attempts, oldest first, with what is needed to judge them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function history(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.percentage, a.submitted_at, q.title, q.opens_at, q.month_year,
                    COALESCE(q.pass_mark, 50) AS pass_mark, c.class_name
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :sid
                AND a.attempt_status = 'graded'
                AND a.percentage IS NOT NULL
              ORDER BY a.submitted_at ASC, a.id ASC"
        );
        $stmt->execute([':sid' => $studentId]);

        return array_map(static function (array $r): array {
            $r['percentage'] = (float) $r['percentage'];
            $r['pass_mark'] = (float) $r['pass_mark'];
            return $r;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * How far to the next level, from the same thresholds that set it.
     *
     * @param array<string, mixed> $progress
     * @return array<string, mixed>|null null when already at the top or with no data yet
     */
    private function nextLevel(array $progress): ?array
    {
        if (empty($progress['has_data'])) {
            return null;
        }

        // The student-facing level names (LevelService) share their lower
        // bands with ProgressService's labels, so both always agree on
        // where a student is; they only add a top band above 95.
        $l = LevelService::forScore((float) $progress['score']);
        if ($l['next_level'] === null) {
            return null;
        }
        return [
            'label'   => $l['next_name'],
            'at'      => $l['next_at'],
            'to_go'   => (int) ceil($l['next_at'] - (float) $progress['score']),
            'percent' => $l['band_progress'],
        ];
    }

    /**
     * The two most recent months with marks, compared.
     *
     * The latest two months that have any, not strictly this month and
     * last: early in a month there are no marks yet, and "no change"
     * would be less true than "here is how your last two months went".
     *
     * @param array<int, array<string, mixed>> $history
     * @return array<string, mixed>|null
     */
    private function trend(array $history): ?array
    {
        $byMonth = [];
        foreach ($history as $h) {
            $byMonth[(string) $h['month_year']][] = $h['percentage'];
        }

        if (count($byMonth) < 2) {
            return null;
        }

        ksort($byMonth);
        $months = array_keys($byMonth);
        $recent = array_pop($months);
        $previous = array_pop($months);

        $recentAvg = array_sum($byMonth[$recent]) / count($byMonth[$recent]);
        $previousAvg = array_sum($byMonth[$previous]) / count($byMonth[$previous]);

        return [
            'recent_month'   => $recent,
            'previous_month' => $previous,
            'recent'         => round($recentAvg),
            'previous'       => round($previousAvg),
            'delta'          => (int) round($recentAvg - $previousAvg),
        ];
    }

    /**
     * Classes attended in a row, now and at best.
     *
     * Late still counts as there -- turning up late is turning up, and a
     * streak that a traffic jam can break teaches the wrong lesson. Only
     * an absence breaks it.
     *
     * @return array{current: int, best: int, sessions: int}
     */
    private function streak(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT status FROM attendance WHERE student_id = :sid ORDER BY date ASC, id ASC"
        );
        $stmt->execute([':sid' => $studentId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $run = 0;
        $best = 0;
        foreach ($statuses as $status) {
            if ($status === 'absent') {
                $run = 0;
                continue;
            }
            $run++;
            $best = max($best, $run);
        }

        return ['current' => $run, 'best' => $best, 'sessions' => count($statuses)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function personalGoals(int $studentId): array
    {
        try {
            return (new GoalService($this->db))->forStudent($studentId);
        } catch (\Throwable $e) {
            error_log('Personal goals unavailable: ' . $e->getMessage()); // before migration 015
            return [];
        }
    }

    /**
     * Up to three concrete things to do next, most urgent first.
     *
     * "Work harder" motivates nobody. "Sit Plants Around Us before
     * Sunday" does, because it can be done tonight.
     *
     * @param array<string, mixed> $progress
     * @return array<int, array<string, mixed>>
     */
    private function goals(int $studentId, array $progress): array
    {
        $goals = [];
        $now = (string) $this->db->query('SELECT NOW()')->fetchColumn();
        $month = ContentAccessService::normaliseMonth(substr($now, 0, 10));
        $openMap = (new ContentAccessService())->openClassMap($studentId, $month);

        // 1. A quiz that is open now and not yet sat.
        $stmt = $this->db->prepare(
            "SELECT q.id, q.title, q.class_id, q.closes_at
               FROM quizzes q
               JOIN enrollments e ON e.class_id = q.class_id AND e.student_id = :sid1 AND e.enrollment_status = 'active'
              WHERE q.quiz_status = 'published' AND q.opens_at <= :n1 AND q.closes_at > :n2
                AND NOT EXISTS (SELECT 1 FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.student_id = :sid2
                                  AND a.attempt_status IN ('submitted', 'graded'))
              ORDER BY q.closes_at"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId, ':n1' => $now, ':n2' => $now]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
            if ($openMap[(int) $q['class_id']] ?? false) {
                $goals[] = ['key' => 'sit_quiz', 'vars' => ['title' => $q['title'], 'when' => $q['closes_at']],
                            'href' => '/my/quizzes/' . (int) $q['id'] . '/sit'];
                break;
            }
        }

        // 2. Material posted and not opened, in classes that are open.
        $stmt = $this->db->prepare(
            "SELECT m.class_id, COUNT(*) AS n
               FROM class_materials m
               JOIN enrollments e ON e.class_id = m.class_id AND e.student_id = :sid1 AND e.enrollment_status = 'active'
              WHERE m.status = 'published'
                AND NOT EXISTS (SELECT 1 FROM material_views v WHERE v.material_id = m.id AND v.student_id = :sid2)
              GROUP BY m.class_id"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId]);
        $unopened = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($openMap[(int) $row['class_id']] ?? false) {
                $unopened += (int) $row['n'];
            }
        }
        if ($unopened > 0) {
            $goals[] = ['key' => 'open_material', 'vars' => ['n' => $unopened], 'href' => '/my/classes'];
        }

        // 3. The quickest route up a level: the weakest of the three parts.
        $next = $this->nextLevel($progress);
        if ($next !== null) {
            $parts = [];
            if (($progress['marks']['quizzes_taken'] ?? 0) > 0) {
                $parts['marks'] = (float) $progress['marks']['average_percentage'];
            }
            if (($progress['attendance']['sessions_recorded'] ?? 0) > 0) {
                $parts['attendance'] = (float) $progress['attendance']['rate'];
            }
            if (($progress['engagement']['materials_total'] ?? 0) > 0) {
                $parts['material'] = (float) $progress['engagement']['rate'];
            }
            if ($parts !== []) {
                asort($parts);
                $goals[] = ['key' => 'level_up', 'vars' => ['level' => $next['label'], 'points' => $next['to_go'],
                            'part' => array_key_first($parts)], 'href' => null];
            }
        } elseif (!empty($progress['has_data'])) {
            $goals[] = ['key' => 'stay_top', 'vars' => [], 'href' => null];
        }

        return array_slice($goals, 0, 3);
    }

    /**
     * The one line at the top of the page. Chosen from what is true,
     * never a generic "Great job!" when it is not.
     *
     * @param array<string, mixed> $progress
     * @param array<string, mixed>|null $trend
     * @return array{key: string, vars: array<string, mixed>}
     */
    private function headline(array $progress, ?array $trend): array
    {
        if (empty($progress['has_data'])) {
            return ['key' => 'start', 'vars' => []];
        }
        if ($trend !== null && $trend['delta'] >= 3) {
            return ['key' => 'rising', 'vars' => ['delta' => $trend['delta'], 'month' => $trend['previous_month']]];
        }
        if ($trend !== null && $trend['delta'] <= -3) {
            return ['key' => 'dip', 'vars' => ['delta' => abs($trend['delta'])]];
        }
        if (LevelService::forScore((float) $progress['score'])['next_level'] === null) {
            return ['key' => 'top', 'vars' => []];
        }
        return ['key' => 'steady', 'vars' => []];
    }
}
