<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AttachmentService;
use App\Services\ContentAccessService;
use App\Services\TopicService;

/**
 * Past papers and marking schemes, by grade, year, paper type and medium.
 *
 * A student sees the published papers of the grades they are actively
 * enrolled in, and can open them when this month's fee is settled for at
 * least one class of that grade (the same rule as class material). Marking
 * schemes open only when the teacher releases them.
 */
class PastPaperController extends BaseController
{
    private const MANAGERS = [AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_TEACHER];
    private const MAX_BYTES = 15 * 1024 * 1024;
    public const TYPES = ['term1' => 'First term test', 'term2' => 'Second term test', 'term3' => 'Third term test', 'model' => 'Model paper', 'provincial' => 'Provincial paper', 'school' => 'School paper', 'other' => 'Other'];
    public const MEDIA = ['sinhala' => 'Sinhala', 'english' => 'English', 'tamil' => 'Tamil'];

    /** GET /past-papers -- staff library */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::MANAGERS);

        $grade = (int) ($_GET['grade'] ?? 0);
        $sql = "SELECT p.*, t.title AS topic_title, t.title_si AS topic_title_si, t.unit_no AS topic_unit, u.full_name AS uploader
                  FROM past_papers p
             LEFT JOIN topics t ON t.id = p.topic_id
             LEFT JOIN users u ON u.id = p.uploaded_by
                 WHERE p.status <> 'archived'" . ($grade > 0 ? " AND p.grade = :g" : '') . "
              ORDER BY p.grade, p.year DESC, p.paper_type, p.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($grade > 0 ? [':g' => $grade] : []);

        $this->view('papers/index', [
            'pageTitle' => t('Past papers'),
            'papers'    => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'grade'     => $grade,
        ]);
    }

    /** GET /past-papers/create */
    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::MANAGERS);

        $grade = max(6, min(13, (int) ($_GET['grade'] ?? 6)));
        $this->view('papers/create', [
            'pageTitle' => t('Add a past paper'),
            'grade'     => $grade,
            'topics'    => (new TopicService($this->db))->forGrade($grade),
        ]);
    }

    /** POST /past-papers */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::MANAGERS);
        verifyCsrfToken();

        $grade = (int) ($_POST['grade'] ?? 0);
        $year = (int) ($_POST['year'] ?? 0);
        $type = (string) ($_POST['paper_type'] ?? '');
        $medium = (string) ($_POST['medium'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $back = '/past-papers/create?grade=' . max(6, $grade);

        if ($grade < 1 || $grade > 13 || $year < 1990 || $year > (int) date('Y') + 1
            || !isset(self::TYPES[$type]) || !isset(self::MEDIA[$medium])) {
            $this->flash('error', t('Choose the grade, year, paper type and medium.'));
            $this->redirect($back);
        }
        if ($title === '') {
            $title = t('Grade {n}', ['n' => $grade]) . ' ' . t(self::TYPES[$type]) . ' ' . $year;
        }

        $topicId = null;
        if ((int) ($_POST['topic_id'] ?? 0) > 0) {
            foreach ((new TopicService($this->db))->forGrade($grade) as $tp) {
                if ((int) $tp['id'] === (int) $_POST['topic_id']) {
                    $topicId = (int) $tp['id'];
                }
            }
        }

        $files = new AttachmentService();
        try {
            $paper = $files->store($_FILES['paper_file'] ?? null, 'papers', self::MAX_BYTES);
            if ($paper === null) {
                throw new \RuntimeException(t('Attach the paper (PDF, PNG or JPG).'));
            }
            $answers = $files->store($_FILES['answers_file'] ?? null, 'papers', self::MAX_BYTES);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($back);
        }

        $publish = ($_POST['action'] ?? '') === 'publish';
        $this->db->prepare(
            "INSERT INTO past_papers (grade, subject, year, paper_type, medium, title, topic_id, paper_path, paper_name,
                                      answers_path, answers_name, show_answers, status, uploaded_by)
             VALUES (:g, 'Science', :y, :t, :m, :title, :topic, :pp, :pn, :ap, :an, :sa, :st, :by)"
        )->execute([
            ':g' => $grade, ':y' => $year, ':t' => $type, ':m' => $medium, ':title' => mb_substr($title, 0, 255),
            ':topic' => $topicId, ':pp' => $paper[0], ':pn' => $paper[1],
            ':ap' => $answers[0] ?? null, ':an' => $answers[1] ?? null,
            ':sa' => ($answers !== null && isset($_POST['show_answers'])) ? 1 : 0,
            ':st' => $publish ? 'published' : 'draft', ':by' => (int) session_get('user_id'),
        ]);

        $this->audit('add_past_paper', (int) $this->db->lastInsertId(), ['grade' => $grade, 'year' => $year, 'published' => $publish]);
        $this->flash('success', $publish ? t('Paper published for Grade {n} students.', ['n' => $grade]) : t('Saved as a draft.'));
        $this->redirect('/past-papers?grade=' . $grade);
    }

    /** POST /past-papers/{id}/status  (publish | draft | archive | answers_on | answers_off) */
    public function status(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::MANAGERS);
        verifyCsrfToken();

        $paper = $this->find((int) $id);
        $sql = match ((string) ($_POST['to'] ?? '')) {
            'publish'     => "UPDATE past_papers SET status = 'published' WHERE id = :id",
            'draft'       => "UPDATE past_papers SET status = 'draft' WHERE id = :id",
            'archive'     => "UPDATE past_papers SET status = 'archived' WHERE id = :id",
            'answers_on'  => "UPDATE past_papers SET show_answers = 1 WHERE id = :id AND answers_path IS NOT NULL",
            'answers_off' => "UPDATE past_papers SET show_answers = 0 WHERE id = :id",
            default       => null,
        };
        if ($sql !== null) {
            $this->db->prepare($sql)->execute([':id' => (int) $paper['id']]);
            $this->audit('past_paper_' . $_POST['to'], (int) $paper['id'], []);
            $this->flash('success', t('Updated.'));
        }
        $this->redirect('/past-papers?grade=' . (int) $paper['grade']);
    }

    /** GET /my/papers -- the student's library */
    public function mine(): void
    {
        $studentId = $this->requireStudent();
        $grades = $this->studentGrades($studentId);

        $papers = [];
        if ($grades !== []) {
            $in = implode(',', array_map('intval', array_keys($grades)));
            $papers = $this->db->query(
                "SELECT p.id, p.grade, p.year, p.paper_type, p.medium, p.title, p.answers_path IS NOT NULL AND p.show_answers = 1 AS has_answers,
                        t.title AS topic_title, t.title_si AS topic_title_si, t.unit_no AS topic_unit
                   FROM past_papers p LEFT JOIN topics t ON t.id = p.topic_id
                  WHERE p.status = 'published' AND p.subject = 'Science' AND p.grade IN ($in)
               ORDER BY p.grade, p.year DESC, p.paper_type"
            )->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->view('papers/mine', [
            'pageTitle' => t('Past papers'),
            'papers'    => $papers,
            'open'      => $grades,
        ]);
    }

    /** GET /past-papers/{id}/file/{which}  which = paper | answers */
    public function file(string $id, string $which): void
    {
        AuthMiddleware::requireAuth();
        $paper = $this->find((int) $id, false);
        $isStaff = session_get('user_type') === 'user' && in_array(session_get('user_role'), self::MANAGERS, true);

        if (!$isStaff) {
            if (session_get('user_type') !== 'student' || $paper['status'] !== 'published') {
                $this->deny();
            }
            $open = $this->studentGrades((int) session_get('user_id'));
            if (empty($open[(int) $paper['grade']])) {
                $this->deny();
            }
            if ($which === 'answers' && (int) $paper['show_answers'] !== 1) {
                $this->deny();
            }
        }

        if ($which === 'answers') {
            if (empty($paper['answers_path'])) {
                $this->deny();
            }
            (new AttachmentService())->send((string) $paper['answers_path'], (string) ($paper['answers_name'] ?: 'marking-scheme'));
        }
        (new AttachmentService())->send((string) $paper['paper_path'], (string) ($paper['paper_name'] ?: 'paper'));
    }

    /**
     * Grades the student is actively enrolled in => whether this month is
     * settled for at least one class of that grade.
     *
     * @return array<int, bool>
     */
    private function studentGrades(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.grade FROM enrollments e JOIN classes c ON c.id = e.class_id
              WHERE e.student_id = :s AND e.enrollment_status = 'active' AND c.status = 'active' AND c.subject = 'Science'"
        );
        $stmt->execute([':s' => $studentId]);
        $access = new ContentAccessService();
        $grades = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $g = TopicService::gradeNumber($row['grade']);
            if ($g === null) {
                continue;
            }
            $grades[$g] = ($grades[$g] ?? false) || $access->hasPaidFor($studentId, (int) $row['id']);
        }
        return $grades;
    }

    private function requireStudent(): int
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'student') {
            $this->flash('error', t('That area is for students.'));
            $this->redirect('/dashboard');
        }
        return (int) session_get('user_id');
    }

    /** @return array<string, mixed> */
    private function find(int $id, bool $redirect = true): array
    {
        $stmt = $this->db->prepare("SELECT * FROM past_papers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $paper = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$paper) {
            if (!$redirect) {
                $this->deny();
            }
            $this->flash('error', t('That paper does not exist.'));
            $this->redirect('/past-papers');
        }
        return $paper;
    }

    private function deny(): never
    {
        http_response_code(403);
        exit('Not allowed.');
    }

    private function audit(string $action, int $id, array $details): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'past_paper', $id, $details);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
