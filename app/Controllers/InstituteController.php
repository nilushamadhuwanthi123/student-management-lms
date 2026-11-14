<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

class InstituteController extends BaseController
{
    private \Institute $instituteModel;
    private \User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->instituteModel = new \Institute();
        $this->userModel = new \User();
    }

    /**
     * 1.2 Institutes Page: List institutes with search and columns:
     * INSTITUTE, COORDINATOR, PHONE (1), STATUS, ACTIONS (Edit, Activate/Deactivate, Delete)
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $search = trim($_GET['search'] ?? '');
        $institutes = $this->instituteModel->getAllWithCoordinator($search);
        $institutes = array_values(array_filter($institutes, static fn (array $i): bool => BranchScope::current()->allowsInstitute((int) $i['id'])));

        // Fetch coordinators for modal / quick register
        $coordinators = $this->userModel->getByRole('coordinator');

        $this->view('institutes/index', [
            'pageTitle'    => 'Institutes',
            'institutes'   => $institutes,
            'coordinators' => $coordinators,
            'search'       => $search,
        ], 'layouts/admin');
    }

    /**
     * Show Register Institute Form
     */
    public function create(): void
    {
        AuthMiddleware::requireAuth();
        // Adding, closing or removing a branch is the super admin's decision.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $coordinators = $this->userModel->getByRole('coordinator');

        $this->view('institutes/create', [
            'pageTitle'    => 'Register New Institute',
            'coordinators' => $coordinators,
        ], 'layouts/admin');
    }

    /**
     * Store new Institute
     */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        // Adding, closing or removing a branch is the super admin's decision.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $name          = trim($_POST['name'] ?? '');
        $coordinatorId = !empty($_POST['coordinator_id']) ? (int) $_POST['coordinator_id'] : null;
        $location      = trim($_POST['location'] ?? '');
        $phone1        = trim($_POST['phone1'] ?? '');
        $phone2        = trim($_POST['phone2'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');
        // Only a real Google Maps address is kept -- this value is put in
        // an iframe src on the public contact page.
        $mapUrl        = \App\Services\PublicSite::isGoogleMapsUrl(trim($_POST['map_url'] ?? ''))
            ? trim($_POST['map_url'])
            : null;
        $publicDesc    = trim($_POST['public_description'] ?? '');
        $videoUrl      = \Institute::videoHref($_POST['video_url'] ?? '');
        $status        = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
        // How this branch orders against the others on the public site
        // (Classes page, home page, footer). Lower shows first.
        $sortOrder     = (int) ($_POST['sort_order'] ?? 0);
        $bannerSize    = in_array($_POST['banner_size'] ?? '', ['small', 'medium', 'large'], true) ? $_POST['banner_size'] : 'medium';

        $errors = [];
        if (empty($name)) {
            $errors[] = 'Institute Name is required.';
        }
        if (empty($location)) {
            $errors[] = 'Location is required.';
        }

        if (!empty($errors)) {
            $this->flash('error', implode('<br>', $errors));
            $this->redirect('/institutes/create');
        }

        try {
            $id = $this->instituteModel->create([
                'name'               => $name,
                'coordinator_id'     => $coordinatorId,
                'location'           => $location,
                'map_url'            => $mapUrl,
                'phone1'             => $phone1,
                'phone2'             => $phone2,
                'notes'              => $notes,
                'public_description' => $publicDesc !== '' ? $publicDesc : null,
                'video_url'          => $videoUrl !== '' ? $videoUrl : null,
                'status'             => $status,
                'sort_order'         => $sortOrder,
                'banner_size'        => $bannerSize,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                try {
                    $this->instituteModel->setPhoto((int) $id, $_FILES['photo']);
                } catch (\InvalidArgumentException $e) {
                    $this->flash('error', $e->getMessage());
                }
            }
            // A pasted video link (handled above, via $videoUrl) always wins over an
            // uploaded file if both are submitted in the same save.
            if ($videoUrl === '' && !empty($_FILES['video']['tmp_name']) && is_uploaded_file($_FILES['video']['tmp_name'])) {
                try {
                    $this->instituteModel->setVideo((int) $id, $_FILES['video']);
                } catch (\InvalidArgumentException $e) {
                    $this->flash('error', $e->getMessage());
                }
            }

            $this->logAudit('create_institute', 'institute', $id, ['name' => $name]);
            $this->flash('success', "Institute '{$name}' registered successfully.");
            $this->redirect('/institutes');
        } catch (\Throwable $e) {
            error_log('Institute create error: ' . $e->getMessage());
            $this->flash('error', 'Failed to register institute: ' . $e->getMessage());
            $this->redirect('/institutes/create');
        }
    }

    /**
     * Show Edit Institute Form
     */
    public function edit(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $institute = $this->instituteModel->find((int) $id);
        if (!$institute) {
            $this->flash('error', 'Institute not found.');
            $this->redirect('/institutes');
        }
        if (!BranchScope::current()->allowsInstitute((int) $institute['id'])) {
            http_response_code(403);
            $this->flash('error', 'That branch is not yours to manage.');
            $this->redirect('/institutes');
        }

        $coordinators = $this->userModel->getByRole('coordinator');

        $this->view('institutes/edit', [
            'pageTitle'    => 'Edit Institute - ' . ($institute['name'] ?? ''),
            'institute'    => $institute,
            'coordinators' => $coordinators,
        ], 'layouts/admin');
    }

    /**
     * Process Institute Update
     */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $institute = $this->instituteModel->find((int) $id);
        if (!$institute) {
            $this->flash('error', 'Institute not found.');
            $this->redirect('/institutes');
        }
        if (!BranchScope::current()->allowsInstitute((int) $institute['id'])) {
            http_response_code(403);
            $this->flash('error', 'That branch is not yours to manage.');
            $this->redirect('/institutes');
        }

        $name          = trim($_POST['name'] ?? '');
        $coordinatorId = !empty($_POST['coordinator_id']) ? (int) $_POST['coordinator_id'] : null;
        $location      = trim($_POST['location'] ?? '');
        $phone1        = trim($_POST['phone1'] ?? '');
        $phone2        = trim($_POST['phone2'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');
        // Only a real Google Maps address is kept -- this value is put in
        // an iframe src on the public contact page.
        $mapUrl        = \App\Services\PublicSite::isGoogleMapsUrl(trim($_POST['map_url'] ?? ''))
            ? trim($_POST['map_url'])
            : null;
        $publicDesc    = trim($_POST['public_description'] ?? '');
        $videoUrl      = \Institute::videoHref($_POST['video_url'] ?? '');
        $status        = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : $institute['status'];
        $sortOrder     = (int) ($_POST['sort_order'] ?? $institute['sort_order'] ?? 0);
        $bannerSize    = in_array($_POST['banner_size'] ?? '', ['small', 'medium', 'large'], true) ? $_POST['banner_size'] : ($institute['banner_size'] ?? 'medium');

        try {
            $this->instituteModel->update((int) $id, [
                'name'               => $name,
                'coordinator_id'     => $coordinatorId,
                'location'           => $location,
                'map_url'            => $mapUrl,
                'phone1'             => $phone1,
                'phone2'             => $phone2,
                'notes'              => $notes,
                'public_description' => $publicDesc !== '' ? $publicDesc : null,
                // A typed link always wins; otherwise keep whatever is already saved
                // (which may be a previously uploaded video file) rather than wiping
                // it just because the link box was left blank -- setVideo() below
                // updates this column directly when a new file is uploaded instead.
                'video_url'          => $videoUrl !== '' ? $videoUrl : ($institute['video_url'] ?? null),
                'status'             => $status,
                'sort_order'         => $sortOrder,
                'banner_size'        => $bannerSize,
                'updated_at'         => date('Y-m-d H:i:s'),
            ] + (\App\Services\BranchScope::current()->isGlobal() ? [
                // What the public website shows is the super admin's call.
                'is_public'        => isset($_POST['is_public']) ? 1 : 0,
                'public_show_fees' => isset($_POST['public_show_fees']) ? 1 : 0,
            ] : []));

            if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                try {
                    $this->instituteModel->setPhoto((int) $id, $_FILES['photo']);
                } catch (\InvalidArgumentException $e) {
                    $this->flash('error', $e->getMessage());
                }
            }
            // A pasted video link (handled above, via $videoUrl) always wins over an
            // uploaded file if both are submitted in the same save.
            if ($videoUrl === '' && !empty($_FILES['video']['tmp_name']) && is_uploaded_file($_FILES['video']['tmp_name'])) {
                try {
                    $this->instituteModel->setVideo((int) $id, $_FILES['video']);
                } catch (\InvalidArgumentException $e) {
                    $this->flash('error', $e->getMessage());
                }
            }

            $this->logAudit('update_institute', 'institute', (int) $id, ['name' => $name]);
            $this->flash('success', "Institute '{$name}' updated successfully.");
            $this->redirect('/institutes');
        } catch (\Throwable $e) {
            error_log('Institute update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update institute.');
            $this->redirect(BASE_URL . "/institutes/{$id}/edit");
        }
    }

    /**
     * Toggle Active/Inactive Status
     */
    public function toggleStatus(string $id): void
    {
        AuthMiddleware::requireAuth();
        // Adding, closing or removing a branch is the super admin's decision.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $newStatus = $this->instituteModel->toggleStatus((int) $id);
        $this->logAudit('toggle_institute_status', 'institute', (int) $id, ['new_status' => $newStatus]);

        $this->flash('success', "Institute status updated to " . ucfirst($newStatus) . ".");
        $this->redirect('/institutes');
    }

    /**
     * Delete Institute
     */
    public function delete(string $id): void
    {
        AuthMiddleware::requireAuth();
        // Adding, closing or removing a branch is the super admin's decision.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $instId = (int) $id;
        $institute = $this->instituteModel->find($instId);

        if (!$institute) {
            $this->flash('error', 'Institute not found.');
            $this->redirect('/institutes');
        }
        if (!BranchScope::current()->allowsInstitute((int) $institute['id'])) {
            http_response_code(403);
            $this->flash('error', 'That branch is not yours to manage.');
            $this->redirect('/institutes');
        }

        // Safety check: check if classes are associated
        $classes = $this->instituteModel->getClasses($instId);
        if (!empty($classes)) {
            $count = count($classes);
            $this->flash('error', "Cannot delete this institute because it has {$count} active class(es) linked to it. Please reassign or delete the classes first.");
            $this->redirect('/institutes');
        }

        $this->instituteModel->delete($instId);
        $this->logAudit('delete_institute', 'institute', $instId, ['name' => $institute['name']]);

        $this->flash('success', "Institute '{$institute['name']}' has been deleted.");
        $this->redirect('/institutes');
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'super_admin',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log error: ' . $e->getMessage());
        }
    }
}
