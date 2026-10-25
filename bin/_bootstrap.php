<?php

declare(strict_types=1);

/** Shared start-up for the command-line tools in bin/. Refuses web requests. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
if (is_file(BASE_PATH . '/config/lang.php')) {
    require_once BASE_PATH . '/config/lang.php';
}
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

function cli_flag(string $name): bool
{
    global $argv;
    return in_array('--' . $name, $argv ?? [], true);
}

function cli_option(string $name): ?string
{
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}
