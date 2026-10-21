<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * The lifecycle of a quiz attempt, start to mark.
 *
 * Everything here is decided on the server. A quiz has a window and a
 * time limit, and both are enforced against the database clock rather
 * than anything the browser reports: a countdown in JavaScript is a
 * courtesy to the student, not a control. The deadline for an attempt
 * is written down when it starts, so reloading the page, opening a
 * second tab or changing the system clock cannot buy more time.
 *
 * Marking runs in the same transaction as submission. A paper that is
 * recorded as submitted but never scored is worse than one that failed
 * to submit at all, because nobody notices until results are published.
 */
class QuizService
{
    private PDO $db;
    private ContentAccessService $access;

    /** Grace given to a late submission, in seconds, to absorb slow networks. */
    private const SUBMIT_GRACE_SECONDS = 30;

    public function __construct()
    {
        $this->db = \Database::connect();
        $this->access = new ContentAccessService();
    }

    /**
     * Start an attempt, or hand back the one already running.
     *
     * Returns the attempt row. Refuses, with a reason the caller can
     * show the student, when the quiz is shut, the fee is unpaid or
     * the attempt allowance is spent.
     *
     * @throws RuntimeException
     */
    public function startAttempt(int $quizId, int $studentId): array
    {
        $quiz = $this->requireQuiz($quizId);

        if ($quiz['quiz_status'] !== 'published') {
            throw new RuntimeException('This quiz is not open.');
        }

        // The window is checked against the database's own clock so that
        // PHP and MySQL disagreeing about the time cannot let someone in
        // early or lock someone out late.
        $now = $this->now();

        if ($now < $quiz['opens_at']) {
            $when = date('j M Y, g:i a', strtotime($quiz['opens_at']));
            throw new RuntimeException("This quiz opens on {$when}.");
        }

        if ($now > $quiz['closes_at']) {
            throw new RuntimeException('This quiz has closed.');
        }

        if (!$this->access->hasPaidFor($studentId, (int) $quiz['class_id'], $quiz['month_year'])) {
            throw new RuntimeException('Settle this month\'s class fee to sit this quiz.');
        }

        // An attempt already running is resumed, not restarted: a student
        // who loses their connection mid-quiz should come back to the same
        // paper and the same deadline.
        $running = $this->findRunningAttempt($quizId, $studentId);
        if ($running !== null) {
            return $running;
        }

        $used = $this->countAttempts($quizId, $studentId);
        if ($used >= (int) $quiz['max_attempts']) {
            throw new RuntimeException(
                (int) $quiz['max_attempts'] === 1
                    ? 'You have already sat this quiz.'
                    : "You have used all {$quiz['max_attempts']} attempts."
            );
        }

        return $this->createAttempt($quiz, $studentId, $used + 1);
    }

    /**
     * Insert the attempt row, fixing its deadline at insert time.
     */
    private function createAttempt(array $quiz, int $studentId, int $attemptNumber): array
    {
        $limit = $quiz['time_limit_minutes'] !== null ? (int) $quiz['time_limit_minutes'] : null;

        // The attempt ends at the earlier of "started + limit" and the
        // quiz's own closing time, so a paper begun two minutes before
        // close cannot run on past it.
        $expiresAt = null;
        if ($limit !== null) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($this->now()) + ($limit * 60));
            if ($expiresAt > $quiz['closes_at']) {
                $expiresAt = $quiz['closes_at'];
            }
        }

        $sql = "INSERT INTO quiz_attempts
                       (quiz_id, student_id, attempt_number, started_at, expires_at, attempt_status)
                VALUES (:quiz_id, :student_id, :attempt_number, :started_at, :expires_at, 'in_progress')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':quiz_id'        => $quiz['id'],
            ':student_id'     => $studentId,
            ':attempt_number' => $attemptNumber,
            ':started_at'     => $this->now(),
            ':expires_at'     => $expiresAt,
        ]);

        return $this->findAttempt((int) $this->db->lastInsertId());
    }

    /**
     * Save one answer mid-attempt.
     *
     * Answers are stored as they are given rather than held in the
     * session until submit, so a browser that dies halfway through has
     * still banked everything answered up to that point.
     */
    public function saveAnswer(int $attemptId, int $questionId, int $studentId, ?string $selectedOption, ?string $answerText): void
    {
        $attempt = $this->requireOwnedAttempt($attemptId, $studentId);

        if ($attempt['attempt_status'] !== 'in_progress') {
            throw new RuntimeException('This attempt has already been submitted.');
        }

        if ($this->hasExpired($attempt)) {
            // Close it off rather than silently dropping the answer: the
            // student needs to be told, and the paper needs a score.
            $this->submitAttempt($attemptId, $studentId, true);
            throw new RuntimeException('Your time ran out. The quiz was submitted automatically.');
        }

        $sql = "INSERT INTO quiz_answers (attempt_id, question_id, selected_option, answer_text)
                VALUES (:attempt_id, :question_id, :selected_option, :answer_text)
                ON DUPLICATE KEY UPDATE
                    selected_option = VALUES(selected_option),
                    answer_text     = VALUES(answer_text)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attempt_id'      => $attemptId,
            ':question_id'     => $questionId,
            ':selected_option' => $selectedOption,
            ':answer_text'     => $answerText,
        ]);
    }

    /**
     * Submit and mark in one transaction.
     *
     * @param bool $auto True when the clock ended the attempt rather than the student.
     * @return array The graded attempt.
     */
    public function submitAttempt(int $attemptId, int $studentId, bool $auto = false): array
    {
        $attempt = $this->requireOwnedAttempt($attemptId, $studentId);

        if ($attempt['attempt_status'] !== 'in_progress') {
            return $attempt;
        }

        if (!$auto && $this->isPastGrace($attempt)) {
            $auto = true;
        }

        $this->db->beginTransaction();

        try {
            $marked = $this->markAnswers($attemptId);

            $sql = "UPDATE quiz_attempts
                       SET submitted_at   = :submitted_at,
                           score          = :score,
                           total_marks    = :total_marks,
                           percentage     = :percentage,
                           needs_marking  = :needs_marking,
                           attempt_status = :attempt_status
                     WHERE id = :id";

            $percentage = $marked['total'] > 0
                ? round(($marked['score'] / $marked['total']) * 100, 2)
                : 0.0;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':submitted_at'   => $this->now(),
                ':score'          => $marked['score'],
                ':total_marks'    => $marked['total'],
                ':percentage'     => $percentage,
                ':needs_marking'  => $marked['pending'] > 0 ? 1 : 0,
                // A paper with short answers still waiting on the teacher
                // is not 'graded' yet, however much of it the machine could
                // score on its own.
                ':attempt_status' => $marked['pending'] > 0
                    ? ($auto ? 'auto_submitted' : 'submitted')
                    : 'graded',
                ':id'             => $attemptId,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->findAttempt($attemptId);
    }

    /**
     * Score every answer the machine can score.
     *
     * @return array{score: float, total: float, pending: int}
     */
    private function markAnswers(int $attemptId): array
    {
        $sql = "SELECT a.id            AS answer_id,
                       a.selected_option,
                       a.answer_text,
                       q.id            AS question_id,
                       q.question_type,
                       q.marks,
                       q.correct_option,
                       q.correct_text
                  FROM quiz_questions q
                  LEFT JOIN quiz_answers a
                         ON a.question_id = q.id
                        AND a.attempt_id  = :attempt_id
                 WHERE q.quiz_id = (SELECT quiz_id FROM quiz_attempts WHERE id = :attempt_id2)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':attempt_id' => $attemptId, ':attempt_id2' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $score = 0.0;
        $total = 0.0;
        $pending = 0;

        $update = $this->db->prepare(
            "UPDATE quiz_answers SET is_correct = :is_correct, marks_awarded = :marks WHERE id = :id"
        );

        foreach ($rows as $row) {
            $marks = (float) $row['marks'];
            $total += $marks;

            // Unanswered question: nothing to mark, nothing awarded.
            if ($row['answer_id'] === null) {
                continue;
            }

            if ($row['question_type'] === 'short_answer') {
                $expected = trim((string) ($row['correct_text'] ?? ''));

                // Without a model answer there is nothing to compare
                // against, so it goes to the teacher.
                if ($expected === '') {
                    $pending++;
                    continue;
                }

                $given = $this->normaliseText((string) ($row['answer_text'] ?? ''));
                $correct = $this->normaliseText($expected);
                $isCorrect = $given !== '' && $given === $correct;

                // A wrong-looking short answer may still deserve marks, so
                // only a match is settled here; the rest waits for a human.
                if (!$isCorrect) {
                    $pending++;
                    continue;
                }

                $update->execute([':is_correct' => 1, ':marks' => $marks, ':id' => $row['answer_id']]);
                $score += $marks;
                continue;
            }

            $given = strtolower(trim((string) ($row['selected_option'] ?? '')));
            $correct = strtolower(trim((string) ($row['correct_option'] ?? '')));
            $isCorrect = $given !== '' && $given === $correct;

            $awarded = $isCorrect ? $marks : 0.0;
            $update->execute([
                ':is_correct' => $isCorrect ? 1 : 0,
                ':marks'      => $awarded,
                ':id'         => $row['answer_id'],
            ]);
            $score += $awarded;
        }

        return ['score' => $score, 'total' => $total, 'pending' => $pending];
    }

    /**
     * Fold away the differences that should not cost a mark.
     */
    private function normaliseText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return preg_replace('/[.,;:!?]+$/u', '', $value) ?? $value;
    }

    // ---------- lookups and guards ----------

    public function findAttempt(int $attemptId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM quiz_attempts WHERE id = :id");
        $stmt->execute([':id' => $attemptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('Attempt not found.');
        }

        return $row;
    }

    /**
     * Load an attempt and prove it belongs to this student.
     *
     * Ownership is checked on every write rather than trusting the id in
     * the form, so one student cannot submit into another's paper by
     * editing a hidden field.
     */
    private function requireOwnedAttempt(int $attemptId, int $studentId): array
    {
        $attempt = $this->findAttempt($attemptId);

        if ((int) $attempt['student_id'] !== $studentId) {
            throw new RuntimeException('That attempt belongs to someone else.');
        }

        return $attempt;
    }

    private function requireQuiz(int $quizId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM quizzes WHERE id = :id");
        $stmt->execute([':id' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('Quiz not found.');
        }

        return $row;
    }

    private function findRunningAttempt(int $quizId, int $studentId): ?array
    {
        $sql = "SELECT * FROM quiz_attempts
                 WHERE quiz_id = :quiz_id
                   AND student_id = :student_id
                   AND attempt_status = 'in_progress'
                 ORDER BY id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':quiz_id' => $quizId, ':student_id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // A running attempt whose clock has already run out is closed off
        // here rather than offered back as if it were still live.
        if ($this->hasExpired($row)) {
            return $this->submitAttempt((int) $row['id'], $studentId, true);
        }

        return $row;
    }

    private function countAttempts(int $quizId, int $studentId): int
    {
        $sql = "SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = :quiz_id AND student_id = :student_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':quiz_id' => $quizId, ':student_id' => $studentId]);

        return (int) $stmt->fetchColumn();
    }

    private function hasExpired(array $attempt): bool
    {
        return $attempt['expires_at'] !== null && $this->now() > $attempt['expires_at'];
    }

    private function isPastGrace(array $attempt): bool
    {
        if ($attempt['expires_at'] === null) {
            return false;
        }

        return strtotime($this->now()) > strtotime($attempt['expires_at']) + self::SUBMIT_GRACE_SECONDS;
    }

    /**
     * The database's clock, not PHP's.
     */
    private function now(): string
    {
        return (string) $this->db->query('SELECT NOW()')->fetchColumn();
    }

    /**
     * Seconds left in an attempt, for the countdown the student sees.
     */
    public function secondsRemaining(array $attempt): ?int
    {
        if ($attempt['expires_at'] === null) {
            return null;
        }

        return max(0, strtotime($attempt['expires_at']) - strtotime($this->now()));
    }
}
