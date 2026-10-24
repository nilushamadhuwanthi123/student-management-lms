<?php

/**
 * The reminder job.
 *
 * Run from Task Scheduler every ten minutes:
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\student-management-lms\bin\send_reminders.php
 *
 * Running it more often than that is harmless -- the unique key on
 * reminder_dispatches means a second run inside the same minute sends
 * nothing -- and running it less often is survivable too, because each
 * lead reminds about everything between now and the horizon rather than
 * a narrow slice, so a missed hour is caught up rather than lost.
 *
 * It is deliberately a CLI script and not a hook on page load. Tying
 * reminders to someone visiting the site means the quietest night is
 * also the night nobody gets told about tomorrow's test.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/CalendarService.php';
require_once BASE_PATH . '/app/Services/ReminderService.php';

$startedAt = microtime(true);

try {
    $counts = (new App\Services\ReminderService())->dispatchDue();
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] reminder job failed: {$e->getMessage()}\n");
    exit(1);
}

printf(
    "[%s] %d occurrences due, %d reminders sent, %d already out, %d with nobody to tell (%.2fs)\n",
    date('Y-m-d H:i:s'),
    $counts['occurrences'],
    $counts['sent'],
    $counts['already_sent'],
    $counts['no_recipients'],
    microtime(true) - $startedAt
);

exit(0);
