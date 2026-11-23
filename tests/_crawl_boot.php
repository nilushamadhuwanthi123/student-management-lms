<?php
/**
 * Shared bootstrap for the crawl scripts: .env parsing and a PDO handle.
 *
 * Deliberately standalone -- it must work without the app's own
 * bootstrap, so that a fault in the app cannot hide a fault in the app.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function crawl_env(): array
{
    static $env = null;
    if ($env !== null) { return $env; }
    $env = [];
    $path = dirname(__DIR__) . '/.env';
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) { continue; }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim(trim($v), "\"'");
        }
    }
    return $env;
}

function crawl_db(): PDO
{
    static $db = null;
    if ($db !== null) { return $db; }
    $e = crawl_env();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
        $e['DB_HOST'] ?? '127.0.0.1', $e['DB_DATABASE'] ?? $e['DB_NAME'] ?? 'student_lms');
    $db = new PDO($dsn, $e['DB_USERNAME'] ?? $e['DB_USER'] ?? 'root', $e['DB_PASSWORD'] ?? $e['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $db;
}

function crawl_base_url(): string
{
    $e = crawl_env();
    return rtrim($e['CRAWL_URL'] ?? 'http://localhost/student-management-lms', '/');
}
