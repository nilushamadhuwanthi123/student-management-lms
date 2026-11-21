<!DOCTYPE html>
<html lang="<?= function_exists('lang_current') ? lang_current() : 'en' ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? (\App\Services\PublicSite::teacherName() ?? t('Science classes')), ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Science classes for Grades 6 to 11.', ENT_QUOTES, 'UTF-8') ?>">
    <?= csrfMeta() ?>

    <?php
    // Look and feel, straight from Website Content -> "Look and feel".
    // Every value is validated there (a colour must be #rrggbb, a timing
    // is clamped to its field's bounds), so nothing user-typed reaches
    // the stylesheet raw.
    $__p   = \App\Services\PublicSite::class;
    $__pri = $__p::color('theme_primary');
    $__sec = $__p::color('theme_secondary');
    $__pbg = $__p::color('theme_page_bg');
    $__nbg = $__p::color('theme_nav_bg');
    $__ntx = $__p::color('theme_nav_text');
    $__acc = $__p::color('theme_accent');
    $__rad = (int) $__p::num('theme_radius');
    $__tint = (int) $__p::num('hero_tint_percent');
    // The same colours as "r, g, b" triplets.
    //
    // The overlays and tints below need the theme colour at part
    // opacity. color-mix() does that in one line, but it needs a 2023
    // browser, and a phone older than that drops the whole declaration:
    // the hero overlay disappears and white heading text lands on a
    // bright photo, unreadable. rgba(var(--x-rgb), .5) works in every
    // browser that understands CSS variables at all, which is every
    // browser this site already required.
    $__rgb = static function (string $hex): string {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) !== 6 || preg_match('/^[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return '29, 78, 216';   // the shipped primary, so a bad value still paints something
        }
        return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
    };
    $__priRgb = $__rgb($__pri);
    $__secRgb = $__rgb($__sec);
    $__nbgRgb = $__rgb($__nbg);
    $__accRgb = $__rgb($__acc);

    $__fontKey = \App\Services\SiteContentService::FONTS[$__p::content('theme_font')] ?? null;
    $__fontBody = $__fontKey[1] ?? "'Inter', system-ui, sans-serif";
    $__fontHead = $__fontKey[2] ?? "'Playfair Display', Georgia, serif";
    ?>
    <style id="tsCollapse">
        /* A line hidden from the CMS renders as nothing, which leaves the
           tag that held it behind: an empty <h2> still takes its margin,
           so the page shows a gap where the sentence used to be. These
           collapse any text element the CMS has emptied.

           :empty only matches an element with nothing at all inside it,
           including whitespace -- which is why the templates print these
           with no space around the tag. An element that still holds
           anything is untouched, so this can never blank something that
           is actually there. */
        h1:empty, h2:empty, h3:empty, h4:empty, h5:empty, h6:empty,
        p:empty, li:empty, blockquote:empty,
        .ts-rich:empty, .ts-lead:empty, .ts-kicker:empty,
        .ts-section__title:empty, .ts-section__text:empty,
        .ts-card__title:empty, .ts-card__text:empty {
            display: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    </style>

    <?php
    $__ogTitle = $pageTitle ?? (\App\Services\PublicSite::teacherName() ?? t('Science classes'));
    $__ogDesc = $metaDescription ?? 'Science classes for Grades 6 to 11.';
    $__origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $__ogImagePath = \App\Services\PublicSite::content('teacher_photo_image');
    $__ogImage = $__origin . BASE_URL . ($__ogImagePath !== '' ? $__ogImagePath : '/assets/img/teacher-smartboard.jpg');
    $__ogUrl = $__origin . strtok($_SERVER['REQUEST_URI'] ?? (BASE_URL . '/'), '?');
    ?>
    <?php include __DIR__ . '/../components/favicon.php'; ?>

    <!-- Social share preview -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars(\App\Services\PublicSite::teacherName() ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($__ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($__ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($__ogUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($__ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($__ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($__ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($__ogImage, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Google Fonts & Resource Hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

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

    <?php /* After style.css on purpose. Both this and the stylesheet
             set --primary, --secondary and --bg on :root, at the same
             specificity, so whichever comes last wins. With this block
             first, the stylesheet quietly overrode the chosen colours:
             --ts-nav-bg and the rest took effect, because nothing else
             defines those, so the menu bar changed and the buttons and
             page did not -- a style that looked half applied. */ ?>
    <style id="tsTheme">
        :root {
            --primary: <?= $__pri ?>;
            --primary-hover: <?= $__pri ?>;
            --secondary: <?= $__sec ?>;
            --bg: <?= $__pbg ?>;
            --ts-nav-bg: <?= $__nbg ?>;
            --ts-nav-text: <?= $__ntx ?>;
            --ts-float-speed: <?= (int) $__p::num('hero_float_speed') ?>s;
            --ts-accent: <?= $__acc ?>;
            --ts-radius: <?= $__rad ?>px;
            --ts-font-body: <?= $__fontBody ?>;
            --ts-font-head: <?= $__fontHead ?>;
            --primary-rgb: <?= $__priRgb ?>;
            --secondary-rgb: <?= $__secRgb ?>;
            --ts-nav-rgb: <?= $__nbgRgb ?>;
            --ts-accent-rgb: <?= $__accRgb ?>;
        }
        body { background: var(--bg); font-family: var(--ts-font-body); }
        .nav-fb { display: inline-flex; align-items: center; gap: .4rem; }
        .nav-fb svg { flex: none; }

        <?php if (\App\Services\PublicSite::content('nav_style') === 'solid'): ?>
        /* Solid bar: the chosen colour from the first scroll pixel, so
           the menu never sits see-through over a busy photo. The glass
           rules below it are overridden rather than removed, because
           they also carry the layout and the scrolled transition. */
        .navbar--liquid-glass,
        .navbar--liquid-glass.scrolled {
            background: var(--ts-nav-bg) !important;
            -webkit-backdrop-filter: none !important;
            backdrop-filter: none !important;
            border-bottom: 1px solid rgba(0, 0, 0, .14) !important;
        }
        .navbar--liquid-glass .navbar__links a,
        .navbar--liquid-glass.scrolled .navbar__links a,
        .navbar--liquid-glass .navbar__brand,
        .navbar--liquid-glass.scrolled .navbar__brand {
            color: var(--ts-nav-text) !important;
        }
        .navbar--liquid-glass .navbar__links a.active,
        .navbar--liquid-glass.scrolled .navbar__links a.active {
            color: var(--ts-accent) !important;
        }
        .navbar--liquid-glass .navbar__toggle { color: var(--ts-nav-text) !important; }
        <?php else: ?>
        /* See-through bar: the chosen colour is what it frosts to once
           the page is scrolled; at the very top it stays transparent so
           the background photo reads through it. */
        .navbar--liquid-glass.scrolled {
            background: rgba(var(--ts-nav-rgb), .86);
        }
        .navbar--liquid-glass.scrolled .navbar__links a,
        .navbar--liquid-glass.scrolled .navbar__brand {
            color: var(--ts-nav-text);
        }
        <?php endif; ?>
        h1, h2, h3, .ts-name { font-family: var(--ts-font-head); }

        /* Corner roundness, from "Corner roundness" in the CMS. */
        .ts-btn, .btn, .card, .ts-card, .sc-card { border-radius: var(--ts-radius); }

        /* The yellow highlight the theme picks. */
        .ts-btn--yellow { background: var(--ts-accent); }

        /* How strongly the colour sits over the background photo.
           0% leaves the photo bare; the shipped look is 100%. */
        .ts-hero-bg::after { opacity: <?= max(0, min(100, $__tint)) / 100 ?>; }
    </style>

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

        /*
         * Mobile nav (screens <= 1040px):
         * - Deep navy glass dropdown (no jarring white background)
         * - Crisp white links with gold active highlights
         * - High contrast header text when scrolled (never white on white)
         */
        @media (max-width: 1040px) {
            .navbar--liquid-glass .navbar__toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .navbar--liquid-glass .navbar__links {
                display: none;
                position: absolute;
                top: 64px;
                left: 0;
                right: 0;
                flex-direction: column;
                align-items: stretch;
                padding: 1.25rem 1.5rem;
                gap: .85rem;
                background: #0B1B3F !important;
                background: rgba(11, 27, 63, 0.98) !important;
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
            }
            .navbar--liquid-glass .navbar__links.open {
                display: flex;
            }
            .navbar--liquid-glass .navbar__links > li > a:not(.nav-login) {
                color: rgba(255, 255, 255, 0.92) !important;
                font-size: 0.95rem;
                padding: 0.4rem 0;
                font-weight: 500;
            }
            .navbar--liquid-glass .navbar__links > li > a:not(.nav-login):hover,
            .navbar--liquid-glass .navbar__links > li > a.active {
                color: #FACC15 !important;
            }
            .navbar--liquid-glass .navbar__links .nav-lang {
                color: #FFFFFF !important;
                border-color: rgba(255, 255, 255, 0.35) !important;
                display: inline-block;
                width: fit-content;
            }
            .navbar--liquid-glass .navbar__links #themeToggle {
                color: #FFFFFF !important;
            }

            /* Over the dark hero (not scrolled): white header text */
            .navbar--on-dark:not(.scrolled) .navbar__toggle,
            .navbar--on-dark:not(.scrolled) .navbar__brand,
            .navbar--on-dark:not(.scrolled) .navbar__brand small {
                color: #fff !important;
            }

            /* When scrolled on mobile: dark text on light glass (or light text in dark theme), font is clearly visible */
            .navbar--liquid-glass.scrolled .navbar__brand {
                color: var(--text) !important;
            }
            .navbar--liquid-glass.scrolled .navbar__brand small {
                color: var(--text-secondary) !important;
            }
            .navbar--liquid-glass.scrolled .navbar__toggle {
                color: var(--text) !important;
            }

            /* When mobile menu is open: keep top navbar seamlessly unified with the dark menu */
            .navbar--liquid-glass.menu-open,
            .navbar--liquid-glass:has(.navbar__links.open) {
                background: #0B1B3F !important;
                background: rgba(11, 27, 63, 0.98) !important;
            }
            .navbar--liquid-glass.menu-open .navbar__brand,
            .navbar--liquid-glass.menu-open .navbar__brand small,
            .navbar--liquid-glass.menu-open .navbar__toggle,
            .navbar--liquid-glass:has(.navbar__links.open) .navbar__brand,
            .navbar--liquid-glass:has(.navbar__links.open) .navbar__brand small,
            .navbar--liquid-glass:has(.navbar__links.open) .navbar__toggle {
                color: #fff !important;
            }
        }
        @media (min-width: 1041px) and (max-width: 1180px) {
            .navbar__brand small { display: none; }
        }
        .navbar__brand { min-width: 0; }
        .navbar__brand .brand-text { min-width: 0; }
        .navbar__brand .font-semibold { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 46vw; }
        body.public-layout { overflow-x: hidden; }

        /* Navigation over the dark blue hero (desktop): white until scrolled */
        @media (min-width: 1041px) {
            .navbar--on-dark:not(.scrolled) .navbar__brand,
            .navbar--on-dark:not(.scrolled) .navbar__brand small,
            .navbar--on-dark:not(.scrolled) .navbar__links > li > a:not(.nav-login),
            .navbar--on-dark:not(.scrolled) #themeToggle { color: #fff !important; }
            .navbar--on-dark:not(.scrolled) .navbar__links > li > a.active::after { background: #FACC15; }
            .navbar--on-dark:not(.scrolled) .navbar__links > li > a.active { color: #FACC15 !important; }
        }
        .navbar--on-dark:not(.scrolled) .navbar__toggle,
        .navbar--on-dark:not(.scrolled) .navbar__brand,
        .navbar--on-dark:not(.scrolled) .navbar__brand small { color: #fff !important; }
        .navbar--liquid-glass.scrolled .navbar__brand { color: var(--text) !important; }
        .navbar--liquid-glass.scrolled .navbar__brand small { color: var(--text-secondary) !important; }
        .navbar--liquid-glass.scrolled .navbar__toggle { color: var(--text) !important; }
        .navbar__brand small { display: block; font-size: .72rem; font-weight: 500; opacity: .8; letter-spacing: .01em; line-height: 1.1; }
        .navbar__brand .brand-text { display: flex; flex-direction: column; line-height: 1.15; }
        .nav-lang { border: 1px solid currentColor; border-radius: 999px; padding: .25rem .7rem !important; text-transform: none !important; font-size: .8rem !important; }

        /* Noscript fallback */
        noscript .reveal { opacity: 1; transform: none; }

        /* ---- Opening titles ------------------------------------------
           The sheet is `hidden` in the markup and only revealed by the
           script, so with scripting off nothing ever covers the page. */
        .ts-intro { position: fixed; inset: 0; z-index: 10000; display: grid; place-items: center;
            background: radial-gradient(120% 120% at 50% 0%, #15368F 0%, #0B1B3F 60%, #070F29 100%);
            transition: opacity .55s ease, transform .55s ease; }
        .ts-intro[hidden] { display: none; }
        .ts-intro.is-out { opacity: 0; transform: translateY(-3%); pointer-events: none; }
        /* Hold the scrollbar still while the sheet is up. */
        html.ts-intro-on, html.ts-intro-on body { overflow: hidden; }

        .ts-intro__inner { display: grid; justify-items: center; gap: 1.3rem; padding: 2rem; text-align: center; }
        .ts-intro__atom { width: clamp(96px, 22vw, 140px); height: clamp(96px, 22vw, 140px);
            fill: none; overflow: visible; }
        .ts-intro__nucleus { fill: #FACC15; opacity: 0; animation: tsiCore .5s ease .55s forwards; }
        .ts-intro__shell { stroke: rgba(147, 197, 253, .9); stroke-width: 1.6; stroke-linecap: round;
            /* 2*pi*sqrt((50^2+19^2)/2) is near enough for a dash sweep. */
            stroke-dasharray: 240; stroke-dashoffset: 240;
            animation: tsiDraw .85s ease forwards; transform-origin: 60px 60px; }
        .ts-intro__shell--b { animation-delay: .16s; transform: rotate(60deg); }
        .ts-intro__shell--c { animation-delay: .32s; transform: rotate(120deg); }

        .ts-intro__text { display: grid; gap: .35rem; opacity: 0; animation: tsiRise .7s ease .75s forwards; }
        .ts-intro__name { font-family: 'Playfair Display', 'Noto Sans Sinhala', Georgia, serif;
            font-size: clamp(1.5rem, 5vw, 2.3rem); font-weight: 700; color: #FDE9B0; letter-spacing: .01em; }
        .ts-intro__miss { display: inline-block; vertical-align: middle; font-family: 'Inter', sans-serif;
            font-size: .42em; font-weight: 700; letter-spacing: .04em; color: #0B1B3F;
            background: #FACC15; border-radius: 6px; padding: .3em .6em; margin-left: .3em; }
        .ts-intro__sub { font-size: clamp(.78rem, 2.4vw, .92rem); font-weight: 500;
            letter-spacing: .06em; text-transform: uppercase; color: rgba(219, 234, 254, .8); }

        @keyframes tsiDraw { to { stroke-dashoffset: 0; } }
        @keyframes tsiCore { to { opacity: 1; } }
        @keyframes tsiRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .ts-intro { display: none !important; } }
    </style>
</head>
<body class="public-layout">
<?php if (\App\Services\PublicSite::isPreviewing()): ?>
    <!-- Only ever rendered for the super admin who started the preview. -->
    <div class="ts-preview-bar" role="status">
        <span><strong><?= te('Preview') ?></strong> &mdash; <?= te('this is how the site would look. Nothing has been saved yet, and visitors still see the live version.') ?></span>
        <a href="<?= BASE_URL ?>/settings/site-content/preview/stop"><?= te('Finish and go back to the editor') ?></a>
    </div>
    <style>
        .ts-preview-bar {
            position: sticky; top: 0; z-index: 9999;
            display: flex; flex-wrap: wrap; gap: .75rem;
            align-items: center; justify-content: center;
            padding: .6rem 1rem; text-align: center;
            background: #FACC15; color: #1C2538;
            font-size: .88rem; font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,.18);
        }
        .ts-preview-bar a { color: #15307E; text-decoration: underline; white-space: nowrap; }
    </style>
<?php endif; ?>


    <?php
    // The opening titles: an atom draws itself, the teacher's name
    // arrives under it, and the whole sheet lifts off the page.
    //
    // Three rules keep a decorative thing from ever costing someone the
    // site: it is shown once per browser session, it is skipped for
    // anyone who asks for reduced motion, and it is torn down by a
    // timer as well as by the load event, so a stalled image or a
    // scripting error cannot leave the page behind a blue sheet. With
    // scripting off it is never shown at all.
    $introName = \App\Services\PublicSite::teacherName();
    ?>
    <div id="tsIntro" class="ts-intro" aria-hidden="true" hidden>
        <div class="ts-intro__inner">
            <svg class="ts-intro__atom" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="ts-intro__nucleus" cx="60" cy="60" r="7"/>
                <ellipse class="ts-intro__shell" cx="60" cy="60" rx="50" ry="19"/>
                <ellipse class="ts-intro__shell ts-intro__shell--b" cx="60" cy="60" rx="50" ry="19"/>
                <ellipse class="ts-intro__shell ts-intro__shell--c" cx="60" cy="60" rx="50" ry="19"/>
            </svg>
            <div class="ts-intro__text">
                <?php if ($introName !== null): ?>
                    <span class="ts-intro__name"><?= htmlspecialchars((string) strtok((string) $introName, ' '), ENT_QUOTES, 'UTF-8') ?> <span class="ts-intro__miss"><?= te('Miss') ?></span></span>
                <?php endif; ?>
                <span class="ts-intro__sub"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></span>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var sheet = document.getElementById('tsIntro');
        if (!sheet) { return; }

        var seen = false;
        // Turned off in Website Content -> "Look and feel": never show it.
        if (!<?= \App\Services\PublicSite::isOn('intro_enabled') ? 'true' : 'false' ?>) {
            if (sheet && sheet.parentNode) { sheet.parentNode.removeChild(sheet); }
            return;
        }

        try { seen = window.sessionStorage.getItem('tsIntroSeen') === '1'; } catch (e) { seen = false; }
        var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (seen || still) { sheet.parentNode.removeChild(sheet); return; }

        try { window.sessionStorage.setItem('tsIntroSeen', '1'); } catch (e) {}

        sheet.hidden = false;
        document.documentElement.classList.add('ts-intro-on');

        var gone = false;
        function finish() {
            if (gone) { return; }
            gone = true;
            document.documentElement.classList.remove('ts-intro-on');
            sheet.classList.add('is-out');
            // Take it out of the page once the lift-off has played.
            setTimeout(function () { if (sheet.parentNode) { sheet.parentNode.removeChild(sheet); } }, 900);
        }

        // Whichever comes first: the page finishing, or 2.2 seconds.
        // Both timings come from Website Content -> "Look and feel", so the
        // opening can be slowed down for people to read it, or sped up,
        // without touching this file. The first is the pause after the
        // page is ready; the second is the hard stop, so a slow page can
        // never leave the sheet up indefinitely.
        var holdMs = <?= (int) \App\Services\PublicSite::num('intro_hold_ms') ?>;
        window.addEventListener('load', function () { setTimeout(finish, Math.round(holdMs * 0.2)); });
        setTimeout(finish, holdMs);
        // A click should never be trapped behind it.
        sheet.addEventListener('click', finish);
    })();
    </script>

    <!-- Liquid-Glass Navbar -->
    <nav class="navbar navbar--liquid-glass<?= in_array($activePage ?? '', ['home', 'about', 'contact', 'branch', 'classes', 'downloads', 'events', 'enroll'], true) ? ' navbar--on-dark' : '' ?>" id="mainNav">
        <div class="nav-shimmer"></div>
        <a href="<?= BASE_URL ?>/" class="navbar__brand font-body">
            <span style="width:36px;height:36px;border-radius:11px;background:#FACC15;display:grid;place-items:center;color:#0B1B3F;flex:0 0 36px;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg>
            </span>
            <span class="brand-text"><span class="font-semibold tracking-tight"><?= htmlspecialchars(\App\Services\PublicSite::teacherName() ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?></span><small><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></small></span>
        </a>

        <button class="navbar__toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <ul class="navbar__links" id="navLinks">
            <li><a href="<?= BASE_URL ?>/" class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>"><?= te('Home') ?></a></li>
            <li><a href="<?= BASE_URL ?>/about" class="<?= ($activePage ?? '') === 'about' ? 'active' : '' ?>"><?= te('About') ?></a></li>
            <li><a href="<?= BASE_URL ?>/our-classes" class="<?= ($activePage ?? '') === 'classes' ? 'active' : '' ?>"><?= te('Classes') ?></a></li>
            <?php if (\App\Services\PublicSite::isOn('show_nav_events')): ?>
            <li><a href="<?= BASE_URL ?>/events" class="<?= ($activePage ?? '') === 'events' ? 'active' : '' ?>"><?= te('Events') ?></a></li>
            <?php endif; ?>
            <?php if (\App\Services\PublicSite::isOn('show_nav_downloads')): ?>
            <li><a href="<?= BASE_URL ?>/downloads" class="<?= ($activePage ?? '') === 'downloads' ? 'active' : '' ?>"><?= te('Downloads') ?></a></li>
            <?php endif; ?>
            <li><a href="<?= BASE_URL ?>/contact" class="<?= ($activePage ?? '') === 'contact' ? 'active' : '' ?>"><?= te('Contact') ?></a></li>
            <?php
            // Only shown when the switch is on AND a Facebook address has
            // actually been entered on the Contact tab -- a button that
            // goes nowhere is worse than no button.
            $__fb = \App\Services\PublicSite::externalUrl(\App\Services\PublicSite::content('contact_facebook_url'));
            ?>
            <?php if (($__fb ?? '') !== '' && \App\Services\PublicSite::isOn('show_nav_facebook')): ?>
            <li>
                <a href="<?= htmlspecialchars((string) $__fb, ENT_QUOTES, 'UTF-8') ?>" class="nav-fb"
                   target="_blank" rel="noopener noreferrer" aria-label="<?= te('Our Facebook page') ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99A10 10 0 0 0 22 12z"/></svg>
                    <span><?= te('Facebook') ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li><a href="<?= BASE_URL ?>/lang/<?= (function_exists('lang_current') && lang_current() === 'si') ? 'en' : 'si' ?>" class="nav-lang" hreflang="<?= (function_exists('lang_current') && lang_current() === 'si') ? 'en' : 'si' ?>"><?= (function_exists('lang_current') && lang_current() === 'si') ? 'English' : 'සිංහල' ?></a></li>
            <?php if (\App\Services\PublicSite::isOn('show_nav_enrol')): ?>
            <li>
                <a href="<?= BASE_URL ?>/enroll" class="inline-flex items-center px-5 py-2 rounded-full text-sm font-semibold transition-all duration-300"
                   style="text-transform:none; background:#1D4ED8; color:#fff;">
                    <?= te('Enrol now') ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Services\PublicSite::isOn('show_nav_login')): ?>
            <li>
                <a href="<?= BASE_URL ?>/login" class="nav-login inline-flex items-center px-5 py-2 rounded-full text-sm font-semibold transition-all duration-300"
                   style="text-transform:none; background:#FACC15; color:#0B1B3F;">
                    <?= te('Login') ?>
                </a>
            </li>
            <?php endif; ?>
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

    <!-- Footer: only real details (branches and phones from the database) -->
    <?php $__fb = \App\Services\PublicSite::branches(); $__ft = \App\Services\PublicSite::teacherName(); ?>
    <footer class="relative overflow-hidden" style="background: #0B1220;">
        <div class="absolute top-0 left-0 right-0 h-px" style="background: linear-gradient(90deg, transparent, #FACC15, #1D4ED8, transparent);"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-14 pb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-10">
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-3">
                        <span style="width:34px;height:34px;border-radius:10px;background:#FACC15;display:grid;place-items:center;color:#0B1B3F;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg>
                        </span>
                        <span class="text-white font-semibold text-lg tracking-tight"><?= htmlspecialchars($__ft ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <p class="text-sm leading-relaxed" style="color:#94A3B8;"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></p>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4"><?= te('Pages') ?></h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="<?= BASE_URL ?>/" class="hover:text-white" style="color:#94A3B8;"><?= te('Home') ?></a></li>
                        <li><a href="<?= BASE_URL ?>/about" class="hover:text-white" style="color:#94A3B8;"><?= te('About') ?></a></li>
                        <li><a href="<?= BASE_URL ?>/our-classes" class="hover:text-white" style="color:#94A3B8;"><?= te('Classes') ?></a></li>
                        <?php if (\App\Services\PublicSite::isOn('show_nav_events')): ?>
                        <li><a href="<?= BASE_URL ?>/events" class="hover:text-white" style="color:#94A3B8;"><?= te('Events') ?></a></li>
                        <?php endif; ?>
                        <?php if (\App\Services\PublicSite::isOn('show_nav_downloads')): ?>
                        <li><a href="<?= BASE_URL ?>/downloads" class="hover:text-white" style="color:#94A3B8;"><?= te('Downloads') ?></a></li>
                        <?php endif; ?>
                        <li><a href="<?= BASE_URL ?>/enroll" class="hover:text-white" style="color:#94A3B8;"><?= te('Enrol now') ?></a></li>
                        <li><a href="<?= BASE_URL ?>/contact" class="hover:text-white" style="color:#94A3B8;"><?= te('Contact') ?></a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4"><?= te('Branches') ?></h4>
                    <ul class="space-y-3 text-sm" style="color:#94A3B8;">
                        <?php foreach ($__fb as $__b): ?>
                            <li><a href="<?= BASE_URL ?>/branches/<?= (int) $__b['id'] ?>" class="text-white hover:underline"><?= htmlspecialchars((string) $__b['name'], ENT_QUOTES, 'UTF-8') ?></a>
                                <?php if (!empty($__b['phone1'])): ?><br><a href="<?= htmlspecialchars(\App\Services\PublicSite::telHref($__b['phone1']), ENT_QUOTES, 'UTF-8') ?>" class="hover:text-white" style="color:#94A3B8;">&#128222; <?= htmlspecialchars((string) $__b['phone1'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-white mb-4"><?= te('Students and parents') ?></h4>
                    <p class="text-sm mb-4" style="color:#94A3B8;"><?= te('Log in to see classes, marks, homework and fees.') ?></p>
                    <a href="<?= BASE_URL ?>/login" class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-semibold" style="background:#FACC15;color:#0B1B3F;"><?= te('Login') ?> &rarr;</a>
                </div>
            </div>
            <div class="border-t pt-6 text-center" style="border-color:#1E293B;">
                <p class="text-xs" style="color:#64748B;">&copy; <?= date('Y') ?> <?= htmlspecialchars($__ft ?? t('Science classes'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= te('Science classes') ?> &middot; Developed by <a href="https://www.facebook.com/chandilagroups/" target="_blank" rel="noopener noreferrer" class="hover:underline" style="color:#94A3B8;">Chandila</a></p>
            </div>
        </div>
    </footer>

    <noscript><style>.reveal { opacity: 1; transform: none; }</style></noscript>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: 1 ?>"></script>
    <script>
    (function () {
        // ── Mobile nav toggle ──
        var nav = document.getElementById('mainNav');
        var toggle = document.getElementById('navToggle');
        var links = document.getElementById('navLinks');
        if (toggle && links) {
            toggle.addEventListener('click', function () {
                var isOpen = links.classList.toggle('open');
                if (nav) nav.classList.toggle('menu-open', isOpen);
                toggle.setAttribute('aria-expanded', String(isOpen));
            });
            links.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () {
                    links.classList.remove('open');
                    if (nav) nav.classList.remove('menu-open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });
        }

        // â”€â”€ Theme toggle â”€â”€
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

        // â”€â”€ Navbar scroll effect â”€â”€
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

        // â”€â”€ Scroll reveal (IntersectionObserver) â”€â”€
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

        // â”€â”€ Counter animation â”€â”€
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
    <?php require BASE_PATH . '/app/Views/partials/chatbot.php'; ?>
    <?php require BASE_PATH . '/app/Views/partials/site-popup.php'; ?>
</body>
</html>
