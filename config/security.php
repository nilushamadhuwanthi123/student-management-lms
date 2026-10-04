<?php
declare(strict_types=1);

/**
 * Security Configuration
 * Defines security-related constants and settings.
 */

// Session timeout in seconds (default 30 min)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', '1800'));
}

// Max login attempts before lockout
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', '5'));
}

// Login lockout duration in minutes
if (!defined('LOGIN_LOCKOUT_MINUTES')) {
    define('LOGIN_LOCKOUT_MINUTES', (int) env('LOGIN_LOCKOUT_MINUTES', '15'));
}

// Remember me token lifetime in days
if (!defined('REMEMBER_TOKEN_DAYS')) {
    define('REMEMBER_TOKEN_DAYS', (int) env('REMEMBER_TOKEN_DAYS', '30'));
}

// Minimum password length
if (!defined('MIN_PASSWORD_LENGTH')) {
    define('MIN_PASSWORD_LENGTH', (int) env('MIN_PASSWORD_LENGTH', '8'));
}
