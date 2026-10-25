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
    private const ROLES = ['super_admin', 'admin', 'coordinator', 'staff'];

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
