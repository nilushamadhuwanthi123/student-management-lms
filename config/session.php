<?php

declare(strict_types=1);

/**
 * Session Configuration and Initialization
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }

    $sessionTimeout = (int) (defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800);
    ini_set('session.gc_maxlifetime', (string) $sessionTimeout);
    ini_set('session.cookie_lifetime', '0');   // Until browser closes

    // Scope the session cookie to the application's base path
    // so it isn't shared with other apps on the same host.
    $cookiePath = '/';
    if (defined('BASE_URL') && BASE_URL !== '') {
        $cookiePath = BASE_URL . '/';
    }
    ini_set('session.cookie_path', $cookiePath);

    session_name('STUDENT_MGMT_SESSID');
    session_start();
}

/**
 * Get a value from the session.
 */
function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Set a value in the session.
 */
function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Check if a key exists in the session.
 */
function session_has(string $key): bool
{
    return isset($_SESSION[$key]);
}

/**
 * Set a flash message (available for the next request only).
 */
function session_flash(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

/**
 * Get and remove a flash message.
 */
function session_get_flash(string $key, mixed $default = null): mixed
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/**
 * Check if a flash message exists.
 */
function session_has_flash(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

/**
 * Destroy the session completely.
 */
function session_destroy_all(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Regenerate the session ID (e.g. after login).
 */
function session_regenerate(): void
{
    session_regenerate_id(true);
}
