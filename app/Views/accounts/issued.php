<?php
/**
 * One-time display of a temporary password or reset code.
 * Variables: $items (list of kind/name/login_id/secret/expires_at/phone/email), $backUrl
 * Nothing here is stored: refreshing the page shows it empty.
 *
 * "Send by WhatsApp" is a click-to-chat link (wa.me), not an API send --
 * same pattern as the rest of the site's WhatsApp messaging (see
 * WhatsAppService). It needs no gateway account, no API key and no fee,
 * unlike SMS, which only ever works through a paid SMS gateway -- there is
 * no free/built-in way to deliver a real SMS, that is true of every such
 * system, not a gap in this one. WhatsApp is the zero-setup option: the
 * staff member just taps it and presses send on their own phone.
 */
$items = $items ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$waBody = static function (array $it): string {
    return \App\Controllers\AccountController::credentialMessage(
        (string) ($it['kind'] ?? 'password'),
        (string) ($it['name'] ?? ''),
        (string) ($it['login_id'] ?? ''),
        (string) ($it['secret'] ?? '')
    );
};
?>
<div class="page-header">
    <h1 class="page-title"><?= te('Login details') ?></h1>
</div>

<?php if ($items === []): ?>
    <div class="card"><div class="card__body">
        <p><?= te('Nothing to show. Login details are shown only once; issue a new reset code if they were lost.') ?></p>
        <a class="btn btn--secondary" href="<?= BASE_URL . $e($backUrl) ?>"><?= te('Back') ?></a>
    </div></div>
<?php else: ?>
    <div class="alert alert--warning" role="note">
        <?= te('Shown only once. Give these details to the person directly -- in person, by calling the phone number already on file, or using the Send buttons below. Do not forward them to anyone else, and do not keep a copy.') ?>
    </div>

    <?php foreach ($items as $i => $it): ?>
        <?php
            $kind = (string) ($it['kind'] ?? 'password');
            $phone = trim((string) ($it['phone'] ?? ''));
            $email = trim((string) ($it['email'] ?? ''));
            $initial = mb_strtoupper(mb_substr((string) $it['name'], 0, 1));
        ?>
        <div class="credential-card" data-credential>
            <div class="credential-card__head">
                <div class="credential-card__avatar"><?= $e($initial) ?></div>
                <div>
                    <div class="credential-card__name"><?= $e($it['name']) ?></div>
                    <div class="credential-card__kind">
                        <?= $kind === 'reset_code' ? te('Password reset code') : te('Temporary password') ?>
                    </div>
                </div>
            </div>

            <div class="credential-card__body">
                <div class="credential-row">
                    <span class="credential-row__label"><?= te('Login ID') ?></span>
                    <code class="credential-row__value"><?= $e($it['login_id']) ?></code>
                </div>
                <?php if ($kind === 'reset_code'): ?>
                    <div class="credential-row">
                        <span class="credential-row__label"><?= te('Reset code') ?></span>
                        <code class="credential-row__value credential-row__value--big"><?= $e($it['secret']) ?></code>
                    </div>
                    <div class="credential-row">
                        <span class="credential-row__label"><?= te('Valid until') ?></span>
                        <span class="credential-row__value"><?= $e(lang_date('j M Y, g:i a', (string) $it['expires_at'])) ?></span>
                    </div>
                    <p class="credential-card__hint">
                        <?= te('They open the page below, then enter their login ID, this code and a new password. (The "Forgot password?" link is NOT the right page -- that one only sends a brand new code by SMS.)') ?>
                        <br>
                        <?php $resetLink = \App\Controllers\AccountController::resetUrl() . '?id=' . rawurlencode((string) $it['login_id']); ?>
                        <a class="credential-reset-link" href="<?= $e($resetLink) ?>" target="_blank" rel="noopener noreferrer"><?= $e(\App\Controllers\AccountController::resetUrl()) ?></a>
                    </p>
                <?php else: ?>
                    <div class="credential-row">
                        <span class="credential-row__label"><?= te('Temporary password') ?></span>
                        <code class="credential-row__value credential-row__value--big"><?= $e($it['secret']) ?></code>
                    </div>
                    <p class="credential-card__hint"><?= te('They must choose their own password the first time they log in.') ?></p>
                <?php endif; ?>
            </div>

            <div class="credential-card__actions">
                <form class="credential-send-form" data-send-form data-via="sms" onsubmit="return false;">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <input type="hidden" name="via" value="sms">
                    <input type="hidden" name="kind" value="<?= $e($kind) ?>">
                    <input type="hidden" name="name" value="<?= $e($it['name']) ?>">
                    <input type="hidden" name="login_id" value="<?= $e($it['login_id']) ?>">
                    <input type="hidden" name="secret" value="<?= $e($it['secret']) ?>">
                    <input type="hidden" name="expires_at" value="<?= $e($it['expires_at'] ?? '') ?>">
                    <input type="hidden" name="phone" value="<?= $e($phone) ?>">
                    <button type="submit" class="btn btn--secondary btn--sm" <?= $phone === '' ? 'disabled' : '' ?>
                        title="<?= $phone === '' ? $e(t('No phone number on file')) : $e($phone) ?>">
                        <?= te('Send by SMS') ?>
                    </button>
                </form>
                <?php $autoSentEmail = !empty($it['auto_sent_email']); ?>
                <form class="credential-send-form" data-send-form data-via="email" onsubmit="return false;">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <input type="hidden" name="via" value="email">
                    <input type="hidden" name="kind" value="<?= $e($kind) ?>">
                    <input type="hidden" name="name" value="<?= $e($it['name']) ?>">
                    <input type="hidden" name="login_id" value="<?= $e($it['login_id']) ?>">
                    <input type="hidden" name="secret" value="<?= $e($it['secret']) ?>">
                    <input type="hidden" name="expires_at" value="<?= $e($it['expires_at'] ?? '') ?>">
                    <input type="hidden" name="email" value="<?= $e($email) ?>">
                    <button type="submit" class="btn btn--secondary btn--sm" <?= $email === '' ? 'disabled' : '' ?>
                        title="<?= $email === '' ? $e(t('No email address on file')) : $e($email) ?>">
                        <?= $autoSentEmail ? te('Resend by email') : te('Send by email') ?>
                    </button>
                </form>
                <?php if ($autoSentEmail): ?>
                    <span class="credential-send-status credential-send-status--ok">&#10003; <?= te('Already emailed automatically') ?></span>
                <?php endif; ?>
                <?php
                    $waPhone = \App\Services\WhatsAppService::normalisePhone($phone);
                ?>
                <a class="btn btn--secondary btn--sm" target="_blank" rel="noopener noreferrer"
                   href="<?= $waPhone !== null ? $e(\App\Services\WhatsAppService::chatLink($waPhone, $waBody($it))) : '#' ?>"
                   <?= $waPhone === null ? 'aria-disabled="true" style="opacity:.5; pointer-events:none;"' : '' ?>
                   title="<?= $waPhone === null ? $e(t('No phone number on file')) : $e($phone) ?>">
                    <?= te('Send by WhatsApp') ?>
                </a>
                <span class="credential-send-status" data-send-status aria-live="polite"></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="credential-page-actions">
        <button type="button" class="btn btn--secondary" onclick="window.print()"><?= te('Print') ?></button>
        <a class="btn btn--primary" href="<?= BASE_URL . $e($backUrl) ?>"><?= te('Done') ?></a>
    </div>
<?php endif; ?>

<style>
    .credential-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #E2E8F0);
        border-radius: 12px;
        margin-bottom: 1rem;
        padding: 1.25rem;
        max-width: 560px;
    }
    .credential-card__head { display: flex; align-items: center; gap: .75rem; margin-bottom: .9rem; }
    .credential-card__avatar {
        width: 40px; height: 40px; border-radius: 50%;
        background: #4F46E5; color: #fff; font-weight: 700;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .credential-card__name { font-weight: 600; font-size: 1.02rem; }
    .credential-card__kind { font-size: .82rem; color: #64748B; }
    .credential-card__body { background: #F8FAFC; border-radius: 8px; padding: .75rem .9rem; margin-bottom: .9rem; }
    .credential-row { display: flex; justify-content: space-between; align-items: center; padding: .3rem 0; gap: 1rem; }
    .credential-row__label { color: #64748B; font-size: .85rem; }
    .credential-row__value { font-weight: 600; }
    .credential-row__value--big { font-size: 1.2rem; letter-spacing: .06em; color: #4F46E5; }
    .credential-card__hint { margin: .5rem 0 0; font-size: .82rem; color: #64748B; }
    .credential-reset-link { display: inline-block; margin-top: .35rem; font-weight: 600; color: #4F46E5; word-break: break-all; }
    .credential-card__actions { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .credential-send-form { display: inline-block; }
    .btn--sm { padding: .35rem .75rem; font-size: .85rem; }
    .credential-send-status { font-size: .85rem; }
    .credential-send-status--ok { color: #16A34A; }
    .credential-send-status--err { color: #DC2626; }
    .credential-page-actions { margin-top: 1rem; display: flex; gap: .5rem; }
    @media print {
        .credential-card__actions, .credential-page-actions { display: none; }
    }
</style>

<script>
(function () {
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

    document.querySelectorAll('[data-send-form]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var card = form.closest('[data-credential]');
            var status = card ? card.querySelector('[data-send-status]') : null;
            var button = form.querySelector('button');
            var fd = new FormData(form);

            button.disabled = true;
            if (status) {
                status.textContent = <?= json_encode(t('Sending...')) ?>;
                status.className = 'credential-send-status';
            }

            fetch('<?= BASE_URL ?>/accounts/issued/send', {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (status) {
                        status.textContent = data.message || '';
                        status.className = 'credential-send-status ' + (data.ok ? 'credential-send-status--ok' : 'credential-send-status--err');
                    }
                })
                .catch(function () {
                    if (status) {
                        status.textContent = <?= json_encode(t('Could not reach the server. Try again.')) ?>;
                        status.className = 'credential-send-status credential-send-status--err';
                    }
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });
})();
</script>
