<?php
/**
 * Reset password with a code from the branch office.
 *
 * Two things here exist because of real confusion in the field:
 *  - The login ID and code are put back after a failed attempt. Clearing
 *    them made a mistyped password look like the code had been used up.
 *  - Both password boxes can be revealed. "The two passwords do not
 *    match" with no way to see either one is a dead end, especially when
 *    a browser autofills one of them.
 *
 * Variables: $oldLoginId, $oldResetCode (either may be '')
 * Layout: layouts/auth
 */
$oldLoginId = $oldLoginId ?? '';
$oldResetCode = $oldResetCode ?? '';
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="login-card">
    <h1 class="login-card__title"><?= te('Reset your password') ?></h1>
    <p class="login-card__subtitle">
        <?= te('Ask your branch office for a reset code. They will first confirm who you are, in person or by calling the phone number they have on file. The code works once, for 30 minutes.') ?>
    </p>

    <form action="<?= BASE_URL ?>/reset-password" method="POST" autocomplete="off" novalidate>
        <?= csrfField() ?>
        <div class="form-group">
            <label class="form-label" for="login_id"><?= te('Your login ID') ?></label>
            <input type="text" id="login_id" name="login_id" class="form-control" required
                   value="<?= $esc($oldLoginId) ?>"
                   placeholder="<?= te('Student ID, staff ID or parent phone number') ?>" autocapitalize="characters">
        </div>
        <div class="form-group">
            <label class="form-label" for="reset_code"><?= te('Reset code') ?></label>
            <input type="text" id="reset_code" name="reset_code" class="form-control" required
                   value="<?= $esc($oldResetCode) ?>"
                   placeholder="XXXX-XXXX" maxlength="12" autocapitalize="characters" spellcheck="false">
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password"><?= te('New password') ?></label>
            <div class="pw-wrap">
                <input type="password" id="new_password" name="new_password" class="form-control" required autocomplete="new-password">
                <button type="button" class="pw-eye" data-toggle="new_password" aria-label="<?= te('Show password') ?>">👁</button>
            </div>
            <div class="form-text"><?= te('At least 8 characters, with upper and lower case letters, a number and a symbol.') ?></div>
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password_confirmation"><?= te('Confirm new password') ?></label>
            <div class="pw-wrap">
                <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control" required autocomplete="new-password">
                <button type="button" class="pw-eye" data-toggle="new_password_confirmation" aria-label="<?= te('Show password') ?>">👁</button>
            </div>
            <div class="form-text pw-match" id="pw_match" aria-live="polite"></div>
        </div>
        <button type="submit" class="btn btn--primary btn--block btn--lg"><?= te('Set new password') ?></button>
    </form>

    <p style="text-align:center; margin-top:1rem;">
        <a href="<?= BASE_URL ?>/login"><?= te('Back to login') ?></a>
    </p>
</div>

<style>
    .pw-wrap { position: relative; }
    .pw-wrap .form-control { padding-right: 2.75rem; }
    .pw-eye {
        position: absolute; right: .6rem; top: 50%; transform: translateY(-50%);
        background: none; border: 0; cursor: pointer; font-size: 1rem; line-height: 1;
        padding: .25rem; opacity: .6;
    }
    .pw-eye:hover { opacity: 1; }
    .pw-match.is-ok { color: #16A34A; }
    .pw-match.is-bad { color: #DC2626; }
</style>

<script>
(function () {
    document.querySelectorAll('.pw-eye').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle'));
            if (!input) { return; }
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.style.opacity = input.type === 'text' ? '1' : '.6';
        });
    });

    var pw = document.getElementById('new_password');
    var confirm = document.getElementById('new_password_confirmation');
    var out = document.getElementById('pw_match');
    var MATCH = <?= json_encode(t('Both passwords match.')) ?>;
    var DIFFER = <?= json_encode(t('The two passwords are still different.')) ?>;

    function check() {
        if (!out) { return; }
        if (confirm.value === '') { out.textContent = ''; out.className = 'form-text pw-match'; return; }
        var same = pw.value === confirm.value;
        out.textContent = same ? MATCH : DIFFER;
        out.className = 'form-text pw-match ' + (same ? 'is-ok' : 'is-bad');
    }

    if (pw && confirm) {
        pw.addEventListener('input', check);
        confirm.addEventListener('input', check);
    }
})();
</script>
