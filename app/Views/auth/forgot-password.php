<?php
/**
 * Ask for a reset code without going through the office.
 *
 * The code is sent to the mobile number already on the account, so this
 * page only helps somebody who holds that phone. It never says whether
 * the ID typed in exists.
 *
 * Layout: layouts/auth
 */
?>
<div class="login-card">
    <h1 class="login-card__title"><?= te('Forgotten password') ?></h1>
    <p class="login-card__subtitle">
        <?= te('Type your login ID and we will send a reset code to the mobile number or email address registered on your account. The code works once, for 30 minutes.') ?>
    </p>

    <form action="<?= BASE_URL ?>/forgot-password" method="POST" autocomplete="off" novalidate>
        <?= csrfField() ?>
        <div class="form-group">
            <label class="form-label" for="login_id"><?= te('Your login ID') ?></label>
            <input type="text" id="login_id" name="login_id" class="form-control" required
                   placeholder="<?= te('Student ID, staff ID or parent phone number') ?>" autocapitalize="characters">
        </div>
        <button type="submit" class="btn btn--primary btn--block btn--lg"><?= te('Send me a reset code') ?></button>
    </form>

    <p class="form-text" style="margin-top:1rem;">
        <?= te('No mobile number or email address on your account, or they have changed? Call the branch office and they will give you a code after confirming who you are.') ?>
    </p>

    <div class="forgot-divider"><span><?= te('or') ?></span></div>

    <a href="<?= BASE_URL ?>/reset-password" class="btn btn--secondary btn--block btn--lg">
        <?= te('I already have a code') ?>
    </a>
    <p class="form-text" style="text-align:center; margin-top:.5rem;">
        <?= te('Use this if the branch office already gave you a code.') ?>
    </p>

    <p style="text-align:center; margin-top:1rem;">
        <a href="<?= BASE_URL ?>/login"><?= te('Back to login') ?></a>
    </p>

<style>
    .forgot-divider { display:flex; align-items:center; gap:.75rem; margin:1.25rem 0 1rem; color:#94A3B8; font-size:.8rem; }
    .forgot-divider::before, .forgot-divider::after { content:""; flex:1; height:1px; background:#E2E8F0; }
</style>
</div>
