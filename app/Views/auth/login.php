<?php
/**
 * Login Page
 * Layout: layouts/auth
 */
?>

<div class="login-card">
    <div class="login-card__logo">
        <svg width="48" height="48" viewBox="0 0 32 32" fill="none" aria-hidden="true" style="margin: 0 auto;">
            <rect width="32" height="32" rx="8" fill="var(--primary)"/>
            <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="var(--text-inverse)" stroke-width="1.5" fill="none"/>
            <path d="M16 12v10M8 12l8 4 8-4" stroke="var(--text-inverse)" stroke-width="1.5"/>
        </svg>
    </div>

    <h1 class="login-card__title">Welcome Back</h1>
    <p class="login-card__subtitle">Sign in to your EduManager account</p>

    <form action="/login" method="POST" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="login_user_id">User ID</label>
            <input type="text" id="login_user_id" name="user_id"
                   class="form-control <?= !empty($errors['user_id']) ? 'is-invalid' : '' ?>"
                   placeholder="e.g. ADM20260001"
                   value="<?= htmlspecialchars($old['user_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="username" required autofocus>
            <?php if (!empty($errors['user_id'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['user_id'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="login_password">Password</label>
            <div style="position: relative;">
                <input type="password" id="login_password" name="password"
                       class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                       placeholder="Enter your password"
                       autocomplete="current-password" required
                       style="padding-right: 2.75rem;">
                <button type="button" id="togglePassword"
                        style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem; font-size: 1.1rem;"
                        aria-label="Show password" tabindex="-1">
                    <span class="pw-icon-show">&#128065;</span>
                    <span class="pw-icon-hide" style="display:none;">&#128064;</span>
                </button>
            </div>
            <?php if (!empty($errors['password'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="form-check" style="margin-bottom: 1.5rem;">
            <input type="checkbox" id="remember_me" name="remember_me" value="1">
            <label for="remember_me">Remember me</label>
        </div>

        <button type="submit" class="btn btn--primary btn--block btn--lg">Sign In</button>
    </form>
</div>

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
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    }
})();
</script>
