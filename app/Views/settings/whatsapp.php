<?php
/**
 * Settings - WhatsApp & WA Client Configuration
 *
 * Configures how the LMS sends or opens WhatsApp messages (fee reminders,
 * notifications, parent alerts).
 *
 * Options:
 * 1. Standard Click-to-Chat (wa.me)
 * 2. WhatsApp Web Direct (web.whatsapp.com)
 * 3. WhatsApp Desktop App (whatsapp:// protocol)
 * 4. WA Client / HTTP API Gateway (Baileys, WPPConnect, UltraMsg, GreenAPI, Evolution API)
 *
 * Variables: $config, $modes, $isConfigured, $hasApiKey
 */

use App\Services\WhatsAppService;

$config       = $config ?? [];
$modes        = $modes ?? WhatsAppService::MODES;
$isConfigured = $isConfigured ?? false;
$hasApiKey    = $hasApiKey ?? false;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$activeMode    = (string) ($config[WhatsAppService::KEY_CLIENT_MODE] ?? WhatsAppService::MODE_WA_ME);
$gatewayUrl    = (string) ($config[WhatsAppService::KEY_GATEWAY_URL] ?? '');
$instanceId    = (string) ($config[WhatsAppService::KEY_GATEWAY_INSTANCE] ?? '');
$method        = strtoupper((string) ($config[WhatsAppService::KEY_METHOD] ?? 'POST'));
$payloadFormat = strtolower((string) ($config[WhatsAppService::KEY_PAYLOAD_FORMAT] ?? 'json'));
$phoneParam    = (string) ($config[WhatsAppService::KEY_PHONE_PARAM] ?? 'number');
$messageParam  = (string) ($config[WhatsAppService::KEY_MESSAGE_PARAM] ?? 'message');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('WhatsApp & WA Client Configuration') ?></h1>
        <p class="text-sm text-muted">
            <?= te('Configure how the LMS interacts with WhatsApp for fee reminders, alerts, and communications. You can use direct web/desktop links or connect a custom WA Client API gateway.') ?>
        </p>
    </div>
</div>

<!-- Status Banner -->
<div class="card" style="margin-bottom:1.25rem;">
    <?php if ($activeMode === WhatsAppService::MODE_GATEWAY): ?>
        <?php if ($isConfigured): ?>
            <p style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                <span style="color:#16A34A; font-size:1.1rem;">&#9679;</span>
                <strong style="color:#16A34A;"><?= te('WA Client API Gateway is active.') ?></strong>
                <span class="text-muted text-sm"><?= te('Messages are dispatched directly to your WhatsApp client.') ?></span>
            </p>
        <?php else: ?>
            <p style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                <span style="color:#DC2626; font-size:1.1rem;">&#9679;</span>
                <strong style="color:#DC2626;"><?= te('Gateway mode selected, but API endpoint URL is missing.') ?></strong>
            </p>
        <?php endif; ?>
    <?php elseif ($activeMode === WhatsAppService::MODE_WEB_DIRECT): ?>
        <p style="margin:0; display:flex; align-items:center; gap:0.5rem;">
            <span style="color:#0284C7; font-size:1.1rem;">&#9679;</span>
            <strong><?= te('WhatsApp Web Direct mode is active.') ?></strong>
            <span class="text-muted text-sm"><?= te('Clicking send opens WhatsApp Web directly in a browser tab.') ?></span>
        </p>
    <?php elseif ($activeMode === WhatsAppService::MODE_DESKTOP_APP): ?>
        <p style="margin:0; display:flex; align-items:center; gap:0.5rem;">
            <span style="color:#0284C7; font-size:1.1rem;">&#9679;</span>
            <strong><?= te('WhatsApp Desktop App mode is active.') ?></strong>
            <span class="text-muted text-sm"><?= te('Clicking send launches the installed WhatsApp application.') ?></span>
        </p>
    <?php else: ?>
        <p style="margin:0; display:flex; align-items:center; gap:0.5rem;">
            <span style="color:#16A34A; font-size:1.1rem;">&#9679;</span>
            <strong><?= te('Standard wa.me click-to-chat links are active.') ?></strong>
            <span class="text-muted text-sm"><?= te('Compatible with all phones, tablets, and desktop browsers.') ?></span>
        </p>
    <?php endif; ?>
</div>

<!-- Configuration Form -->
<form method="POST" action="<?= BASE_URL ?>/settings/whatsapp" class="card" style="margin-bottom:1.5rem;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <h2 style="font-size:1.1rem; margin:0 0 1rem; font-weight:600;"><?= te('WhatsApp Client Sending Mode') ?></h2>

    <div class="form-group" style="margin-bottom:1.5rem;">
        <label class="form-label" for="wa_client_mode"><?= te('Sending Mode') ?></label>
        <select id="wa_client_mode" name="wa_client_mode" class="form-control" style="max-width:540px;" onchange="toggleGatewaySection(this.value)">
            <?php foreach ($modes as $key => $label): ?>
                <option value="<?= $e($key) ?>" <?= $activeMode === $key ? 'selected' : '' ?>>
                    <?= $e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-hint">
            <?= te('Select how staff and automated notifications should interact with WhatsApp.') ?>
        </div>
    </div>

    <!-- Gateway Specific Configuration -->
    <div id="gateway_settings_section" style="<?= $activeMode === WhatsAppService::MODE_GATEWAY ? '' : 'display:none;' ?>; background:var(--bg-subtle, rgba(0,0,0,0.02)); border:1px solid var(--border); border-radius:8px; padding:1.25rem; margin-bottom:1.5rem;">
        <h3 style="font-size:1rem; margin:0 0 0.5rem; font-weight:600; color:var(--text);"><?= te('WA Client / Gateway API Settings') ?></h3>
        <p class="text-sm text-muted" style="margin:0 0 1.25rem;">
            <?= te('Compatible with self-hosted WhatsApp clients (Baileys, WPPConnect, Evolution API) or cloud services (UltraMsg, GreenAPI).') ?>
        </p>

        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="wa_gateway_url"><?= te('Gateway API Endpoint URL') ?> <span style="color:#DC2626;">*</span></label>
            <input type="url" id="wa_gateway_url" name="wa_gateway_url" class="form-control"
                   value="<?= $e($gatewayUrl) ?>"
                   placeholder="http://localhost:3000/message/sendText or https://api.ultramsg.com/instance/messages/chat">
            <div class="form-hint">
                <?= te('The full HTTP/HTTPS endpoint URL where requests will be sent.') ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group">
                <label class="form-label" for="wa_gateway_key"><?= te('API Key / Bearer Token') ?></label>
                <input type="password" id="wa_gateway_key" name="wa_gateway_key" class="form-control"
                       autocomplete="new-password"
                       placeholder="<?= $hasApiKey ? $e(t('Saved. Leave empty to keep existing key.')) : $e(t('API Token / Secret Key')) ?>">
                <div class="form-hint"><?= te('Passed as Authorization Bearer header or {key} placeholder.') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="wa_gateway_instance"><?= te('Instance / Session Name') ?></label>
                <input type="text" id="wa_gateway_instance" name="wa_gateway_instance" class="form-control"
                       value="<?= $e($instanceId) ?>"
                       placeholder="e.g. default, instance1234, session1">
                <div class="form-hint"><?= te('Required by some multi-session WA clients.') ?></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
            <div class="form-group">
                <label class="form-label" for="wa_method"><?= te('HTTP Method') ?></label>
                <select id="wa_method" name="wa_method" class="form-control">
                    <option value="POST" <?= $method === 'POST' ? 'selected' : '' ?>>POST (Recommended)</option>
                    <option value="GET" <?= $method === 'GET' ? 'selected' : '' ?>>GET</option>
                </select>
                <div class="form-hint"><?= te('Most REST WA clients use POST.') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="wa_payload_format"><?= te('Payload Format') ?></label>
                <select id="wa_payload_format" name="wa_payload_format" class="form-control">
                    <option value="json" <?= $payloadFormat === 'json' ? 'selected' : '' ?>>JSON (application/json)</option>
                    <option value="form" <?= $payloadFormat === 'form' ? 'selected' : '' ?>>Form URL-Encoded (x-www-form-urlencoded)</option>
                </select>
                <div class="form-hint"><?= te('Encoding for request body.') ?></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem;">
            <div class="form-group">
                <label class="form-label" for="wa_phone_param"><?= te('Phone Parameter Name') ?></label>
                <input type="text" id="wa_phone_param" name="wa_phone_param" class="form-control"
                       value="<?= $e($phoneParam) ?>" placeholder="number, phone, to, or chatId">
                <div class="form-hint"><?= te('Key name for recipient number in payload (default: number).') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="wa_message_param"><?= te('Message Parameter Name') ?></label>
                <input type="text" id="wa_message_param" name="wa_message_param" class="form-control"
                       value="<?= $e($messageParam) ?>" placeholder="message, text, or body">
                <div class="form-hint"><?= te('Key name for message body in payload (default: message).') ?></div>
            </div>
        </div>
    </div>

    <div>
        <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
    </div>
</form>

<!-- Test Sending Card -->
<form method="POST" action="<?= BASE_URL ?>/settings/whatsapp/test" class="card">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
    <h2 style="font-size:1.1rem; margin:0 0 0.5rem; font-weight:600;"><?= te('Test WhatsApp Client Sending') ?></h2>
    <p class="text-sm text-muted" style="margin-top:0; margin-bottom:1.25rem;">
        <?= te('Send a test message to verify that your WA Client or WhatsApp configuration works properly.') ?>
    </p>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
        <div class="form-group">
            <label class="form-label" for="test_phone"><?= te('Recipient Phone Number') ?> <span style="color:#DC2626;">*</span></label>
            <input type="text" id="test_phone" name="test_phone" class="form-control"
                   placeholder="07X XXX XXXX" inputmode="tel" required>
            <div class="form-hint"><?= te('Enter your test phone number.') ?></div>
        </div>

        <div class="form-group">
            <label class="form-label" for="test_message"><?= te('Message Content') ?></label>
            <input type="text" id="test_message" name="test_message" class="form-control"
                   value="Hello! This is a test message from Science Classes LMS WhatsApp Client." required>
            <div class="form-hint"><?= te('The test text to dispatch.') ?></div>
        </div>
    </div>

    <button type="submit" class="btn btn--secondary">
        <?= te('Send Test Message') ?>
    </button>
</form>

<script>
function toggleGatewaySection(mode) {
    var section = document.getElementById('gateway_settings_section');
    if (section) {
        section.style.display = (mode === 'wa_gateway') ? 'block' : 'none';
    }
}
</script>

