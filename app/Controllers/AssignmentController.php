<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AnnouncementService;
use App\Services\AttachmentService;
use App\Services\BranchScope;
use App\Services\ContentAccessService;
use PDO;

/**
 * Homework: the teacher sets it for a class, students hand it in, the
 * teacher marks it with feedback, and parents can see how it went.
 *
 * Who may do what:
 *   teacher            their own classes
 *   admin              their branch's classes (set, mark)
 *   coordinator        their branch's classes (read only)
 *   super admin        every class
 *   student            assignments of classes they are actively enrolled
 *                      in, for months whose fee is settled
 *   parent             read-only view of their child's assignments
 */
class AssignmentController extends BaseController
{
    private const STAFF_ROLES = ['super_admin', 'admin', 'coordinator', 'teacher'];

    // ------------------------------------------------------------------
    // Teacher / staff side
    // ------------------------------------------------------------------

    public function index(string $classId): void
    {
        $class = $this->requireClass((int) $classId);

        $stmt = $this->db->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS handed_in,
                    (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.marks IS NULL) AS to_mark
               FROM assignments a
              WHERE a.class_id = :c AND a.status <> 'archived'
              ORDER BY a.due_at DESC"
        );
        $stmt->execute([':c' => (int) $class['id']]);

        $this->view('assignments/index', [
            'pageTitle'   => 'Assignments',
            'activePage'  => 'teaching',
            'class'       => $class,
            'assignments' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'enrolled'    => $this->enrolledCount((int) $class['id']),
        ]);
    }

    public function create(string $classId): void
    {
        $class = $this->requireClass((int) $classId, true);

        $this->view('assignments/create', [
            'pageTitle'  => 'Set an assignment',
            'activePage' => 'teaching',
            'class'      => $class,
        ]);
    }

    public function store(string $classId): void
    {
        $class = $this->requireClass((int) $classId, true);
        verifyCsrfToken();

        $title = trim((string) ($_POST['title'] ?? ''));
        $due = trim((string) ($_POST['due_at'] ?? ''));
        $marks = (float) ($_POST['total_marks'] ?? 10);
        $publish = ($_POST['action'] ?? '') === 'publish';

        $dueTs = strtotime($due);
        if ($title === '' || $dueTs === false || $marks <= 0 || $marks > 1000) {
            $this->flash('error', t('Give the assignment a title, a due date and marks between 1 and 1000.'));
            $this->redirect('/classes/' . (int) $class['id'] . '/assignments/create');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO assignments (class_id, title, instructions, due_at, total_marks, allow_file, status, created_by)
             VALUES (:c, :t, :i, :d, :m, :f, :s, :by)"
        );
        $stmt->execute([
            ':c'  => (int) $class['id'],
            ':t'  => mb_substr($title, 0, 255),
            ':i'  => trim((string) ($_POST['instructions'] ?? '')) ?: null,
            ':d'  => date('Y-m-d H:i:s', $dueTs),
            ':m'  => $marks,
            ':f'  => isset($_POST['allow_file']) ? 1 : 0,
            ':s'  => $publish ? 'published' : 'draft',
            ':by' => (int) session_get('user_id'),
        ]);
        $id = (int) $this->db->lastInsertId();
        (new \App\Services\TopicService($this->db))->tag('assignments', $id, (int) $class['id'], $_POST['topic_id'] ?? null);

        $this->audit('create_assignment', $id, ['class_id' => (int) $class['id'], 'published' => $publish]);
        if ($publish) {
            $this->announce($id);
        }

        $this->flash('success', $publish ? t('Assignment published. The class has been told.') : t('Saved as a draft.'));
        $this->redirect('/classes/' . (int) $class['id'] . '/assignments/' . $id);
    }

    public function show(string $classId, string $id): void
    {
        $class = $this->requireClass((int) $classId);
        $assignment = $this->requireAssignment((int) $id, (int) $class['id']);

        // Everyone enrolled, with their hand-in if there is one, so the
        // teacher sees who has not handed in as well as who has.
        $stmt = $this->db->prepare(
            "SELECT st.id AS student_id, st.full_name, st.student_id_display,
                    sub.id AS submission_id, sub.answer_text, sub.original_name, sub.file_path,
                    sub.submitted_at, sub.is_late, sub.marks, sub.feedback, sub.marked_at
               FROM enrollments e
               JOIN students st ON st.id = e.student_id
               LEFT JOIN assignment_submissions sub
                      ON sub.student_id = st.id AND sub.assignment_id = :a
              WHERE e.class_id = :c AND e.enrollment_status = 'active'
              ORDER BY (sub.id IS NULL), sub.marks IS NOT NULL, st.full_name"
        );
        $stmt->execute([':a' => (int) $assignment['id'], ':c' => (int) $class['id']]);

        $this->view('assignments/show', [
            'pageTitle'  => $assignment['title'],
            'activePage' => 'teaching',
            'class'      => $class,
            'assignment' => $assignment,
            'roster'     => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'canEdit'    => $this->mayEdit($class),
        ]);
    }

    public function publish(string $classId, string $id): void
    {
        $class = $this->requireClass((int) $classId, true);
        verifyCsrfToken();
        $assignment = $this->requireAssignment((int) $id, (int) $class['id']);

        $this->db->prepare("UPDATE assignments SET status = 'published' WHERE id = :id")->execute([':id' => (int) $assignment['id']]);
        $this->audit('publish_assignment', (int) $assignment['id']);
        $this->announce((int) $assignment['id']);

        $this->flash('success', t('Assignment published. The class has been told.'));
        $this->redirect('/classes/' . (int) $class['id'] . '/assignments/' . (int) $assignment['id']);
    }

    public function archive(string $classId, string $id): void
    {
        $class = $this->requireClass((int) $classId, true);
        verifyCsrfToken();
        $assignment = $this->requireAssignment((int) $id, (int) $class['id']);

        // Archived, not deleted: hand-ins and marks stay on the record.
        $this->db->prepare("UPDATE assignments SET status = 'archived' WHERE id = :id")->execute([':id' => (int) $assignment['id']]);
        $this->audit('archive_assignment', (int) $assignment['id']);

        $this->flash('success', t('Assignment archived. Hand-ins and marks are kept.'));
        $this->redirect('/classes/' . (int) $class['id'] . '/assignments');
    }

    public function mark(string $classId, string $id): void
    {
        $class = $this->requireClass((int) $classId, true);
        verifyCsrfToken();
        $assignment = $this->requireAssignment((int) $id, (int) $class['id']);

        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $marks = ($_POST['marks'] ?? '') === '' ? null : (float) $_POST['marks'];
        $feedback = trim((string) ($_POST['feedback'] ?? '')) ?: null;

        if ($marks !== null && ($marks < 0 || $marks > (float) $assignment['total_marks'])) {
            $this->flash('error', t('Marks must be between 0 and {max}.', ['max' => rtrim(rtrim((string) $assignment['total_marks'], '0'), '.')]));
            $this->redirect('/classes/' . (int) $class['id'] . '/assignments/' . (int) $assignment['id']);
        }

        $before = $this->db->prepare("SELECT marks FROM assignment_submissions WHERE id = :s AND assignment_id = :a");
        $before->execute([':s' => $submissionId, ':a' => (int) $assignment['id']]);
        $old = $before->fetchColumn();
        if ($old === false) {
            $this->flash('error', t('That hand-in was not found.'));
            $this->redirect('/classes/' . (int) $class['id'] . '/assignments/' . (int) $assignment['id']);
        }

        $this->db->prepare(
            "UPDATE assignment_submissions
                SET marks = :m, feedback = :f, marked_by = :by, marked_at = NOW()
              WHERE id = :s AND assignment_id = :a"
        )->execute([':m' => $marks, ':f' => $feedback, ':by' => (int) session_get('user_id'), ':s' => $submissionId, ':a' => (int) $assignment['id']]);

        // Marks changes are audited with the old and new value.
        $this->audit('mark_assignment', $submissionId, ['assignment_id' => (int) $assignment['id'], 'from' => $old, 'to' => $marks]);

        $stu = $this->db->prepare("SELECT student_id FROM assignment_submissions WHERE id = :s");
        $stu->execute([':s' => $submissionId]);
        $studentId = (int) $stu->fetchColumn();
        (new AnnouncementService())->notifyAll(
            [['type' => 'student', 'id' => $studentId]],
            'Your homework has been marked: ' . $assignment['title'],
            $marks !== null ? 'Marks: ' . rtrim(rtrim((string) $marks, '0'), '.') . ' / ' . rtrim(rtrim((string) $assignment['total_marks'], '0'), '.') : 'Feedback added.',
            'assignment',
            (int) $assignment['id']
        );

        $this->flash('success', t('Marks saved.'));
        $this->redirect('/classes/' . (int) $class['id'] . '/assignments/' . (int) $assignment['id']);
    }

    /** Download a hand-in's file: the owner, their parent, or staff for that class. */
    public function file(string $id): void
    {
        AuthMiddleware::requireAuth();
        $stmt = $this->db->prepare(
            "SELECT s.*, a.class_id FROM assignment_submissions s
               JOIN assignments a ON a.id = s.assignment_id
              WHERE s.id = :id"
        );
        $stmt->execute([':id' => (int) $id]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        $type = (string) session_get('user_type');
        $me = (int) session_get('user_id');
        $allowed = false;
        if ($sub !== false && !empty($sub['file_path'])) {
            if ($type === 'student') {
                $allowed = (int) $sub['student_id'] === $me;
            } elseif ($type === 'parent') {
                $allowed = $this->parentStudentId() === (int) $sub['student_id'];
            } elseif ($type === 'user') {
                $allowed = in_array((string) session_get('user_role'), self::STAFF_ROLES, true)
                    && BranchScope::current()->allowsClass((int) $sub['class_id']);
            }
        }
        if (!$allowed) {
            http_response_code(404);
            exit('Not found.');
        }

        (new AttachmentService())->send((string) $sub['file_path'], (string) ($sub['original_name'] ?: 'homework'));
    }

    // ------------------------------------------------------------------
    // Student side
    // ------------------------------------------------------------------

    public function mine(): void
    {
        $studentId = $this->requireStudent();

        $this->view('assignments/mine', [
            'pageTitle'   => 'My assignments',
            'activePage'  => 'my-assignments',
            'assignments' => $this->forStudent($studentId),
            'now'         => date('Y-m-d H:i:s'),
        ]);
    }

    public function sit(string $id): void
    {
        $studentId = $this->requireStudent();
        $assignment = $this->studentAssignment($studentId, (int) $id);

        $stmt = $this->db->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = :a AND student_id = :s");
        $stmt->execute([':a' => (int) $assignment['id'], ':s' => $studentId]);

        $this->view('assignments/sit', [
            'pageTitle'  => $assignment['title'],
            'activePage' => 'my-assignments',
            'assignment' => $assignment,
            'submission' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null,
            'now'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function submit(string $id): void
    {
        $studentId = $this->requireStudent();
        verifyCsrfToken();
        $assignment = $this->studentAssignment($studentId, (int) $id);
        $back = '/my/assignments/' . (int) $assignment['id'];

        $existing = $this->db->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = :a AND student_id = :s");
        $existing->execute([':a' => (int) $assignment['id'], ':s' => $studentId]);
        $prev = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

        // Once marked, the hand-in is final; the mark belongs to what was marked.
        if ($prev !== null && $prev['marks'] !== null) {
            $this->flash('error', t('This homework has already been marked, so it cannot be changed.'));
            $this->redirect($back);
        }

        $text = trim((string) ($_POST['answer_text'] ?? ''));
        try {
            $file = (int) $assignment['allow_file'] === 1
                ? (new AttachmentService())->store($_FILES['attachment'] ?? null, 'assignments')
                : null;
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect($back);
        }

        if ($text === '' && $file === null && $prev === null) {
            $this->flash('error', t('Write an answer or attach a file before handing in.'));
            $this->redirect($back);
        }

        $late = strtotime((string) $assignment['due_at']) < time() ? 1 : 0;
        $this->db->prepare(
            "INSERT INTO assignment_submissions (assignment_id, student_id, answer_text, file_path, original_name, submitted_at, is_late)
             VALUES (:a, :s, :t, :fp, :fn, NOW(), :late)
             ON DUPLICATE KEY UPDATE
                answer_text = VALUES(answer_text),
                file_path = COALESCE(VALUES(file_path), file_path),
                original_name = COALESCE(VALUES(original_name), original_name),
                submitted_at = NOW(), is_late = VALUES(is_late)"
        )->execute([
            ':a' => (int) $assignment['id'], ':s' => $studentId, ':t' => $text !== '' ? $text : null,
            ':fp' => $file[0] ?? null, ':fn' => $file[1] ?? null, ':late' => $late,
        ]);

        $this->flash('success', $late ? t('Handed in. It is after the due date, so it is marked late.') : t('Handed in. Well done!'));
        $this->redirect($back);
    }

    // ------------------------------------------------------------------
    // Parent side
    // ------------------------------------------------------------------

    public function family(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'parent') {
            $this->redirect('/dashboard');
        }
        $studentId = $this->parentStudentId();
        if ($studentId === null) {
            $this->flash('error', t('No student is linked to this login. Contact the office.'));
            $this->redirect('/logout');
        }

        $name = $this->db->prepare("SELECT full_name FROM students WHERE id = :s");
        $name->execute([':s' => $studentId]);

        $this->view('assignments/family', [
            'pageTitle'   => 'Assignments',
            'activePage'  => 'family-assignments',
            'childName'   => (string) $name->fetchColumn(),
            'assignments' => $this->forStudent($studentId),
            'now'         => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Published assignments of the student's active classes, with their
     * own hand-in and mark. Months whose fee is unpaid are listed as
     * locked rather than hidden, the same as material and quizzes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function forStudent(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.title, a.due_at, a.total_marks, a.class_id, c.class_name,
                    sub.submitted_at, sub.is_late, sub.marks, sub.feedback
               FROM assignments a
               JOIN classes c ON c.id = a.class_id
               JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = :s1 AND e.enrollment_status = 'active'
               LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = :s2
              WHERE a.status = 'published'
              ORDER BY (sub.id IS NOT NULL), a.due_at ASC"
        );
        $stmt->execute([':s1' => $studentId, ':s2' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $access = new ContentAccessService();
        foreach ($rows as &$r) {
            $r['locked'] = !$access->hasPaidFor($studentId, (int) $r['class_id'], date('Y-m-01', strtotime((string) $r['due_at'])));
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function studentAssignment(int $studentId, int $id): array
    {
        foreach ($this->forStudent($studentId) as $row) {
            if ((int) $row['id'] === $id) {
                if ($row['locked']) {
                    $this->flash('error', t("Settle this month's fee to open the class material."));
                    $this->redirect('/my/assignments');
                }
                $full = $this->db->prepare("SELECT a.*, c.class_name FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = :id");
                $full->execute([':id' => $id]);
                return $full->fetch(PDO::FETCH_ASSOC);
            }
        }

        $this->flash('error', t('That assignment is not available.'));
        $this->redirect('/my/assignments');
    }

    /** @return array<string, mixed> */
    private function requireClass(int $classId, bool $editing = false): array
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user' || !in_array((string) session_get('user_role'), self::STAFF_ROLES, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/dashboard');
        }

        $stmt = $this->db->prepare("SELECT c.*, i.name AS institute_name FROM classes c LEFT JOIN institutes i ON i.id = c.institute_id WHERE c.id = :id");
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class === false || !BranchScope::current()->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', 'That is not one of your classes.');
            $this->redirect('/teaching');
        }
        if ($editing && !$this->mayEdit($class)) {
            http_response_code(403);
            $this->flash('error', t('Only the class teacher or an admin can set homework for this class.'));
            $this->redirect('/classes/' . $classId . '/assignments');
        }

        return $class;
    }

    /** Setting homework: the class's teacher, admins and the super admin. */
    private function mayEdit(array $class): bool
    {
        $role = (string) session_get('user_role');
        // A teacher attached through user_classes teaches the class just
        // as much as one named in classes.teacher_id, so ask BranchScope
        // rather than reading that one column.
        return in_array($role, ['super_admin', 'admin'], true)
            || ($role === 'teacher'
                && \App\Services\BranchScope::current()->allowsClass((int) ($class['id'] ?? 0)));
    }

    /** @return array<string, mixed> */
    private function requireAssignment(int $id, int $classId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM assignments WHERE id = :id AND class_id = :c");
        $stmt->execute([':id' => $id, ':c' => $classId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($a === false) {
            $this->flash('error', t('That assignment is not available.'));
            $this->redirect('/classes/' . $classId . '/assignments');
        }

        return $a;
    }

    private function requireStudent(): int
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'student') {
            $this->flash('error', 'That area is for students.');
            $this->redirect('/dashboard');
        }

        return (int) session_get('user_id');
    }

    private function parentStudentId(): ?int
    {
        $stmt = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
        $stmt->execute([':p' => (int) session_get('user_id')]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function enrolledCount(int $classId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM enrollments WHERE class_id = :c AND enrollment_status = 'active'");
        $stmt->execute([':c' => $classId]);

        return (int) $stmt->fetchColumn();
    }

    /** Tell the class and their parents, once. */
    private function announce(int $assignmentId): void
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, c.class_name FROM assignments a JOIN classes c ON c.id = a.class_id
              WHERE a.id = :id AND a.status = 'published' AND a.notified_at IS NULL"
        );
        $stmt->execute([':id' => $assignmentId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($a === false) {
            return;
        }

        $mark = $this->db->prepare("UPDATE assignments SET notified_at = NOW() WHERE id = :id AND notified_at IS NULL");
        $mark->execute([':id' => $assignmentId]);
        if ($mark->rowCount() === 0) {
            return; // someone else announced it a moment ago
        }

        $service = new AnnouncementService();
        $service->notifyAll(
            $service->familiesFor((int) $a['class_id'], null),
            'New homework: ' . $a['title'],
            $a['class_name'] . ' - due ' . date('j M, g:ia', strtotime((string) $a['due_at'])) . '.',
            'assignment',
            $assignmentId
        );
    }

    private function audit(string $action, int $entityId, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'assignment', $entityId, $details);
        } catch (\Throwable $e) {
            error_log('Assignment audit failed: ' . $e->getMessage());
        }
    }
}
