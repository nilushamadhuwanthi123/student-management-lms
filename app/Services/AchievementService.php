<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Achievements, worked out from the student's real records only.
 *
 * Each achievement has a rule, a target and a progress figure. When a rule
 * first becomes true the date is remembered in student_achievements (the
 * date of the event itself where the records show it), so an earned date
 * never changes afterwards. Achievements are private: only the student,
 * their parent and their teachers see them.
 */
final class AchievementService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * code => [title, what was done, how to earn it, icon]. English; the view
     * translates. The first eight keys (first_quiz, high_flyer, on_a_roll,
     * comeback, early_bird, never_missed, bookworm, perfect) are the badges
     * the progress page already showed -- same keys, same wording, so the
     * existing translations still apply.
     */
    public const DEFS = [
        'first_quiz'     => ['First step', 'Sat your first quiz.', 'Sit your first quiz.', '&#127891;'],
        'quiz_explorer'  => ['Quiz Explorer', 'Handed in 5 quizzes.', 'Hand in 5 quizzes.', '&#129517;'],
        'quiz_master'    => ['Quiz Master', 'Handed in 20 quizzes.', 'Hand in 20 quizzes.', '&#127941;'],
        'high_flyer'     => ['High flyer', 'Scored 80% or more in a quiz.', 'Score 80% or more in any quiz.', '&#128640;'],
        'on_a_roll'      => ['On a roll', 'Three quizzes in a row at 70% or more.', 'Score 70% or more in three quizzes in a row.', '&#128293;'],
        'perfect'        => ['Perfect score', 'Got full marks in a quiz.', 'Get full marks in a quiz.', '&#127942;'],
        'personal_best'  => ['Personal Best', 'Beat your own best quiz mark.', 'Beat your own best quiz mark.', '&#128200;'],
        'comeback'       => ['Comeback', 'Beat your last quiz by 15 points.', 'Beat your previous quiz mark by 15 points.', '&#128170;'],
        'improver'       => ['Improvement Champion', 'Raised your average by 15 points.', 'Raise your average by 15 points (last 3 quizzes compared with your first 3).', '&#127775;'],
        'early_bird'     => ['Early bird', 'Handed in a quiz within a day of it opening.', 'Hand in a quiz within a day of it opening.', '&#9200;'],
        'homework_hero'  => ['Homework Hero', 'Handed in 5 homework tasks on time.', 'Hand in 5 homework tasks on time.', '&#9997;&#65039;'],
        'early_finisher' => ['Early Finisher', 'Handed in homework a day early, 3 times.', 'Hand in homework a day or more early, 3 times.', '&#9889;'],
        'never_missed'   => ['Never missed', 'Attended 8 classes in a row.', 'Come to 8 classes in a row.', '&#128197;'],
        'attendance_90'  => ['Regular Attender', 'Attended 90% of classes in 30 days.', 'Attend 90% of your classes in the last 30 days (at least 4 classes).', '&#9989;'],
        'attendance_100' => ['Full Attendance', 'Attended every class in 30 days.', 'Attend every class in the last 30 days (at least 4 classes).', '&#127894;&#65039;'],
        'streak_7'       => ['7-Day Streak', 'Learned on 7 days in a row.', 'Learn on 7 days in a row (one rest day allowed).', '&#128293;'],
        'bookworm'       => ['Bookworm', 'Opened every recording and note.', 'Open every recording and note in your classes.', '&#128218;'],
        'topic_master'   => ['Topic Master', 'Scored 80% or more in a Science unit.', 'Score 80% or more in a Science unit (at least 20 marks assessed).', '&#9883;&#65039;'],
    ];

    /**
     * Every achievement with its state. Newly earned ones are recorded.
     *
     * @return list<array{code: string, title: string, how: string, icon: string,
     *                    earned: bool, earned_at: ?string, progress: int, target: int, hint: string}>
     */
    public function forStudent(int $studentId, array $progress = []): array
    {
        $rules = $this->evaluate($studentId, $progress);

        $saved = [];
        $stmt = $this->db->prepare("SELECT code, earned_at FROM student_achievements WHERE student_id = :s");
        $stmt->execute([':s' => $studentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $saved[$r['code']] = (string) $r['earned_at'];
        }

        $insert = $this->db->prepare(
            "INSERT IGNORE INTO student_achievements (student_id, code, earned_at) VALUES (:s, :c, :e)"
        );

        $out = [];
        foreach (self::DEFS as $code => [$title, $done, $how, $icon]) {
            $r = $rules[$code];
            $earned = isset($saved[$code]) || $r['earned'];
            if ($earned && !isset($saved[$code])) {
                $when = $r['when'] ?? date('Y-m-d H:i:s');
                $insert->execute([':s' => $studentId, ':c' => $code, ':e' => $when]);
                $saved[$code] = $when;
            }
            $out[] = [
                'code'      => $code,
                'title'     => $title,
                'key'       => $code,
                'done'      => $done,
                'how'       => $how,
                'icon'      => $icon,
                'earned'    => $earned,
                'earned_at' => $saved[$code] ?? null,
                'earned_on' => $saved[$code] ?? null,
                'progress'  => min($r['progress'], $r['target']),
                'target'    => $r['target'],
                'hint'      => $earned ? '' : $r['hint'],
            ];
        }

        // Earned first (newest first), then the closest to being earned.
        usort($out, static function (array $a, array $b): int {
            if ($a['earned'] !== $b['earned']) {
                return $a['earned'] ? -1 : 1;
            }
            if ($a['earned']) {
                return strcmp((string) $b['earned_at'], (string) $a['earned_at']);
            }
            $pa = $a['target'] > 0 ? $a['progress'] / $a['target'] : 0;
            $pb = $b['target'] > 0 ? $b['progress'] / $b['target'] : 0;
            return $pb <=> $pa;
        });
        return $out;
    }

    /**
     * The rules themselves.
     *
     * @return array<string, array{earned: bool, when: ?string, progress: int, target: int, hint: string}>
     */
    private function evaluate(int $studentId, array $progress): array
    {
        $r = [];
        $rule = static fn (bool $earned, ?string $when, int $progress, int $target, string $hint): array =>
            ['earned' => $earned, 'when' => $when, 'progress' => $progress, 'target' => $target, 'hint' => $hint];

        // ---- quizzes --------------------------------------------------
        $stmt = $this->db->prepare(
            "SELECT a.submitted_at, a.percentage, q.opens_at FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
              WHERE a.student_id = :s AND a.attempt_status IN ('submitted','auto_submitted','graded') AND a.submitted_at IS NOT NULL
           ORDER BY a.submitted_at, a.id"
        );
        $stmt->execute([':s' => $studentId]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $n = count($attempts);
        $nth = static fn (int $k): ?string => $attempts[$k - 1]['submitted_at'] ?? null;

        $r['first_quiz'] = $rule($n >= 1, $nth(1), $n, 1, t('Hand in a quiz to unlock this.'));
        $r['quiz_explorer'] = $rule($n >= 5, $nth(5), $n, 5, t('Hand in {n} more quizzes to unlock this.', ['n' => max(0, 5 - $n)]));
        $r['quiz_master'] = $rule($n >= 20, $nth(20), $n, 20, t('Hand in {n} more quizzes to unlock this.', ['n' => max(0, 20 - $n)]));

        $perfectAt = null; $best = null; $pbAt = null; $bestPct = 0;
        foreach ($attempts as $a) {
            if ($a['percentage'] === null) {
                continue;
            }
            $p = (float) $a['percentage'];
            if ($perfectAt === null && $p >= 100) {
                $perfectAt = $a['submitted_at'];
            }
            if ($best !== null && $p > $best && $pbAt === null) {
                $pbAt = $a['submitted_at'];
            }
            $best = $best === null ? $p : max($best, $p);
            $bestPct = (int) round($best);
        }
        $r['perfect'] = $rule($perfectAt !== null, $perfectAt, $bestPct, 100, t('Your best so far is {n}%.', ['n' => $bestPct]));
        $r['personal_best'] = $rule($pbAt !== null, $pbAt, min($n, 2), 2,
            $n < 2 ? t('Hand in two quizzes, then beat your first mark.') : t('Beat {n}% in your next quiz.', ['n' => $bestPct]));

        // high flyer, on a roll, comeback, early bird (the original badges)
        $highAt = null; $rollAt = null; $run = 0; $bestRun = 0; $comeAt = null; $jump = 0; $prev = null; $birdAt = null;
        foreach ($attempts as $a) {
            if (!empty($a['opens_at']) && $birdAt === null
                && strtotime((string) $a['submitted_at']) - strtotime((string) $a['opens_at']) <= 86400) {
                $birdAt = $a['submitted_at'];
            }
            if ($a['percentage'] === null) {
                continue;
            }
            $p = (float) $a['percentage'];
            if ($highAt === null && $p >= 80) {
                $highAt = $a['submitted_at'];
            }
            $run = $p >= 70 ? $run + 1 : 0;
            $bestRun = max($bestRun, $run);
            if ($rollAt === null && $run >= 3) {
                $rollAt = $a['submitted_at'];
            }
            if ($prev !== null) {
                $jump = max($jump, (int) round($p - $prev));
                if ($comeAt === null && $p - $prev >= 15) {
                    $comeAt = $a['submitted_at'];
                }
            }
            $prev = $p;
        }
        $r['high_flyer'] = $rule($highAt !== null, $highAt, $bestPct, 80, t('Your best so far is {n}%.', ['n' => $bestPct]));
        $r['on_a_roll'] = $rule($rollAt !== null, $rollAt, $run, 3, t('{n} in a row at 70% or more right now.', ['n' => $run]));
        $r['comeback'] = $rule($comeAt !== null, $comeAt, max(0, $jump), 15, t('Beat your last quiz mark by 15 points.'));
        $r['early_bird'] = $rule($birdAt !== null, $birdAt, $birdAt !== null ? 1 : 0, 1, t('Hand in a quiz within a day of it opening.'));

        $pcts = array_values(array_map(static fn ($a) => (float) $a['percentage'], array_filter($attempts, static fn ($a) => $a['percentage'] !== null)));
        $gain = 0;
        $improvedAt = null;
        if (count($pcts) >= 6) {
            $first = array_sum(array_slice($pcts, 0, 3)) / 3;
            $last = array_sum(array_slice($pcts, -3)) / 3;
            $gain = (int) round($last - $first);
            if ($gain >= 15) {
                $improvedAt = $attempts[$n - 1]['submitted_at'];
            }
        }
        $r['improver'] = $rule($improvedAt !== null, $improvedAt, max(0, $gain), 15,
            count($pcts) < 6 ? t('Needs at least 6 marked quizzes ({n} so far).', ['n' => count($pcts)])
                             : t('Your recent average is {n} points above your first quizzes.', ['n' => $gain]));

        // ---- homework -------------------------------------------------
        $stmt = $this->db->prepare(
            "SELECT sub.submitted_at, sub.is_late, a.due_at FROM assignment_submissions sub
               JOIN assignments a ON a.id = sub.assignment_id
              WHERE sub.student_id = :s ORDER BY sub.submitted_at, sub.id"
        );
        $stmt->execute([':s' => $studentId]);
        $onTime = 0; $heroAt = null; $early = 0; $earlyAt = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            if ((int) $h['is_late'] === 0) {
                $onTime++;
                if ($onTime === 5) {
                    $heroAt = $h['submitted_at'];
                }
            }
            if (strtotime((string) $h['due_at']) - strtotime((string) $h['submitted_at']) >= 86400) {
                $early++;
                if ($early === 3) {
                    $earlyAt = $h['submitted_at'];
                }
            }
        }
        $r['homework_hero'] = $rule($onTime >= 5, $heroAt, $onTime, 5, t('{n} more on-time hand-ins to go.', ['n' => max(0, 5 - $onTime)]));
        $r['early_finisher'] = $rule($early >= 3, $earlyAt, $early, 3, t('{n} more early hand-ins to go.', ['n' => max(0, 3 - $early)]));

        // ---- attendance (last 30 days) ----------------------------------
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) total, SUM(status IN ('present','late')) came, MAX(CASE WHEN status IN ('present','late') THEN date END) last_came FROM attendance
              WHERE student_id = :s AND date >= :f AND class_id IS NOT NULL"
        );
        $stmt->execute([':s' => $studentId, ':f' => date('Y-m-d', strtotime('-30 days'))]);
        $att = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'came' => 0];
        $total = (int) $att['total']; $came = (int) $att['came'];
        $pct = $total > 0 ? (int) floor($came / $total * 100) : 0;
        $enough = $total >= 4;
        $attHint = $enough ? t('{n}% in the last 30 days.', ['n' => $pct]) : t('Needs at least 4 marked classes in the last 30 days ({n} so far).', ['n' => $total]);
        $attWhen = $att['last_came'] ? $att['last_came'] . ' 18:00:00' : null;
        $r['attendance_90'] = $rule($enough && $pct >= 90, $attWhen, $enough ? $pct : 0, 90, $attHint);
        $r['attendance_100'] = $rule($enough && $came === $total, $attWhen, $enough ? $pct : 0, 100, $attHint);

        // ---- classes in a row (late still counts as there) ---------------
        $stmt = $this->db->prepare("SELECT date, status FROM attendance WHERE student_id = :s ORDER BY date, id");
        $stmt->execute([':s' => $studentId]);
        $inRow = 0; $missAt = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $inRow = $row['status'] === 'absent' ? 0 : $inRow + 1;
            if ($missAt === null && $inRow >= 8) {
                $missAt = $row['date'] . ' 18:00:00';
            }
        }
        $r['never_missed'] = $rule($missAt !== null, $missAt, $inRow, 8, t('{n} classes in a row so far.', ['n' => $inRow]));

        // ---- material (from ProgressService's engagement figures) --------
        $mTotal = (int) ($progress['engagement']['materials_total'] ?? 0);
        $mOpened = (int) ($progress['engagement']['materials_opened'] ?? 0);
        $r['bookworm'] = $rule($mTotal >= 5 && $mOpened >= $mTotal, null, $mOpened, max(5, $mTotal),
            t('{a} of {b} opened.', ['a' => $mOpened, 'b' => $mTotal]));

        // ---- streak ---------------------------------------------------
        $days = (new StreakService($this->db))->learningDays($studentId);
        $streak = StreakService::fromDays($days, date('Y-m-d'));
        $streakAt = null;
        if ($streak['longest'] >= 7) {
            // the day the 7th learning day in a run was reached
            $run = 0; $prev = null;
            foreach ($days as $d) {
                $run = ($prev !== null && (strtotime($d) - strtotime($prev)) / 86400 <= StreakService::MAX_GAP_DAYS) ? $run + 1 : 1;
                $prev = $d;
                if ($run === 7) {
                    $streakAt = $d . ' 18:00:00';
                    break;
                }
            }
        }
        $r['streak_7'] = $rule($streak['longest'] >= 7, $streakAt, $streak['current'], 7,
            t('Current streak: {n} days.', ['n' => $streak['current']]));

        // ---- topic master ----------------------------------------------
        $best = $this->bestTopic($studentId);
        $r['topic_master'] = $rule($best !== null && $best['pct'] >= 80 && $best['max'] >= 20, null,
            $best ? (int) round($best['pct']) : 0, 80,
            $best ? t('Your strongest unit is at {n}%.', ['n' => (int) round($best['pct'])]) : t('Do quizzes and homework that are tagged with a unit.'));

        return $r;
    }

    /** Best unit by marks (quiz answers, latest attempt per quiz, plus marked homework). @return array{pct: float, max: float}|null */
    private function bestTopic(int $studentId): ?array
    {
        $scores = [];
        $latest = "SELECT MAX(x.id) FROM quiz_attempts x WHERE x.quiz_id = a.quiz_id AND x.student_id = a.student_id
                     AND x.attempt_status IN ('submitted','auto_submitted','graded')";
        $stmt = $this->db->prepare(
            "SELECT COALESCE(qq.topic_id, q.topic_id) t, SUM(COALESCE(ans.marks_awarded, 0)) got, SUM(qq.marks) max
               FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id JOIN quiz_questions qq ON qq.quiz_id = a.quiz_id
          LEFT JOIN quiz_answers ans ON ans.attempt_id = a.id AND ans.question_id = qq.id
              WHERE a.student_id = :s AND a.attempt_status IN ('submitted','auto_submitted','graded') AND a.id = ($latest)
                AND (ans.id IS NULL OR ans.marks_awarded IS NOT NULL) AND COALESCE(qq.topic_id, q.topic_id) IS NOT NULL
           GROUP BY COALESCE(qq.topic_id, q.topic_id)"
        );
        try {
            $stmt->execute([':s' => $studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $scores[(int) $row['t']] = [(float) $row['got'], (float) $row['max']];
            }
            $stmt = $this->db->prepare(
                "SELECT a.topic_id t, SUM(sub.marks) got, SUM(a.total_marks) max FROM assignment_submissions sub
                   JOIN assignments a ON a.id = sub.assignment_id
                  WHERE sub.student_id = :s AND sub.marks IS NOT NULL AND a.topic_id IS NOT NULL GROUP BY a.topic_id"
            );
            $stmt->execute([':s' => $studentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $k = (int) $row['t'];
                $scores[$k] = [($scores[$k][0] ?? 0) + (float) $row['got'], ($scores[$k][1] ?? 0) + (float) $row['max']];
            }
        } catch (\Throwable $e) {
            error_log('Topic scores unavailable: ' . $e->getMessage()); // before migration 014
            return null;
        }
        $best = null;
        foreach ($scores as [$got, $max]) {
            if ($max <= 0) {
                continue;
            }
            $p = $got / $max * 100;
            // prefer units with enough marks behind them
            if ($best === null || ($max >= 20) > ($best['max'] >= 20) || (($max >= 20) === ($best['max'] >= 20) && $p > $best['pct'])) {
                $best = ['pct' => $p, 'max' => $max];
            }
        }
        return $best;
    }
}
