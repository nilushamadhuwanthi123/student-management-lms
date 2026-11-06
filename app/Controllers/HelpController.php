<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AnnouncementService;
use App\Services\AttachmentService;
use App\Services\BranchScope;
use App\Services\MentionService;
use PDO;

/**
 * Questions to the teacher, and support requests to the branch office.
 *
 * One conversation model serves both, so the rules live in one place:
 *
 *   question  a student asks about a class they are enrolled in; the
 *             class's teacher (and the branch's admin/coordinator) can
 *             see and answer it.
 *   support   a student or parent asks the office (account access,
 *             enrolment, payments, schedules); the branch's admin,
 *             coordinator and staff can see and answer it.
 *
 * A conversation is visible to the student it concerns, to the parent
 * who opened it (or the student's guardian for the student's own
 * support requests), and to the staff described above -- never to
 * other students or other branches.
 */
class HelpController extends BaseController
{
    private const STATUSES = ['open', 'in_progress', 'answered', 'resolved', 'closed'];

    // ------------------------------------------------------------------
    // Students and parents
    // ------------------------------------------------------------------

    public function mine(): void
    {
        [$type, $me, $studentId] = $this->requireFamily();

        $sql = "SELECT t.*, c.class_name,
                       (SELECT COUNT(*) FROM help_messages m WHERE m.thread_id = t.id) AS messages
                  FROM help_threads t
                  LEFT JOIN classes c ON c.id = t.class_id
                 WHERE t.student_id = :s";
        if ($type === 'parent') {
            $sql .= " AND t.kind = 'support'";
        }
        $stmt = $this->db->prepare($sql . " ORDER BY COALESCE(t.last_reply_at, t.created_at) DESC");
        $stmt->execute([':s' => $studentId]);

        $classes = [];
        if ($type === 'student') {
            $c = $this->db->prepare(
                "SELECT c.id, c.class_name FROM enrollments e JOIN classes c ON c.id = e.class_id
                  WHERE e.student_id = :s AND e.enrollment_status = 'active' ORDER BY c.class_name"
            );
            $c->execute([':s' => $studentId]);
            $classes = $c->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->view('help/mine', [
            'pageTitle'  => 'Help & questions',
            'activePage' => $type === 'parent' ? 'family-help' : 'my-help',
            'threads'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'classes'    => $classes,
            'who'        => $type,
            'base'       => $type === 'parent' ? '/family/help' : '/my/help',
        ]);
    }

    public function create(): void
    {
        [$type, $me, $studentId] = $this->requireFamily();
        verifyCsrfToken();
        $base = $type === 'parent' ? '/family/help' : '/my/help';

        $kind = ($type === 'student' && ($_POST['kind'] ?? '') === 'question') ? 'question' : 'support';
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($subject === '' || $body === '') {
            $this->flash('error', t('Write a subject and your message.'));
            $this->redirect($base);
        }

        $classId = null;
        if ($kind === 'question') {
            $classId = (int) ($_POST['class_id'] ?? 0);
            $ok = $this->db->prepare("SELECT 1 FROM enrollments WHERE student_id = :s AND class_id = :c AND enrollment_status = 'active'");
            $ok->execute([':s' => $studentId, ':c' => $classId]);
            if (!$ok->fetchColumn()) {
                $this->flash('error', t('Choose one of your classes.'));
                $this->redirect($base);
            }
        }

        try {
            $file = (new AttachmentService())->store($_FILES['attachment'] ?? null, 'help');
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect($base);
        }

        $institute = $this->instituteOf($studentId, $classId);

        $this->db->beginTransaction();
        $this->db->prepare(
            "INSERT INTO help_threads (kind, student_id, parent_id, class_id, institute_id, subject, status, last_reply_at)
             VALUES (:k, :s, :p, :c, :i, :sub, 'open', NOW())"
        )->execute([
            ':k' => $kind, ':s' => $studentId, ':p' => $type === 'parent' ? $me : null,
            ':c' => $classId, ':i' => $institute, ':sub' => mb_substr($subject, 0, 255),
        ]);
        $threadId = (int) $this->db->lastInsertId();
        $this->addMessage($threadId, $type, $me, $body, $file);
        $this->db->commit();

        $this->notifyStaff($threadId, ($kind === 'question' ? 'New question: ' : 'New support request: ') . $subject);

        $this->flash('success', $kind === 'question' ? t('Your question has been sent to the teacher.') : t('Your request has been sent to the office.'));
        $this->redirect($base . '/' . $threadId);
    }

    public function showMine(string $id): void
    {
        [$type, $me, $studentId] = $this->requireFamily();
        $thread = $this->familyThread((int) $id, $type, $studentId);

        $this->view('help/thread', [
            'pageTitle'  => $thread['subject'],
            'activePage' => $type === 'parent' ? 'family-help' : 'my-help',
            'thread'     => $thread,
            'messages'   => $this->messages((int) $thread['id']),
            'base'       => $type === 'parent' ? '/family/help' : '/my/help',
            'staff'      => false,
        ]);
    }

    public function replyMine(string $id): void
    {
        [$type, $me, $studentId] = $this->requireFamily();
        verifyCsrfToken();
        $thread = $this->familyThread((int) $id, $type, $studentId);
        $base = ($type === 'parent' ? '/family/help/' : '/my/help/') . (int) $thread['id'];

        if (in_array($thread['status'], ['closed'], true)) {
            $this->flash('error', t('This conversation is closed. Start a new one if you need more help.'));
            $this->redirect($base);
        }
        $body = trim((string) ($_POST['body'] ?? ''));
        try {
            $file = (new AttachmentService())->store($_FILES['attachment'] ?? null, 'help');
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect($base);
        }
        if ($body === '') {
            $this->flash('error', t('Write your message first.'));
            $this->redirect($base);
        }

        $this->addMessage((int) $thread['id'], $type, $me, $body, $file);
        $this->setStatus((int) $thread['id'], 'open');
        $this->notifyStaff((int) $thread['id'], 'New reply: ' . $thread['subject']);

        $this->redirect($base);
    }

    public function resolveMine(string $id): void
    {
        [$type, $me, $studentId] = $this->requireFamily();
        verifyCsrfToken();
        $thread = $this->familyThread((int) $id, $type, $studentId);
        $this->setStatus((int) $thread['id'], 'resolved');
        $this->flash('success', t('Marked as resolved. Thank you!'));
        $this->redirect(($type === 'parent' ? '/family/help/' : '/my/help/') . (int) $thread['id']);
    }

    // ------------------------------------------------------------------
    // Staff
    // ------------------------------------------------------------------

    public function inbox(): void
    {
        $this->requireStaff();
        $status = in_array($_GET['status'] ?? '', self::STATUSES, true) ? $_GET['status'] : null;

        [$where, $params] = $this->staffScopeSql();
        $sql = "SELECT t.*, s.full_name AS student_name, s.student_id_display, c.class_name, i.name AS institute_name,
                       (SELECT COUNT(*) FROM help_messages m WHERE m.thread_id = t.id) AS messages
                  FROM help_threads t
                  JOIN students s ON s.id = t.student_id
                  LEFT JOIN classes c ON c.id = t.class_id
                  LEFT JOIN institutes i ON i.id = t.institute_id
                 WHERE {$where}";
        if ($status !== null) {
            $sql .= " AND t.status = :st";
            $params[':st'] = $status;
        }
        $stmt = $this->db->prepare($sql . " ORDER BY FIELD(t.status,'open','in_progress','answered','resolved','closed'), COALESCE(t.last_reply_at, t.created_at) DESC LIMIT 300");
        $stmt->execute($params);

        $this->view('help/inbox', [
            'pageTitle'  => 'Help desk',
            'activePage' => 'help',
            'threads'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'status'     => $status,
        ]);
    }

    public function show(string $id): void
    {
        $this->requireStaff();
        $thread = $this->staffThread((int) $id);

        $this->view('help/thread', [
            'pageTitle'  => $thread['subject'],
            'activePage' => 'help',
            'thread'     => $thread,
            'messages'   => $this->messages((int) $thread['id']),
            'base'       => '/help',
            'staff'      => true,
            'statuses'   => self::STATUSES,
        ]);
    }

    public function reply(string $id): void
    {
        $this->requireStaff();
        verifyCsrfToken();
        $thread = $this->staffThread((int) $id);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            $this->flash('error', t('Write your message first.'));
            $this->redirect('/help/' . (int) $thread['id']);
        }
        try {
            $file = (new AttachmentService())->store($_FILES['attachment'] ?? null, 'help');
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect('/help/' . (int) $thread['id']);
        }

        $this->addMessage((int) $thread['id'], 'user', (int) session_get('user_id'), $body, $file);
        $this->setStatus((int) $thread['id'], 'answered');

        $recipients = [['type' => 'student', 'id' => (int) $thread['student_id']]];
        if (!empty($thread['parent_id'])) {
            $recipients = [['type' => 'parent', 'id' => (int) $thread['parent_id']]];
        }
        (new AnnouncementService())->notifyAll($recipients, 'Reply: ' . $thread['subject'], mb_substr($body, 0, 200), 'help_thread', (int) $thread['id']);
        $this->notifyMentions($body, (int) $thread['id'], $thread['subject']);

        $this->flash('success', t('Reply sent.'));
        $this->redirect('/help/' . (int) $thread['id']);
    }

    public function status(string $id): void
    {
        $this->requireStaff();
        verifyCsrfToken();
        $thread = $this->staffThread((int) $id);
        $status = (string) ($_POST['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $this->setStatus((int) $thread['id'], $status);
            $this->audit('help_status', (int) $thread['id'], ['status' => $status]);
            if (in_array($status, ['resolved', 'closed', 'in_progress'], true)) {
                $to = !empty($thread['parent_id'])
                    ? [['type' => 'parent', 'id' => (int) $thread['parent_id']]]
                    : [['type' => 'student', 'id' => (int) $thread['student_id']]];
                (new AnnouncementService())->notifyAll($to, 'Status update: ' . $thread['subject'], 'Now: ' . str_replace('_', ' ', $status), 'help_thread', (int) $thread['id']);
            }
        }
        $this->redirect('/help/' . (int) $thread['id']);
    }

    /** Attachment of one message, for anyone allowed to see its conversation. */
    public function file(string $id): void
    {
        AuthMiddleware::requireAuth();
        $stmt = $this->db->prepare("SELECT * FROM help_messages WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($msg === false || empty($msg['file_path']) || !$this->canSeeThread((int) $msg['thread_id'])) {
            http_response_code(404);
            exit('Not found.');
        }

        (new AttachmentService())->send((string) $msg['file_path'], (string) ($msg['original_name'] ?: 'attachment'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array{0: string, 1: int, 2: int} [type, own id, student id] */
    private function requireFamily(): array
    {
        AuthMiddleware::requireAuth();
        $type = (string) session_get('user_type');
        $me = (int) session_get('user_id');

        if ($type === 'student') {
            return ['student', $me, $me];
        }
        if ($type === 'parent') {
            $stmt = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $stmt->execute([':p' => $me]);
            $sid = $stmt->fetchColumn();
            if ($sid !== false) {
                return ['parent', $me, (int) $sid];
            }
        }

        $this->flash('error', 'That area is for students and parents.');
        $this->redirect('/dashboard');
    }

    /** @return array<string, mixed> */
    private function familyThread(int $id, string $type, int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, c.class_name FROM help_threads t LEFT JOIN classes c ON c.id = t.class_id
              WHERE t.id = :id AND t.student_id = :s"
        );
        $stmt->execute([':id' => $id, ':s' => $studentId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);

        // A parent sees the family's support requests, not the student's
        // own questions to the teacher.
        if ($t === false || ($type === 'parent' && $t['kind'] !== 'support')) {
            $this->flash('error', t('That conversation was not found.'));
            $this->redirect($type === 'parent' ? '/family/help' : '/my/help');
        }

        return $t;
    }

    private function requireStaff(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user') {
            $this->redirect('/dashboard');
        }
        AuthMiddleware::requireRole(['super_admin', 'admin', 'coordinator', 'staff', 'teacher']);
    }

    /**
     * Teachers: questions about classes they teach. Office roles: support
     * requests for their branch, plus questions about their branch's
     * classes (so a question is never stranded if a teacher is away).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function staffScopeSql(): array
    {
        $scope = BranchScope::current();
        if ($scope->isGlobal()) {
            return ['1 = 1', []];
        }
        if ($scope->isTeacher()) {
            return ["t.kind = 'question'" . $scope->classSql('t.class_id'), []];
        }
        $role = (string) session_get('user_role');
        $support = "(t.kind = 'support'" . $scope->instituteSql('t.institute_id') . ')';
        if ($role === 'staff') {
            return [$support, []];
        }

        return ['(' . $support . " OR (t.kind = 'question'" . $scope->classSql('t.class_id') . '))', []];
    }

    /** @return array<string, mixed> */
    private function staffThread(int $id): array
    {
        [$where, $params] = $this->staffScopeSql();
        $stmt = $this->db->prepare(
            "SELECT t.*, s.full_name AS student_name, s.student_id_display, c.class_name, i.name AS institute_name,
                    p.parent_name
               FROM help_threads t
               JOIN students s ON s.id = t.student_id
               LEFT JOIN classes c ON c.id = t.class_id
               LEFT JOIN institutes i ON i.id = t.institute_id
               LEFT JOIN parents p ON p.id = t.parent_id
              WHERE t.id = :id AND {$where}"
        );
        $stmt->execute([':id' => $id] + $params);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t === false) {
            http_response_code(403);
            $this->flash('error', t('That conversation was not found.'));
            $this->redirect('/help');
        }

        return $t;
    }

    private function canSeeThread(int $threadId): bool
    {
        $type = (string) session_get('user_type');
        $me = (int) session_get('user_id');
        $stmt = $this->db->prepare("SELECT * FROM help_threads WHERE id = :id");
        $stmt->execute([':id' => $threadId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t === false) {
            return false;
        }
        if ($type === 'student') {
            return (int) $t['student_id'] === $me;
        }
        if ($type === 'parent') {
            $p = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $p->execute([':p' => $me]);
            return (int) $p->fetchColumn() === (int) $t['student_id'] && $t['kind'] === 'support';
        }
        if ($type !== 'user') {
            return false;
        }
        [$where, $params] = $this->staffScopeSql();
        $s = $this->db->prepare("SELECT 1 FROM help_threads t WHERE t.id = :id AND {$where}");
        $s->execute([':id' => $threadId] + $params);

        return (bool) $s->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    private function messages(int $threadId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*,
                    CASE m.author_type
                        WHEN 'user'    THEN (SELECT u.full_name FROM users u WHERE u.id = m.author_id)
                        WHEN 'student' THEN (SELECT s.full_name FROM students s WHERE s.id = m.author_id)
                        WHEN 'parent'  THEN (SELECT p.parent_name FROM parents p WHERE p.id = m.author_id)
                    END AS author_name
               FROM help_messages m WHERE m.thread_id = :t ORDER BY m.id ASC"
        );
        $stmt->execute([':t' => $threadId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array{0: string, 1: string}|null $file */
    private function addMessage(int $threadId, string $authorType, int $authorId, string $body, ?array $file): void
    {
        $this->db->prepare(
            "INSERT INTO help_messages (thread_id, author_type, author_id, body, file_path, original_name)
             VALUES (:t, :at, :a, :b, :fp, :fn)"
        )->execute([
            ':t' => $threadId, ':at' => $authorType, ':a' => $authorId, ':b' => mb_substr($body, 0, 5000),
            ':fp' => $file[0] ?? null, ':fn' => $file[1] ?? null,
        ]);
        $this->db->prepare("UPDATE help_threads SET last_reply_at = NOW() WHERE id = :t")->execute([':t' => $threadId]);
    }

    private function setStatus(int $threadId, string $status): void
    {
        $this->db->prepare("UPDATE help_threads SET status = :s WHERE id = :t")->execute([':s' => $status, ':t' => $threadId]);
    }

    private function instituteOf(int $studentId, ?int $classId): ?int
    {
        if ($classId !== null) {
            $s = $this->db->prepare("SELECT institute_id FROM classes WHERE id = :c");
            $s->execute([':c' => $classId]);
        } else {
            $s = $this->db->prepare(
                "SELECT c.institute_id FROM enrollments e JOIN classes c ON c.id = e.class_id
                  WHERE e.student_id = :s ORDER BY (e.enrollment_status = 'active') DESC, e.id DESC LIMIT 1"
            );
            $s->execute([':s' => $studentId]);
        }
        $id = $s->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** The class teacher for a question; the branch office for support. */
    private function notifyStaff(int $threadId, string $title): void
    {
        $t = $this->db->prepare("SELECT t.*, c.teacher_id FROM help_threads t LEFT JOIN classes c ON c.id = t.class_id WHERE t.id = :id");
        $t->execute([':id' => $threadId]);
        $thread = $t->fetch(PDO::FETCH_ASSOC);
        if ($thread === false) {
            return;
        }

        $ids = [];
        if ($thread['kind'] === 'question' && !empty($thread['teacher_id'])) {
            $ids[] = (int) $thread['teacher_id'];
        } elseif ($thread['institute_id'] !== null) {
            $s = $this->db->prepare(
                "SELECT ui.user_id FROM user_institutes ui JOIN users u ON u.id = ui.user_id
                  WHERE ui.institute_id = :i AND u.status = 'active' AND u.role IN ('admin','coordinator','staff')"
            );
            $s->execute([':i' => (int) $thread['institute_id']]);
            $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($ids === []) {
            // Nobody assigned: the super admin sees it, rather than no one.
            $ids = array_map('intval', $this->db->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN));
        }

        (new AnnouncementService())->notifyAll(
            array_map(static fn (int $id): array => ['type' => 'user', 'id' => $id], array_unique($ids)),
            mb_substr($title, 0, 480),
            'Open the help desk to reply.',
            'help_thread',
            $threadId
        );
    }

    /**
     * "@Full Name" inside a staff reply notifies that colleague directly
     * -- a personal nudge on top of the normal student/parent notice,
     * through the same AnnouncementService::notifyAll() every module
     * already uses. See MentionService for the scoping rule.
     */
    private function notifyMentions(string $body, int $threadId, string $subject): void
    {
        $ids = (new MentionService($this->db))->mentionedStaffIds($body, (int) session_get('user_id'));
        if ($ids === []) {
            return;
        }
        (new AnnouncementService())->notifyAll(
            array_map(static fn (int $id): array => ['type' => 'user', 'id' => $id], $ids),
            t('You were mentioned') . ': ' . $subject,
            mb_substr($body, 0, 200),
            'help_thread',
            $threadId
        );
    }

    private function audit(string $action, int $id, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'help_thread', $id, $details);
        } catch (\Throwable $e) {
            error_log('Help audit failed: ' . $e->getMessage());
        }
    }
}
