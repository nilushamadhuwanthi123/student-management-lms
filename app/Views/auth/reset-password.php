<?php
/**
 * Reset password with a code from the branch office.
 * Layout: layouts/auth
 */
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
                   placeholder="<?= te('Student ID, staff ID or parent phone number') ?>" autocapitalize="characters">
        </div>
        <div class="form-group">
            <label class="form-label" for="reset_code"><?= te('Reset code') ?></label>
            <input type="text" id="reset_code" name="reset_code" class="form-control" required
                   placeholder="XXXX-XXXX" maxlength="12" autocapitalize="characters" spellcheck="false">
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password"><?= te('New password') ?></label>
            <input type="password" id="new_password" name="new_password" class="form-control" required autocomplete="new-password">
            <div class="form-text"><?= te('At least 8 characters, with upper and lower case letters, a number and a symbol.') ?></div>
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password_confirmation"><?= te('Confirm new password') ?></label>
            <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn--primary btn--block btn--lg"><?= te('Set new password') ?></button>
    </form>

    <p style="text-align:center; margin-top:1rem;">
        <a href="<?= BASE_URL ?>/login"><?= te('Back to login') ?></a>
    </p>
</div>
