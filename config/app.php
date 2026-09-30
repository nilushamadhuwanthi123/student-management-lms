<?php
/**
 * Application Configuration
 * Loads .env and provides global config access.
 */

// Load .env file
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key]           = $value;
            $_SERVER[$key]        = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Colombo'));

return [
    'name'     => env('APP_NAME', 'Student Management System'),
    'url'      => env('APP_URL', 'http://localhost/student-management-lms'),
    'env'      => env('APP_ENV', 'development'),
    'debug'    => env('APP_DEBUG', 'true') === 'true',
    'timezone' => env('APP_TIMEZONE', 'Asia/Colombo'),
    'currency' => env('APP_CURRENCY', 'LKR'),
];
