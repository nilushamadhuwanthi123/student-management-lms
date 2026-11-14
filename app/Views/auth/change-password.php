<?php
/**
 * Change Password Page
 * Layout: layouts/admin
 */
?>

<div class="content-header">
    <h1><?= te('Change Password') ?></h1>
    <p class="text-muted"><?= te('Update your account password') ?></p>
</div>

<div class="card" style="max-width: 500px;">
    <div class="card-body">
        <form action="<?= BASE_URL ?>/change-password" method="POST" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="current_password"><?= te('Current Password') ?></label>
                <input type="password" id="current_password" name="current_password"
                       class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password"><?= te('New Password') ?></label>
                <input type="password" id="new_password" name="new_password"
                       class="form-control" required autocomplete="new-password"
                       minlength="8">
                <small class="form-text text-muted">
                    <?= te('Minimum 8 characters with at least 1 uppercase, 1 lowercase, 1 number, and 1 special character.') ?>
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password_confirmation"><?= te('Confirm New Password') ?></label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                       class="form-control" required autocomplete="new-password">
            </div>

            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn--primary"><?= te('Update Password') ?></button>
                <a href="<?= BASE_URL ?>/dashboard" class="btn btn--secondary"><?= te('Cancel') ?></a>
            </div>
        </form>
    </div>
</div>
