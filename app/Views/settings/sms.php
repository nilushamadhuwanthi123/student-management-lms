<?php
/**
 * Settings - Text messages (SMS)
 *
 * The gateway that carries password-reset codes to the phone number on
 * an account. With nothing configured the portal behaves as it always
 * did: a reset code is queued for the office to send by WhatsApp. Once
 * a gateway is on, the code texts itself and the queue stays only as
 * the fallback for when the gateway is unreachable.
 *
 * Variables: $providers, $config, $isConfigured, $hasApiKey
 */

use App\Services\SmsService;

$providers    = $providers ?? [];
$config       = $config ?? [];
$isConfigured = $isConfigured ?? false;
$hasApiKey    = $hasApiKey ?? false;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$provider = (string) ($config[SmsService::KEY_PROVIDER] ?? '');
$enabled  = ($config[SmsService::KEY_ENABLED] ?? '0') === '1';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Text messages (SMS)') ?></h1>
        <p class="text-sm text-muted">
            <?= te('When a student or parent asks to reset a forgotten password, the one-time code goes to the phone number already on their account. Set up a gateway here and the code is texted automatically.') ?>
        </p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem;">
    <?php if ($isConfigured): ?>
        <p style="margin:0;"><strong style="color:#16A34A;">&#9679; <?= te('Reset codes are being texted automatically.') ?></strong></p>
    <?php else: ?>
        <p style="margin:0;">
            <strong style="color:#B45309;">&#9679; <?= te('No gateway yet.') ?></strong>
            <?= te('Reset codes are queued for the office to send by WhatsApp, which someone has to tap for each student.') ?>
        </p>
    <?php endif; ?>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/sms" class="card">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label style="display:flex; align-items:center; gap:.5rem;">
            <input type="checkbox" name="sms_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <strong><?= te('Send reset codes by text message') ?></strong>
        </label>
        <div class="form-hint"><?= te('Leave this off to keep using the office WhatsApp queue.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="sms_provider"><?= te('Gateway') ?></label>
        <select id="sms_provider" name="sms_provider" class="form-control" style="max-width:420px;">
            <option value=""><?= te('Choose a gateway') ?></option>
            <?php foreach ($providers as $key => $label): ?>
                <option value="<?= $e($key) ?>" <?= $provider === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-hint"><?= te('Notify.lk and Text.lk are Sri Lankan gateways. Choose "Other" to paste the address of any gateway that accepts a web request.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="sms_api_key"><?= te('API key or token') ?></label>
        <input type="password" id="sms_api_key" name="sms_api_key" class="form-control" style="max-width:420px;"
               autocomplete="new-password"
               placeholder="<?= $hasApiKey ? $e(t('Saved. Type a new one only to replace it.')) : $e(t('Paste the key from the gateway')) ?>">
        <div class="form-hint"><?= te('Stored for the system and never shown again. Leaving this blank keeps the key already saved.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="sms_user_id"><?= te('User ID') ?></label>
        <input type="text" id="sms_user_id" name="sms_user_id" class="form-control" style="max-width:420px;"
               value="<?= $e($config[SmsService::KEY_USER_ID] ?? '') ?>">
        <div class="form-hint"><?= te('Notify.lk only. Leave blank for the others.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="sms_sender_id"><?= te('Sender name') ?></label>
        <input type="text" id="sms_sender_id" name="sms_sender_id" class="form-control" style="max-width:420px;"
               value="<?= $e($config[SmsService::KEY_SENDER_ID] ?? '') ?>">
        <div class="form-hint"><?= te('The name the message appears to come from. It has to be one the gateway has approved for this account.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="sms_custom_url"><?= te('Gateway address') ?></label>
        <input type="text" id="sms_custom_url" name="sms_custom_url" class="form-control"
               value="<?= $e($config[SmsService::KEY_CUSTOM_URL] ?? '') ?>"
               placeholder="https://example.lk/send?key={api_key}&amp;to={to}&amp;text={message}">
        <div class="form-hint">
            <?= te('For "Other" only. Use {to} where the number goes and {message} where the text goes; {api_key} and {sender_id} work too. It must start with https.') ?>
        </div>
    </div>

    <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
</form>

<form method="POST" action="<?= BASE_URL ?>/settings/sms/test" class="card" style="margin-top:1rem;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
    <h2 style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('Send a test message') ?></h2>
    <p class="text-sm text-muted" style="margin-top:0;">
        <?= te('Send one real message to your own phone to prove the gateway works, before a student has to rely on it. This uses one message from your gateway balance.') ?>
    </p>
    <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" for="test_phone"><?= te('Phone number') ?></label>
        <input type="text" id="test_phone" name="test_phone" class="form-control" style="max-width:300px;"
               placeholder="07X XXX XXXX" inputmode="tel">
    </div>
    <button type="submit" class="btn btn--secondary" <?= $isConfigured ? '' : 'disabled' ?>>
        <?= te('Send test message') ?>
    </button>
    <?php if (!$isConfigured): ?>
        <div class="form-hint"><?= te('Save the gateway details and switch it on first.') ?></div>
    <?php endif; ?>
</form>
