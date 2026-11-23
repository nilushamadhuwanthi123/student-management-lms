<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ContentAccessService;
use App\Services\ProgressService;
use PDO;

/**
 * What a guardian sees: their own child, and nothing else.
 *
 * Read-only by design. A parent can see how their child is doing and
 * what is owed, but cannot change a record, sit a quiz, or open the
 * class material — that last one matters, because material the family
 * has not paid for stays shut whoever is looking at it.
 *
 * The child is resolved from the guardian row in the session, never
 * from a student id in the URL. There is therefore no id to increment
 * to reach somebody else's child.
 */
class ParentPortalController extends BaseController
{
    /**
     * The child's standing at a glance.
     */
    public function overview(): void
    {
        [$parentId, $student] = $this->requireParent();

        $progress = new ProgressService();
        $access = new ContentAccessService();
        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.monthly_fee,
                    i.name AS institute_name,
                    u.full_name AS teacher_name,
                    e.scholarship_type
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               LEFT JOIN users u ON u.id = c.teacher_id
              WHERE e.student_id = :student_id
                AND e.enrollment_status = 'active'
              ORDER BY c.grade, c.class_name"
        );
        $stmt->execute([':student_id' => $student['id']]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classes as &$class) {
            $class['progress'] = $progress->forStudent((int) $student['id'], (int) $class['id']);
            $class['fee'] = $access->gateStatus((int) $student['id'], (int) $class['id'], $month);
        }
        unset($class);

        $this->logView($parentId, (int) $student['id'], 'overview');

        $this->view('portal/parent-overview', [
            'pageTitle' => 'My child',
            'student'   => $student,
            // Same figure as the QR terminal and the student's own
            // profile; one service answers all three so the office is
            // never arguing with a parent about which screen is right.
            'feeDue'    => (new \App\Services\PaymentDueService($this->db))->summary((int) $student['id']),
            'classes'   => $classes,
            'overall'   => $progress->forStudent((int) $student['id']),
            'month'     => $month,
            'recent'    => $this->recentMarks((int) $student['id']),
            'attendance' => $this->recentAttendance((int) $student['id']),
        ], 'layouts/admin');
    }

    /**
     * The guardian's own account: who they are signed in as, the numbers
     * the office holds for them, and the child the login is linked to.
     * Read-only apart from changing the password -- the login number is
     * the parent's identity, so the office changes it, not the parent.
     */
    public function account(): void
    {
        [$parentId, $student] = $this->requireParent();

        $p = $this->db->prepare("SELECT id, parent_name, phone1, phone2, whatsapp, profile_photo, status, created_at FROM parents WHERE id = :id");
        $p->execute([':id' => $parentId]);
        $parent = $p->fetch(PDO::FETCH_ASSOC) ?: [];

        $c = $this->db->prepare(
            "SELECT c.class_name, c.grade, i.name AS institute_name
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE e.student_id = :s AND e.enrollment_status = 'active'
              ORDER BY c.grade, c.class_name"
        );
        $c->execute([':s' => (int) $student['id']]);

        $this->view('portal/parent-account', [
            'pageTitle'  => t('My account'),
            'activePage' => 'family-account',
            'parent'     => $parent,
            'student'    => $student,
            'classes'    => $c->fetchAll(PDO::FETCH_ASSOC),
        ], 'layouts/admin');
    }

    /**
     * Let a guardian set their own small profile photo. Nothing else
     * about the account can be changed here -- the photo is purely
     * cosmetic, so there is no reason to route it through the office.
     *
     * POST /family/account/photo
     */
    public function uploadPhoto(): void
    {
        [$parentId] = $this->requireParent();
        verifyCsrfToken();

        if (empty($_FILES['profile_photo']['name']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', t('Please choose a photo to upload.'));
            $this->redirect('/family/account');
        }

        if ($_FILES['profile_photo']['size'] > 3 * 1024 * 1024) {
            $this->flash('error', t('That photo is larger than 3 MB. Please choose a smaller one.'));
            $this->redirect('/family/account');
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['profile_photo']['tmp_name']);
        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mimeType])) {
            $this->flash('error', t('Photo must be a JPG, PNG, or WEBP image.'));
            $this->redirect('/family/account');
        }

        $targetDir = BASE_PATH . '/public/uploads/profiles';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename   = 'parent_' . $parentId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mimeType];
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
            $this->flash('error', t('Could not save the photo. Please try again.'));
            $this->redirect('/family/account');
        }

        $old = $this->db->prepare('SELECT profile_photo FROM parents WHERE id = :id');
        $old->execute([':id' => $parentId]);
        $oldPhoto = (string) ($old->fetchColumn() ?: '');

        $this->db->prepare('UPDATE parents SET profile_photo = :photo, updated_at = NOW() WHERE id = :id')
            ->execute([':photo' => '/uploads/profiles/' . $filename, ':id' => $parentId]);

        if ($oldPhoto !== '' && str_starts_with($oldPhoto, '/uploads/profiles/')) {
            $oldPath = BASE_PATH . '/public' . $oldPhoto;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $this->flash('success', t('Profile photo updated.'));
        $this->redirect('/family/account');
    }

    /**
     * Every mark the child has, newest first.
     */
    public function marks(): void
    {
        [$parentId, $student] = $this->requireParent();

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
        $stmt->execute([':student_id' => $student['id']]);

        $this->logView($parentId, (int) $student['id'], 'marks');

        $this->view('portal/parent-marks', [
            'pageTitle' => t('{name} - marks', ['name' => $student['full_name']]),
            'student'   => $student,
            'attempts'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ], 'layouts/admin');
    }

    /**
     * Fees: what is settled and what is not.
     */
    public function fees(): void
    {
        [$parentId, $student] = $this->requireParent();

        $stmt = $this->db->prepare(
            "SELECT p.id, p.month_year, p.amount_due, p.amount_paid, p.payment_status, p.paid_at,
                    c.class_name, c.grade
               FROM payments p
               JOIN classes c ON c.id = p.class_id
              WHERE p.student_id = :student_id
              ORDER BY p.month_year DESC, c.class_name ASC"
        );
        $stmt->execute([':student_id' => $student['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $outstanding = 0.0;
        foreach ($rows as $row) {
            if (in_array($row['payment_status'], ['pending', 'overdue'], true)) {
                $outstanding += (float) $row['amount_due'] - (float) $row['amount_paid'];
            }
        }

        $this->logView($parentId, (int) $student['id'], 'fees');

        $this->view('portal/parent-fees', [
            'pageTitle'   => t('{name} - fees', ['name' => $student['full_name']]),
            'student'     => $student,
            'payments'    => $rows,
            'outstanding' => $outstanding,
        ], 'layouts/admin');
    }

    // ---------- helpers ----------

    /**
     * The guardian in the session and the child they are attached to.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function requireParent(): array
    {
        AuthMiddleware::requireAuth();

        if (session_get('user_type') !== 'parent') {
            $this->flash('error', 'That area is for parents and guardians.');
            $this->redirect('/dashboard');
        }

        $parentId = (int) session_get('user_id');

        $stmt = $this->db->prepare(
            "SELECT s.*, p.parent_name, p.id AS parent_row_id
               FROM parents p
               JOIN students s ON s.id = p.student_id
              WHERE p.id = :parent_id
              LIMIT 1"
        );
        $stmt->execute([':parent_id' => $parentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student === false) {
            $this->flash('error', 'No student is linked to this login. Contact the office.');
            $this->redirect('/logout');
        }

        return [$parentId, $student];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMarks(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.percentage, a.submitted_at, q.title, c.class_name
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :student_id
                AND a.attempt_status = 'graded'
              ORDER BY a.submitted_at DESC
              LIMIT 5"
        );
        $stmt->execute([':student_id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The last few sessions, so "has she been going?" has an answer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentAttendance(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.date, a.status, a.check_in_time, c.class_name
               FROM attendance a
               JOIN classes c ON c.id = a.class_id
              WHERE a.student_id = :student_id
              ORDER BY a.date DESC
              LIMIT 8"
        );
        $stmt->execute([':student_id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record that a guardian looked.
     *
     * A child's marks and attendance are sensitive, and "who saw this"
     * is a question worth being able to answer later. Logging failure
     * never blocks the page -- an audit gap is better than a parent
     * locked out of their child's results.
     */
    private function logView(int $parentId, int $studentId, string $page): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO parent_access_log (parent_id, student_id, viewed_page, ip_address)
                 VALUES (:p, :s, :page, :ip)"
            )->execute([
                ':p'    => $parentId,
                ':s'    => $studentId,
                ':page' => $page,
                ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\PDOException $e) {
            error_log('parent_access_log write failed: ' . $e->getMessage());
        }
    }
}
