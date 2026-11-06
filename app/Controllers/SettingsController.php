<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class SettingsController extends BaseController
{
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

        $this->view('settings/index', [
            'pageTitle' => 'System Settings',
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
        $settings = $_POST['settings'] ?? [];

        try {
            foreach ($settings as $key => $value) {
                $this->settingModel->set((string) $key, (string) $value, $userId);
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
