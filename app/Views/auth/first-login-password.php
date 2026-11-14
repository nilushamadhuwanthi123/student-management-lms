<?php
/**
 * First Login Password Change
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

    <h1 class="login-card__title"><?= te('Set Your New Password') ?></h1>
    <p class="login-card__subtitle">
        <?= te('For security, you must change your password on first login. Choose a strong password that you have not used before.') ?>
    </p>

    <form action="<?= BASE_URL ?>/first-login-password" method="POST" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="new_password">
                <?= te('New Password') ?> <span class="required">*</span>
            </label>
            <div style="position: relative;">
                <input type="password" id="new_password" name="new_password"
                       class="form-control <?= !empty($errors['new_password']) ? 'is-invalid' : '' ?>"
                       placeholder="<?= te('Minimum 8 characters') ?>"
                       autocomplete="new-password" required
                       style="padding-right: 2.75rem;">
                <button type="button" class="toggle-pw-btn"
                        style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem; font-size: 1.1rem;"
                        aria-label="<?= te('Show password') ?>" tabindex="-1">
                    <span class="pw-icon-show">&#128065;</span>
                    <span class="pw-icon-hide" style="display:none;">&#128064;</span>
                </button>
            </div>
            <?php if (!empty($errors['new_password'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['new_password'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <!-- Password Strength Indicator -->
            <div class="password-strength" style="margin-top: 0.5rem;">
                <div style="display: flex; gap: 4px; margin-bottom: 0.25rem;">
                    <div class="strength-bar" style="flex: 1; height: 4px; border-radius: 2px; background: var(--border); transition: background 0.3s;"></div>
                    <div class="strength-bar" style="flex: 1; height: 4px; border-radius: 2px; background: var(--border); transition: background 0.3s;"></div>
                    <div class="strength-bar" style="flex: 1; height: 4px; border-radius: 2px; background: var(--border); transition: background 0.3s;"></div>
                    <div class="strength-bar" style="flex: 1; height: 4px; border-radius: 2px; background: var(--border); transition: background 0.3s;"></div>
                </div>
                <span class="strength-label text-xs text-muted"></span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="confirm_password">
                <?= te('Confirm Password') ?> <span class="required">*</span>
            </label>
            <div style="position: relative;">
                <input type="password" id="confirm_password" name="new_password_confirmation"
                       class="form-control <?= !empty($errors['new_password_confirmation']) ? 'is-invalid' : '' ?>"
                       placeholder="<?= te('Re-enter your new password') ?>"
                       autocomplete="new-password" required
                       style="padding-right: 2.75rem;">
                <button type="button" class="toggle-pw-btn"
                        style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem; font-size: 1.1rem;"
                        aria-label="<?= te('Show password') ?>" tabindex="-1">
                    <span class="pw-icon-show">&#128065;</span>
                    <span class="pw-icon-hide" style="display:none;">&#128064;</span>
                </button>
            </div>
            <?php if (!empty($errors['new_password_confirmation'])): ?>
                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['new_password_confirmation'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--block btn--lg" style="margin-top: 0.5rem;">
            <?= te('Set Password & Continue') ?>
        </button>
    </form>
</div>

<script>
(function () {
    // Password show/hide toggles
    document.querySelectorAll('.toggle-pw-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.querySelector('.pw-icon-show').style.display = isPassword ? 'none' : 'inline';
            btn.querySelector('.pw-icon-hide').style.display = isPassword ? 'inline' : 'none';
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    // Password strength indicator
    var pwInput = document.getElementById('new_password');
    var bars = document.querySelectorAll('.strength-bar');
    var label = document.querySelector('.strength-label');

    if (pwInput && bars.length) {
        pwInput.addEventListener('input', function () {
            var val = pwInput.value;
            var score = 0;

            if (val.length >= 8) score++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^a-zA-Z0-9]/.test(val)) score++;

            var colors = ['var(--danger)', 'var(--warning)', 'var(--info)', 'var(--success)'];
            var labels = ['Weak', 'Fair', 'Good', 'Strong'];

            bars.forEach(function (bar, i) {
                bar.style.background = i < score ? colors[score - 1] : 'var(--border)';
            });

            label.textContent = val.length > 0 ? labels[Math.max(0, score - 1)] : '';
            label.style.color = val.length > 0 ? colors[Math.max(0, score - 1)] : '';
        });
    }
})();
</script>
