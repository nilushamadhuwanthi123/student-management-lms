<?php

declare(strict_types=1);

/**
 * CSRF Token Management
 *
 * Uses a per-session token with time-based expiry (not single-use),
 * so multi-tab usage works correctly.
 */

/** Token lifetime in seconds (default: 1 hour). */
define('CSRF_TOKEN_TTL', (int) env('CSRF_TOKEN_LIFETIME', '3600'));

/**
 * Generate a CSRF token and store it in the session with a timestamp.
 */
function generateCsrfToken(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['_csrf_token'] = $token;
    $_SESSION['_csrf_token_time'] = time();
    return $token;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist or has expired.
 */
function getCsrfToken(): string
{
    $token = $_SESSION['_csrf_token'] ?? null;
    $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;

    // Regenerate if missing or expired
    if ($token === null || (time() - $tokenTime) > CSRF_TOKEN_TTL) {
        return generateCsrfToken();
    }

    return $token;
}

/**
 * Validate a CSRF token from a POST request.
 */
function validateCsrfToken(?string $token): bool
{
    if ($token === null || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;

    // Check if token has expired
    if ((time() - $tokenTime) > CSRF_TOKEN_TTL) {
        // Regenerate for next request
        generateCsrfToken();
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $token);
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
            exit('Invalid or expired CSRF token. Please refresh the page and try again.');
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
