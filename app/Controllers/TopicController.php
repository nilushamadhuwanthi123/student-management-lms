<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\TopicService;

/**
 * The teacher's list of syllabus units per grade. Shared by every branch.
 */
class TopicController extends BaseController
{
    private const ROLES = [AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_TEACHER];

    /** GET /topics */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ROLES);

        $subjects = $this->subjects();
        $subject = (string) ($_GET['subject'] ?? 'Science');
        if (!in_array($subject, $subjects, true)) {
            $subject = $subjects[0] ?? 'Science';
        }
        $grade = (int) ($_GET['grade'] ?? 6);
        if ($grade < 1 || $grade > 13) {
            $grade = 6;
        }

        $topics = (new TopicService($this->db))->forGrade($grade, $subject, true);
        $usage = [];
        if ($topics !== []) {
            $ids = implode(',', array_map(static fn ($t): int => (int) $t['id'], $topics));
            foreach (['class_materials', 'quizzes', 'quiz_questions', 'assignments'] as $table) {
                foreach ($this->db->query("SELECT topic_id, COUNT(*) n FROM $table WHERE topic_id IN ($ids) GROUP BY topic_id") as $r) {
                    $usage[(int) $r['topic_id']] = ($usage[(int) $r['topic_id']] ?? 0) + (int) $r['n'];
                }
            }
        }

        $this->view('topics/index', [
            'pageTitle' => t('Topics'),
            'topics'    => $topics,
            'usage'     => $usage,
            'grade'     => $grade,
            'subject'   => $subject,
            'subjects'  => $subjects,
        ]);
    }

    /** POST /topics */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ROLES);
        verifyCsrfToken();

        [$data, $error] = $this->readForm();
        $back = '/topics?grade=' . $data['grade'] . '&subject=' . rawurlencode($data['subject']);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect($back);
        }

        try {
            $this->db->prepare(
                "INSERT INTO topics (grade, subject, unit_no, title, title_si, sort_order, created_by)
                 VALUES (:g, :s, :u, :t, :ts, :o, :by)"
            )->execute([
                ':g' => $data['grade'], ':s' => $data['subject'], ':u' => $data['unit_no'], ':t' => $data['title'],
                ':ts' => $data['title_si'], ':o' => $data['unit_no'] ?? 0, ':by' => (int) session_get('user_id'),
            ]);
            $this->flash('success', t('Topic added.'));
        } catch (\PDOException $e) {
            $this->flash('error', $e->getCode() === '23000' ? t('That topic already exists for this grade.') : t('The topic could not be saved.'));
        }
        $this->redirect($back);
    }

    /** POST /topics/{id}/edit */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ROLES);
        verifyCsrfToken();

        $topic = $this->find((int) $id);
        [$data, $error] = $this->readForm((int) $topic['grade'], (string) $topic['subject']);
        $back = '/topics?grade=' . (int) $topic['grade'] . '&subject=' . rawurlencode((string) $topic['subject']);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect($back);
        }
        try {
            $this->db->prepare("UPDATE topics SET unit_no = :u, title = :t, title_si = :ts, sort_order = :o WHERE id = :id")
                ->execute([':u' => $data['unit_no'], ':t' => $data['title'], ':ts' => $data['title_si'], ':o' => $data['unit_no'] ?? 0, ':id' => (int) $topic['id']]);
            $this->flash('success', t('Topic updated.'));
        } catch (\PDOException $e) {
            $this->flash('error', t('That topic already exists for this grade.'));
        }
        $this->redirect($back);
    }

    /** POST /topics/{id}/archive -- toggles; tagged work keeps its tag. */
    public function archive(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ROLES);
        verifyCsrfToken();

        $topic = $this->find((int) $id);
        $next = $topic['status'] === 'active' ? 'archived' : 'active';
        $this->db->prepare("UPDATE topics SET status = :s WHERE id = :id")->execute([':s' => $next, ':id' => (int) $topic['id']]);
        $this->flash('success', $next === 'archived' ? t('Topic hidden from the pickers. Work already tagged keeps it.') : t('Topic restored.'));
        $this->redirect('/topics?grade=' . (int) $topic['grade'] . '&subject=' . rawurlencode((string) $topic['subject']));
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM topics WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $topic = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$topic) {
            $this->flash('error', t('That topic does not exist.'));
            $this->redirect('/topics');
        }
        return $topic;
    }

    /** @return array{0: array<string, mixed>, 1: ?string} */
    private function readForm(?int $grade = null, ?string $subject = null): array
    {
        $grade ??= (int) ($_POST['grade'] ?? 0);
        $subject ??= trim((string) ($_POST['subject'] ?? 'Science'));
        $unit = trim((string) ($_POST['unit_no'] ?? ''));
        $data = [
            'grade'    => $grade,
            'subject'  => $subject,
            'unit_no'  => $unit === '' ? null : (int) $unit,
            'title'    => trim((string) ($_POST['title'] ?? '')),
            'title_si' => trim((string) ($_POST['title_si'] ?? '')) ?: null,
        ];
        if ($grade < 1 || $grade > 13 || !in_array($subject, $this->subjects(), true)) {
            return [$data, t('Choose a grade and subject.')];
        }
        if ($data['title'] === '' || mb_strlen($data['title']) > 255 || mb_strlen((string) $data['title_si']) > 255) {
            return [$data, t('Give the topic a name (up to 255 characters).')];
        }
        if ($data['unit_no'] !== null && ($data['unit_no'] < 1 || $data['unit_no'] > 99)) {
            return [$data, t('The unit number must be between 1 and 99.')];
        }
        return [$data, null];
    }

    /** Subjects actually taught, Science first. @return list<string> */
    private function subjects(): array
    {
        $list = $this->db->query("SELECT DISTINCT subject FROM classes WHERE status = 'active' ORDER BY subject")->fetchAll(\PDO::FETCH_COLUMN);
        $list = array_values(array_unique(array_merge(['Science'], array_map('strval', $list))));
        return $list;
    }
}
