<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * Public "Downloads" page (past papers, notes and other documents the
 * teacher or super admin has shared), plus the manage screen behind it.
 *
 * Files are not stored on this server: each row is just a title and an
 * external link (typically a Google Drive share link), kept deliberately
 * light in the same spirit as SiteAdService.
 */
class DownloadController extends BaseController
{
    /** Public: the Downloads nav tab, open to anyone -- same audience as the rest of the public site. */
    public function index(): void
    {
        $service = new \App\Services\DownloadService($this->db);
        $this->view('public/downloads', [
            'pageTitle'       => t('Downloads') . ' | ' . t('Science classes'),
            'metaDescription' => 'Past papers, notes and other files shared by the teacher.',
            'activePage'      => 'downloads',
            'folders'         => $service->activeGrouped(),
        ], 'layouts/public');
    }

    /** Manage screen: the super admin, or the teacher who records the uploads. */
    public function manage(): void
    {
        $this->guard();
        $service = new \App\Services\DownloadService($this->db);
        $this->view('downloads/manage', [
            'pageTitle'  => 'Downloads',
            'activePage' => 'downloads',
            'items'      => $service->all(),
            'categories' => $service->categories(),
        ]);
    }

    public function store(): void
    {
        $this->guard();
        verifyCsrfToken();

        $service = new \App\Services\DownloadService($this->db);
        $userId = (int) session_get('user_id');

        try {
            $id = $service->create([
                'title'      => $_POST['title'] ?? '',
                'category'   => $_POST['category'] ?? '',
                'drive_url'  => $_POST['drive_url'] ?? '',
                'is_active'  => $_POST['is_active'] ?? '',
                'sort_order' => $_POST['sort_order'] ?? 0,
            ], $userId);
            $this->logAudit('create_download', 'site_download', $id);
            $this->flash('success', t('Download added.'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Download create error: ' . $e->getMessage());
            $this->flash('error', t('Could not add the download.'));
        }

        $this->redirect(BASE_URL . '/downloads/manage');
    }

    public function update(string $id): void
    {
        $this->guard();
        verifyCsrfToken();

        $service = new \App\Services\DownloadService($this->db);

        try {
            $service->update((int) $id, [
                'title'      => $_POST['title'] ?? '',
                'category'   => $_POST['category'] ?? '',
                'drive_url'  => $_POST['drive_url'] ?? '',
                'is_active'  => $_POST['is_active'] ?? '',
                'sort_order' => $_POST['sort_order'] ?? 0,
            ]);
            $this->logAudit('update_download', 'site_download', (int) $id);
            $this->flash('success', t('Download updated.'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Download update error: ' . $e->getMessage());
            $this->flash('error', t('Could not update the download.'));
        }

        $this->redirect(BASE_URL . '/downloads/manage');
    }

    public function toggle(string $id): void
    {
        $this->guard();
        verifyCsrfToken();

        (new \App\Services\DownloadService($this->db))->toggle((int) $id);
        $this->logAudit('toggle_download', 'site_download', (int) $id);
        $this->redirect(BASE_URL . '/downloads/manage');
    }

    public function delete(string $id): void
    {
        $this->guard();
        verifyCsrfToken();

        (new \App\Services\DownloadService($this->db))->delete((int) $id);
        $this->logAudit('delete_download', 'site_download', (int) $id);
        $this->flash('success', t('Download deleted.'));
        $this->redirect(BASE_URL . '/downloads/manage');
    }

    /** Only the super admin, or a teacher recording their own uploads. */
    private function guard(): void
    {
        AuthMiddleware::requireAuth();
        $role = (string) session_get('user_role');
        if (!in_array($role, [AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_TEACHER], true)) {
            $this->flash('error', t('You do not have permission to access this page.'));
            $this->redirect(BASE_URL . '/dashboard');
        }
    }

    private function logAudit(string $action, string $entityType, int $entityId): void
    {
        try {
            $user = $this->getCurrentUser();
            (new \AuditLog())->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'unknown',
                $action,
                $entityType,
                $entityId,
                []
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
