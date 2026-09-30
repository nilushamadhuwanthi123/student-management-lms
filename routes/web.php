<?php

declare(strict_types=1);

/**
 * Router class and route definitions.
 */

class Router
{
    /** @var array<array{method: string, pattern: string, handler: callable|array, middleware: array<string>}> */
    private array $routes = [];

    /** @var array<string> Current middleware group */
    private array $currentMiddleware = [];

    /** @var string Current route prefix */
    private string $currentPrefix = '';

    /**
     * Register a GET route.
     */
    public function get(string $uri, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $handler, $middleware);
    }

    /**
     * Group routes with shared middleware and/or prefix.
     *
     * @param array{middleware?: array<string>, prefix?: string} $options
     */
    public function group(array $options, callable $callback): self
    {
        $previousMiddleware = $this->currentMiddleware;
        $previousPrefix = $this->currentPrefix;

        if (isset($options['middleware'])) {
            $this->currentMiddleware = array_merge(
                $this->currentMiddleware,
                (array) $options['middleware']
            );
        }

        if (isset($options['prefix'])) {
            $this->currentPrefix .= '/' . trim($options['prefix'], '/');
        }

        $callback($this);

        $this->currentMiddleware = $previousMiddleware;
        $this->currentPrefix = $previousPrefix;

        return $this;
    }

    /**
     * Add a route to the collection.
     */
    private function addRoute(string $method, string $uri, callable|array $handler, array $middleware = []): self
    {
        $fullUri = $this->currentPrefix . '/' . trim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');

        // Normalize root path
        if ($fullUri === '') {
            $fullUri = '/';
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullUri,
            'handler' => $handler,
            'middleware' => array_merge($this->currentMiddleware, $middleware),
        ];

        return $this;
    }

    /**
     * Match a request URI and method against registered routes.
     *
     * @return array{handler: callable|array, params: array<string, string>, middleware: array<string>}|null
     */
    public function match(string $method, string $uri): ?array
    {
        // Strip query string
        $uri = strtok($uri, '?') ?: '/';
        $uri = '/' . trim($uri, '/');

        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPattern($route['pattern'], $uri);

            if ($params !== null) {
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    /**
     * Match a route pattern against a URI and extract parameters.
     *
     * @return array<string, string>|null  Params on match, null on no match.
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Convert {param} to named regex groups
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Extract only named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return null;
    }

    /**
     * Get all registered routes (for debugging).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

// ──────────────────────────────────────────────────────────
// Route Definitions
// ──────────────────────────────────────────────────────────

$router = new Router();

// Public routes
$router->get('/', ['App\Controllers\HomeController', 'index']);
$router->get('/about', ['App\Controllers\HomeController', 'about']);
$router->get('/features', ['App\Controllers\HomeController', 'features']);
$router->get('/contact', ['App\Controllers\HomeController', 'contact']);
$router->post('/contact', ['App\Controllers\HomeController', 'contactSubmit']);

// Authentication routes
$router->get('/login', ['App\Controllers\AuthController', 'showLogin']);
$router->post('/login', ['App\Controllers\AuthController', 'login']);
$router->get('/logout', ['App\Controllers\AuthController', 'logout']);
$router->get('/first-login-password', ['App\Controllers\AuthController', 'showFirstLoginPassword']);
$router->post('/first-login-password', ['App\Controllers\AuthController', 'updateFirstLoginPassword']);

// Authenticated routes
$router->group(['middleware' => ['auth']], function (Router $router) {

    // Dashboard
    $router->get('/dashboard', ['App\Controllers\DashboardController', 'index']);

    // Students
    $router->get('/students', ['App\Controllers\StudentController', 'index']);
    $router->get('/students/create', ['App\Controllers\StudentController', 'create']);
    $router->post('/students/create', ['App\Controllers\StudentController', 'store']);
    $router->get('/students/{id}', ['App\Controllers\StudentController', 'show']);
    $router->get('/students/{id}/edit', ['App\Controllers\StudentController', 'edit']);
    $router->post('/students/{id}/edit', ['App\Controllers\StudentController', 'update']);
    $router->post('/students/{id}/delete', ['App\Controllers\StudentController', 'delete']);

    // Payments
    $router->get('/payments', ['App\Controllers\PaymentController', 'index']);
    $router->get('/payments/{studentId}', ['App\Controllers\PaymentController', 'studentPayments']);
    $router->post('/payments/mark-paid', ['App\Controllers\PaymentController', 'markPaid']);

    // Workflows
    $router->get('/workflows', ['App\Controllers\WorkflowController', 'index']);
    $router->post('/workflows/{id}/approve', ['App\Controllers\WorkflowController', 'approve']);
    $router->post('/workflows/{id}/reject', ['App\Controllers\WorkflowController', 'reject']);

    // Audit & History
    $router->get('/audit-log', ['App\Controllers\AuditController', 'index']);
    $router->get('/workflow-history', ['App\Controllers\AuditController', 'workflowHistory']);

    // Settings
    $router->get('/settings', ['App\Controllers\SettingsController', 'index']);
    $router->post('/settings', ['App\Controllers\SettingsController', 'update']);
    $router->get('/settings/deactivation', ['App\Controllers\SettingsController', 'deactivation']);
    $router->post('/settings/deactivation', ['App\Controllers\SettingsController', 'updateDeactivation']);
    $router->get('/settings/deletion', ['App\Controllers\SettingsController', 'deletion']);
    $router->post('/settings/deletion', ['App\Controllers\SettingsController', 'updateDeletion']);
    $router->get('/settings/dashboard-items', ['App\Controllers\SettingsController', 'dashboardItems']);
    $router->post('/settings/dashboard-items', ['App\Controllers\SettingsController', 'updateDashboardItems']);

    // API-style endpoints
    $router->get('/api/classes-by-institute/{id}', ['App\Controllers\ApiController', 'classesByInstitute']);
});

return $router;
