<?php

/**
 * Announces scheduled notices once their publish time arrives.
 *
 * Run from Task Scheduler (or cron) every ten minutes, next to
 * send_reminders.php:
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\student-management-lms\bin\publish_notices.php
 *
 * Re-running is harmless: each notice records when it was announced, and
 * that record is claimed before anyone is told.
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
require_once BASE_PATH . '/app/Services/AnnouncementService.php';
require_once BASE_PATH . '/app/Services/NoticeService.php';

try {
    $sent = (new App\Services\NoticeService())->announceDue();
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] notice job failed: {$e->getMessage()}\n");
    exit(1);
}

printf("[%s] %d notifications sent for due notices\n", date('Y-m-d H:i:s'), $sent);
exit(0);
