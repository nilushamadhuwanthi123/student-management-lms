<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use PDO;

/**
 * Enrolment inquiries and contact messages from the public website.
 *
 * Super admin: everything. Branch admin / coordinator / staff: the
 * enrolment inquiries for their own branch. Personal details from the
 * public are not shown to anyone else.
 */
class InquiryController extends BaseController
{
    // Staff deliberately left out: enrolment inquiries go to the branch's
    // admin/coordinator, not the front-office staff. Super admin keeps
    // oversight of every branch's inquiries.
    private const ROLES = ['super_admin', 'admin', 'coordinator'];

    public function index(): void
    {
        $this->guard();
        $status = in_array($_GET['status'] ?? '', ['new', 'read', 'responded'], true) ? $_GET['status'] : null;

        $sql = "SELECT m.*, i.name AS branch_name FROM contact_messages m
                  LEFT JOIN institutes i ON i.id = m.institute_id
                 WHERE " . $this->scopeSql();
        $params = [];
        if ($status !== null) {
            $sql .= " AND m.status = :s";
            $params[':s'] = $status;
        }
        $stmt = $this->db->prepare($sql . " ORDER BY FIELD(m.status, 'new', 'read', 'responded'), m.created_at DESC LIMIT 300");
        $stmt->execute($params);

        $this->view('inquiries/index', [
            'pageTitle'  => 'Inquiries',
            'activePage' => 'inquiries',
            'items'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'status'     => $status,
        ]);
    }

    public function status(string $id): void
    {
        $this->guard();
        verifyCsrfToken();
        $status = in_array($_POST['status'] ?? '', ['new', 'read', 'responded'], true) ? $_POST['status'] : 'read';

        $stmt = $this->db->prepare("UPDATE contact_messages m SET m.status = :s WHERE m.id = :id AND " . $this->scopeSql());
        $stmt->execute([':s' => $status, ':id' => (int) $id]);
        if ($stmt->rowCount() > 0) {
            try {
                (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), 'inquiry_status', 'contact_message', (int) $id, ['status' => $status]);
            } catch (\Throwable $e) {
                error_log('Inquiry audit failed: ' . $e->getMessage());
            }
        }

        $this->redirect('/inquiries');
    }

    /**
     * "Approve" an enrolment inquiry: marks it responded and sends the
     * admin/coordinator straight to the student registration form with
     * the parent's details already filled in, so nothing from the
     * inquiry has to be retyped. Registering the student is still a
     * separate, deliberate step -- approving an inquiry never creates a
     * login on its own.
     */
    public function approve(string $id): void
    {
        $this->guard();
        verifyCsrfToken();

        $id = (int) $id;
        $stmt = $this->db->prepare("SELECT m.* FROM contact_messages m WHERE m.id = :id AND m.kind = 'enrollment' AND " . $this->scopeSql());
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $this->flash('error', 'That inquiry was not found, or is not an enrolment inquiry for your branch.');
            $this->redirect('/inquiries');
        }

        $this->db->prepare("UPDATE contact_messages SET status = 'responded' WHERE id = :id")->execute([':id' => $id]);
        try {
            (new \AuditLog())->log((int) session_get('user_id'), 'user', (string) session_get('user_role'), 'inquiry_approve', 'contact_message', $id, ['student_name' => $row['student_name'] ?? '']);
        } catch (\Throwable $e) {
            error_log('Inquiry audit failed: ' . $e->getMessage());
        }

        $this->redirect('/students/create?from_inquiry=' . $id);
    }

    private function guard(): void
    {
        AuthMiddleware::requireAuth();
        if (session_get('user_type') !== 'user' || !in_array((string) session_get('user_role'), self::ROLES, true)) {
            $this->flash('error', 'You do not have permission to access this page.');
            $this->redirect('/dashboard');
        }
    }

    private function scopeSql(): string
    {
        $scope = BranchScope::current();
        if ($scope->isGlobal()) {
            return '1 = 1';
        }

        return "m.kind = 'enrollment'" . $scope->instituteSql('m.institute_id');
    }
}
