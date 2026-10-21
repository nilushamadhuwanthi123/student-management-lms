<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
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

        $month = ContentAccessService::normaliseMonth($_GET['month'] ?? null);
        $classId = ($_GET['class_id'] ?? '') !== '' ? (int) $_GET['class_id'] : null;
        $instituteId = ($_GET['institute_id'] ?? '') !== '' ? (int) $_GET['institute_id'] : null;
        $templateKey = $_GET['template'] ?? 'fee_reminder';

        $template = $this->wa->template($templateKey) ?? $this->wa->template('fee_reminder');
        $rows = $this->wa->unpaidRecipients($month, $classId, $instituteId);

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
                'teacher' => $teacherName,
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

        $this->view('whatsapp/log', [
            'pageTitle'  => 'Message log',
            'activePage' => 'whatsapp',
            'messages'   => $this->wa->log(200, $status),
            'counts'     => $this->wa->counts(),
            'status'     => $status,
        ]);
    }

    // ---------- helpers ----------

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
            "SELECT id, class_name FROM classes WHERE status = 'active' ORDER BY grade, class_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instituteOptions(): array
    {
        return $this->db->query(
            "SELECT id, name FROM institutes WHERE status = 'active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
