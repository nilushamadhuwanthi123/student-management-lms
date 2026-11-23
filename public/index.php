<?php

declare(strict_types=1);

/**
 * Main Entry Point / Front Controller
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Base URL for subfolder deployment (e.g. /student-management-lms)
$_baseUrl = '';
if (file_exists(BASE_PATH . '/.env')) {
    $_envLines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($_envLines as $_el) {
        if (str_starts_with(trim($_el), 'APP_URL=')) {
            $_baseUrl = trim(explode('=', $_el, 2)[1], " \t\n\r\0\x0B\"'");
        }
    }
}
$_parsedPath = $_baseUrl ? (parse_url($_baseUrl, PHP_URL_PATH) ?: '') : '';
define('BASE_URL', rtrim($_parsedPath, '/'));

// ──────────────────────────────────────────────────────────
// Autoloader for app/ classes and global models
// ──────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $class = ltrim($class, '\\');

    // Namespaced classes: App\Controllers\Foo => app/Controllers/Foo.php
    if (str_starts_with($class, 'App\\')) {
        $relativePath = str_replace('\\', '/', substr($class, 4));
        $filePath = BASE_PATH . '/app/' . $relativePath . '.php';
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }

    // Support global models in app/Models/
    $modelFile = BASE_PATH . '/app/Models/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return;
    }
});

// ──────────────────────────────────────────────────────────
// Load configuration
// ──────────────────────────────────────────────────────────
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';

// Create global PDO connection for controllers that use global $pdo
$pdo = Database::connect();

require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/csrf.php';
require_once BASE_PATH . '/config/lang.php';
// One map of which roles may open which page; views ask can() before
// drawing a control, so no role is shown a link its route refuses.
require_once BASE_PATH . '/config/permissions.php';
require_once BASE_PATH . '/config/security.php';

// ──────────────────────────────────────────────────────────
// Error handling
// ──────────────────────────────────────────────────────────
$appDebug = env('APP_DEBUG', 'false') === 'true';

if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $e) use ($appDebug): void {
    http_response_code(500);

    if ($appDebug) {
        echo '<h1>Application Error</h1>';
        echo '<p><strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>Something went wrong. Please try again later.</p>';
    }

    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
});

// Always send an explicit UTF-8 charset with the response, so the
// browser never has to guess (or fall back to a different default)
// before it reaches the <meta charset="UTF-8"> tag in the HTML --
// without this, Sinhala text can render as mojibake in some browsers.
header('Content-Type: text/html; charset=UTF-8');

// Apply security headers
\App\Middleware\SecurityHeaders::apply();

// ──────────────────────────────────────────────────────────
// Load routes
// ──────────────────────────────────────────────────────────
$router = require BASE_PATH . '/routes/web.php';

// ──────────────────────────────────────────────────────────
// Parse request and dispatch
// ──────────────────────────────────────────────────────────
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Strip query string
if (($qPos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $qPos);
}

// Strip base path for subfolder deployment so routes match /login not /student-management-lms/public/login
$basePath = BASE_URL . '/public';
$basePaths = [$basePath, BASE_URL ?: '/'];
foreach ($basePaths as $bp) {
    if ($bp !== '' && $bp !== '/' && str_starts_with($requestUri, $bp)) {
        $requestUri = substr($requestUri, strlen($bp));
        break;
    }
}
if ($requestUri === '' || $requestUri === false) {
    $requestUri = '/';
}

// Match the route
$match = $router->match($requestMethod, $requestUri);

if ($match === null) {
    http_response_code(404);

    if (file_exists(BASE_PATH . '/app/Views/errors/404.php')) {
        require BASE_PATH . '/app/Views/errors/404.php';
    } else {
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>The page you are looking for does not exist.</p>';
    }
    exit;
}

// ──────────────────────────────────────────────────────────
// Execute middleware
// ──────────────────────────────────────────────────────────
foreach ($match['middleware'] as $middleware) {
    match ($middleware) {
        'auth' => \App\Middleware\AuthMiddleware::requireAuth(),
        'guest' => \App\Middleware\AuthMiddleware::requireGuest(),
        'super_admin' => \App\Middleware\AuthMiddleware::requireSuperAdmin(),
        'admin' => \App\Middleware\AuthMiddleware::requireAdmin(),
        'coordinator' => \App\Middleware\AuthMiddleware::requireCoordinator(),
        'staff' => \App\Middleware\AuthMiddleware::requireStaff(),
        'teacher' => \App\Middleware\AuthMiddleware::requireTeacher(),
        'csrf' => verifyCsrfToken(),
        default => null,
    };
}

// ──────────────────────────────────────────────────────────
// Execute the handler
// ──────────────────────────────────────────────────────────
$handler = $match['handler'];
$params = $match['params'];

if (is_array($handler)) {
    [$controllerClass, $method] = $handler;

    if (!class_exists($controllerClass)) {
        throw new RuntimeException("Controller class {$controllerClass} not found.");
    }

    $controller = new $controllerClass();

    if (!method_exists($controller, $method)) {
        throw new RuntimeException("Method {$method} not found in {$controllerClass}.");
    }

    // Call controller method with route parameters
    call_user_func_array([$controller, $method], $params);
} elseif (is_callable($handler)) {
    call_user_func_array($handler, $params);
} else {
    throw new RuntimeException('Invalid route handler.');
}
