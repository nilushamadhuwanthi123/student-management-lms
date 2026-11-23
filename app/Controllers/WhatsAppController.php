<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\ContentAccessService;
use App\Services\WhatsAppService;
use PDO;

/**
 * Fee reminders and notices over WhatsApp.
 *
 * The screen the teacher actually wants is "who has not paid this
 * month, and send them all a reminder". So that is the screen: a
 * filtered list of unpaid students, each with the message already
 * written and a button that opens WhatsApp.
 *
 * Nothing is sent by the server. Each row opens a click-to-chat link,
 * and the teacher confirms afterwards, which is what gets written to
 * the log. That is an honest record of a channel where no delivery
 * receipt exists.
 */
class WhatsAppController extends BaseController
{
    private WhatsAppService $wa;

    public function __construct()
    {
        parent::__construct();
        $this->wa = new WhatsAppService();
    }

    /**
     * Compose reminders for everyone unpaid in a month.
     */
    public function reminders(): void
    {
        AuthMiddleware::requireStaff();

        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);
        $classId = ($_GET['class_id'] ?? '') !== '' ? (int) $_GET['class_id'] : null;
        $instituteId = ($_GET['institute_id'] ?? '') !== '' ? (int) $_GET['institute_id'] : null;
        $templateKey = $_GET['template'] ?? 'fee_reminder';

        $template = $this->wa->template($templateKey) ?? $this->wa->template('fee_reminder');
        $rows = $this->wa->unpaidRecipients($month, $classId, $instituteId);
        // Only this branch's families appear on a branch admin's list.
        $scope = BranchScope::current();
        $rows = array_values(array_filter($rows, static fn (array $r): bool => $scope->allowsClass((int) $r['class_id'])));

        $teacherName = $this->teacherName();
        $monthLabel = date('F Y', strtotime($month));

        // Compose each message up front so the page shows exactly what
        // will be sent, rather than a template the teacher has to
        // imagine filled in.
        $recipients = [];
        $noNumber = [];

        foreach ($rows as $row) {
            $to = WhatsAppService::bestRecipient($row);

            if ($to === null) {
                $noNumber[] = $row;
                continue;
            }

            $outstanding = (float) $row['amount_due'] - (float) $row['amount_paid'];

            $body = WhatsAppService::render($template['body'], [
                'student' => $row['student_name'],
                'class'   => $row['class_name'],
                'month'   => $monthLabel,
                'amount'  => number_format($outstanding, 2),
                // The class's own teacher signs the message; the first
                // teacher in the table was signing every branch's reminders.
                'teacher' => $this->classTeacher((int) $row['class_id']) ?? $teacherName,
                'branch'  => $row['institute_name'] ?? '',
            ]);

            $recipients[] = [
                'row'         => $row,
                'to'          => $to,
                'body'        => $body,
                'link'        => WhatsAppService::chatLink($to['phone'], $body),
                'outstanding' => $outstanding,
            ];
        }

        $this->view('whatsapp/reminders', [
            'pageTitle'   => 'WhatsApp reminders',
            'activePage'  => 'whatsapp',
            'month'       => $month,
            'months'      => $this->monthOptions(),
            'classes'     => $this->classOptions(),
            'institutes'  => $this->instituteOptions(),
            'templates'   => $this->wa->templates('fee'),
            'templateKey' => $template['template_key'],
            'recipients'  => $recipients,
            'noNumber'    => $noNumber,
            'filters'     => ['class_id' => $classId, 'institute_id' => $instituteId],
            'totalOwed'   => array_sum(array_column($recipients, 'outstanding')),
        ]);
    }

    /**
     * Write a message to the log as queued, and hand back its id.
     *
     * Called by the page just before it opens the chat link, so the log
     * records the attempt even if the teacher never comes back to
     * confirm.
     */
    public function queue(): void
    {
        AuthMiddleware::requireStaff();
        verifyCsrfToken();

        $phone = WhatsAppService::normalisePhone($_POST['phone'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($phone === null || $body === '') {
            $this->json(['ok' => false, 'message' => 'That number or message is not usable.'], 422);
        }

        $scope = BranchScope::current();
        $qClass = ($_POST['class_id'] ?? '') !== '' ? (int) $_POST['class_id'] : null;
        $qStudent = ($_POST['student_id'] ?? '') !== '' ? (int) $_POST['student_id'] : null;
        if (!$scope->isGlobal()
            && (($qClass === null && $qStudent === null)
                || ($qClass !== null && !$scope->allowsClass($qClass))
                || ($qStudent !== null && !$scope->allowsStudent($qStudent)))) {
            $this->json(['ok' => false, 'message' => 'That family is not in your branch.'], 403);
        }

        $id = $this->wa->queue([
            'student_id'     => ($_POST['student_id'] ?? '') !== '' ? (int) $_POST['student_id'] : null,
            'class_id'       => ($_POST['class_id'] ?? '') !== '' ? (int) $_POST['class_id'] : null,
            'payment_id'     => ($_POST['payment_id'] ?? '') !== '' ? (int) $_POST['payment_id'] : null,
            'recipient_name' => trim($_POST['recipient_name'] ?? 'Guardian'),
            'recipient_phone' => $phone,
            'recipient_type' => in_array($_POST['recipient_type'] ?? '', ['student', 'parent', 'other'], true)
                ? $_POST['recipient_type'] : 'parent',
            'template_key'   => ($_POST['template_key'] ?? '') !== '' ? $_POST['template_key'] : null,
            'body'           => $body,
        ]);

        if ($this->wa->isGatewayConfigured()) {
            $gwRes = $this->wa->sendViaGateway($phone, $body);
            if ($gwRes['ok']) {
                $this->wa->markSent($id, (int) session_get('user_id'));
                $this->json(['ok' => true, 'id' => $id, 'gateway' => true, 'sent' => true]);
                return;
            }
        }

        $this->json(['ok' => true, 'id' => $id]);
    }

    /**
     * Confirm that a queued message was actually sent.
     */
    public function confirm(): void
    {
        AuthMiddleware::requireStaff();
        verifyCsrfToken();

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) session_get('user_id');

        if (!$this->messageInScope($id)) {
            $this->json(['ok' => false, 'message' => 'That message is not in your branch.'], 403);
        }

        $ok = ($_POST['outcome'] ?? 'sent') === 'skipped'
            ? $this->wa->markSkipped($id, $userId, trim($_POST['note'] ?? '') ?: null)
            : $this->wa->markSent($id, $userId);

        $this->json(['ok' => $ok]);
    }

    /**
     * Everything that has been composed, and what became of it.
     */
    public function log(): void
    {
        AuthMiddleware::requireStaff();

        $status = in_array($_GET['status'] ?? '', ['queued', 'sent', 'skipped', 'failed'], true)
            ? $_GET['status'] : null;

        $messages = $this->wa->log(200, $status);
        $counts = $this->wa->counts();
        $scope = BranchScope::current();
        if (!$scope->isGlobal()) {
            $messages = array_values(array_filter($messages, fn (array $m): bool => $this->rowInScope($m)));
            $all = array_values(array_filter($this->wa->log(500, null), fn (array $m): bool => $this->rowInScope($m)));
            $counts = ['queued' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
            foreach ($all as $m) {
                $counts[$m['send_status']] = ($counts[$m['send_status']] ?? 0) + 1;
            }
        }

        $this->view('whatsapp/log', [
            'pageTitle'  => 'Message log',
            'activePage' => 'whatsapp',
            'messages'   => $messages,
            'counts'     => $counts,
            'status'     => $status,
        ]);
    }

    // ---------- helpers ----------

    /** @var array<int, string|null> */
    private array $teacherByClass = [];

    private function classTeacher(int $classId): ?string
    {
        if (!array_key_exists($classId, $this->teacherByClass)) {
            $stmt = $this->db->prepare(
                "SELECT u.full_name FROM classes c JOIN users u ON u.id = c.teacher_id WHERE c.id = :c"
            );
            $stmt->execute([':c' => $classId]);
            $name = $stmt->fetchColumn();
            $this->teacherByClass[$classId] = $name !== false ? (string) $name : null;
        }

        return $this->teacherByClass[$classId];
    }

    /** @param array<string, mixed> $m */
    private function rowInScope(array $m): bool
    {
        $scope = BranchScope::current();
        if (!empty($m['class_id'])) {
            return $scope->allowsClass((int) $m['class_id']);
        }
        if (!empty($m['student_id'])) {
            return $scope->allowsStudent((int) $m['student_id']);
        }

        return $scope->isGlobal();
    }

    private function messageInScope(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT class_id, student_id FROM whatsapp_messages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false && $this->rowInScope($row);
    }

    private function teacherName(): string
    {
        $name = $this->db->query(
            "SELECT full_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY id LIMIT 1"
        )->fetchColumn();

        return $name !== false ? (string) $name : 'The class';
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(): array
    {
        return $this->db->query(
            "SELECT id, class_name FROM classes WHERE status = 'active'"
            . BranchScope::current()->classSql('id') . " ORDER BY grade, class_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instituteOptions(): array
    {
        return $this->db->query(
            "SELECT id, name FROM institutes WHERE status = 'active'"
            . BranchScope::current()->instituteSql('id') . " ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
