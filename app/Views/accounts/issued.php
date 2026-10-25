<?php
/**
 * One-time display of a temporary password or reset code.
 * Variables: $items (list of kind/name/login_id/secret/expires_at), $backUrl
 * Nothing here is stored: refreshing the page shows it empty.
 */
$items = $items ?? [];
?>
<div class="page-header">
    <h1 class="page-title"><?= te('Login details') ?></h1>
</div>

<?php if ($items === []): ?>
    <div class="card"><div class="card__body">
        <p><?= te('Nothing to show. Login details are shown only once; issue a new reset code if they were lost.') ?></p>
        <a class="btn btn--secondary" href="<?= BASE_URL . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"><?= te('Back') ?></a>
    </div></div>
<?php else: ?>
    <div class="alert alert--warning" role="note">
        <?= te('Shown only once. Give these details to the person directly (in person, or by calling the phone number already on file). Do not send them to anyone else, and do not keep a copy.') ?>
    </div>
    <?php foreach ($items as $it): ?>
        <div class="card" style="margin-bottom:1rem;"><div class="card__body">
            <div style="font-weight:600; margin-bottom:.5rem;"><?= htmlspecialchars((string) $it['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <table class="table" style="max-width:520px;">
                <tr><th><?= te('Login ID') ?></th><td><code><?= htmlspecialchars((string) $it['login_id'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                <?php if (($it['kind'] ?? '') === 'reset_code'): ?>
                    <tr><th><?= te('Reset code') ?></th><td><code style="font-size:1.25rem; letter-spacing:.1em;"><?= htmlspecialchars((string) $it['secret'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                    <tr><th><?= te('Valid until') ?></th><td><?= htmlspecialchars(lang_date('j M Y, g:i a', (string) $it['expires_at']), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td colspan="2"><?= te('They open the login page, choose "Forgot password?", and enter their login ID, this code and a new password.') ?></td></tr>
                <?php else: ?>
                    <tr><th><?= te('Temporary password') ?></th><td><code style="font-size:1.25rem; letter-spacing:.05em;"><?= htmlspecialchars((string) $it['secret'], ENT_QUOTES, 'UTF-8') ?></code></td></tr>
                    <tr><td colspan="2"><?= te('They must choose their own password the first time they log in.') ?></td></tr>
                <?php endif; ?>
            </table>
        </div></div>
    <?php endforeach; ?>
    <button type="button" class="btn btn--secondary" onclick="window.print()"><?= te('Print') ?></button>
    <a class="btn btn--primary" href="<?= BASE_URL . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"><?= te('Done') ?></a>
<?php endif; ?>
