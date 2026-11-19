<?php
/**
 * Login Page
 * Layout: layouts/auth
 * Note: The glass card wrapper, heading and logo are now in layouts/auth.php
 */
?>

<form action="<?= BASE_URL ?>/login" method="POST" novalidate>
    <?= csrfField() ?>

    <div class="form-group">
        <label class="form-label" for="login_user_id" style="font-size: 0.875rem; font-weight: 500;"><?= te('User ID') ?></label>
        <input type="text" id="login_user_id" name="user_id"
               class="form-control <?= !empty($errors['user_id']) ? 'is-invalid' : '' ?>"
               placeholder="<?= te('e.g. {id}', ['id' => 'STU20260001']) ?>"
               aria-describedby="login_user_hint"
               value="<?= htmlspecialchars($old['user_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               autocomplete="username" required autofocus>
        <div id="login_user_hint" class="text-xs" style="color: var(--text-muted); margin-top: .35rem;">
            <?= te('Students: your student ID. Parents: your phone number.') ?>
        </div>
        <?php if (!empty($errors['user_id'])): ?>
            <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['user_id'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label class="form-label" for="login_password" style="font-size: 0.875rem; font-weight: 500;"><?= te('Password') ?></label>
        <div style="position: relative;">
            <input type="password" id="login_password" name="password"
                   class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                   placeholder="<?= te('Enter your password') ?>"
                   autocomplete="current-password" required
                   style="padding-right: 3rem;">
            <button type="button" id="togglePassword"
                    style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem;"
                    aria-label="<?= te('Show password') ?>" tabindex="-1">
                <span class="pw-icon-show">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </span>
                <span class="pw-icon-hide" style="display:none;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                </span>
            </button>
        </div>
        <?php if (!empty($errors['password'])): ?>
            <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <div class="login-help-links">
        <a href="<?= BASE_URL ?>/reset-password"><?= te('I have a reset code') ?></a>
        <span aria-hidden="true">&middot;</span>
        <a href="<?= BASE_URL ?>/forgot-password"><?= te('Forgot password?') ?></a>
    </div>


    <button type="submit" class="btn btn--primary btn--block btn--lg"
            style="border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; transition: all 0.3s ease;">
        <?= te('Sign In') ?>
    </button>
</form>

<style>
    .login-help-links {
        display: flex; flex-wrap: wrap; justify-content: flex-end;
        align-items: center; gap: .5rem;
        margin: -0.25rem 0 0.9rem; font-size: 0.875rem;
    }
    .login-help-links span { color: var(--text-muted, #94A3B8); }
</style>

<script>
(function () {
    var toggle = document.getElementById('togglePassword');
    var input = document.getElementById('login_password');
    if (toggle && input) {
        toggle.addEventListener('click', function () {
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggle.querySelector('.pw-icon-show').style.display = isPassword ? 'none' : 'inline';
            toggle.querySelector('.pw-icon-hide').style.display = isPassword ? 'inline' : 'none';
            toggle.setAttribute('aria-label', isPassword ? <?= json_encode(t('Hide password')) ?> : <?= json_encode(t('Show password')) ?>);
        });
    }
})();
</script>
