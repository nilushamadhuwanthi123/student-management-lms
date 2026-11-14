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
            'groups'       => \App\Services\SiteContentService::GROUPS,
            'langs'        => \App\Services\SiteContentService::LANGS,
            'baseLang'     => \App\Services\SiteContentService::BASE_LANG,
            'values'       => $service->getAllForEditing(),
        ]);
    }

    /**
     * The SMS gateway that carries password-reset codes to the phone on
     * the account. Super Admin only: the API key is a credential.
     */
    public function sms(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();

        $service = new \App\Services\SmsService($this->settingModel);
        $config = $service->config();

        $this->view('settings/sms', [
            'pageTitle'      => 'Text messages (SMS)',
            'activeSubPage'  => 'sms',
            'providers'      => \App\Services\SmsService::PROVIDERS,
            'config'         => $config,
            'isConfigured'   => $service->isConfigured(),
            // Never sent back to the browser; the form shows whether one
            // is saved and replaces it only when a new one is typed.
            'hasApiKey'      => $config[\App\Services\SmsService::KEY_API_KEY] !== '',
        ]);
    }

    public function updateSms(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $keys = \App\Services\SmsService::class;

        try {
            $provider = (string) ($_POST['sms_provider'] ?? '');
            if ($provider !== '' && !isset(\App\Services\SmsService::PROVIDERS[$provider])) {
                throw new \InvalidArgumentException(t('Choose one of the listed gateways.'));
            }

            $this->settingModel->set($keys::KEY_ENABLED, empty($_POST['sms_enabled']) ? '0' : '1', $userId);
            $this->settingModel->set($keys::KEY_PROVIDER, $provider, $userId);
            $this->settingModel->set($keys::KEY_USER_ID, trim((string) ($_POST['sms_user_id'] ?? '')), $userId);
            $this->settingModel->set($keys::KEY_SENDER_ID, trim((string) ($_POST['sms_sender_id'] ?? '')), $userId);
            $this->settingModel->set($keys::KEY_CUSTOM_URL, trim((string) ($_POST['sms_custom_url'] ?? '')), $userId);

            // An empty key box means "leave the saved one alone", so a
            // save after a reload does not wipe the credential.
            $apiKey = trim((string) ($_POST['sms_api_key'] ?? ''));
            if ($apiKey !== '') {
                $this->settingModel->set($keys::KEY_API_KEY, $apiKey, $userId);
            }

            $this->logAudit('update_sms_settings', 'system_settings', 0);
            $this->flash('success', t('Text message settings saved.'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('SMS settings update error: ' . $e->getMessage());
            $this->flash('error', t('Could not save the text message settings.'));
        }

        $this->redirect(BASE_URL . '/settings/sms');
    }

    /**
     * Send one real message to a number the Super Admin types, so the
     * gateway can be proven before a student depends on it.
     */
    public function testSms(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $to = trim((string) ($_POST['test_phone'] ?? ''));
        $service = new \App\Services\SmsService($this->settingModel);

        if (!$service->isConfigured()) {
            $this->flash('error', t('Fill in the gateway details and switch it on first.'));
            $this->redirect(BASE_URL . '/settings/sms');
        }

        $sent = $service->send($to, t('This is a test message from the class portal. The gateway is working.'));

        $this->logAudit('test_sms', 'system_settings', 0);
        if ($sent) {
            $this->flash('success', t('Test message sent. Check the phone.'));
        } else {
            $this->flash('error', t('The gateway did not accept the message. Check the number and the details, then try again.'));
        }

        $this->redirect(BASE_URL . '/settings/sms');
    }

    /**
     * Plain-text email for login details, sent by PHP's built-in mail().
     * Super Admin only -- same reasoning as the SMS page.
     */
    public function email(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();

        $service = new \App\Services\EmailService($this->settingModel);
        $config = $service->config();

        $this->view('settings/email', [
            'pageTitle'     => 'Email',
            'activeSubPage' => 'email',
            'config'        => $config,
            'isConfigured'  => $service->isConfigured(),
        ]);
    }

    public function updateEmail(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $keys = \App\Services\EmailService::class;

        try {
            $fromAddress = trim((string) ($_POST['email_from_address'] ?? ''));
            if ($fromAddress !== '' && !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(t('Enter a valid "From" email address.'));
            }

            $smtpHost = trim((string) ($_POST['email_smtp_host'] ?? ''));
            $smtpPort = trim((string) ($_POST['email_smtp_port'] ?? ''));
            $smtpEncryption = in_array($_POST['email_smtp_encryption'] ?? '', ['', 'ssl', 'tls'], true)
                ? (string) $_POST['email_smtp_encryption'] : '';
            $smtpUsername = trim((string) ($_POST['email_smtp_username'] ?? ''));
            $smtpPassword = (string) ($_POST['email_smtp_password'] ?? '');

            if ($smtpPort !== '' && (!ctype_digit($smtpPort) || (int) $smtpPort < 1 || (int) $smtpPort > 65535)) {
                throw new \InvalidArgumentException(t('Enter a valid SMTP port (1-65535).'));
            }

            $this->settingModel->set($keys::KEY_ENABLED, empty($_POST['email_enabled']) ? '0' : '1', $userId);
            $this->settingModel->set($keys::KEY_FROM_NAME, trim((string) ($_POST['email_from_name'] ?? '')), $userId);
            $this->settingModel->set($keys::KEY_FROM_ADDRESS, $fromAddress, $userId);
            $this->settingModel->set($keys::KEY_SMTP_HOST, $smtpHost, $userId);
            $this->settingModel->set($keys::KEY_SMTP_PORT, $smtpPort, $userId);
            $this->settingModel->set($keys::KEY_SMTP_ENCRYPTION, $smtpEncryption, $userId);
            $this->settingModel->set($keys::KEY_SMTP_USERNAME, $smtpUsername, $userId);
            // The password field is left blank in the form on every page
            // load (never echoed back); only overwrite the stored one
            // when the office actually typed a new value.
            if ($smtpPassword !== '') {
                $this->settingModel->set($keys::KEY_SMTP_PASSWORD, $smtpPassword, $userId);
            }

            $this->logAudit('update_email_settings', 'system_settings', 0);
            $this->flash('success', t('Email settings saved.'));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Email settings update error: ' . $e->getMessage());
            $this->flash('error', t('Could not save the email settings.'));
        }

        $this->redirect(BASE_URL . '/settings/email');
    }

    /**
     * Send one real email to an address the Super Admin types, so
     * mail() on this server can be proven before anyone depends on it.
     */
    public function testEmail(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $to = trim((string) ($_POST['test_email'] ?? ''));
        $service = new \App\Services\EmailService($this->settingModel);

        if (!$service->isConfigured()) {
            $this->flash('error', t('Fill in a "From" address and switch email on first.'));
            $this->redirect(BASE_URL . '/settings/email');
        }

        $sent = $service->send(
            $to,
            t('Test email from the class portal'),
            t('This is a test email from the class portal. If you can read this, email sending is working.')
        );

        $this->logAudit('test_email', 'system_settings', 0);
        if ($sent) {
            $this->flash('success', t('Test email handed to the server. Check the inbox (and spam folder).'));
        } else {
            $this->flash('error', t('The email could not be sent. If no SMTP host is filled in above, add one (Gmail, the domain\'s own mailbox, etc.) -- a local XAMPP install has no mail server of its own, so plain mail() will not work here.'));
        }

        $this->redirect(BASE_URL . '/settings/email');
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
                foreach (\App\Services\SiteContentService::LANGS as $lang) {
                    $posted = $_POST['fields'][$lang][$key] ?? null;
                    if ($posted === null) {
                        continue; // that language was not in the form at all
                    }
                    $value = trim((string) $posted);
                    $service->set($key, mb_substr($value, 0, 4000), $userId, $lang);
                }
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


    /**
     * Standalone promotional banners for the public home page (see
     * SiteAdService / migration 023) -- separate from the "show on the
     * website" flag on a seminar, which advertises a specific dated
     * event. This is for a plain announcement with no event behind it.
     */
    public function ads(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $service = new \App\Services\SiteAdService();
        $this->view('settings/ads', [
            'pageTitle'     => 'Website Ads',
            'activeSubPage' => 'ads',
            'ads'           => $service->all(),
        ]);
    }

    public function storeAd(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $service = new \App\Services\SiteAdService();
        $userId = (int) session_get('user_id');

        try {
            $id = $service->create([
                'title'         => $_POST['title'] ?? '',
                'link_url'      => $_POST['link_url'] ?? '',
                'starts_on'     => $_POST['starts_on'] ?? '',
                'ends_on'       => $_POST['ends_on'] ?? '',
                'is_active'     => $_POST['is_active'] ?? '',
                'show_as_popup' => $_POST['show_as_popup'] ?? '',
                'sort_order'    => $_POST['sort_order'] ?? 0,
            ], $userId);

            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $service->setImage($id, $_FILES['image']);
            }

            $this->logAudit('create_site_ad', 'site_ad', $id);
            $this->flash('success', 'Ad created.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Site ad create error: ' . $e->getMessage());
            $this->flash('error', 'Failed to create the ad.');
        }

        $this->redirect(BASE_URL . '/settings/ads');
    }

    public function updateAd(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $service = new \App\Services\SiteAdService();
        $userId = (int) session_get('user_id');

        try {
            $service->update((int) $id, [
                'title'         => $_POST['title'] ?? '',
                'link_url'      => $_POST['link_url'] ?? '',
                'starts_on'     => $_POST['starts_on'] ?? '',
                'ends_on'       => $_POST['ends_on'] ?? '',
                'is_active'     => $_POST['is_active'] ?? '',
                'show_as_popup' => $_POST['show_as_popup'] ?? '',
                'sort_order'    => $_POST['sort_order'] ?? 0,
            ], $userId);

            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $service->setImage((int) $id, $_FILES['image']);
            }

            $this->logAudit('update_site_ad', 'site_ad', (int) $id);
            $this->flash('success', 'Ad updated.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Site ad update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update the ad.');
        }

        $this->redirect(BASE_URL . '/settings/ads');
    }

    public function toggleAd(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        (new \App\Services\SiteAdService())->toggle((int) $id);
        $this->logAudit('toggle_site_ad', 'site_ad', (int) $id);
        $this->redirect(BASE_URL . '/settings/ads');
    }

    public function deleteAd(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        (new \App\Services\SiteAdService())->delete((int) $id);
        $this->logAudit('delete_site_ad', 'site_ad', (int) $id);
        $this->flash('success', 'Ad deleted.');
        $this->redirect(BASE_URL . '/settings/ads');
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
