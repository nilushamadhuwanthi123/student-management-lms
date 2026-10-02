<?php

declare(strict_types=1);

/**
 * Main Entry Point / Front Controller
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// ──────────────────────────────────────────────────────────
// Autoloader for app/ classes
// ──────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    // Convert namespace to file path: App\Controllers\Foo => app/Controllers/Foo.php
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
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/csrf.php';
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
