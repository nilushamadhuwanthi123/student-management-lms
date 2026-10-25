<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\NoticeService;
use PDO;

/**
 * Notice board.
 *
 * Posting rights follow the branch scope:
 *   super admin        every branch, one branch, or one class
 *   admin/coordinator  their own branch, or a class in it
 *   teacher            the classes they teach
 *   staff              read only
 */
class NoticeController extends BaseController
{
    private const POSTERS = ['super_admin', 'admin', 'coordinator', 'teacher'];

    public function index(): void
    {
        $this->requireStaffUser();
        $scope = BranchScope::current();

        $sql = "SELECT n.*, c.class_name, i.name AS institute_name, u.full_name AS author
                  FROM notices n
                  LEFT JOIN classes c ON c.id = n.class_id
                  LEFT JOIN institutes i ON i.id = n.institute_id
                  LEFT JOIN users u ON u.id = n.created_by
                 WHERE n.status <> 'archived'";
        if (!$scope->isGlobal()) {
            // What this person may manage, plus staff notices addressed to them.
            $sql .= " AND (n.audience = 'all' AND n.for_staff = 1
                       OR (n.audience = 'branch'" . $scope->instituteSql('n.institute_id') . ")
                       OR (n.audience = 'class'" . $scope->classSql('n.class_id') . "))";
        }
        $rows = $this->db->query($sql . " ORDER BY COALESCE(n.publish_at, n.created_at) DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['can_manage'] = $this->mayPost($r['audience'], $r['institute_id'] !== null ? (int) $r['institute_id'] : null, $r['class_id'] !== null ? (int) $r['class_id'] : null);
        }

        $this->view('notices/index', [
            'pageTitle'  => 'Notice board',
            'activePage' => 'notices',
            'notices'    => $rows,
            'canPost'    => in_array((string) session_get('user_role'), self::POSTERS, true),
        ]);
    }

    public function create(): void
    {
        $this->requirePoster();
        $scope = BranchScope::current();

        $this->view('notices/create', [
            'pageTitle'  => 'New notice',
            'activePage' => 'notices',
            'institutes' => $scope->isTeacher() ? [] : $this->db->query(
                "SELECT id, name FROM institutes WHERE status = 'active'" . $scope->instituteSql('id') . " ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'classes'    => $this->db->query(
                "SELECT id, class_name FROM classes WHERE status = 'active'" . $scope->classSql('id') . " ORDER BY grade, class_name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'isGlobal'   => $scope->isGlobal(),
        ]);
    }

    public function store(): void
    {
        $this->requirePoster();
        verifyCsrfToken();

        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $audience = in_array($_POST['audience'] ?? '', ['all', 'branch', 'class'], true) ? $_POST['audience'] : 'class';
        $instituteId = $audience === 'branch' ? (int) ($_POST['institute_id'] ?? 0) : null;
        $classId = $audience === 'class' ? (int) ($_POST['class_id'] ?? 0) : null;
        $publishAt = trim((string) ($_POST['publish_at'] ?? ''));
        $publishTs = $publishAt !== '' ? strtotime($publishAt) : null;
        $publish = ($_POST['action'] ?? '') === 'publish';

        $students = isset($_POST['for_students']) ? 1 : 0;
        $parents = isset($_POST['for_parents']) ? 1 : 0;
        $staff = isset($_POST['for_staff']) ? 1 : 0;

        if ($title === '' || $body === '' || $publishTs === false || ($students + $parents + $staff) === 0) {
            $this->flash('error', t('Write a title and message, and choose who should see it.'));
            $this->redirect('/notices/create');
        }
        if (!$this->mayPost($audience, $instituteId, $classId)) {
            http_response_code(403);
            $this->flash('error', t('You can only post notices for your own branch or classes.'));
            $this->redirect('/notices/create');
        }

        $this->db->prepare(
            "INSERT INTO notices (title, body, audience, institute_id, class_id, for_students, for_parents, for_staff, status, publish_at, created_by)
             VALUES (:t, :b, :a, :i, :c, :fs, :fp, :ff, :st, :pa, :by)"
        )->execute([
            ':t' => mb_substr($title, 0, 255), ':b' => $body, ':a' => $audience, ':i' => $instituteId, ':c' => $classId,
            ':fs' => $students, ':fp' => $parents, ':ff' => $staff,
            ':st' => $publish ? 'published' : 'draft',
            ':pa' => $publishTs !== null ? date('Y-m-d H:i:s', $publishTs) : null,
            ':by' => (int) session_get('user_id'),
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->audit('create_notice', $id, ['audience' => $audience, 'published' => $publish]);

        $sent = $publish ? (new NoticeService($this->db))->announce($id) : 0;
        $this->flash('success', $publish
            ? ($publishTs !== null && $publishTs > time() ? t('Scheduled. It appears and is announced at the chosen time.') : t('Published to {n} people.', ['n' => $sent]))
            : t('Saved as a draft.'));
        $this->redirect('/notices');
    }

    public function publish(string $id): void
    {
        $notice = $this->managedNotice((int) $id);
        verifyCsrfToken();
        $this->db->prepare("UPDATE notices SET status = 'published' WHERE id = :id")->execute([':id' => (int) $notice['id']]);
        $sent = (new NoticeService($this->db))->announce((int) $notice['id']);
        $this->audit('publish_notice', (int) $notice['id']);
        $this->flash('success', t('Published to {n} people.', ['n' => $sent]));
        $this->redirect('/notices');
    }

    public function archive(string $id): void
    {
        $notice = $this->managedNotice((int) $id);
        verifyCsrfToken();
        $this->db->prepare("UPDATE notices SET status = 'archived' WHERE id = :id")->execute([':id' => (int) $notice['id']]);
        $this->audit('archive_notice', (int) $notice['id']);
        $this->flash('success', t('Notice archived.'));
        $this->redirect('/notices');
    }

    // Students and parents --------------------------------------------

    public function mine(): void
    {
        AuthMiddleware::requireAuth();
        $type = (string) session_get('user_type');
        if ($type === 'student') {
            $studentId = (int) session_get('user_id');
        } elseif ($type === 'parent') {
            $s = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $s->execute([':p' => (int) session_get('user_id')]);
            $studentId = (int) $s->fetchColumn();
        } else {
            $this->redirect('/notices');
        }

        $this->view('notices/mine', [
            'pageTitle'  => 'Notices',
            'activePage' => $type === 'parent' ? 'family-notices' : 'my-notices',
            'notices'    => (new NoticeService($this->db))->forStudent($studentId, $type === 'parent'),
        ]);
    }

    // Helpers -----------------------------------------------------------

    private function requireStaffUser(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user') {
            $this->redirect('/dashboard');
        }
    }

    private function requirePoster(): void
    {
        $this->requireStaffUser();
        if (!in_array((string) session_get('user_role'), self::POSTERS, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/notices');
        }
    }

    private function mayPost(string $audience, ?int $instituteId, ?int $classId): bool
    {
        $scope = BranchScope::current();
        if (!in_array((string) session_get('user_role'), self::POSTERS, true)) {
            return false;
        }

        return match ($audience) {
            'all'    => $scope->isGlobal(),
            'branch' => !$scope->isTeacher() && $instituteId !== null && $scope->allowsInstitute($instituteId),
            'class'  => $classId !== null && $scope->allowsClass($classId),
            default  => false,
        };
    }

    /** @return array<string, mixed> */
    private function managedNotice(int $id): array
    {
        $this->requirePoster();
        $s = $this->db->prepare("SELECT * FROM notices WHERE id = :id");
        $s->execute([':id' => $id]);
        $n = $s->fetch(PDO::FETCH_ASSOC);
        if ($n === false || !$this->mayPost($n['audience'], $n['institute_id'] !== null ? (int) $n['institute_id'] : null, $n['class_id'] !== null ? (int) $n['class_id'] : null)) {
            http_response_code(403);
            $this->flash('error', t('That notice is not yours to change.'));
            $this->redirect('/notices');
        }

        return $n;
    }

    private function audit(string $action, int $id, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'notice', $id, $details);
        } catch (\Throwable $e) {
            error_log('Notice audit failed: ' . $e->getMessage());
        }
    }
}
