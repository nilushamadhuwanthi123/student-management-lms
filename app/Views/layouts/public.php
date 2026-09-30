<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'EduManager - Student Management System', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'EduManager - A comprehensive student management system for educational institutes.', ENT_QUOTES, 'UTF-8') ?>">
    <?= csrfMeta() ?>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="public-layout">

    <!-- Glass Navbar -->
    <nav class="navbar navbar--glass" id="mainNav">
        <a href="/" class="navbar__brand">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                <rect width="32" height="32" rx="8" fill="var(--primary)"/>
                <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="var(--text-inverse)" stroke-width="1.5" fill="none"/>
                <path d="M16 12v10M8 12l8 4 8-4" stroke="var(--text-inverse)" stroke-width="1.5"/>
            </svg>
            <span>EduManager</span>
        </a>

        <button class="navbar__toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            &#9776;
        </button>

        <ul class="navbar__links" id="navLinks">
            <li><a href="/" class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
            <li><a href="/about" class="<?= ($activePage ?? '') === 'about' ? 'active' : '' ?>">About</a></li>
            <li><a href="/features" class="<?= ($activePage ?? '') === 'features' ? 'active' : '' ?>">Features</a></li>
            <li><a href="/contact" class="<?= ($activePage ?? '') === 'contact' ? 'active' : '' ?>">Contact</a></li>
            <li>
                <a href="/login" class="btn btn--primary btn--sm">Login</a>
            </li>
            <li>
                <button type="button" class="btn btn--ghost btn--sm" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">
                    <span class="theme-icon-light">&#9790;</span>
                    <span class="theme-icon-dark" style="display:none;">&#9728;</span>
                </button>
            </li>
        </ul>
    </nav>

    <!-- Flash Messages -->
    <main style="padding-top: 64px;">
        <div class="container">
            <?php include __DIR__ . '/../components/flash-messages.php'; ?>
        </div>

        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer__grid">
            <!-- About Column -->
            <div>
                <h4 class="footer__heading">About EduManager</h4>
                <p style="font-size: 0.875rem; line-height: 1.7; color: #A7B6AE;">
                    EduManager is a comprehensive student management system designed to streamline
                    educational institute operations, from enrollment to payment tracking and beyond.
                </p>
            </div>

            <!-- Quick Links Column -->
            <div>
                <h4 class="footer__heading">Quick Links</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem;">
                    <li><a href="/">Home</a></li>
                    <li><a href="/about">About Us</a></li>
                    <li><a href="/features">Features</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/login">Login</a></li>
                </ul>
            </div>

            <!-- Contact Info Column -->
            <div>
                <h4 class="footer__heading">Contact Info</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem; color: #A7B6AE;">
                    <li>&#9993; info@edumanager.com</li>
                    <li>&#9742; +94 11 234 5678</li>
                    <li>&#9962; 123 Education Lane, Colombo, Sri Lanka</li>
                </ul>
            </div>
        </div>

        <div class="footer__bottom">
            &copy; 2024 EduManager. All rights reserved.
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
    <script>
    (function () {
        // Mobile nav toggle
        var toggle = document.getElementById('navToggle');
        var links = document.getElementById('navLinks');
        if (toggle && links) {
            toggle.addEventListener('click', function () {
                links.classList.toggle('open');
                toggle.setAttribute('aria-expanded', links.classList.contains('open'));
            });
        }

        // Theme toggle
        var themeBtn = document.getElementById('themeToggle');
        var html = document.documentElement;
        var stored = localStorage.getItem('theme');
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
