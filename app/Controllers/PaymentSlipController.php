<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AnnouncementService;
use App\Services\AttachmentService;
use App\Services\BranchScope;
use PDO;

/**
 * Bank-slip payments.
 *
 *   1. A student or parent uploads a slip for one of their own fees.
 *   2. It waits, as "pending", in the office's review queue.
 *   3. Office staff approve it (with the amount actually seen on the
 *      slip) or reject it with a reason.
 *   4. Approval updates the fee inside one transaction; the receipt then
 *      becomes available. Rejection leaves the fee untouched.
 *   5. The family is told the real outcome, and the decision is audited.
 *
 * An upload on its own changes nothing about the fee or the class lock.
 */
class PaymentSlipController extends BaseController
{
    private const REVIEWERS = ['super_admin', 'admin', 'staff'];
    private const METHODS = ['bank_deposit', 'bank_transfer', 'online_banking', 'other'];

    // ------------------------------------------------------------------
    // Students and parents
    // ------------------------------------------------------------------

    public function mine(): void
    {
        [$type, , $studentId] = $this->requireFamily();

        $fees = $this->db->prepare(
            "SELECT p.*, c.class_name,
                    (SELECT COUNT(*) FROM payment_slips s WHERE s.payment_id = p.id AND s.status = 'pending') AS pending_slips
               FROM payments p JOIN classes c ON c.id = p.class_id
              WHERE p.student_id = :s
              ORDER BY p.month_year DESC, c.class_name"
        );
        $fees->execute([':s' => $studentId]);

        $slips = $this->db->prepare(
            "SELECT s.*, p.month_year, c.class_name
               FROM payment_slips s
               JOIN payments p ON p.id = s.payment_id
               JOIN classes c ON c.id = p.class_id
              WHERE s.student_id = :s
              ORDER BY s.id DESC LIMIT 50"
        );
        $slips->execute([':s' => $studentId]);

        $this->view('payments/slips-family', [
            'pageTitle'  => 'Payments',
            'activePage' => $type === 'parent' ? 'family-payments' : 'my-payments',
            'fees'       => $fees->fetchAll(PDO::FETCH_ASSOC),
            'slips'      => $slips->fetchAll(PDO::FETCH_ASSOC),
            'base'       => $type === 'parent' ? '/family/payments' : '/my/payments',
            'methods'    => self::METHODS,
        ]);
    }

    public function upload(): void
    {
        [$type, $me, $studentId] = $this->requireFamily();
        verifyCsrfToken();
        $back = $type === 'parent' ? '/family/payments' : '/my/payments';

        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $pay = $this->db->prepare("SELECT * FROM payments WHERE id = :id AND student_id = :s");
        $pay->execute([':id' => $paymentId, ':s' => $studentId]);
        $payment = $pay->fetch(PDO::FETCH_ASSOC);

        if ($payment === false || !in_array($payment['payment_status'], ['pending', 'overdue'], true)) {
            $this->flash('error', t('That fee is not waiting for payment.'));
            $this->redirect($back);
        }

        $dupe = $this->db->prepare("SELECT COUNT(*) FROM payment_slips WHERE payment_id = :p AND status = 'pending'");
        $dupe->execute([':p' => $paymentId]);
        if ((int) $dupe->fetchColumn() > 0) {
            $this->flash('error', t('A slip for this fee is already waiting for review.'));
            $this->redirect($back);
        }

        $amount = round((float) ($_POST['amount'] ?? 0), 2);
        $paidOn = (string) ($_POST['paid_on'] ?? '');
        $method = in_array($_POST['method'] ?? '', self::METHODS, true) ? $_POST['method'] : 'bank_deposit';
        $reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100);
        $bank = mb_substr(trim((string) ($_POST['bank_name'] ?? '')), 0, 100);
        $outstanding = (float) $payment['amount_due'] - (float) $payment['amount_paid'];

        $paidTs = strtotime($paidOn);
        if ($amount <= 0 || $amount > 1000000 || $paidTs === false || $paidTs > time() + 86400) {
            $this->flash('error', t('Enter the amount on the slip and the date it was paid.'));
            $this->redirect($back);
        }

        // The same bank reference used twice is almost always a mistake
        // (or a re-used slip); the office decides, but it is refused here.
        if ($reference !== '') {
            $ref = $this->db->prepare("SELECT COUNT(*) FROM payment_slips WHERE bank_reference = :r AND status IN ('pending','approved')");
            $ref->execute([':r' => $reference]);
            if ((int) $ref->fetchColumn() > 0) {
                $this->flash('error', t('That bank reference has already been submitted.'));
                $this->redirect($back);
            }
        }

        try {
            $file = (new AttachmentService())->store($_FILES['slip'] ?? null, 'slips');
        } catch (\RuntimeException $e) {
            $this->flash('error', t($e->getMessage()));
            $this->redirect($back);
        }
        if ($file === null) {
            $this->flash('error', t('Attach a photo or PDF of the slip.'));
            $this->redirect($back);
        }

        $this->db->prepare(
            "INSERT INTO payment_slips (payment_id, student_id, submitted_by_type, submitted_by_id, amount, paid_on, method, bank_name, bank_reference, file_path, original_name)
             VALUES (:p, :s, :t, :by, :a, :d, :m, :b, :r, :f, :n)"
        )->execute([
            ':p' => $paymentId, ':s' => $studentId, ':t' => $type, ':by' => $me, ':a' => $amount,
            ':d' => date('Y-m-d', $paidTs), ':m' => $method, ':b' => $bank !== '' ? $bank : null,
            ':r' => $reference !== '' ? $reference : null, ':f' => $file[0], ':n' => $file[1],
        ]);
        $slipId = (int) $this->db->lastInsertId();

        $this->notifyReviewers($paymentId, $slipId);

        $this->flash('success', $amount > $outstanding + 0.001
            ? t('Slip sent for review. Note: it is more than the amount due; the office will check it.')
            : t('Slip sent for review. Your class opens once the office approves it.'));
        $this->redirect($back);
    }

    // ------------------------------------------------------------------
    // Office review
    // ------------------------------------------------------------------

    public function queue(): void
    {
        $this->requireReviewer();
        $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';

        $stmt = $this->db->prepare(
            "SELECT s.*, p.month_year, p.amount_due, p.amount_paid, p.payment_status, p.class_id,
                    c.class_name, i.name AS institute_name, st.full_name AS student_name, st.student_id_display,
                    u.full_name AS reviewer_name
               FROM payment_slips s
               JOIN payments p ON p.id = s.payment_id
               JOIN classes c ON c.id = p.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               JOIN students st ON st.id = s.student_id
               LEFT JOIN users u ON u.id = s.reviewed_by
              WHERE s.status = :st" . BranchScope::current()->classSql('p.class_id') . "
              ORDER BY s.created_at " . ($status === 'pending' ? 'ASC' : 'DESC') . " LIMIT 200"
        );
        $stmt->execute([':st' => $status]);

        $this->view('payments/slips-queue', [
            'pageTitle'  => 'Payment slips',
            'activePage' => 'payments',
            'slips'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'status'     => $status,
        ]);
    }

    public function approve(string $id): void
    {
        $this->requireReviewer();
        verifyCsrfToken();
        $slip = $this->slipInScope((int) $id);
        $approved = round((float) ($_POST['approved_amount'] ?? $slip['amount']), 2);

        $this->db->beginTransaction();
        try {
            // Lock the fee row so two reviewers cannot both add the amount.
            $p = $this->db->prepare("SELECT * FROM payments WHERE id = :id FOR UPDATE");
            $p->execute([':id' => (int) $slip['payment_id']]);
            $payment = $p->fetch(PDO::FETCH_ASSOC);

            $s = $this->db->prepare("SELECT status FROM payment_slips WHERE id = :id FOR UPDATE");
            $s->execute([':id' => (int) $slip['id']]);
            if ($s->fetchColumn() !== 'pending') {
                throw new \DomainException(t('This slip has already been reviewed.'));
            }

            $outstanding = (float) $payment['amount_due'] - (float) $payment['amount_paid'];
            if ($approved <= 0 || $approved > $outstanding + 0.001) {
                throw new \DomainException(t('The approved amount must be more than 0 and no more than the Rs. {n} still due.', ['n' => number_format($outstanding, 2)]));
            }

            $newPaid = round((float) $payment['amount_paid'] + $approved, 2);
            $settled = $newPaid + 0.001 >= (float) $payment['amount_due'];

            $this->db->prepare(
                "UPDATE payments
                    SET amount_paid = :paid,
                        payment_status = :status,
                        paid_at = CASE WHEN :settled1 = 1 THEN :paid_on ELSE paid_at END,
                        marked_by = :by,
                        notes = CONCAT(COALESCE(notes, ''), :note)
                  WHERE id = :id"
            )->execute([
                ':paid' => $newPaid,
                ':status' => $settled ? 'paid' : $payment['payment_status'],
                ':settled1' => $settled ? 1 : 0,
                ':paid_on' => $slip['paid_on'] . ' 12:00:00',
                ':by' => (int) session_get('user_id'),
                ':note' => "\n[slip #" . (int) $slip['id'] . "] approved Rs. " . number_format($approved, 2),
                ':id' => (int) $payment['id'],
            ]);

            $this->db->prepare(
                "UPDATE payment_slips SET status = 'approved', approved_amount = :a, reviewed_by = :by, reviewed_at = NOW() WHERE id = :id"
            )->execute([':a' => $approved, ':by' => (int) session_get('user_id'), ':id' => (int) $slip['id']]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->flash('error', $e instanceof \DomainException ? $e->getMessage() : t('The slip could not be approved. Nothing was changed.'));
            if (!$e instanceof \DomainException) {
                error_log('Slip approval failed: ' . $e->getMessage());
            }
            $this->redirect('/payments/slips');
        }

        $this->audit('approve_payment_slip', (int) $slip['id'], ['payment_id' => (int) $slip['payment_id'], 'amount' => $approved, 'settled' => $settled]);
        $this->tellFamily($slip, $settled
            ? 'Payment approved: your fee is settled and the receipt is ready.'
            : 'Payment approved: Rs. ' . number_format($approved, 2) . ' recorded. Some of the fee is still due.');

        $this->flash('success', $settled ? t('Approved. The fee is now settled.') : t('Approved. Part of the fee is still due.'));
        $this->redirect('/payments/slips');
    }

    public function reject(string $id): void
    {
        $this->requireReviewer();
        verifyCsrfToken();
        $slip = $this->slipInScope((int) $id);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        if ($reason === '') {
            $this->flash('error', t('Give the family a reason for the rejection.'));
            $this->redirect('/payments/slips');
        }

        $stmt = $this->db->prepare(
            "UPDATE payment_slips SET status = 'rejected', review_note = :r, reviewed_by = :by, reviewed_at = NOW()
              WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':r' => $reason, ':by' => (int) session_get('user_id'), ':id' => (int) $slip['id']]);
        if ($stmt->rowCount() === 0) {
            $this->flash('error', t('This slip has already been reviewed.'));
            $this->redirect('/payments/slips');
        }

        $this->audit('reject_payment_slip', (int) $slip['id'], ['payment_id' => (int) $slip['payment_id'], 'reason' => $reason]);
        $this->tellFamily($slip, 'Payment slip not accepted: ' . $reason);

        $this->flash('success', t('Rejected. The family has been told why.'));
        $this->redirect('/payments/slips');
    }

    /** The slip image/PDF: its family, or office staff for that class. */
    public function file(string $id): void
    {
        AuthMiddleware::requireAuth();
        $stmt = $this->db->prepare("SELECT s.*, p.class_id FROM payment_slips s JOIN payments p ON p.id = s.payment_id WHERE s.id = :id");
        $stmt->execute([':id' => (int) $id]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);

        $type = (string) session_get('user_type');
        $me = (int) session_get('user_id');
        $ok = false;
        if ($slip !== false) {
            if ($type === 'student') {
                $ok = (int) $slip['student_id'] === $me;
            } elseif ($type === 'parent') {
                $p = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
                $p->execute([':p' => $me]);
                $ok = (int) $p->fetchColumn() === (int) $slip['student_id'];
            } elseif ($type === 'user') {
                $ok = in_array((string) session_get('user_role'), self::REVIEWERS, true)
                    && BranchScope::current()->allowsClass((int) $slip['class_id']);
            }
        }
        if (!$ok) {
            http_response_code(404);
            exit('Not found.');
        }

        (new AttachmentService())->send((string) $slip['file_path'], (string) ($slip['original_name'] ?: 'slip'));
    }

    // ------------------------------------------------------------------

    /** @return array{0: string, 1: int, 2: int} */
    private function requireFamily(): array
    {
        AuthMiddleware::requireAuth();
        $type = (string) session_get('user_type');
        $me = (int) session_get('user_id');
        if ($type === 'student') {
            return ['student', $me, $me];
        }
        if ($type === 'parent') {
            $s = $this->db->prepare("SELECT student_id FROM parents WHERE id = :p");
            $s->execute([':p' => $me]);
            $sid = $s->fetchColumn();
            if ($sid !== false) {
                return ['parent', $me, (int) $sid];
            }
        }
        $this->flash('error', 'That area is for students and parents.');
        $this->redirect('/dashboard');
    }

    private function requireReviewer(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user' || !in_array((string) session_get('user_role'), self::REVIEWERS, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/dashboard');
        }
    }

    /** @return array<string, mixed> */
    private function slipInScope(int $id): array
    {
        $stmt = $this->db->prepare("SELECT s.*, p.class_id FROM payment_slips s JOIN payments p ON p.id = s.payment_id WHERE s.id = :id");
        $stmt->execute([':id' => $id]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($slip === false || !BranchScope::current()->allowsClass((int) $slip['class_id'])) {
            http_response_code(403);
            $this->flash('error', t('That slip is not in your branch.'));
            $this->redirect('/payments/slips');
        }

        return $slip;
    }

    private function notifyReviewers(int $paymentId, int $slipId): void
    {
        $c = $this->db->prepare("SELECT c.institute_id FROM payments p JOIN classes c ON c.id = p.class_id WHERE p.id = :p");
        $c->execute([':p' => $paymentId]);
        $inst = (int) $c->fetchColumn();
        $s = $this->db->prepare(
            "SELECT ui.user_id FROM user_institutes ui JOIN users u ON u.id = ui.user_id
              WHERE ui.institute_id = :i AND u.status = 'active' AND u.role IN ('admin','staff')"
        );
        $s->execute([':i' => $inst]);
        $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) {
            $ids = array_map('intval', $this->db->query("SELECT id FROM users WHERE role = 'super_admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN));
        }
        (new AnnouncementService())->notifyAll(
            array_map(static fn (int $id): array => ['type' => 'user', 'id' => $id], $ids),
            'Payment slip waiting for review',
            'Open Payments > Payment slips to approve or reject it.',
            'payment_slip',
            $slipId
        );
    }

    /** @param array<string, mixed> $slip */
    private function tellFamily(array $slip, string $message): void
    {
        $to = [['type' => 'student', 'id' => (int) $slip['student_id']]];
        $p = $this->db->prepare("SELECT id FROM parents WHERE student_id = :s AND status = 'active'");
        $p->execute([':s' => (int) $slip['student_id']]);
        foreach ($p->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $to[] = ['type' => 'parent', 'id' => (int) $pid];
        }
        (new AnnouncementService())->notifyAll($to, $message, '', 'payment_slip', (int) $slip['id']);
    }

    private function audit(string $action, int $id, array $details = []): void
    {
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), $action, 'payment_slip', $id, $details);
        } catch (\Throwable $e) {
            error_log('Slip audit failed: ' . $e->getMessage());
        }
    }
}
