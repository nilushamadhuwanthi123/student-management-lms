<?php

declare(strict_types=1);

/**
 * CSRF Token Management
 */

/**
 * Generate a CSRF token and store it in the session.
 */
function generateCsrfToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['_csrf_token'] = $token;
    return $token;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist.
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        return generateCsrfToken();
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Validate a CSRF token from a POST request.
 */
function validateCsrfToken(?string $token): bool
{
    if ($token === null || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    $valid = hash_equals($_SESSION['_csrf_token'], $token);

    // Regenerate token after validation to prevent reuse
    if ($valid) {
        generateCsrfToken();
    }

    return $valid;
}

/**
 * Validate the CSRF token from the current POST request.
 * Terminates with 403 if invalid.
 */
function verifyCsrfToken(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!validateCsrfToken($token)) {
            http_response_code(403);
            exit('Invalid CSRF token. Please refresh the page and try again.');
        }
    }
}

/**
 * Return a hidden input field with the CSRF token.
 */
function csrfField(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

/**
 * Return a meta tag with the CSRF token (for AJAX requests).
 */
function csrfMeta(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">';
}
