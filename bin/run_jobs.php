<?php

/**
 * Every timed job in one run: scheduled notices whose time has come,
 * calendar reminders (tests, quizzes, extra classes), and any published
 * seminar not yet announced.
 *
 * Schedule this ONE script every ten minutes -- Windows Task Scheduler on
 * the office computer, or cron on a web host:
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\student-management-lms\bin\run_jobs.php
 *   0,10,20,30,40,50 * * * * php /path/to/site/bin/run_jobs.php
 *
 * Every job records what it has sent before telling anyone, so running it
 * twice, or late, never sends anything twice. One job failing does not
 * stop the others.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
if (is_file(BASE_PATH . '/config/lang.php')) {
    require_once BASE_PATH . '/config/lang.php';
}

$jobs = [
    'notices'   => static fn (): string => (new App\Services\NoticeService())->announceDue() . ' notice alerts',
    'reminders' => static function (): string {
        $c = (new App\Services\ReminderService())->dispatchDue();
        return ($c['sent'] ?? 0) . ' reminders';
    },
    'seminars'  => static fn (): string => (new App\Services\SeminarService())->announceDue() . ' seminar alerts',
];

$failed = 0;
foreach ($jobs as $name => $job) {
    try {
        printf("[%s] %-9s %s\n", date('Y-m-d H:i:s'), $name, $job());
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] {$name} failed: {$e->getMessage()}\n");
    }
}
exit($failed > 0 ? 1 : 0);
