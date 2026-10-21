<?php

declare(strict_types=1);

/**
 * A quiz set for one class, with a sitting window.
 *
 * The lifecycle state a student cares about ("open now", "opens
 * Friday", "closed") is derived from the clock every time it is asked
 * for rather than stored, so a quiz becomes available on time without
 * anything having to run on a schedule to flip a flag.
 */
class Quiz extends BaseModel
{
    protected string $table = 'quizzes';

    protected array $fillable = [
        'class_id',
        'title',
        'description',
        'instructions',
        'opens_at',
        'closes_at',
        'time_limit_minutes',
        'total_marks',
        'pass_mark',
        'max_attempts',
        'shuffle_questions',
        'show_answers_after',
        'month_year',
        'quiz_status',
        'created_by',
    ];

    /**
     * Quizzes for a class, with how many have sat each one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forClass(int $classId): array
    {
        $sql = "SELECT q.*,
                       (SELECT COUNT(DISTINCT a.student_id)
                          FROM quiz_attempts a
                         WHERE a.quiz_id = q.id
                           AND a.attempt_status <> 'in_progress') AS sat_count,
                       (SELECT COUNT(*)
                          FROM quiz_attempts a
                         WHERE a.quiz_id = q.id
                           AND a.needs_marking = 1)                AS awaiting_marking,
                       (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
                  FROM quizzes q
                 WHERE q.class_id = :class_id
                 ORDER BY q.opens_at DESC, q.id DESC";

        return $this->query($sql, [':class_id' => $classId])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quizzes a student can see for a class and month, with their own
     * attempt attached.
     *
     * Draft quizzes never appear. The student's best graded attempt is
     * joined in so the list can show a mark without a second query per
     * row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $classId, string $monthYear, int $studentId): array
    {
        $sql = "SELECT q.id, q.class_id, q.title, q.description, q.instructions,
                       q.opens_at, q.closes_at, q.time_limit_minutes,
                       q.total_marks, q.pass_mark, q.max_attempts, q.month_year,
                       q.show_answers_after,
                       (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
                       (SELECT COUNT(*) FROM quiz_attempts a
                         WHERE a.quiz_id = q.id AND a.student_id = :student_id) AS attempts_used,
                       best.percentage AS best_percentage,
                       best.score      AS best_score,
                       best.id         AS best_attempt_id,
                       live.id         AS live_attempt_id
                  FROM quizzes q
                  LEFT JOIN quiz_attempts best
                         ON best.id = (
                              SELECT a2.id FROM quiz_attempts a2
                               WHERE a2.quiz_id = q.id
                                 AND a2.student_id = :student_id2
                                 AND a2.attempt_status = 'graded'
                               ORDER BY a2.percentage DESC, a2.id DESC
                               LIMIT 1
                            )
                  LEFT JOIN quiz_attempts live
                         ON live.quiz_id = q.id
                        AND live.student_id = :student_id3
                        AND live.attempt_status = 'in_progress'
                 WHERE q.class_id = :class_id
                   AND q.month_year = :month_year
                   AND q.quiz_status = 'published'
                 ORDER BY q.opens_at DESC, q.id DESC";

        return $this->query($sql, [
            ':class_id'    => $classId,
            ':month_year'  => $monthYear,
            ':student_id'  => $studentId,
            ':student_id2' => $studentId,
            ':student_id3' => $studentId,
        ])->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Where a quiz stands against the clock right now.
     *
     * @return array{state: string, label: string, tone: string}
     */
    public static function window(array $quiz, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        if (($quiz['quiz_status'] ?? '') === 'draft') {
            return ['state' => 'draft', 'label' => 'Draft', 'tone' => 'muted'];
        }

        if (($quiz['quiz_status'] ?? '') === 'closed' || $now > $quiz['closes_at']) {
            return ['state' => 'closed', 'label' => 'Closed', 'tone' => 'muted'];
        }

        if ($now < $quiz['opens_at']) {
            return [
                'state' => 'scheduled',
                'label' => 'Opens ' . date('j M, g:i a', strtotime($quiz['opens_at'])),
                'tone'  => 'info',
            ];
        }

        return [
            'state' => 'open',
            'label' => 'Open until ' . date('j M, g:i a', strtotime($quiz['closes_at'])),
            'tone'  => 'success',
        ];
    }

    /**
     * Recalculate total_marks from the questions actually attached.
     *
     * Called after any question change so the header figure can never
     * drift from the sum of its parts — a quiz that says 50 marks while
     * its questions add to 45 undermines every percentage derived from it.
     */
    public function syncTotalMarks(int $quizId): float
    {
        $total = (float) $this->query(
            "SELECT COALESCE(SUM(marks), 0) FROM quiz_questions WHERE quiz_id = :quiz_id",
            [':quiz_id' => $quizId]
        )->fetchColumn();

        $this->query(
            "UPDATE quizzes SET total_marks = :total WHERE id = :id",
            [':total' => $total, ':id' => $quizId]
        );

        return $total;
    }

    /**
     * Questions in order, for sitting or editing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function questions(int $quizId, bool $withAnswers = false): array
    {
        $columns = $withAnswers
            ? '*'
            : 'id, quiz_id, question_number, question_text, question_type, marks,
               option_a, option_b, option_c, option_d';

        return $this->query(
            "SELECT {$columns} FROM quiz_questions WHERE quiz_id = :quiz_id ORDER BY question_number ASC",
            [':quiz_id' => $quizId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
