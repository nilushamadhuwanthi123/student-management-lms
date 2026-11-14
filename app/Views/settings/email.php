<?php
/**
 * Settings - Email
 *
 * Lets login details (student/parent username + temporary password, or
 * a password-reset code) be emailed from the "Login details" screen, as
 * an alternative to reading them out by phone or in person.
 *
 * Two ways to send, and either works without touching code:
 *  - SMTP (recommended): type in a mailbox's SMTP host, port, username
 *    and password below (Gmail, the domain's own cPanel mailbox, Zoho,
 *    etc.) and mail goes out through that account directly -- works the
 *    same on localhost and on the live host.
 *  - PHP's built-in mail() (leave the SMTP host blank): needs a mail
 *    transport already set up on the server itself (sendmail on Linux
 *    hosting). A local XAMPP install usually has none, so a test send
 *    there will fail until SMTP details are filled in above.
 *
 * Variables: $config, $isConfigured
 */

use App\Services\EmailService;

$config       = $config ?? [];
$isConfigured = $isConfigured ?? false;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$enabled = ($config[EmailService::KEY_ENABLED] ?? '0') === '1';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Email') ?></h1>
        <p class="text-sm text-muted">
            <?= te('Lets the office email a student or parent their login ID and temporary password, or a password-reset code, straight from the "Login details" screen.') ?>
        </p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem;">
    <?php if ($isConfigured): ?>
        <p style="margin:0;"><strong style="color:#16A34A;">&#9679; <?= te('Email sending is switched on.') ?></strong></p>
    <?php else: ?>
        <p style="margin:0;">
            <strong style="color:#B45309;">&#9679; <?= te('Not set up yet.') ?></strong>
            <?= te('The "Send by email" button will not work until this is switched on and a "From" address is set.') ?>
        </p>
    <?php endif; ?>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/email" class="card">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label style="display:flex; align-items:center; gap:.5rem;">
            <input type="checkbox" name="email_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <strong><?= te('Allow sending login details by email') ?></strong>
        </label>
        <div class="form-hint"><?= te('Leave this off to keep using phone/in-person and SMS only.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="email_from_address"><?= te('"From" email address') ?></label>
        <input type="email" id="email_from_address" name="email_from_address" class="form-control" style="max-width:420px;"
               value="<?= $e($config[EmailService::KEY_FROM_ADDRESS] ?? '') ?>" placeholder="office@yourschool.lk">
        <div class="form-hint"><?= te('If left blank, the general "Admin email" from Settings is used instead.') ?></div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="email_from_name"><?= te('"From" name') ?></label>
        <input type="text" id="email_from_name" name="email_from_name" class="form-control" style="max-width:420px;"
               value="<?= $e($config[EmailService::KEY_FROM_NAME] ?? '') ?>" placeholder="<?= $e(t('Class Portal')) ?>">
    </div>

    <hr style="margin:1.5rem 0; border:none; border-top:1px solid var(--border);">

    <h2 style="font-size:1.05rem; margin:0 0 .35rem;"><?= te('SMTP (recommended)') ?></h2>
    <p class="text-sm text-muted" style="margin-top:0;">
        <?= te('Fill this in with a real mailbox\'s SMTP details and mail is sent through that account directly -- this is what makes email actually deliver, on this server or the live one. Leave the host blank to fall back to the server\'s own mail() instead.') ?>
    </p>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="email_smtp_host"><?= te('SMTP host') ?></label>
        <input type="text" id="email_smtp_host" name="email_smtp_host" class="form-control" style="max-width:420px;"
               value="<?= $e($config[EmailService::KEY_SMTP_HOST] ?? '') ?>" placeholder="smtp.gmail.com">
    </div>

    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="email_smtp_port"><?= te('Port') ?></label>
            <input type="number" min="1" max="65535" id="email_smtp_port" name="email_smtp_port" class="form-control" style="max-width:120px;"
                   value="<?= $e($config[EmailService::KEY_SMTP_PORT] ?? '') ?>" placeholder="587">
        </div>

        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="email_smtp_encryption"><?= te('Encryption') ?></label>
            <?php $enc = $config[EmailService::KEY_SMTP_ENCRYPTION] ?? ''; ?>
            <select id="email_smtp_encryption" name="email_smtp_encryption" class="form-control" style="max-width:160px;">
                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>><?= te('STARTTLS (587)') ?></option>
                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>><?= te('SSL (465)') ?></option>
                <option value="" <?= $enc === '' ? 'selected' : '' ?>><?= te('None') ?></option>
            </select>
        </div>
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="email_smtp_username"><?= te('SMTP username') ?></label>
        <input type="text" id="email_smtp_username" name="email_smtp_username" class="form-control" style="max-width:420px;"
               autocomplete="off"
               value="<?= $e($config[EmailService::KEY_SMTP_USERNAME] ?? '') ?>" placeholder="office@yourschool.lk">
    </div>

    <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="email_smtp_password"><?= te('SMTP password') ?></label>
        <input type="password" id="email_smtp_password" name="email_smtp_password" class="form-control" style="max-width:420px;"
               autocomplete="new-password" placeholder="<?= $e(($config[EmailService::KEY_SMTP_PASSWORD] ?? '') !== '' ? t('Leave blank to keep the saved password') : '') ?>">
        <div class="form-hint"><?= te('For Gmail, this needs to be a 16-character "App Password", not the normal account password (Google Account -> Security -> App passwords).') ?></div>
    </div>

    <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
</form>

<form method="POST" action="<?= BASE_URL ?>/settings/email/test" class="card" style="margin-top:1rem;">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
    <h2 style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('Send a test email') ?></h2>
    <p class="text-sm text-muted" style="margin-top:0;">
        <?= te('Send one email to prove this server can actually deliver, before a parent has to rely on it. A local development server usually has no mail transport configured, so this is expected to fail here.') ?>
    </p>
    <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" for="test_email"><?= te('Email address') ?></label>
        <input type="email" id="test_email" name="test_email" class="form-control" style="max-width:300px;">
    </div>
    <button type="submit" class="btn btn--secondary" <?= $isConfigured ? '' : 'disabled' ?>>
        <?= te('Send test email') ?>
    </button>
    <?php if (!$isConfigured): ?>
        <div class="form-hint"><?= te('Save a "From" address and switch email on first.') ?></div>
    <?php endif; ?>
</form>
