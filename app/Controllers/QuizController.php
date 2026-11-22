<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ContentAccessService;
use PDO;
use Quiz;

/**
 * The teacher's side of quizzes: set one, fill it with questions,
 * publish it, and read the results afterwards.
 *
 * A quiz is built in two steps rather than one enormous form. The
 * first saves the paper and its window; the second adds questions one
 * at a time. That is deliberate — a teacher writing fifteen questions
 * into a single form loses all of them to one mistyped date, and a
 * half-written quiz is useful to come back to.
 *
 * A quiz cannot be published until it has questions. Publishing an
 * empty paper is a mistake with no upside, and students sitting a
 * zero-question quiz would all score zero out of zero.
 */
class QuizController extends BaseController
{
    private Quiz $quizzes;

    public function __construct()
    {
        parent::__construct();
        $this->quizzes = new Quiz();
    }

    /**
     * Every quiz set for a class.
     */
    public function index(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $this->view('quizzes/index', [
            'pageTitle' => 'Quizzes',
            'class'     => $class,
            'quizzes'   => $this->quizzes->forClass($classId),
        ]);
    }

    /**
     * The "set a quiz" form.
     */
    public function create(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $this->view('quizzes/create', [
            'pageTitle' => 'Set a quiz',
            'class'     => $class,
        ]);
    }

    /**
     * Save the paper, then send the teacher straight to its questions.
     */
    public function store(string $classId): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $data = [
            'title'     => trim($_POST['title'] ?? ''),
            'opens_at'  => trim($_POST['opens_at'] ?? ''),
            'closes_at' => trim($_POST['closes_at'] ?? ''),
        ];

        $errors = $this->validate($data, [
            'title'     => 'required|max:255',
            'opens_at'  => 'required',
            'closes_at' => 'required',
        ]);

        if ($errors !== []) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect("/classes/{$classId}/quizzes/create");
        }

        $opens = strtotime($data['opens_at']);
        $closes = strtotime($data['closes_at']);

        if ($opens === false || $closes === false) {
            $this->flash('error', 'Those dates could not be read.');
            $this->redirect("/classes/{$classId}/quizzes/create");
        }

        // A window that closes before it opens would never be sittable,
        // and the error is far cheaper to catch here than to debug from
        // a student saying "it says closed".
        if ($closes <= $opens) {
            $this->flash('error', 'The closing time has to be after the opening time.');
            $this->redirect("/classes/{$classId}/quizzes/create");
        }

        $limit = ($_POST['time_limit_minutes'] ?? '') !== ''
            ? max(1, (int) $_POST['time_limit_minutes'])
            : null;

        $quizId = $this->quizzes->create([
            'class_id'           => $classId,
            'title'              => $data['title'],
            'description'        => trim($_POST['description'] ?? '') ?: null,
            'instructions'       => trim($_POST['instructions'] ?? '') ?: null,
            'opens_at'           => date('Y-m-d H:i:s', $opens),
            'closes_at'          => date('Y-m-d H:i:s', $closes),
            'time_limit_minutes' => $limit,
            'pass_mark'          => ($_POST['pass_mark'] ?? '') !== '' ? (float) $_POST['pass_mark'] : null,
            'max_attempts'       => max(1, (int) ($_POST['max_attempts'] ?? 1)),
            'shuffle_questions'  => isset($_POST['shuffle_questions']) ? 1 : 0,
            'show_answers_after' => isset($_POST['show_answers_after']) ? 1 : 0,
            'month_year'         => ContentAccessService::normaliseMonth(
                $_POST['month_year'] ?? date('Y-m', $opens)
            ),
            // Always a draft to begin with. Questions come next, and a
            // paper with none of them must not be sittable.
            'quiz_status'        => 'draft',
            'created_by'         => (int) session_get('user_id'),
        ]);

        (new \App\Services\TopicService($this->db))->tag('quizzes', (int) $quizId, (int) $classId, $_POST['topic_id'] ?? null);

        $this->flash('success', 'Quiz saved as a draft. Add the questions next.');
        $this->redirect("/classes/{$classId}/quizzes/{$quizId}");
    }

    /**
     * The question builder for one quiz.
     */
    public function show(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);

        $quiz = $this->requireQuiz((int) $id, $classId);

        $this->view('quizzes/show', [
            'pageTitle' => $quiz['title'],
            'class'     => $class,
            'quiz'      => $quiz,
            'questions' => $this->quizzes->questions((int) $id, true),
            'window'    => Quiz::window($quiz),
        ]);
    }

    /**
     * Add one question.
     */
    public function addQuestion(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $quiz = $this->requireQuiz((int) $id, $classId);

        $type = $_POST['question_type'] ?? 'mcq';
        $text = trim($_POST['question_text'] ?? '');
        $marks = (float) ($_POST['marks'] ?? 1);

        if ($text === '') {
            $this->flash('error', 'The question needs some text.');
            $this->redirect("/classes/{$classId}/quizzes/{$id}");
        }

        if ($marks <= 0) {
            $this->flash('error', 'A question has to be worth more than zero.');
            $this->redirect("/classes/{$classId}/quizzes/{$id}");
        }

        $options = [
            'a' => trim($_POST['option_a'] ?? ''),
            'b' => trim($_POST['option_b'] ?? ''),
            'c' => trim($_POST['option_c'] ?? ''),
            'd' => trim($_POST['option_d'] ?? ''),
        ];

        $correctOption = strtolower(trim($_POST['correct_option'] ?? ''));
        $correctText = trim($_POST['correct_text'] ?? '');

        // Per-type checks, so a half-filled question never reaches a
        // student. An MCQ whose "correct" letter has no option behind it
        // is unanswerable, and marks every sitting wrong.
        if ($type === 'mcq') {
            $filled = array_filter($options, static fn (string $v): bool => $v !== '');

            if (count($filled) < 2) {
                $this->flash('error', 'A multiple-choice question needs at least two options.');
                $this->redirect("/classes/{$classId}/quizzes/{$id}");
            }

            if (!isset($filled[$correctOption])) {
                $this->flash('error', 'Mark which of the options you filled in is the correct one.');
                $this->redirect("/classes/{$classId}/quizzes/{$id}");
            }
        } elseif ($type === 'true_false') {
            if (!in_array($correctOption, ['true', 'false'], true)) {
                $this->flash('error', 'Choose whether the statement is true or false.');
                $this->redirect("/classes/{$classId}/quizzes/{$id}");
            }
            $options = ['a' => 'True', 'b' => 'False', 'c' => '', 'd' => ''];
            $correctOption = $correctOption === 'true' ? 'a' : 'b';
        } else {
            // Short answer may be left without a model answer on purpose:
            // it then falls to the teacher to mark, which is a valid choice.
            $correctOption = null;
        }

        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(question_number), 0) + 1 FROM quiz_questions WHERE quiz_id = :q"
        );
        $stmt->execute([':q' => $id]);
        $next = (int) $stmt->fetchColumn();

        $this->db->prepare(
            "INSERT INTO quiz_questions (quiz_id, question_number, question_text, question_type,
                                         marks, option_a, option_b, option_c, option_d,
                                         correct_option, correct_text, explanation)
             VALUES (:quiz, :no, :text, :type, :marks, :a, :b, :c, :d, :correct, :ctext, :exp)"
        )->execute([
            ':quiz'    => $id,
            ':no'      => $next,
            ':text'    => $text,
            ':type'    => $type,
            ':marks'   => $marks,
            ':a'       => $options['a'] !== '' ? $options['a'] : null,
            ':b'       => $options['b'] !== '' ? $options['b'] : null,
            ':c'       => $options['c'] !== '' ? $options['c'] : null,
            ':d'       => $options['d'] !== '' ? $options['d'] : null,
            ':correct' => $correctOption,
            ':ctext'   => $correctText !== '' ? $correctText : null,
            ':exp'     => trim($_POST['explanation'] ?? '') ?: null,
        ]);

        // A question's own topic; when none is chosen it counts under the quiz's topic.
        (new \App\Services\TopicService($this->db))->tag('quiz_questions', (int) $this->db->lastInsertId(), (int) $classId, $_POST['topic_id'] ?? null);

        $total = $this->quizzes->syncTotalMarks((int) $id);

        $this->flash('success', "Question {$next} added. The paper is now worth {$total} marks.");
        $this->redirect("/classes/{$classId}/quizzes/{$id}");
    }

    /**
     * Remove a question and renumber what is left.
     *
     * Renumbering matters: a paper that runs 1, 2, 4, 5 reads as though
     * question 3 went missing during the exam.
     */
    public function deleteQuestion(string $classId, string $id, string $questionId): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $this->requireQuiz((int) $id, $classId);

        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                "DELETE FROM quiz_questions WHERE id = :q AND quiz_id = :quiz"
            )->execute([':q' => $questionId, ':quiz' => $id]);

            $rows = $this->db->prepare(
                "SELECT id FROM quiz_questions WHERE quiz_id = :quiz ORDER BY question_number ASC"
            );
            $rows->execute([':quiz' => $id]);

            $renumber = $this->db->prepare(
                "UPDATE quiz_questions SET question_number = :n WHERE id = :id"
            );

            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $i => $qid) {
                $renumber->execute([':n' => $i + 1, ':id' => $qid]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->quizzes->syncTotalMarks((int) $id);
        $this->flash('success', 'Question removed.');
        $this->redirect("/classes/{$classId}/quizzes/{$id}");
    }

    /**
     * Publish, or pull back to draft.
     */
    public function publish(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $quiz = $this->requireQuiz((int) $id, $classId);

        $target = $_POST['quiz_status'] ?? 'published';

        if (!in_array($target, ['draft', 'published', 'closed'], true)) {
            $this->flash('error', 'Unknown status.');
            $this->redirect("/classes/{$classId}/quizzes/{$id}");
        }

        if ($target === 'published') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = :q");
            $stmt->execute([':q' => $id]);
            $count = (int) $stmt->fetchColumn();

            if ($count === 0) {
                $this->flash('error', 'Add at least one question before publishing.');
                $this->redirect("/classes/{$classId}/quizzes/{$id}");
            }
        }

        $this->quizzes->update((int) $id, ['quiz_status' => $target]);

        // Announce it once, on the way to published. Pulling a quiz back
        // to draft and publishing it again does not re-announce: the
        // dispatch guard is keyed on the quiz, not on the button press,
        // so the class is told about a quiz exactly once.
        $announced = ['sent' => 0];
        if ($target === 'published') {
            $announced = (new \App\Services\AnnouncementService())->quizPublished((int) $id);
        }

        $this->flash('success', match ($target) {
            'published' => 'Published. The class can sit it inside the window.'
                . ($announced['sent'] > 0 ? " {$announced['sent']} students and guardians notified." : ''),
            'draft'     => 'Pulled back to draft. Students can no longer see it.',
            default     => 'Closed. No further attempts will be accepted.',
        });

        $this->redirect("/classes/{$classId}/quizzes/{$id}");
    }

    /**
     * Who sat it, what they scored, and who did not sit it at all.
     *
     * The students who did not sit are the point of this screen, which
     * is why they are fetched by the same query rather than left to be
     * noticed by their absence from a list of attempts.
     */
    public function results(string $classId, string $id): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $class = $this->requireClass($classId);
        $this->requireClassAccess($class);
        $quiz = $this->requireQuiz((int) $id, $classId);

        $sql = "SELECT s.id AS student_id, s.student_id_display, s.full_name,
                       a.id AS attempt_id, a.score, a.total_marks, a.percentage,
                       a.attempt_status, a.submitted_at, a.needs_marking
                  FROM enrollments e
                  JOIN students s ON s.id = e.student_id
                  LEFT JOIN quiz_attempts a
                         ON a.student_id = s.id
                        AND a.quiz_id = :quiz
                 WHERE e.class_id = :class_id
                   AND e.enrollment_status = 'active'
                 ORDER BY (a.percentage IS NULL), a.percentage DESC, s.full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':quiz' => $id, ':class_id' => $classId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sat = array_values(array_filter($rows, static fn (array $r): bool => $r['attempt_id'] !== null));
        $scores = array_map(static fn (array $r): float => (float) $r['percentage'], $sat);

        $this->view('quizzes/results', [
            'pageTitle' => 'Results: ' . $quiz['title'],
            'class'     => $class,
            'quiz'      => $quiz,
            'rows'      => $rows,
            'stats'     => [
                'enrolled' => count($rows),
                'sat'      => count($sat),
                'average'  => $scores !== [] ? round(array_sum($scores) / count($scores), 1) : null,
                'highest'  => $scores !== [] ? max($scores) : null,
                'lowest'   => $scores !== [] ? min($scores) : null,
                'passed'   => $quiz['pass_mark'] !== null
                    ? count(array_filter($scores, static fn (float $p): bool => $p >= (float) $quiz['pass_mark']))
                    : null,
            ],
        ]);
    }

    // ---------- guards ----------

    private function requireClass(int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, i.name AS institute_name
               FROM classes c
               LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class === false) {
            $this->flash('error', 'That class does not exist.');
            $this->redirect('/classes');
        }

        return $class;
    }

    private function requireClassAccess(array $class): void
    {
        // One rule for every role, and BranchScope already holds it:
        // global roles see every class, office roles see their own
        // branch's, and a teacher sees the classes they are attached to
        // -- whether through classes.teacher_id or through user_classes.
        //
        // The old check here read classes.teacher_id alone, so a teacher
        // assigned to a class through user_classes was shown the class in
        // their list and then bounced off its material, recordings and
        // quizzes with "that is not one of your classes". Staff, whom the
        // route lets in, were refused the same way.
        if (\App\Services\BranchScope::current()->allowsClass((int) ($class['id'] ?? 0))) {
            return;
        }

        $this->flash('error', 'That is not one of your classes.');
        $this->redirect('/classes');
    }

    /**
     * The quiz must belong to the class in the path, so a quiz id from
     * another teacher's class cannot be reached by pairing it with a
     * class this teacher does own.
     */
    private function requireQuiz(int $quizId, int $classId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM quizzes WHERE id = :id AND class_id = :class_id LIMIT 1");
        $stmt->execute([':id' => $quizId, ':class_id' => $classId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($quiz === false) {
            $this->flash('error', 'That quiz is not in this class.');
            $this->redirect("/classes/{$classId}/quizzes");
        }

        return $quiz;
    }
}
