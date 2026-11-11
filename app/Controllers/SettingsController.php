<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class SettingsController extends BaseController
{
    /** The only keys the General Settings form may read or write. */
    private const GENERAL_KEYS = ['site_name', 'admin_email'];

    private \Setting $settingModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new \Setting();
    }

    /**
     * Main settings page.
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings: the super admin only (viewing included).
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $settings = [];
        foreach (self::GENERAL_KEYS as $key) {
            $settings[$key] = (string) $this->settingModel->get($key, '');
        }
        // The template's old placeholder is shown as blank: saving it back
        // would be the same as leaving the name unset anyway.
        if (strcasecmp(trim($settings['site_name']), 'EduManager') === 0) {
            $settings['site_name'] = '';
        }

        $this->view('settings/index', [
            'pageTitle'    => 'System Settings',
            'settings'     => $settings,
            'brandPreview' => \App\Services\PublicSite::brandName(),
        ]);
    }

    /**
     * General settings update (POST).
     */
    public function update(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings apply to every branch: super admin only.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $posted = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];

        // Only the fields this form shows can be written from here; every
        // other system setting has its own page and its own validation.
        $settings = [];
        foreach (self::GENERAL_KEYS as $key) {
            if (array_key_exists($key, $posted) && is_string($posted[$key])) {
                $settings[$key] = trim($posted[$key]);
            }
        }
        if (isset($settings['site_name']) && mb_strlen($settings['site_name']) > 80) {
            $this->flash('error', 'Site name must be 80 characters or fewer.');
            $this->redirect(BASE_URL . '/settings');
            return;
        }
        if (($settings['admin_email'] ?? '') !== '' && !filter_var($settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Please enter a valid email address.');
            $this->redirect(BASE_URL . '/settings');
            return;
        }

        try {
            foreach ($settings as $key => $value) {
                $this->settingModel->set($key, $value, $userId);
            }
            $this->logAudit('update_settings', 'settings', 0, ['keys' => array_keys($settings)]);
            $this->flash('success', 'Settings updated successfully.');
        } catch (\Throwable $e) {
            error_log('Settings update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update settings.');
        }

        $this->redirect(BASE_URL . '/settings');
    }

    /**
     * Deactivation settings page.
     */
    public function deactivation(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings: the super admin only (viewing included).
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $deactivationSettings = [];
        try {
            $deactivationSettings = $this->settingModel->getDeactivationSettings();
        } catch (\Throwable $e) {
            error_log('Deactivation settings load error: ' . $e->getMessage());
        }

        $this->view('settings/deactivation', [
            'pageTitle' => 'Deactivation Settings',
            'settings'  => $deactivationSettings,
        ]);
    }

    /**
     * Update deactivation settings (POST).
     */
    public function updateDeactivation(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings apply to every branch: super admin only.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $data = $_POST['deactivation'] ?? [];

        try {
            $this->settingModel->updateDeactivationSettings($data, $userId);
            $this->logAudit('update_deactivation_settings', 'settings', 0);
            $this->flash('success', 'Deactivation settings updated successfully.');
        } catch (\Throwable $e) {
            error_log('Deactivation settings update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update deactivation settings.');
        }

        $this->redirect(BASE_URL . '/settings/deactivation');
    }

    /**
     * Deletion settings page.
     */
    public function deletion(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings: the super admin only (viewing included).
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $deletionSettings = [];
        try {
            $deletionSettings = $this->settingModel->getDeletionSettings();
        } catch (\Throwable $e) {
            error_log('Deletion settings load error: ' . $e->getMessage());
        }

        $this->view('settings/deletion', [
            'pageTitle' => 'Deletion Settings',
            'settings'  => $deletionSettings,
        ]);
    }

    /**
     * Update deletion settings (POST).
     */
    public function updateDeletion(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings apply to every branch: super admin only.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $data = $_POST['deletion'] ?? [];

        try {
            $this->settingModel->updateDeletionSettings($data, $userId);
            $this->logAudit('update_deletion_settings', 'settings', 0);
            $this->flash('success', 'Deletion settings updated successfully.');
        } catch (\Throwable $e) {
            error_log('Deletion settings update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update deletion settings.');
        }

        $this->redirect(BASE_URL . '/settings/deletion');
    }

    /**
     * Dashboard items configuration page.
     */
    public function dashboardItems(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings: the super admin only (viewing included).
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $roles = AuthMiddleware::ALL_ROLES;
        $widgetsByRole = [];

        try {
            foreach ($roles as $role) {
                $widgetsByRole[$role] = $this->settingModel->getDashboardWidgets($role);
            }
        } catch (\Throwable $e) {
            error_log('Dashboard widgets load error: ' . $e->getMessage());
        }

        $this->view('settings/dashboard-items', [
            'pageTitle'     => 'Dashboard Items',
            'roles'         => $roles,
            'widgetsByRole' => $widgetsByRole,
        ]);
    }

    /**
     * Update dashboard items (POST).
     */
    public function updateDashboardItems(): void
    {
        AuthMiddleware::requireAuth();
        // System-wide settings apply to every branch: super admin only.
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $widgets = $_POST['widgets'] ?? [];

        try {
            foreach ($widgets as $role => $items) {
                foreach ($items as $widgetKey => $isVisible) {
                    $this->settingModel->updateDashboardWidget(
                        (string) $role,
                        (string) $widgetKey,
                        (bool) $isVisible,
                        $userId
                    );
                }
            }
            $this->logAudit('update_dashboard_items', 'settings', 0);
            $this->flash('success', 'Dashboard items updated successfully.');
        } catch (\Throwable $e) {
            error_log('Dashboard items update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update dashboard items.');
        }

        $this->redirect(BASE_URL . '/settings/dashboard-items');
    }

    /**
     * Role-based feature visibility (nav-only) configuration page.
     */
    public function featureVisibility(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $roles = array_values(array_filter(
            AuthMiddleware::ALL_ROLES,
            static fn (string $r): bool => $r !== AuthMiddleware::ROLE_SUPER_ADMIN
        ));
        $visibilityByRole = [];

        try {
            foreach ($roles as $role) {
                $visibilityByRole[$role] = $this->settingModel->getFeatureVisibility($role);
            }
        } catch (\Throwable $e) {
            error_log('Feature visibility load error: ' . $e->getMessage());
        }

        $this->view('settings/feature-visibility', [
            'pageTitle'        => 'Feature Visibility',
            'roles'            => $roles,
            'visibilityByRole' => $visibilityByRole,
        ]);
    }

    /**
     * Update role-based feature visibility (POST).
     */
    public function updateFeatureVisibility(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $role = (string) ($_POST['active_role'] ?? '');
        $features = $_POST['features'] ?? [];

        if ($role === '' || $role === AuthMiddleware::ROLE_SUPER_ADMIN || !in_array($role, AuthMiddleware::ALL_ROLES, true)) {
            $this->flash('error', 'Choose a valid role.');
            $this->redirect(BASE_URL . '/settings/feature-visibility');
        }

        try {
            foreach (\Setting::FEATURE_MODULES as $key => $label) {
                if (!in_array($role, \Setting::FEATURE_ROLES[$key] ?? [], true)) {
                    continue; // not offered for this role; nothing was submitted for it either
                }
                $isVisible = !empty($features[$key]);
                $this->settingModel->updateFeatureVisibility($role, (string) $key, $isVisible, $userId);
            }
            $this->logAudit('update_feature_visibility', 'settings', 0, ['role' => $role]);
            $this->flash('success', 'Feature visibility updated for ' . $role . '.');
        } catch (\Throwable $e) {
            error_log('Feature visibility update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update feature visibility.');
        }

        $this->redirect(BASE_URL . '/settings/feature-visibility?role=' . urlencode($role));
    }

    /**
     * Log an audit entry.
     */
    /**
     * The small, fixed set of editable public-website text blocks (see
     * SiteContentService / migration 019).
     */
    public function siteContent(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $service = new \App\Services\SiteContentService($this->db);
        $this->view('settings/site-content', [
            'pageTitle'    => 'Website Content',
            'activeSubPage' => 'site-content',
            'fields'       => \App\Services\SiteContentService::FIELDS,
            'values'       => $service->getAll(),
        ]);
    }

    public function updateSiteContent(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $service = new \App\Services\SiteContentService($this->db);
        $userId = (int) session_get('user_id');

        try {
            foreach (\App\Services\SiteContentService::FIELDS as $key => $field) {
                if (($field['type'] ?? 'text') === \App\Services\SiteContentService::TYPE_IMAGE) {
                    if (!empty($_FILES['images']['name'][$key]) && ($_FILES['images']['error'][$key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $file = [
                            'tmp_name' => $_FILES['images']['tmp_name'][$key],
                            'name'     => $_FILES['images']['name'][$key],
                            'type'     => $_FILES['images']['type'][$key],
                            'size'     => $_FILES['images']['size'][$key],
                        ];
                        $service->setImage($key, $file, $userId);
                    } elseif (!empty($_POST['remove_images'][$key])) {
                        $service->clearImage($key, $userId);
                    }
                    continue;
                }
                $value = trim((string) ($_POST['fields'][$key] ?? ''));
                $service->set($key, mb_substr($value, 0, 4000), $userId);
            }
            $this->logAudit('update_site_content', 'site_content', 0);
            $this->flash('success', 'Website content updated.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Site content update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update website content.');
        }

        $this->redirect(BASE_URL . '/settings/site-content');
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'unknown',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
