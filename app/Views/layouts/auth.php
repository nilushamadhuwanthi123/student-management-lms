<!DOCTYPE html>
<html lang="<?= lang_current() ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars(($pageTitle ?? t('Login')) . ' | ' . (\App\Services\PublicSite::teacherName() ?? t('Science classes')), ENT_QUOTES, 'UTF-8') ?></title>
    <?= csrfMeta() ?>
    <?php include __DIR__ . '/../components/favicon.php'; ?>

    <!-- Google Fonts & Resource Hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: ['selector', '[data-theme="dark"]'],
        theme: {
            extend: {
                fontFamily: {
                    'display': ['"Playfair Display"', 'Georgia', 'serif'],
                    'body': ['"Inter"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                },
            }
        }
    }
    </script>

    <!-- Base Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(BASE_PATH . '/public/assets/css/style.css') ?: 1 ?>">

    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }

        .login-cinematic {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 1024px) {
            .login-cinematic { grid-template-columns: 1fr 1fr; }
        }

        /* Left side: decorative panel */
        .login-showcase {
            display: none;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #15307E 0%, #1D4ED8 50%, #2563EB 100%);
        }

        @media (min-width: 1024px) {
            .login-showcase { display: flex; align-items: center; justify-content: center; }
        }

        .login-showcase::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.08;
            background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.2) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* Right side: form area */
        .login-form-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            position: relative;
            background: var(--bg);
        }

        /* Decorative gradient blobs on mobile */
        .login-form-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, var(--primary-light) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, var(--secondary-light) 0%, transparent 50%);
            opacity: 0.5;
            pointer-events: none;
        }

        /* Glass card */
        .login-glass-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-xl);
            animation: cardEntrance 0.6s ease forwards;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Premium input focus */
        .login-glass-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--ring), 0 2px 8px rgba(14,111,88,0.1);
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .login-glass-card { animation: none; opacity: 1; }
            * { animation-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <div class="fixed top-4 right-4 z-50">
        <button type="button" class="p-2 rounded-full transition-all duration-300 hover:bg-black/5"
                id="themeToggle" aria-label="<?= te('Toggle dark mode') ?>" title="<?= te('Toggle dark mode') ?>"
                style="color: var(--text-muted);">
            <span class="theme-icon-light">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
            </span>
            <span class="theme-icon-dark" style="display:none;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
            </span>
        </button>
    </div>

    <div class="login-cinematic">
        <!-- Left: Showcase Panel -->
        <?php $__teacher = \App\Services\PublicSite::teacherName(); ?>
        <div class="login-showcase" style="background: linear-gradient(180deg, rgba(10,42,107,.35) 0%, rgba(10,42,107,.78) 55%, rgba(10,42,107,.96) 100%), url('<?= BASE_URL ?>/assets/img/teacher-smartboard.jpg') center 20% / cover no-repeat;">
            <div class="relative z-10 text-center px-12 max-w-md" style="margin-top:auto; padding-bottom:3rem;">
                <div class="mb-5">
                    <span style="width:56px;height:56px;border-radius:16px;background:#FACC15;display:inline-grid;place-items:center;color:#0B1B3F;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg></span>
                </div>
                <h2 class="text-white text-3xl font-bold mb-2 leading-tight"><?= htmlspecialchars($__teacher ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-sm font-semibold mb-3" style="color:#FDE68A;"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></p>
                <p class="text-sm leading-relaxed" style="color:rgba(255,255,255,.85);">
                    <?= te('Your classes, notes, recordings, quizzes and marks, in one place.') ?>
                </p>
                <!-- Decorative circles -->
                <div class="absolute -bottom-20 -left-20 w-60 h-60 rounded-full border border-white/5"></div>
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full border border-white/5"></div>
            </div>
        </div>

        <!-- Right: Login Form -->
        <div class="login-form-side">
            <div class="login-glass-card">
                <!-- Back to home, and the English / Sinhala switch where it is
                     easy to find -- it used to float in the corner under the
                     dark-mode button and was half hidden. -->
                <div class="flex items-center justify-between gap-3 mb-6">
                    <a href="<?= BASE_URL ?>/" class="inline-flex items-center gap-1 text-xs font-medium transition-colors"
                       style="color: var(--text-muted);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        <?= te('Back to Home') ?>
                    </a>
                    <div class="lang-switch" role="group" aria-label="Language / භාෂාව">
                        <a href="<?= lang_switch_url('en') ?>" class="<?= lang_current() === 'en' ? 'is-on' : '' ?>" lang="en" hreflang="en">EN</a>
                        <a href="<?= lang_switch_url('si') ?>" class="<?= lang_current() === 'si' ? 'is-on' : '' ?>" lang="si" hreflang="si">සිංහල</a>
                    </div>
                </div>

                <!-- Logo (mobile) -->
                <div class="flex items-center gap-2 mb-6 lg:hidden">
                    <span style="width:34px;height:34px;border-radius:10px;background:#FACC15;display:grid;place-items:center;color:#0B1B3F;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg></span>
                    <span class="font-semibold text-lg" style="color: var(--text);"><?= htmlspecialchars($__teacher ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <h1 class="font-display text-2xl font-bold mb-1" style="color: var(--text);"><?= te('Welcome Back') ?></h1>
                <p class="text-sm mb-6" style="color: var(--text-secondary);"><?= te('Sign in to the class portal') ?></p>

                <!-- Flash Messages -->
                <?php include __DIR__ . '/../components/flash-messages.php'; ?>

                <!-- Content (login form) -->
                <?= $content ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var themeBtn = document.getElementById('themeToggle');
        var html = document.documentElement;
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark') html.setAttribute('data-theme', 'dark');
        } catch (e) {}
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
            document.querySelectorAll('.theme-icon-light').forEach(function (el) { el.style.display = isDark ? 'none' : 'inline'; });
            document.querySelectorAll('.theme-icon-dark').forEach(function (el) { el.style.display = isDark ? 'inline' : 'none'; });
        }
    })();
    </script>
</body>
</html>
