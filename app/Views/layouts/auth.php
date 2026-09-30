<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'EduManager - Login', ENT_QUOTES, 'UTF-8') ?></title>
    <?= csrfMeta() ?>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--bg);
            position: relative;
        }
        .auth-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, var(--primary-light) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, var(--secondary-light) 0%, transparent 50%),
                radial-gradient(circle at 50% 80%, var(--primary-light) 0%, transparent 50%);
            opacity: 0.6;
            pointer-events: none;
        }
        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-logo a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
        }
        .auth-theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->
    <div class="auth-theme-toggle">
        <button type="button" class="btn btn--ghost btn--sm" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">
            <span class="theme-icon-light">&#9790;</span>
            <span class="theme-icon-dark" style="display:none;">&#9728;</span>
        </button>
    </div>

    <div class="auth-page">
        <div class="auth-container">
            <!-- Logo -->
            <div class="auth-logo">
                <a href="/">
                    <svg width="36" height="36" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <rect width="32" height="32" rx="8" fill="var(--primary)"/>
                        <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="var(--text-inverse)" stroke-width="1.5" fill="none"/>
                        <path d="M16 12v10M8 12l8 4 8-4" stroke="var(--text-inverse)" stroke-width="1.5"/>
                    </svg>
                    <span>EduManager</span>
                </a>
            </div>

            <!-- Flash Messages -->
            <?php include __DIR__ . '/../components/flash-messages.php'; ?>

            <!-- Content -->
            <?= $content ?>
        </div>
    </div>

    <script>
    (function () {
        var themeBtn = document.getElementById('themeToggle');
        var html = document.documentElement;
        var stored = null;
        try { stored = localStorage.getItem('theme'); } catch (e) {}
        if (stored === 'dark') {
            html.setAttribute('data-theme', 'dark');
        }
        updateThemeIcons();

        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var current = html.getAttribute('data-theme');
                var next = current === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', next);
                try { localStorage.setItem('theme', next); } catch (e) {}
                updateThemeIcons();
            });
        }

        function updateThemeIcons() {
            var isDark = html.getAttribute('data-theme') === 'dark';
            var lightIcons = document.querySelectorAll('.theme-icon-light');
            var darkIcons = document.querySelectorAll('.theme-icon-dark');
            lightIcons.forEach(function (el) { el.style.display = isDark ? 'none' : 'inline'; });
            darkIcons.forEach(function (el) { el.style.display = isDark ? 'inline' : 'none'; });
        }
    })();
    </script>
</body>
</html>
