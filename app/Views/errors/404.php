<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | EduManager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
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
            color: var(--primary);
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
            <div class="error-code">404</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-text">The page you are looking for does not exist or has been moved.</p>
            <a href="/dashboard" class="btn btn--primary">Go Home</a>
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
