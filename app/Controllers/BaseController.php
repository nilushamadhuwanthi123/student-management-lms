<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use PDO;

class BaseController
{
    protected ?PDO $db;

    public function __construct()
    {
        // Use Database::connect() as the primary connection provider.
        // Falls back to global $pdo for backward compatibility.
        try {
            $this->db = \Database::connect();
        } catch (\Throwable $e) {
            global $pdo;
            $this->db = $pdo ?? null;
        }
    }

    /**
     * Render a view template with an optional layout.
     *
     * @param string $template  Path relative to resources/views/ (e.g. "students/index")
     * @param array<string, mixed> $data  Variables to extract into the template scope
     * @param string|null $layout  Layout file (null for default "layouts/app")
     */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        $viewsPath = BASE_PATH . '/app/Views';
        $templateFile = $viewsPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("View template not found: {$template}");
        }

        // Make data available as variables
        extract($data);

        // Also provide common variables
        $currentUser = AuthMiddleware::getCurrentUser();
        $flashSuccess = session_get_flash('success');
        $flashError = session_get_flash('error');
        $flashWarning = session_get_flash('warning');
        $flashInfo = session_get_flash('info');

        if ($layout !== null) {
            $layoutFile = $viewsPath . '/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout file not found: {$layout}");
            }

            // Capture the template output as $content for the layout
            ob_start();
            require $templateFile;
            $content = ob_get_clean();

            require $layoutFile;
        } else {
            require $templateFile;
        }
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): never
    {
        header('Location: ' . $this->resolveUrl($url));
        exit;
    }

    /**
     * Make a redirect target subfolder-safe.
     *
     * Root-relative paths ('/dashboard') are prefixed with BASE_URL so they stay
     * inside the app when it is deployed under a subfolder. Absolute URLs and
     * paths already carrying BASE_URL are returned untouched.
     */
    protected function resolveUrl(string $url): string
    {
        if ($url === '' || preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';

        if ($base !== '' && ($url === $base || str_starts_with($url, $base . '/'))) {
            return $url;
        }

        return $url[0] === '/' ? $base . $url : $url;
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data
     */
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect back to the referring page.
     */
    protected function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/dashboard');
        header('Location: ' . $referer);
        exit;
    }

    /**
     * Set a flash message for the next request.
     */
    protected function flash(string $type, string $message): void
    {
        session_flash($type, $message);
    }

    /**
     * Validate input data against rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $rules  e.g. ['name' => 'required|max:255', 'email' => 'required|email']
     * @return array<string, string>  Validation errors (empty if valid)
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $displayField = str_replace('_', ' ', $field);

            foreach ($fieldRules as $rule) {
                $params = [];

                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = match ($rule) {
                    'required' => $this->validateRequired($value, $displayField),
                    'email' => $this->validateEmail($value, $displayField),
                    'min' => $this->validateMin($value, $displayField, (int) ($params[0] ?? 0)),
                    'max' => $this->validateMax($value, $displayField, (int) ($params[0] ?? 255)),
                    'numeric' => $this->validateNumeric($value, $displayField),
                    'integer' => $this->validateInteger($value, $displayField),
                    'in' => $this->validateIn($value, $displayField, $params),
                    'confirmed' => $this->validateConfirmed($data, $field, $displayField),
                    'date' => $this->validateDate($value, $displayField),
                    default => null,
                };

                if ($error !== null && !isset($errors[$field])) {
                    $errors[$field] = $error;
                    break; // Stop on first error for this field
                }
            }
        }

        return $errors;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return array<string, mixed>|null
     */
    protected function getCurrentUser(): ?array
    {
        return AuthMiddleware::getCurrentUser();
    }

    // ──────────────────────────────────────────────────────
    // Validation helpers
    // ──────────────────────────────────────────────────────

    private function validateRequired(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return "The {$field} field is required.";
        }
        return null;
    }

    private function validateEmail(mixed $value, string $field): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} must be a valid email address.";
        }
        return null;
    }

    private function validateMin(mixed $value, string $field, int $min): ?string
    {
        if (is_string($value) && mb_strlen($value) < $min) {
            return "The {$field} must be at least {$min} characters.";
        }
        return null;
    }

    private function validateMax(mixed $value, string $field, int $max): ?string
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            return "The {$field} must not exceed {$max} characters.";
        }
        return null;
    }

    private function validateNumeric(mixed $value, string $field): ?string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return "The {$field} must be a number.";
        }
        return null;
    }

    private function validateInteger(mixed $value, string $field): ?string
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            return "The {$field} must be an integer.";
        }
        return null;
    }

    /**
     * @param array<string> $allowed
     */
    private function validateIn(mixed $value, string $field, array $allowed): ?string
    {
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            return "The {$field} must be one of: " . implode(', ', $allowed) . ".";
        }
        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateConfirmed(array $data, string $field, string $displayField): ?string
    {
        $confirmation = $data[$field . '_confirmation'] ?? null;
        if (($data[$field] ?? null) !== $confirmation) {
            return "The {$displayField} confirmation does not match.";
        }
        return null;
    }

    private function validateDate(mixed $value, string $field): ?string
    {
        if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
            return "The {$field} must be a valid date.";
        }
        return null;
    }

    /**
     * Escape a string for safe HTML output.
     */
    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
