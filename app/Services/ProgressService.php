<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Turns a student's record into "where am I?".
 *
 * The teacher asked for a level a student can see at a glance. The
 * temptation is to average their quiz marks and call it done, but a
 * mark average alone rewards the student who sat one easy quiz and
 * says nothing about the one who stopped turning up. So the level is
 * built from three things a tutor would actually look at:
 *
 *   marks        (60%)  how well they did on what they sat
 *   attendance   (25%)  whether they are still in the room
 *   engagement   (15%)  whether they opened the material at all
 *
 * The weights are deliberate: marks lead, but a perfect-scoring student
 * who has stopped attending should not read as "Excellent", and a
 * student who is present and working through the material is given
 * some credit for it while their marks catch up.
 *
 * Nothing here is cached in a table. These numbers change every time a
 * quiz is marked or a register is taken, and a stale progress row that
 * disagrees with the marks page is worse than one extra query.
 */
class ProgressService
{
    private PDO $db;

    private const WEIGHT_MARKS = 0.60;
    private const WEIGHT_ATTENDANCE = 0.25;
    private const WEIGHT_ENGAGEMENT = 0.15;

    /** Lower bound => label. Read highest-first. */
    // Public so the motivation page can say "6 points to Excellent" from
    // the same thresholds that decide the level, rather than a copy that
    // could drift from them.
    public const LEVELS = [
        85 => ['label' => 'Excellent',       'tone' => 'success'],
        70 => ['label' => 'Strong',          'tone' => 'success'],
        55 => ['label' => 'Steady',          'tone' => 'info'],
        40 => ['label' => 'Developing',      'tone' => 'warning'],
        0  => ['label' => 'Needs attention', 'tone' => 'danger'],
    ];

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * The whole picture for one student, optionally narrowed to a class.
     *
     * @return array{
     *   level: string, tone: string, score: float,
     *   marks: array, attendance: array, engagement: array,
     *   has_data: bool, summary: string
     * }
     */
    public function forStudent(int $studentId, ?int $classId = null): array
    {
        $marks = $this->markPerformance($studentId, $classId);
        $attendance = $this->attendanceRate($studentId, $classId);
        $engagement = $this->engagementRate($studentId, $classId);

        // A student with nothing on record yet gets an honest "not enough
        // yet" rather than a level computed from three zeroes, which would
        // tell a parent their child is failing before the first quiz.
        $hasData = $marks['quizzes_taken'] > 0
            || $attendance['sessions_recorded'] > 0
            || $engagement['materials_total'] > 0;

        if (!$hasData) {
            return [
                'level'      => 'Not started',
                'tone'       => 'muted',
                'score'      => 0.0,
                'marks'      => $marks,
                'attendance' => $attendance,
                'engagement' => $engagement,
                'has_data'   => false,
                'summary'    => 'No quizzes, attendance or material yet.',
            ];
        }

        // Each component only pulls its weight once there is something to
        // measure. Otherwise a class with no quizzes yet would drag every
        // student down by 60 points for a quiz that was never set.
        $parts = [];
        if ($marks['quizzes_taken'] > 0) {
            $parts[] = [self::WEIGHT_MARKS, $marks['average_percentage']];
        }
        if ($attendance['sessions_recorded'] > 0) {
            $parts[] = [self::WEIGHT_ATTENDANCE, $attendance['rate']];
        }
        if ($engagement['materials_total'] > 0) {
            $parts[] = [self::WEIGHT_ENGAGEMENT, $engagement['rate']];
        }

        $weightSum = array_sum(array_column($parts, 0));
        $score = $weightSum > 0
            ? array_sum(array_map(fn (array $p): float => $p[0] * $p[1], $parts)) / $weightSum
            : 0.0;
        $score = round($score, 1);

        $level = $this->levelFor($score);

        return [
            'level'      => $level['label'],
            'tone'       => $level['tone'],
            'score'      => $score,
            'marks'      => $marks,
            'attendance' => $attendance,
            'engagement' => $engagement,
            'has_data'   => true,
            'summary'    => $this->summarise($marks, $attendance, $engagement),
        ];
    }

    /**
     * Quiz marks: how many sat, and how they went.
     *
     * Only graded attempts count. A paper still waiting on the teacher
     * has no score worth averaging, and counting it as zero would make a
     * student look worse for the teacher being behind on marking.
     */
    public function markPerformance(int $studentId, ?int $classId = null): array
    {
        $sql = "SELECT COUNT(*)              AS quizzes_taken,
                       AVG(a.percentage)     AS average_percentage,
                       MAX(a.percentage)     AS best_percentage,
                       MIN(a.percentage)     AS worst_percentage
                  FROM quiz_attempts a
                  JOIN quizzes q ON q.id = a.quiz_id
                 WHERE a.student_id = :student_id
                   AND a.attempt_status = 'graded'
                   AND a.percentage IS NOT NULL";

        $params = [':student_id' => $studentId];

        if ($classId !== null) {
            $sql .= " AND q.class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'quizzes_taken'      => (int) ($row['quizzes_taken'] ?? 0),
            'average_percentage' => round((float) ($row['average_percentage'] ?? 0), 1),
            'best_percentage'    => $row['best_percentage'] !== null ? round((float) $row['best_percentage'], 1) : null,
            'worst_percentage'   => $row['worst_percentage'] !== null ? round((float) $row['worst_percentage'], 1) : null,
            'awaiting_marking'   => $this->countAwaitingMarking($studentId, $classId),
        ];
    }

    private function countAwaitingMarking(int $studentId, ?int $classId): int
    {
        $sql = "SELECT COUNT(*)
                  FROM quiz_attempts a
                  JOIN quizzes q ON q.id = a.quiz_id
                 WHERE a.student_id = :student_id
                   AND a.needs_marking = 1";

        $params = [':student_id' => $studentId];
        if ($classId !== null) {
            $sql .= " AND q.class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Attendance: present (and late) over everything recorded.
     *
     * Late still counts as having turned up. Marking it absent would
     * punish the student twice for the same bus.
     */
    public function attendanceRate(int $studentId, ?int $classId = null): array
    {
        // The column is `status`, not `attendance_status`: this schema
        // names it plainly on `attendance` even though `enrollments` and
        // `payments` use the prefixed form.
        $sql = "SELECT COUNT(*) AS sessions_recorded,
                       SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS attended
                  FROM attendance
                 WHERE student_id = :student_id";

        $params = [':student_id' => $studentId];
        if ($classId !== null) {
            $sql .= " AND class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $recorded = (int) ($row['sessions_recorded'] ?? 0);
        $attended = (int) ($row['attended'] ?? 0);

        return [
            'sessions_recorded' => $recorded,
            'attended'          => $attended,
            'missed'            => max(0, $recorded - $attended),
            'rate'              => $recorded > 0 ? round(($attended / $recorded) * 100, 1) : 0.0,
        ];
    }

    /**
     * Engagement: how much of the published material they opened.
     *
     * Counted only over material they could actually reach -- published,
     * in a class they are enrolled in. Locked months are excluded, since
     * not opening something you were never shown is not disengagement.
     */
    public function engagementRate(int $studentId, ?int $classId = null): array
    {
        $sql = "SELECT COUNT(DISTINCT m.id) AS materials_total,
                       COUNT(DISTINCT v.material_id) AS materials_viewed
                  FROM enrollments e
                  JOIN class_materials m
                       ON m.class_id = e.class_id
                      AND m.status = 'published'
                  LEFT JOIN material_views v
                       ON v.material_id = m.id
                      AND v.student_id = e.student_id
                 WHERE e.student_id = :student_id
                   AND e.enrollment_status = 'active'";

        $params = [':student_id' => $studentId];
        if ($classId !== null) {
            $sql .= " AND e.class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int) ($row['materials_total'] ?? 0);
        $viewed = (int) ($row['materials_viewed'] ?? 0);

        return [
            'materials_total'  => $total,
            'materials_viewed' => $viewed,
            'rate'             => $total > 0 ? round(($viewed / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function levelFor(float $score): array
    {
        foreach (self::LEVELS as $floor => $level) {
            if ($score >= $floor) {
                return $level;
            }
        }

        return self::LEVELS[0];
    }

    /**
     * One sentence naming the thing most worth doing something about.
     *
     * A dashboard that reports three numbers leaves the reader to work
     * out which one matters. This picks the weakest component and says
     * so, because that is the sentence a parent or student acts on.
     */
    private function summarise(array $marks, array $attendance, array $engagement): string
    {
        $candidates = [];

        if ($marks['quizzes_taken'] > 0) {
            $candidates['marks'] = $marks['average_percentage'];
        }
        if ($attendance['sessions_recorded'] > 0) {
            $candidates['attendance'] = $attendance['rate'];
        }
        if ($engagement['materials_total'] > 0) {
            $candidates['engagement'] = $engagement['rate'];
        }

        if ($candidates === []) {
            return 'No quizzes, attendance or material yet.';
        }

        asort($candidates);
        $weakest = array_key_first($candidates);
        $value = $candidates[$weakest];

        if ($value >= 80) {
            return 'Strong across marks, attendance and material.';
        }

        // Through t(), so a student or parent reading in Sinhala gets the
        // sentence in Sinhala; in English the wording is unchanged.
        return match ($weakest) {
            'marks' => t(
                $marks['quizzes_taken'] === 1
                    ? 'Quiz marks are averaging {pct}% over {n} quiz -- the area to work on.'
                    : 'Quiz marks are averaging {pct}% over {n} quizzes -- the area to work on.',
                ['pct' => $value, 'n' => (int) $marks['quizzes_taken']]
            ),
            'attendance' => t(
                'Attended {a} of {b} sessions ({pct}%). Missing class is the main gap.',
                ['a' => (int) $attendance['attended'], 'b' => (int) $attendance['sessions_recorded'], 'pct' => $value]
            ),
            default => t(
                'Opened {a} of {b} posted items ({pct}%). Working through the material would help.',
                ['a' => (int) $engagement['materials_viewed'], 'b' => (int) $engagement['materials_total'], 'pct' => $value]
            ),
        };
    }
}
