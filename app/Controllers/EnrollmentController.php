<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AnnouncementService;
use App\Services\AttachmentService;
use App\Services\BranchScope;
use PDO;

/**
 * Self-service enrolment from the public site: a new student/parent picks
 * a branch + class, fills in their details, and uploads a bank deposit
 * slip as proof of payment. It waits as "pending" for that branch's
 * admin/coordinator to review in the Enrolment Requests queue.
 *
 *   Approve -> the student (and parent, if a guardian phone was given)
 *              account is created and enrolled in the chosen class, in
 *              one step -- same result as Students > Add New, just
 *              run automatically instead of retyped by hand.
 *   Reject  -> nothing is created; a reason is kept for the record.
 *
 * Uploading a slip never creates an account by itself -- only an actual
 * approval by a human reviewer does.
 */
class EnrollmentController extends BaseController
{
    private const REVIEWERS = ['super_admin', 'admin', 'coordinator'];

    // ------------------------------------------------------------------
    // Public: new student / parent
    // ------------------------------------------------------------------

    public function form(): void
    {
        $branches = $this->publicBranches();
        $preset = $this->publicClass((int) ($_GET['class'] ?? 0));

        $this->view('public/enroll', [
            'pageTitle'       => t('Enrol now') . ' | ' . t('Science classes'),
            'metaDescription' => 'Enrol a student in a Science class: pick a branch and class, fill in your details and upload your bank deposit slip.',
            'activePage'      => 'enroll',
            'branches'        => $branches,
            'presetClassId'   => $preset['id'] ?? null,
            'presetInstituteId' => $preset['institute_id'] ?? null,
        ], 'layouts/public');
    }

    /** Public JSON: active, public classes at a public branch (for the branch/class pickers on /enroll). */
    public function classesByInstitute(string $id): void
    {
        header('Content-Type: application/json');
        $instituteId = (int) $id;
        if ($instituteId <= 0 || !$this->db) {
            echo json_encode([]);
            return;
        }
        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.delivery_mode, c.schedule_days, c.start_time, c.end_time
               FROM classes c
               JOIN institutes i ON i.id = c.institute_id AND i.status = 'active' AND i.is_public = 1
              WHERE c.institute_id = :i AND c.status = 'active'
              ORDER BY CAST(SUBSTRING(c.grade, 7) AS UNSIGNED), c.start_time"
        );
        $stmt->execute([':i' => $instituteId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function store(): void
    {
        verifyCsrfToken();
        $back = BASE_URL . '/enroll';

        // Honeypot + minimum fill time, same spam guard as the inquiry form.
        $started = (int) ($_POST['started_at'] ?? 0);
        if (trim((string) ($_POST['website'] ?? '')) !== '' || ($started > 0 && time() - $started < 3)) {
            $this->flash('success', t('Thank you! Your enrolment request has been sent for review.'));
            $this->redirect('/enroll');
        }

        $instituteId = (int) ($_POST['institute_id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $studentName = mb_substr(trim((string) ($_POST['student_name'] ?? '')), 0, 255);
        $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
        $guardianName = mb_substr(trim((string) ($_POST['guardian_name'] ?? '')), 0, 255);
        $guardianPhone = preg_replace('/[\s-]/', '', trim((string) ($_POST['guardian_phone'] ?? ''))) ?? '';
        $guardianEmail = mb_substr(trim((string) ($_POST['guardian_email'] ?? '')), 0, 255);
        $address = mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 500);
        $scholarship = in_array($_POST['scholarship_type'] ?? '', ['full_payment', 'half_payment', 'free_card'], true)
            ? $_POST['scholarship_type'] : 'full_payment';
        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        $paidOn = trim((string) ($_POST['paid_on'] ?? ''));
        $bankName = mb_substr(trim((string) ($_POST['bank_name'] ?? '')), 0, 100);
        $bankRef = mb_substr(trim((string) ($_POST['bank_reference'] ?? '')), 0, 100);

        // The class must actually be an active, public class at an
        // active, public branch -- never trust the posted institute_id.
        $class = $this->publicClass($classId);
        $phoneOk = preg_match('/^(\+94|0)\d{9}$/', $guardianPhone) === 1;
        $dobTs = $dob !== '' ? strtotime($dob) : false;
        $paidTs = $paidOn !== '' ? strtotime($paidOn) : false;

        if ($class === null || (int) $class['institute_id'] !== $instituteId
            || $studentName === '' || $guardianName === '' || !$phoneOk
            || ($guardianEmail !== '' && !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL))
            || ($dob !== '' && $dobTs === false)
            || $amount < 0 || $amount > 1000000
            || ($paidOn !== '' && ($paidTs === false || $paidTs > time() + 86400))
        ) {
            $this->flash('error', t('Please check the branch, class, student and guardian details, and the payment date.'));
            $this->redirect('/enroll');
        }

        try {
            $file = (new AttachmentService())->store($_FILES['slip'] ?? null, 'enrollment');
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect('/enroll');
        }
        if ($file === null) {
            $this->flash('error', t('Attach a photo or PDF of the bank deposit slip.'));
            $this->redirect('/enroll');
        }

        $this->db->prepare(
            "INSERT INTO enrollment_requests
                (institute_id, class_id, student_name, date_of_birth, guardian_name, guardian_phone, guardian_email,
                 address, scholarship_type, amount, paid_on, bank_name, bank_reference, slip_path, slip_original_name, status, created_at)
             VALUES (:i, :c, :sn, :dob, :gn, :gp, :ge, :a, :st, :amt, :po, :bn, :br, :fp, :fn, 'pending', NOW())"
        )->execute([
            ':i' => $instituteId, ':c' => $classId, ':sn' => $studentName,
            ':dob' => $dob !== '' ? date('Y-m-d', $dobTs) : null,
            ':gn' => $guardianName, ':gp' => $guardianPhone, ':ge' => $guardianEmail !== '' ? $guardianEmail : null,
            ':a' => $address !== '' ? $address : null, ':st' => $scholarship,
            ':amt' => $amount > 0 ? $amount : null, ':po' => $paidOn !== '' ? date('Y-m-d', $paidTs) : null,
            ':bn' => $bankName !== '' ? $bankName : null, ':br' => $bankRef !== '' ? $bankRef : null,
            ':fp' => $file[0], ':fn' => $file[1],
        ]);

        $this->notifyReviewers($instituteId, (int) $this->db->lastInsertId(), $studentName);

        $this->flash('success', t('Thank you! Your enrolment request and slip have been sent to the branch office for review.'));
        $this->redirect('/enroll');
    }

    /** Tell that branch's admin/coordinator a new enrolment request is waiting -- same pattern as payment-slip review. */
    private function notifyReviewers(int $instituteId, int $requestId, string $studentName): void
    {
        $s = $this->db->prepare(
            "SELECT ui.user_id FROM user_institutes ui JOIN users u ON u.id = ui.user_id
              WHERE ui.institute_id = :i AND u.status = 'active' AND u.role IN ('admin','coordinator')"
        );
        $s->execute([':i' => $instituteId]);
        $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) {
            $ids = array_map('intval', $this->db->query(
                "SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'"
            )->fetchAll(PDO::FETCH_COLUMN));
        }
        try {
            (new AnnouncementService())->notifyAll(
                array_map(static fn (int $id): array => ['type' => 'user', 'id' => $id], $ids),
                'New enrolment request waiting for review',
                $studentName . ' -- open Enrolment Requests to approve or reject it.',
                'enrollment_request',
                $requestId
            );
        } catch (\Throwable $e) {
            error_log('Enrolment request notify failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Admin / coordinator review
    // ------------------------------------------------------------------

    public function queue(): void
    {
        $this->guard();
        $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';

        $stmt = $this->db->prepare(
            "SELECT r.*, i.name AS branch_name, c.class_name, c.grade
               FROM enrollment_requests r
               LEFT JOIN institutes i ON i.id = r.institute_id
               LEFT JOIN classes c ON c.id = r.class_id
              WHERE r.status = :st" . $this->scopeSql() . "
              ORDER BY r.created_at " . ($status === 'pending' ? 'ASC' : 'DESC') . " LIMIT 200"
        );
        $stmt->execute([':st' => $status]);

        $this->view('enrollment-requests/index', [
            'pageTitle'  => 'Enrolment Requests',
            'activePage' => 'enrollment-requests',
            'items'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'status'     => $status,
        ]);
    }

    public function approve(string $id): void
    {
        $this->guard();
        verifyCsrfToken();
        $req = $this->requestInScope((int) $id);

        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare("SELECT status FROM enrollment_requests WHERE id = :id FOR UPDATE");
            $s->execute([':id' => (int) $req['id']]);
            if ($s->fetchColumn() !== 'pending') {
                throw new \DomainException(t('This request has already been reviewed.'));
            }

            $studentModel = new \Student();
            $studentPassword = \App\Services\AccountService::temporaryPassword();
            // createWithGeneratedId() retries on a student_id_display collision --
            // two approvals landing close together can otherwise both compute the
            // same "next" id and the second INSERT fails (see its docblock).
            $created = $studentModel->createWithGeneratedId([
                'full_name'          => $req['student_name'],
                'date_of_birth'      => $req['date_of_birth'] ?? date('Y-m-d'),
                'address'            => $req['address'],
                'phone1'             => $req['guardian_phone'],
                'status'             => 'active',
                'password_hash'      => password_hash($studentPassword, PASSWORD_DEFAULT),
                'created_by'         => (int) session_get('user_id'),
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);
            $studentId = $created['id'];
            $studentIdDisplay = $created['student_id_display'];
            $this->db->prepare("UPDATE students SET must_change_password = 1 WHERE id = :id")->execute([':id' => $studentId]);

            $issued = [[
                'kind' => 'password', 'name' => $req['student_name'], 'login_id' => $studentIdDisplay,
                'secret' => $studentPassword, 'expires_at' => null,
                'phone' => $req['guardian_phone'] ?? null, 'email' => $req['guardian_email'] ?? null,
            ]];

            $existingParent = $this->db->prepare("SELECT COUNT(*) FROM parents WHERE phone1 = :p");
            $existingParent->execute([':p' => $req['guardian_phone']]);
            $parentPassword = \App\Services\AccountService::temporaryPassword();
            $this->db->prepare(
                "INSERT INTO parents (student_id, parent_name, phone1, email, password_hash, must_change_password, status)
                 VALUES (:s, :n, :p, :em, :h, 1, 'active')"
            )->execute([
                ':s' => $studentId, ':n' => $req['guardian_name'], ':p' => $req['guardian_phone'],
                ':em' => ($req['guardian_email'] ?? '') !== '' ? $req['guardian_email'] : null,
                ':h' => password_hash($parentPassword, PASSWORD_DEFAULT),
            ]);
            if ((int) $existingParent->fetchColumn() === 0) {
                $issued[] = [
                    'kind' => 'password', 'name' => $req['guardian_name'] . ' (' . t('parent') . ')', 'login_id' => $req['guardian_phone'],
                    'secret' => $parentPassword, 'expires_at' => null,
                    'phone' => $req['guardian_phone'] ?? null, 'email' => $req['guardian_email'] ?? null,
                ];
            }

            if (BranchScope::current()->allowsClass((int) $req['class_id'])) {
                $this->db->prepare(
                    "INSERT INTO enrollments (student_id, class_id, scholarship_type, enrollment_status, enrolled_at, updated_at)
                     VALUES (:sid, :cid, :stype, 'active', NOW(), NOW())"
                )->execute([':sid' => $studentId, ':cid' => (int) $req['class_id'], ':stype' => $req['scholarship_type'] ?? 'full_payment']);
            }

            $this->db->prepare(
                "UPDATE enrollment_requests SET status = 'approved', reviewed_by = :by, reviewed_at = NOW(), created_student_id = :sid WHERE id = :id"
            )->execute([':by' => (int) session_get('user_id'), ':sid' => $studentId, ':id' => (int) $req['id']]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->flash('error', $e instanceof \DomainException ? $e->getMessage() : t('The request could not be approved. Nothing was changed.'));
            if (!$e instanceof \DomainException) {
                error_log('Enrolment approval failed: ' . $e->getMessage());
            }
            $this->redirect('/enrollment-requests');
        }

        $this->audit('approve_enrollment_request', (int) $req['id'], ['student_id' => $studentId, 'class_id' => (int) $req['class_id']]);

        // Email the login/password straight to the guardian the moment the
        // branch admin approves -- the office no longer has to remember to
        // open the "Login details" screen and press "Send by email" by
        // hand. Each item is marked with how it went so issued.php can
        // show "already emailed" instead of (or alongside) the manual
        // send buttons; nothing here can fail the approval itself, since
        // the account was already created and committed above.
        $issued = $this->autoEmailCredentials($issued);

        session_set('issued_credentials', $issued);
        session_set('issued_back', '/students/' . $studentId);
        $emailed = count(array_filter($issued, static fn (array $it): bool => !empty($it['auto_sent_email'])));
        $this->flash('success', $emailed > 0
            ? t('Approved. Student ID: {id}. Login details emailed to the guardian automatically.', ['id' => $studentIdDisplay])
            : t('Approved. Student ID: {id}. Could not auto-email the login details -- send them from this screen instead.', ['id' => $studentIdDisplay]));
        $this->redirect('/accounts/issued');
    }

    /**
     * Try to email each issued credential straight away, so the office
     * does not have to do it by hand for every approval. Best-effort: a
     * missing/invalid email, or email not being set up in Settings, just
     * leaves 'auto_sent_email' false and the manual "Send by email"
     * button on issued.php still works as the fallback.
     *
     * @param  array<int, array<string, mixed>> $issued
     * @return array<int, array<string, mixed>>
     */
    private function autoEmailCredentials(array $issued): array
    {
        $emailService = new \App\Services\EmailService();
        if (!$emailService->isConfigured()) {
            return $issued;
        }

        foreach ($issued as &$item) {
            $email = trim((string) ($item['email'] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $body = \App\Controllers\AccountController::credentialMessage(
                (string) ($item['kind'] ?? 'password'),
                (string) ($item['name'] ?? ''),
                (string) ($item['login_id'] ?? ''),
                (string) ($item['secret'] ?? '')
            );
            $sent = $emailService->send($email, t('Your class portal login details'), $body);
            $item['auto_sent_email'] = $sent;
        }
        unset($item);

        return $issued;
    }

    public function reject(string $id): void
    {
        $this->guard();
        verifyCsrfToken();
        $req = $this->requestInScope((int) $id);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        if ($reason === '') {
            $this->flash('error', t('Give a reason for the rejection.'));
            $this->redirect('/enrollment-requests');
        }

        $stmt = $this->db->prepare(
            "UPDATE enrollment_requests SET status = 'rejected', review_note = :r, reviewed_by = :by, reviewed_at = NOW()
              WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':r' => $reason, ':by' => (int) session_get('user_id'), ':id' => (int) $req['id']]);
        if ($stmt->rowCount() === 0) {
            $this->flash('error', t('This request has already been reviewed.'));
            $this->redirect('/enrollment-requests');
        }

        $this->audit('reject_enrollment_request', (int) $req['id'], ['reason' => $reason]);
        $this->flash('success', t('Rejected.'));
        $this->redirect('/enrollment-requests');
    }

    /** The uploaded slip: reviewers for that branch only. */
    public function slipFile(string $id): void
    {
        $req = $this->requestInScope((int) $id);
        (new AttachmentService())->send((string) $req['slip_path'], (string) ($req['slip_original_name'] ?: 'slip'));
    }

    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function publicBranches(): array
    {
        if (!$this->db) {
            return [];
        }
        try {
            return $this->db->query(
                "SELECT id, name, location FROM institutes WHERE status = 'active' AND is_public = 1 ORDER BY sort_order ASC, id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Public branches unavailable: ' . $e->getMessage());
            return [];
        }
    }

    /** A real, active, public class, or null. Never trust the posted institute_id against it without this. */
    private function publicClass(int $classId): ?array
    {
        if ($classId <= 0 || !$this->db) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.institute_id
               FROM classes c
               JOIN institutes i ON i.id = c.institute_id AND i.status = 'active' AND i.is_public = 1
              WHERE c.id = :id AND c.status = 'active'
              LIMIT 1"
        );
        $stmt->execute([':id' => $classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function guard(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user' || !in_array((string) session_get('user_role'), self::REVIEWERS, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/dashboard');
        }
    }

    private function scopeSql(): string
    {
        $scope = BranchScope::current();
        return $scope->isGlobal() ? '' : $scope->instituteSql('r.institute_id');
    }

    /** @return array<string, mixed> */
    private function requestInScope(int $id): array
    {
        $this->guard();
        $stmt = $this->db->prepare("SELECT r.* FROM enrollment_requests r WHERE r.id = :id");
        $stmt->execute([':id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($req === false || !BranchScope::current()->allowsInstitute((int) $req['institute_id'])) {
            http_response_code(403);
            $this->flash('error', t('That request is not in your branch.'));
            $this->redirect('/enrollment-requests');
        }

        return $req;
    }

    private function audit(string $action, int $id, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'enrollment_request', $id, $details);
        } catch (\Throwable $e) {
            error_log('Enrollment audit failed: ' . $e->getMessage());
        }
    }
}
