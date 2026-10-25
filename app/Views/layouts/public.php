<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'EduManager - Student Management System', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'EduManager - A comprehensive student management system for educational institutes.', ENT_QUOTES, 'UTF-8') ?>">
    <?= csrfMeta() ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: ['selector', '[data-theme="dark"]'],
        theme: {
            extend: {
                colors: {
                    brand: {
                        bg: 'var(--bg)',
                        surface: 'var(--surface)',
                        'surface-raised': 'var(--surface-raised)',
                        'surface-sunken': 'var(--surface-sunken)',
                        primary: 'var(--primary)',
                        'primary-hover': 'var(--primary-hover)',
                        'primary-light': 'var(--primary-light)',
                        secondary: 'var(--secondary)',
                        'secondary-light': 'var(--secondary-light)',
                        border: 'var(--border)',
                        text: 'var(--text)',
                        'text-secondary': 'var(--text-secondary)',
                        'text-muted': 'var(--text-muted)',
                        'text-inverse': 'var(--text-inverse)',
                        success: 'var(--success)',
                        warning: 'var(--warning)',
                        danger: 'var(--danger)',
                    },
                    emerald: {
                        DEFAULT: '#1D4ED8',
                        50: '#E8EEFC',
                        100: '#D8E2F2',
                        600: '#1D4ED8',
                        700: '#1A3FAF',
                    },
                    teal: {
                        DEFAULT: '#2563EB',
                        600: '#2563EB',
                    }
                },
                fontFamily: {
                    'display': ['"Playfair Display"', 'Georgia', 'serif'],
                    'body': ['"Inter"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                },
                animation: {
                    'fade-up': 'fadeUp 0.7s ease forwards',
                    'fade-up-delay': 'fadeUp 0.7s ease 0.15s forwards',
                    'fade-up-delay-2': 'fadeUp 0.7s ease 0.3s forwards',
                    'shimmer': 'shimmer 8s linear infinite',
                    'pulse-soft': 'pulseSoft 3s ease-in-out infinite',
                    'counter': 'counter 2s ease-out forwards',
                },
                keyframes: {
                    fadeUp: {
                        '0%': { opacity: '0', transform: 'translateY(30px)' },
                        '100%': { opacity: '1', transform: 'translateY(0)' },
                    },
                    shimmer: {
                        '0%': { backgroundPosition: '-200% center' },
                        '100%': { backgroundPosition: '200% center' },
                    },
                    pulseSoft: {
                        '0%, 100%': { transform: 'scale(1)' },
                        '50%': { transform: 'scale(1.02)' },
                    },
                },
            }
        }
    }
    </script>

    <!-- Base Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(BASE_PATH . '/public/assets/css/style.css') ?: 1 ?>">

    <!-- Premium Public Overrides -->
    <style>
        /* Public pages use Inter as base font */
        .public-layout { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }

        /* Liquid-glass navigation */
        .navbar--liquid-glass {
            background: transparent;
            backdrop-filter: blur(0px);
            -webkit-backdrop-filter: blur(0px);
            border-bottom: 1px solid transparent;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .navbar--liquid-glass.scrolled {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
        }
        .navbar--liquid-glass .navbar__links a {
            letter-spacing: 0.02em;
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        .navbar--liquid-glass .navbar__links a:hover {
            transform: translateY(-1px);
        }
        .navbar--liquid-glass .navbar__links a.active {
            color: var(--primary);
            position: relative;
        }
        .navbar--liquid-glass .navbar__links a.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--primary);
        }

        /* Shimmer border for nav */
        .nav-shimmer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), var(--secondary), transparent);
            background-size: 200% 100%;
            animation: shimmer 8s linear infinite;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .navbar--liquid-glass.scrolled .nav-shimmer { opacity: 0.5; }

        /* Scroll reveal */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-left { transform: translateX(-40px); }
        .reveal-left.visible { transform: translateX(0); }
        .reveal-right { transform: translateX(40px); }
        .reveal-right.visible { transform: translateX(0); }
        .reveal-scale { transform: scale(0.95); }
        .reveal-scale.visible { transform: scale(1); }

        /* Stagger children */
        .reveal-stagger > .reveal:nth-child(1) { transition-delay: 0s; }
        .reveal-stagger > .reveal:nth-child(2) { transition-delay: 0.08s; }
        .reveal-stagger > .reveal:nth-child(3) { transition-delay: 0.16s; }
        .reveal-stagger > .reveal:nth-child(4) { transition-delay: 0.24s; }
        .reveal-stagger > .reveal:nth-child(5) { transition-delay: 0.32s; }
        .reveal-stagger > .reveal:nth-child(6) { transition-delay: 0.40s; }

        /* Premium button glow */
        .btn-glow {
            position: relative;
            overflow: hidden;
        }
        .btn-glow::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: inherit;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            opacity: 0;
            z-index: -1;
            filter: blur(12px);
            transition: opacity 0.3s ease;
        }
        .btn-glow:hover::after { opacity: 0.4; }

        /* Cinematic hero */
        .hero--cinematic {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .hero__media {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .hero__media video,
        .hero__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero__gradient-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
        }

        /* Premium card hover */
        .card-premium {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-premium:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(14, 111, 88, 0.12);
        }
        .card-premium:hover .card-icon-wrap {
            transform: rotate(5deg) scale(1.05);
        }
        .card-icon-wrap {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Wave divider */
        .wave-divider {
            position: relative;
            height: 60px;
            overflow: hidden;
        }
        .wave-divider svg {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 60px;
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .reveal-left, .reveal-right, .reveal-scale { transform: none; }
            .nav-shimmer { animation: none; }
            .btn-glow::after { display: none; }
            .card-premium:hover { transform: none; }
            * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }
        }

        /* Mobile nav glass panel */
        @media (max-width: 768px) {
            .navbar__links {
                background: var(--glass-bg) !important;
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
            }
        }

        /* Noscript fallback */
        noscript .reveal { opacity: 1; transform: none; }
    </style>
</head>
<body class="public-layout">

    <!-- Liquid-Glass Navbar -->
    <nav class="navbar navbar--liquid-glass" id="mainNav">
        <div class="nav-shimmer"></div>
        <a href="<?= BASE_URL ?>/" class="navbar__brand font-body">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                <rect width="32" height="32" rx="8" fill="var(--primary)"/>
                <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="var(--text-inverse)" stroke-width="1.5" fill="none"/>
                <path d="M16 12v10M8 12l8 4 8-4" stroke="var(--text-inverse)" stroke-width="1.5"/>
            </svg>
            <span class="font-semibold tracking-tight">EduManager</span>
        </a>

        <button class="navbar__toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <ul class="navbar__links" id="navLinks">
            <li><a href="<?= BASE_URL ?>/" class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= BASE_URL ?>/timetable" class="<?= ($activePage ?? '') === 'classes' ? 'active' : '' ?>"><?= te('Timetable') ?></a></li>
            <li><a href="<?= BASE_URL ?>/about" class="<?= ($activePage ?? '') === 'about' ? 'active' : '' ?>">About</a></li>
            <li><a href="<?= BASE_URL ?>/features" class="<?= ($activePage ?? '') === 'features' ? 'active' : '' ?>">Features</a></li>
            <li><a href="<?= BASE_URL ?>/contact" class="<?= ($activePage ?? '') === 'contact' ? 'active' : '' ?>">Contact</a></li>
            <li>
                <a href="<?= BASE_URL ?>/login" class="inline-flex items-center px-5 py-2 rounded-full text-sm font-semibold
                    bg-[var(--primary)] text-[var(--text-inverse)] hover:bg-[var(--primary-hover)]
                    transition-all duration-300 btn-glow" style="text-transform:none;">
                    Login
                </a>
            </li>
            <li>
                <button type="button" class="btn btn--ghost btn--sm rounded-full" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">
                    <span class="theme-icon-light">&#9790;</span>
                    <span class="theme-icon-dark" style="display:none;">&#9728;</span>
                </button>
            </li>
        </ul>
    </nav>

    <!-- Flash Messages -->
    <main>
        <div class="container">
            <?php include __DIR__ . '/../components/flash-messages.php'; ?>
        </div>

        <?= $content ?>
    </main>

    <!-- Premium Footer -->
    <footer class="relative overflow-hidden" style="background: #0B1220;">
        <!-- Subtle gradient accent -->
        <div class="absolute top-0 left-0 right-0 h-px" style="background: linear-gradient(90deg, transparent, var(--primary), var(--secondary), transparent);"></div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                <!-- Brand Column -->
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                            <rect width="32" height="32" rx="8" fill="#1D4ED8"/>
                            <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="#fff" stroke-width="1.5" fill="none"/>
                            <path d="M16 12v10M8 12l8 4 8-4" stroke="#fff" stroke-width="1.5"/>
                        </svg>
                        <span class="text-white font-semibold text-lg tracking-tight font-body">EduManager</span>
                    </div>
                    <p class="text-sm leading-relaxed" style="color: #8494AC;">
                        A comprehensive student management system designed to streamline
                        educational institute operations.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="<?= BASE_URL ?>/" class="text-sm hover:text-white transition-colors" style="color: #8494AC;">Home</a></li>
                        <li><a href="<?= BASE_URL ?>/timetable" class="text-sm hover:text-white transition-colors" style="color: #8494AC;"><?= te('Timetable') ?></a></li>
                        <li><a href="<?= BASE_URL ?>/about" class="text-sm hover:text-white transition-colors" style="color: #8494AC;">About Us</a></li>
                        <li><a href="<?= BASE_URL ?>/features" class="text-sm hover:text-white transition-colors" style="color: #8494AC;">Features</a></li>
                        <li><a href="<?= BASE_URL ?>/contact" class="text-sm hover:text-white transition-colors" style="color: #8494AC;">Contact</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Contact</h4>
                    <ul class="space-y-2 text-sm" style="color: #8494AC;">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            info@edumanager.com
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                            +94 11 234 5678
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            123 Education Lane, Colombo, Sri Lanka
                        </li>
                    </ul>
                </div>

                <!-- Social / CTA -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Get Started</h4>
                    <p class="text-sm mb-4" style="color: #8494AC;">Ready to transform your institute?</p>
                    <a href="<?= BASE_URL ?>/login" class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-semibold
                        bg-[#1D4ED8] text-white hover:bg-[#1A3FAF] transition-all duration-300">
                        Login Now
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="border-t pt-6 text-center" style="border-color: #141E31;">
                <p class="text-xs" style="color: #3D5273;">&copy; <?= date('Y') ?> EduManager. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <noscript><style>.reveal { opacity: 1; transform: none; }</style></noscript>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: 1 ?>"></script>
    <script>
    (function () {
        // ── Mobile nav toggle ──
        var toggle = document.getElementById('navToggle');
        var links = document.getElementById('navLinks');
        if (toggle && links) {
            toggle.addEventListener('click', function () {
                links.classList.toggle('open');
                toggle.setAttribute('aria-expanded', links.classList.contains('open'));
            });
        }

        // ── Theme toggle ──
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

        // ── Navbar scroll effect ──
        var nav = document.getElementById('mainNav');
        if (nav) {
            var ticking = false;
            window.addEventListener('scroll', function () {
                if (!ticking) {
                    window.requestAnimationFrame(function () {
                        if (window.scrollY > 50) {
                            nav.classList.add('scrolled');
                        } else {
                            nav.classList.remove('scrolled');
                        }
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        }

        // ── Scroll reveal (IntersectionObserver) ──
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('visible'); });
        } else {
            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            document.querySelectorAll('.reveal').forEach(function (el) {
                revealObserver.observe(el);
            });
        }

        // ── Counter animation ──
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    var target = el.getAttribute('data-count');
                    if (!target) return;
                    var match = target.match(/^([\d.]+)(.*)$/);
                    if (!match) return;
                    var num = parseFloat(match[1]);
                    var suffix = match[2] || '';
                    var duration = 2000;
                    var start = performance.now();
                    var isDecimal = target.indexOf('.') !== -1;

                    function update(now) {
                        var elapsed = now - start;
                        var progress = Math.min(elapsed / duration, 1);
                        progress = 1 - (1 - progress) * (1 - progress);
                        var current = num * progress;
                        el.textContent = (isDecimal ? current.toFixed(1) : Math.floor(current)) + suffix;
                        if (progress < 1) requestAnimationFrame(update);
                    }
                    requestAnimationFrame(update);
                    counterObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('[data-count]').forEach(function (el) {
            counterObserver.observe(el);
        });
    })();
    </script>
</body>
</html>
