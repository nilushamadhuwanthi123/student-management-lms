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
    /**
     * GET /site-builder
     *
     * The way in to everything that changes the public website. It owns
     * no settings of its own: the counts are read only so the cards can
     * say how much is already set up.
     */
    public function siteBuilder(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        // A count that cannot be read is shown as zero rather than
        // breaking the page the owner came here to use.
        $slideCount = 0;
        $adCount = 0;
        try {
            $slideCount = count((new \App\Services\HeroMediaService($this->db))->all());
        } catch (\Throwable $e) {
            error_log('Site Builder: hero count failed: ' . $e->getMessage());
        }
        try {
            $adCount = count((new \App\Services\SiteAdService($this->db))->all());
        } catch (\Throwable $e) {
            error_log('Site Builder: ad count failed: ' . $e->getMessage());
        }

        $this->view('settings/site-builder', [
            'pageTitle'     => t('Site Builder'),
            'activePage'    => 'sitebuilder',
            'activeSubPage' => 'builder-home',
            'slideCount'    => $slideCount,
            'adCount'       => $adCount,
        ]);
    }

    /**
     * POST /settings/site-content/preview
     *
     * Show the live site with the values currently typed into the form,
     * without saving any of them. Clicking through ten styles is only
     * useful if you can see what one does, and "save it and look" means
     * families briefly get whatever was being tried.
     *
     * The values go in the session, never the settings table, and
     * PublicSite only applies them for a signed-in super admin -- so a
     * half-finished look can never reach a visitor.
     */
    public function previewLook(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $base = \App\Services\SiteContentService::BASE_LANG;
        $values = [];

        foreach (\App\Services\SiteContentService::FIELDS as $key => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === \App\Services\SiteContentService::TYPE_ICONSET) {
                if (!empty($_POST['icons_present'][$key])) {
                    $chosen = is_array($_POST['icons'][$key] ?? null) ? $_POST['icons'][$key] : [];
                    $chosen = array_values(array_filter(
                        array_map('strval', $chosen),
                        static fn (string $k): bool => isset(\App\Services\HeroIconLibrary::ICONS[$k])
                    ));
                    $values[$key] = implode(',', $chosen);
                }
                continue;
            }

            // Photos are not previewed: they are uploads, and an upload
            // that has not been saved has nowhere to be served from.
            if ($type === \App\Services\SiteContentService::TYPE_IMAGE
                || $type === \App\Services\SiteContentService::TYPE_MEDIA) {
                continue;
            }

            $posted = $_POST['fields'][$base][$key] ?? null;
            if ($posted === null) {
                continue;
            }
            $values[$key] = mb_substr(trim((string) $posted), 0, 4000);
        }

        // The eyes are previewed too, so the owner can see the page
        // without a line before deciding to hide it for real.
        if (!empty($_POST['hide_present'])) {
            $hide = is_array($_POST['hide'] ?? null) ? $_POST['hide'] : [];
            foreach (\App\Services\SiteContentService::textKeys() as $textKey) {
                $values[\App\Services\SiteContentService::hideKey($textKey)]
                    = empty($hide[$textKey]) ? '0' : '1';
            }
        }

        session_set('site_preview', $values);
        $this->flash('info', t('Showing a preview. Nothing has been saved yet.'));
        $this->redirect('/');
    }

    /**
     * POST|GET /settings/site-content/preview/stop
     *
     * Drop the preview and go back to the editor. Reachable by GET as
     * well, because the banner on the previewed page is a plain link --
     * the only thing it changes is this person's own session.
     */
    public function stopPreview(): void
    {
        AuthMiddleware::requireAuth();
        session_set('site_preview', null);
        $this->redirect('/settings/site-content');
    }

    /**
     * POST /settings/site-content/reset-look
     *
     * Put every look-and-feel field back to the value the site shipped
     * with. Ten styles are one click each to try, and without this the
     * only way back to the original is remembering four hex codes --
     * which nobody does, so people stop trying the styles at all.
     *
     * Clears the stored values rather than writing the defaults in:
     * get() already falls back to the field's default, so a site that
     * has been reset behaves exactly like one that was never edited,
     * and a later change to a default is picked up.
     *
     * Only touches appearance, background and visibility. Wording and
     * photos are the office's own work and are never cleared here.
     */
    public function resetLook(): void
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
        $groups = ['appearance', 'background', 'visibility'];
        $done = 0;

        try {
            foreach (\App\Services\SiteContentService::FIELDS as $key => $field) {
                if (!in_array($field['group'] ?? '', $groups, true)) {
                    continue;
                }
                foreach (\App\Services\SiteContentService::LANGS as $lang) {
                    $service->set($key, '', $userId, $lang);
                }
                $done++;
            }

            $this->logAudit('reset_site_look', 'site_content', 0, ['fields' => $done]);
            $this->flash('success', t('The look has been put back to how the site started. Your wording and photos were not touched.'));
        } catch (\Throwable $e) {
            error_log('Reset look failed: ' . $e->getMessage());
            $this->flash('error', t('The look could not be reset. Please try again.'));
        }

        $this->redirect('/settings/site-content');
    }

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
            'hidden'       => $service->hiddenMap(),
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
     * WhatsApp Sending & WA Client Configuration for Super Admin.
     */
    public function whatsapp(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();

        $waService = new \App\Services\WhatsAppService();
        $config = $waService->config();

        $this->view('settings/whatsapp', [
            'pageTitle'     => t('WhatsApp & WA Client Configuration'),
            'activePage'    => 'settings',
            'activeSubPage' => 'whatsapp',
            'config'        => $config,
            'modes'         => \App\Services\WhatsAppService::MODES,
            'isConfigured'  => $waService->isGatewayConfigured(),
            'hasApiKey'     => !empty($config[\App\Services\WhatsAppService::KEY_GATEWAY_KEY]),
        ]);
    }

    public function updateWhatsapp(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $userId = (int) session_get('user_id');
        $wa = \App\Services\WhatsAppService::class;

        try {
            $mode = trim((string) ($_POST['wa_client_mode'] ?? 'wa_me'));
            if (!isset($wa::MODES[$mode])) {
                $mode = $wa::MODE_WA_ME;
            }
            $this->settingModel->set($wa::KEY_CLIENT_MODE, $mode, $userId);
            $this->settingModel->set($wa::KEY_GATEWAY_URL, trim((string) ($_POST['wa_gateway_url'] ?? '')), $userId);
            $this->settingModel->set($wa::KEY_GATEWAY_INSTANCE, trim((string) ($_POST['wa_gateway_instance'] ?? '')), $userId);
            $this->settingModel->set($wa::KEY_METHOD, in_array($_POST['wa_method'] ?? '', ['POST', 'GET'], true) ? $_POST['wa_method'] : 'POST', $userId);
            $this->settingModel->set($wa::KEY_PAYLOAD_FORMAT, in_array($_POST['wa_payload_format'] ?? '', ['json', 'form'], true) ? $_POST['wa_payload_format'] : 'json', $userId);
            $this->settingModel->set($wa::KEY_PHONE_PARAM, trim((string) ($_POST['wa_phone_param'] ?? 'number')) ?: 'number', $userId);
            $this->settingModel->set($wa::KEY_MESSAGE_PARAM, trim((string) ($_POST['wa_message_param'] ?? 'message')) ?: 'message', $userId);

            if (!empty($_POST['wa_gateway_key'])) {
                $this->settingModel->set($wa::KEY_GATEWAY_KEY, trim((string) $_POST['wa_gateway_key']), $userId);
            }

            $this->logAudit('update_whatsapp_settings', 'system_settings', 0);
            $this->flash('success', t('WhatsApp settings saved successfully.'));
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '/settings/whatsapp');
    }

    public function testWhatsapp(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        $phone = trim((string) ($_POST['test_phone'] ?? ''));
        $msg = trim((string) ($_POST['test_message'] ?? '')) ?: t('Hello from LMS! Test message from WhatsApp Client.');
        $wa = new \App\Services\WhatsAppService();

        if ($wa->isGatewayConfigured()) {
            $res = $wa->sendViaGateway($phone, $msg);
            $this->logAudit('test_whatsapp_gateway', 'system_settings', 0);
            if ($res['ok']) {
                $this->flash('success', t('Test message sent via WA Client! Check the recipient phone.'));
            } else {
                $this->flash('error', t('WA Client error: {err}', ['err' => $res['message'] ?? 'Failed to send.']));
            }
        } else {
            $link = $wa->getChatLink(\App\Services\WhatsAppService::normalisePhone($phone) ?: $phone, $msg);
            $this->flash('info', t('WA Gateway is not enabled. Link generated: {link}', ['link' => $link]));
        }

        $this->redirect(BASE_URL . '/settings/whatsapp');
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
                // Tick-boxes post nothing when all are cleared, so the
                // companion icons_present marker says "this field was on
                // the form" and an empty set is saved as empty rather
                // than silently keeping the old icons.
                if (($field['type'] ?? 'text') === \App\Services\SiteContentService::TYPE_ICONSET) {
                    if (!empty($_POST['icons_present'][$key])) {
                        $chosen = $_POST['icons'][$key] ?? [];
                        $chosen = is_array($chosen) ? $chosen : [];
                        $chosen = array_values(array_filter(
                            array_map('strval', $chosen),
                            static fn (string $k): bool => isset(\App\Services\HeroIconLibrary::ICONS[$k])
                        ));
                        $service->set($key, implode(',', $chosen), $userId, \App\Services\SiteContentService::BASE_LANG);
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
            // The per-sentence eyes. An unticked box posts nothing, so
            // hide_present marks that the switches were on the form at
            // all -- without it, turning every eye back on would look
            // like "no switches submitted" and keep them all hidden.
            if (!empty($_POST['hide_present'])) {
                $hide = is_array($_POST['hide'] ?? null) ? $_POST['hide'] : [];
                foreach (\App\Services\SiteContentService::textKeys() as $textKey) {
                    $service->setHidden($textKey, !empty($hide[$textKey]), $userId);
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
                // The in-system designer. Cleaned and validated in
                // SiteAdService::designFrom(); an empty template there
                // means this ad is an uploaded picture instead.
                'design_template' => $_POST['design_template'] ?? '',
                'headline'        => $_POST['headline'] ?? '',
                'subtext'         => $_POST['subtext'] ?? '',
                'button_text'     => $_POST['button_text'] ?? '',
                'bg_color'        => $_POST['bg_color'] ?? '',
                'text_color'      => $_POST['text_color'] ?? '',
                'accent_color'    => $_POST['accent_color'] ?? '',
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
                // The in-system designer. Cleaned and validated in
                // SiteAdService::designFrom(); an empty template there
                // means this ad is an uploaded picture instead.
                'design_template' => $_POST['design_template'] ?? '',
                'headline'        => $_POST['headline'] ?? '',
                'subtext'         => $_POST['subtext'] ?? '',
                'button_text'     => $_POST['button_text'] ?? '',
                'bg_color'        => $_POST['bg_color'] ?? '',
                'text_color'      => $_POST['text_color'] ?? '',
                'accent_color'    => $_POST['accent_color'] ?? '',
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

    /**
     * CMS-managed photos/videos for the home page hero background
     * slideshow (see HeroMediaService / migration 035). If no slide is
     * added here, the page falls back to the four original fixed photos.
     */
    public function heroMedia(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $service = new \App\Services\HeroMediaService();
        $this->view('settings/hero-media', [
            'pageTitle'     => 'Homepage Background',
            'activeSubPage' => 'hero-media',
            'slides'        => $service->all(),
        ]);
    }

    public function storeHeroMedia(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $service = new \App\Services\HeroMediaService();
        $userId = (int) session_get('user_id');

        try {
            if (empty($_FILES['media']['name']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Choose a photo or video to upload.');
            }
            $service->create($_FILES['media'], [
                'object_position' => $_POST['object_position'] ?? '',
                'is_active'       => $_POST['is_active'] ?? '',
                'sort_order'      => $_POST['sort_order'] ?? 0,
            ], $userId);

            $this->logAudit('create_hero_media', 'hero_media', 0);
            $this->flash('success', 'Slide added.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Hero media create error: ' . $e->getMessage());
            $this->flash('error', 'Failed to add the slide.');
        }

        $this->redirect(BASE_URL . '/settings/hero-media');
    }

    public function updateHeroMedia(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $service = new \App\Services\HeroMediaService();
        $file = (!empty($_FILES['media']['name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) ? $_FILES['media'] : null;

        try {
            $service->update((int) $id, [
                'object_position' => $_POST['object_position'] ?? '',
                'is_active'       => $_POST['is_active'] ?? '',
                'sort_order'      => $_POST['sort_order'] ?? 0,
            ], $file);

            $this->logAudit('update_hero_media', 'hero_media', (int) $id);
            $this->flash('success', 'Slide updated.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Hero media update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update the slide.');
        }

        $this->redirect(BASE_URL . '/settings/hero-media');
    }

    public function toggleHeroMedia(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        (new \App\Services\HeroMediaService())->toggle((int) $id);
        $this->logAudit('toggle_hero_media', 'hero_media', (int) $id);
        $this->redirect(BASE_URL . '/settings/hero-media');
    }

    public function deleteHeroMedia(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireSuperAdmin();
        verifyCsrfToken();

        (new \App\Services\HeroMediaService())->delete((int) $id);
        $this->logAudit('delete_hero_media', 'hero_media', (int) $id);
        $this->flash('success', 'Slide deleted.');
        $this->redirect(BASE_URL . '/settings/hero-media');
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
