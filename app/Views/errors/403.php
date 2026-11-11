<!DOCTYPE html>
<?php
$__t = static fn (string $s): string => function_exists('te') ? te($s) : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$__brand = class_exists(\App\Services\PublicSite::class) ? \App\Services\PublicSite::brandName() : '';
?>
<html lang="<?= function_exists('lang_current') ? lang_current() : 'en' ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>403 - <?= $__t('Access Forbidden') ?><?= $__brand !== '' ? ' | ' . htmlspecialchars($__brand, ENT_QUOTES, 'UTF-8') : '' ?></title>
    <?php if (is_file(__DIR__ . '/../components/favicon.php')) { include __DIR__ . '/../components/favicon.php'; } ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: var(--bg);
            text-align: center;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 800;
            line-height: 1;
            color: var(--danger);
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        .error-text {
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        @media (max-width: 480px) {
            .error-code { font-size: 5rem; }
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div>
            <div class="error-code">403</div>
            <h1 class="error-title"><?= $__t('Access Forbidden') ?></h1>
            <p class="error-text"><?= $__t('You do not have permission to access this page. Contact your administrator if you believe this is an error.') ?></p>
            <a href="<?= BASE_URL ?>/dashboard" class="btn btn--primary"><?= $__t('Go Home') ?></a>
        </div>
    </div>
    <script>
    (function () {
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) {}
    })();
    </script>
</body>
</html>
