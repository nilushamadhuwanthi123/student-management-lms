<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ContentAccessService;
use App\Services\ProgressService;
use App\Services\QuizService;
use ClassMaterial;
use PDO;
use Quiz;
use RuntimeException;

/**
 * What a student sees of their own classes.
 *
 * Every read here is scoped to the student in the session. None of
 * these actions take a student id from the request, so there is no
 * parameter to tamper with: a student can only ever ask about
 * themselves.
 *
 * The fee gate is applied at the controller, not the template. A view
 * that forgets a condition leaks content; a controller that refuses to
 * load it cannot.
 */
class StudentPortalController extends BaseController
{
    private ContentAccessService $access;
    private ClassMaterial $materials;
    private Quiz $quizzes;

    public function __construct()
    {
        parent::__construct();
        $this->access = new ContentAccessService();
        $this->materials = new ClassMaterial();
        $this->quizzes = new Quiz();
    }

    /**
     * The student's classes, each marked open or locked for this month.
     */
    public function classes(): void
    {
        $studentId = $this->requireStudent();
        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        $sql = "SELECT c.id, c.class_name, c.subject, c.grade, c.medium, c.room,
                       c.schedule_days, c.start_time, c.end_time, c.monthly_fee,
                       i.name AS institute_name,
                       u.full_name AS teacher_name,
                       e.scholarship_type
                  FROM enrollments e
                  JOIN classes c ON c.id = e.class_id
                  LEFT JOIN institutes i ON i.id = c.institute_id
                  LEFT JOIN users u ON u.id = c.teacher_id
                 WHERE e.student_id = :student_id
                   AND e.enrollment_status = 'active'
                 ORDER BY c.grade ASC, c.class_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // One query for every class's gate rather than one per row.
        $openMap = $this->access->openClassMap($studentId, $month);

        foreach ($classes as &$class) {
            $class['gate'] = $this->access->gateStatus($studentId, (int) $class['id'], $month);
            $class['is_open'] = $openMap[(int) $class['id']] ?? false;
        }
        unset($class);

        // Per-class detail for the panels: next session, this month's
        // folder, quizzes and standing. Keyed on the session's student.
        $overview = (new \App\Services\ClassOverviewService())->forStudent(
            $studentId,
            $month,
            array_map(static fn (array $c): int => (int) $c['id'], $classes),
            $openMap
        );

        $this->view('portal/classes', [
            'pageTitle'  => 'My classes',
            'activePage' => 'my-classes',
            'classes'    => $classes,
            'overview'   => $overview,
            'month'      => $month,
            'months'     => $this->monthOptions(),
        ], 'layouts/admin');
    }

    /**
     * Inside one class: the material and the quizzes for a month.
     */
    public function classDetail(string $classId): void
    {
        $studentId = $this->requireStudent();
        $classId = (int) $classId;
        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        $class = $this->requireEnrolledClass($studentId, $classId);
        $gate = $this->access->gateStatus($studentId, $classId, $month);

        // Locked months load nothing at all. The page explains why and
        // what it costs to open, but the content never reaches the
        // response body in the first place.
        $materials = [];
        $quizzes = [];

        if ($gate['allowed']) {
            $materials = $this->materials->forStudent($classId, $month, $studentId);
            $quizzes = $this->quizzes->forStudent($classId, $month, $studentId);
        }

        $this->view('portal/class-detail', [
            'pageTitle' => $class['class_name'],
            'class'     => $class,
            'gate'      => $gate,
            'month'     => $month,
            'months'    => $this->monthOptions(),
            'materials' => $materials,
            'quizzes'   => $quizzes,
            'types'     => ClassMaterial::TYPE_LABELS,
        ], 'layouts/admin');
    }

    /**
     * Open a piece of material, and record that it was opened.
     *
     * Links bounce the student out to Zoom or YouTube; files go through
     * the download controller. Either way the view is logged first, so
     * engagement reflects what was actually opened.
     */
    public function openMaterial(string $id): void
    {
        $studentId = $this->requireStudent();
        $material = $this->materials->find((int) $id);

        if ($material === null || $material['status'] !== 'published') {
            $this->flash('error', 'That item is not available.');
            $this->redirect('/my/classes');
        }

        $this->requireEnrolledClass($studentId, (int) $material['class_id']);

        if (!$this->access->hasPaidFor($studentId, (int) $material['class_id'], $material['month_year'])) {
            $this->flash('error', 'Settle this month\'s fee to open the class material.');
            $this->redirect('/my/classes/' . (int) $material['class_id']);
        }

        $this->materials->recordView((int) $id, $studentId);

        if (!empty($material['external_url'])) {
            header('Location: ' . $material['external_url']);
            exit;
        }

        $this->redirect('/materials/' . (int) $id . '/download');
    }

    /**
     * Start or resume a quiz.
     */
    public function sitQuiz(string $quizId): void
    {
        $studentId = $this->requireStudent();
        $quizId = (int) $quizId;

        $service = new QuizService();

        try {
            $attempt = $service->startAttempt($quizId, $studentId);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/my/classes');
        }

        $quiz = $this->quizzes->find($quizId);
        $questions = $this->quizzes->questions($quizId, false);

        if ($quiz['shuffle_questions']) {
            // Seeded on the attempt so a reload keeps the same order --
            // a shuffle that moves on every refresh is disorienting and
            // makes "question 3" meaningless between student and teacher.
            mt_srand((int) $attempt['id']);
            shuffle($questions);
            mt_srand();
        }

        // Answers already banked, so a resumed attempt comes back filled in.
        $saved = $this->db->prepare(
            "SELECT question_id, selected_option, answer_text FROM quiz_answers WHERE attempt_id = :a"
        );
        $saved->execute([':a' => $attempt['id']]);

        $answers = [];
        foreach ($saved->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $answers[(int) $row['question_id']] = $row;
        }

        $this->view('portal/sit-quiz', [
            'pageTitle'  => $quiz['title'],
            'quiz'       => $quiz,
            'attempt'    => $attempt,
            'questions'  => $questions,
            'answers'    => $answers,
            'secondsLeft' => $service->secondsRemaining($attempt),
        ], 'layouts/auth');
    }

    /**
     * Bank one answer, called as the student works through the paper.
     */
    public function saveAnswer(): void
    {
        $studentId = $this->requireStudent();
        verifyCsrfToken();

        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $questionId = (int) ($_POST['question_id'] ?? 0);

        $service = new QuizService();

        try {
            $service->saveAnswer(
                $attemptId,
                $questionId,
                $studentId,
                ($_POST['selected_option'] ?? '') !== '' ? (string) $_POST['selected_option'] : null,
                ($_POST['answer_text'] ?? '') !== '' ? (string) $_POST['answer_text'] : null
            );
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        $this->json(['ok' => true]);
    }

    /**
     * Hand the paper in.
     */
    public function submitQuiz(): void
    {
        $studentId = $this->requireStudent();
        verifyCsrfToken();

        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $service = new QuizService();

        try {
            $attempt = $service->submitAttempt($attemptId, $studentId);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/my/classes');
        }

        $this->flash('success', 'Submitted.');
        $this->redirect('/my/quizzes/' . (int) $attempt['id'] . '/result');
    }

    /**
     * One sat paper, with the answers once the window has closed.
     */
    public function quizResult(string $attemptId): void
    {
        $studentId = $this->requireStudent();

        $stmt = $this->db->prepare(
            "SELECT a.*, q.title, q.class_id, q.pass_mark, q.show_answers_after, q.closes_at
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
              WHERE a.id = :id AND a.student_id = :student_id
              LIMIT 1"
        );
        $stmt->execute([':id' => (int) $attemptId, ':student_id' => $studentId]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attempt === false) {
            $this->flash('error', 'That result is not yours.');
            $this->redirect('/my/marks');
        }

        // Correct answers stay hidden until the window shuts, otherwise
        // the first student to finish can hand the paper to the rest.
        $reveal = (int) $attempt['show_answers_after'] === 1
            && date('Y-m-d H:i:s') > $attempt['closes_at'];

        $answers = [];

        if ($reveal) {
            $stmt = $this->db->prepare(
                "SELECT q.question_number, q.question_text, q.question_type, q.marks,
                        q.option_a, q.option_b, q.option_c, q.option_d,
                        q.correct_option, q.correct_text, q.explanation,
                        a.selected_option, a.answer_text, a.is_correct, a.marks_awarded
                   FROM quiz_questions q
                   LEFT JOIN quiz_answers a ON a.question_id = q.id AND a.attempt_id = :attempt
                  WHERE q.quiz_id = :quiz
                  ORDER BY q.question_number"
            );
            $stmt->execute([':attempt' => $attempt['id'], ':quiz' => $attempt['quiz_id']]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('portal/quiz-result', [
            'pageTitle' => 'Result: ' . $attempt['title'],
            'attempt'   => $attempt,
            'answers'   => $answers,
            'reveal'    => $reveal,
        ], 'layouts/admin');
    }

    /**
     * Every mark the student has.
     */
    public function marks(): void
    {
        $studentId = $this->requireStudent();

        $stmt = $this->db->prepare(
            "SELECT a.id, a.score, a.total_marks, a.percentage, a.attempt_status,
                    a.submitted_at, a.needs_marking,
                    q.title, q.pass_mark, q.month_year,
                    c.class_name, c.grade
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :student_id
                AND a.attempt_status <> 'in_progress'
              ORDER BY a.submitted_at DESC"
        );
        $stmt->execute([':student_id' => $studentId]);

        $progress = new ProgressService();

        $this->view('portal/marks', [
            'pageTitle' => t('My marks'),
            'attempts'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'progress'  => $progress->forStudent($studentId),
        ], 'layouts/admin');
    }

    /**
     * Where the student is, and what is weakest.
     */
    public function progress(): void
    {
        $studentId = $this->requireStudent();
        $service = new ProgressService();

        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
              WHERE e.student_id = :student_id AND e.enrollment_status = 'active'
              ORDER BY c.grade, c.class_name"
        );
        $stmt->execute([':student_id' => $studentId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classes as &$class) {
            $class['progress'] = $service->forStudent($studentId, (int) $class['id']);
        }
        unset($class);

        $overall = $service->forStudent($studentId);

        $this->view('portal/progress', [
            'pageTitle'  => t('My progress'),
            'overall'    => $overall,
            'classes'    => $classes,
            // Streaks, achievements, next steps -- all from this student's
            // own record, never compared with anyone else's.
            'motivation' => (new \App\Services\MotivationService())->forStudent($studentId, $overall),
        ], 'layouts/admin');
    }

    // ---------- guards ----------

    /**
     * The signed-in student's id, or out.
     *
     * Everything in this controller reads from the session rather than
     * the request, which is what makes "only yourself" structural rather
     * than a check somebody can forget to write.
     */
    private function requireStudent(): int
    {
        AuthMiddleware::requireAuth();

        if (session_get('user_type') !== 'student') {
            $this->flash('error', 'That area is for students.');
            $this->redirect('/dashboard');
        }

        return (int) session_get('user_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireEnrolledClass(int $studentId, int $classId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, i.name AS institute_name, u.full_name AS teacher_name
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               LEFT JOIN users u ON u.id = c.teacher_id
              WHERE e.student_id = :student_id
                AND e.class_id = :class_id
                AND e.enrollment_status = 'active'
              LIMIT 1"
        );
        $stmt->execute([':student_id' => $studentId, ':class_id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class === false) {
            $this->flash('error', 'You are not enrolled in that class.');
            $this->redirect('/my/classes');
        }

        return $class;
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(): array
    {
        $options = [];

        for ($i = 0; $i < 6; $i++) {
            $month = date('Y-m-01', strtotime("-{$i} month"));
            $options[$month] = date('F Y', strtotime($month));
        }

        return $options;
    }
}
