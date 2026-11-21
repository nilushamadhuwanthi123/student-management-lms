<?php

declare(strict_types=1);

/**
 * Will this survive being put on the real server?
 *
 * Everything here is a thing that works perfectly on a laptop running
 * XAMPP and then fails, quietly or loudly, on shared hosting. Each
 * check says what it found and why it matters, so the answer is
 * actionable rather than a red cross.
 *
 *   php tests/deploy_check.php
 *
 * Exit code is the number of blocking problems (warnings do not count).
 */

require __DIR__ . '/../bin/_bootstrap.php';

$blockers = [];
$warnings = [];
$passed   = [];

function ok(string $m): void    { global $passed;   $passed[] = $m; }
function warn(string $m): void  { global $warnings; $warnings[] = $m; }
function block(string $m): void { global $blockers; $blockers[] = $m; }

// ---- 1. Debug output -------------------------------------------------
$debug = env('APP_DEBUG', 'false');
if ($debug === 'true') {
    block('APP_DEBUG is true. PHP errors, file paths and SQL would be printed to visitors. Set APP_DEBUG=false in the server .env.');
} else {
    ok('APP_DEBUG is off, so errors go to the log and not to the page.');
}

if (env('APP_ENV', '') === 'development') {
    warn('APP_ENV is still "development". Nothing depends on it today, but set it to "production" on the server so a later feature cannot switch on debug behaviour by itself.');
} else {
    ok('APP_ENV is not "development".');
}

// ---- 2. The site address --------------------------------------------
$appUrl = (string) env('APP_URL', '');
if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
    warn('APP_URL still points at localhost (' . ($appUrl ?: 'empty') . '). Links inside emails and SMS are built from it, so a password-reset link would point at the office laptop. Set it to the real address on the server.');
} else {
    ok('APP_URL points at a real address: ' . $appUrl);
}

// ---- 3. Secrets ------------------------------------------------------
$gitignore = @file_get_contents(BASE_PATH . '/.gitignore') ?: '';
if (!preg_match('/^\s*\.env\s*$/m', $gitignore)) {
    block('.env is not in .gitignore, so database passwords could be committed.');
} else {
    ok('.env is git-ignored, so the database password stays off GitHub.');
}

if (env('DB_PASS', '') === '') {
    warn('The database password is empty. Normal on XAMPP; on the server it must be a real password.');
}

// ---- 4. Uploads must never execute -----------------------------------
$uploadHt = BASE_PATH . '/public/uploads/.htaccess';
if (!is_file($uploadHt)) {
    block('public/uploads/.htaccess is missing. Anything uploaded could be run as code. This file must be copied to the server.');
} else {
    $ht = (string) file_get_contents($uploadHt);
    if (stripos($ht, 'php') === false) {
        block('public/uploads/.htaccess exists but does not block PHP.');
    } else {
        ok('Uploads folder is set to refuse code, not just unusual file names.');
    }
}

if (!is_file(BASE_PATH . '/.htaccess')) {
    block('The root .htaccess is missing. Without it every request 404s and config files are reachable.');
} else {
    ok('Root .htaccess is present.');
}

// ---- 5. Writable folders --------------------------------------------
foreach (['public/uploads', 'storage'] as $rel) {
    $dir = BASE_PATH . '/' . $rel;
    if (!is_dir($dir)) {
        warn("Folder {$rel} does not exist yet; it is created on first use, but check the server allows it.");
    } elseif (!is_writable($dir)) {
        block("Folder {$rel} is not writable. Uploads and logs will fail silently.");
    } else {
        ok("Folder {$rel} is writable.");
    }
}

// ---- 6. PHP extensions ----------------------------------------------
// gd is deliberately not in this list: nothing here draws or resizes an
// image. The one image inspection the app does, getimagesize(), is core
// PHP. Demanding gd would fail a host that is in fact fine.
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'] as $ext) {
    if (!extension_loaded($ext)) {
        $why = $ext === 'fileinfo'
            ? 'Uploaded images are checked by reading the file, not by trusting its name. Without this the check cannot run.'
            : 'Required by the application.';
        block("PHP extension {$ext} is not loaded. " . $why);
    }
}
if ($blockers === [] || !str_contains(implode(' ', $blockers), 'extension')) {
    ok('All required PHP extensions are loaded.');
}

if (version_compare(PHP_VERSION, '8.1', '<')) {
    block('PHP ' . PHP_VERSION . ' is too old; this application needs 8.1 or newer.');
} else {
    ok('PHP ' . PHP_VERSION . ' is new enough.');
}

// ---- 7. Database schema up to date ----------------------------------
try {
    $db = Database::connect();
    $applied = $db->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $files = array_map('basename', glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
    $pending = array_values(array_diff($files, $applied));
    if ($pending !== []) {
        block('Migrations not applied yet: ' . implode(', ', $pending) . '. Run php bin/migrate.php on the server.');
    } else {
        ok('All ' . count($files) . ' migrations are applied.');
    }
} catch (Throwable $e) {
    block('Cannot read schema_migrations: ' . $e->getMessage());
}

// ---- 8. Accounts that must not ship ----------------------------------
try {
    $rows = $db->query(
        "SELECT user_id_display, role, full_name FROM users
          WHERE status = 'active'
            AND (full_name LIKE '%TEST%' OR full_name LIKE '%QA %' OR full_name LIKE '%demo%')"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows !== []) {
        $names = array_map(static fn ($r) => $r['user_id_display'] . ' (' . $r['role'] . ')', $rows);
        warn('Active test logins still exist: ' . implode(', ', $names)
            . '. Useful for demonstrating the system; deactivate them once the handover demo is done, because they are real logins.');
    } else {
        ok('No active test logins.');
    }

    $stu = $db->query(
        "SELECT student_id_display, full_name FROM students
          WHERE status = 'active' AND (full_name LIKE '%test%' OR full_name LIKE '%demo%')"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($stu !== []) {
        warn('Test students still active: ' . implode(', ', array_map(static fn ($r) => $r['student_id_display'], $stu)) . '.');
    }
} catch (Throwable $e) {
    warn('Could not check for test accounts: ' . $e->getMessage());
}

// ---- 9. Browser features the site leans on ---------------------------
$cssFiles = array_merge(
    glob(BASE_PATH . '/app/Views/**/*.php') ?: [],
    glob(BASE_PATH . '/public/assets/css/*.css') ?: [],
    glob(BASE_PATH . '/app/Services/*.php') ?: []
);
$modern = 0;
foreach ($cssFiles as $f) {
    $src = (string) file_get_contents($f);
    // Comments explaining why it is banned must not count as uses, or
    // the check reports a problem it created itself.
    foreach (explode("\n", $src) as $lineText) {
        if (preg_match('#^\s*(//|\*|/\*|\#)#', $lineText) === 1) {
            continue;
        }
        $modern += preg_match_all('/color-mix\s*\(/', $lineText);
    }
}
if ($modern > 0) {
    warn("color-mix() is used {$modern} time(s). It needs a 2023 browser; an older phone drops the whole declaration, so whatever it coloured disappears. Use rgba(var(--x-rgb), a) instead.");
} else {
    ok('No color-mix(): the colours work on older phones too.');
}

// ---- 10. Nothing left pointing at the laptop -------------------------
$hard = [];
foreach (array_merge(glob(BASE_PATH . '/app/Views/**/*.php') ?: [], glob(BASE_PATH . '/app/**/*.php') ?: []) as $f) {
    $src = (string) file_get_contents($f);
    foreach (explode("\n", $src) as $lineText) {
        if (preg_match('#https?://(localhost|127\.0\.0\.1)#', $lineText) !== 1) {
            continue;
        }
        // An example inside a placeholder, or a line of explanation, is
        // not an address the site will ever request. Flagging those
        // trains people to ignore this check.
        if (preg_match('#placeholder\s*=#i', $lineText) === 1
            || preg_match('#^\s*(//|\*|/\*|#)#', $lineText) === 1) {
            continue;
        }
        $hard[] = ltrim(str_replace(BASE_PATH, '', $f), '/\\');
        break;
    }
}
if ($hard !== []) {
    block('These files contain a hard-coded localhost address, which breaks on the server: ' . implode(', ', array_unique($hard)));
} else {
    ok('No hard-coded localhost addresses in the application code.');
}

// ---- report ----------------------------------------------------------
$line = str_repeat('=', 68);
echo "Deployment readiness\n{$line}\n\n";

printf("  PASSED   : %d\n  WARNINGS : %d\n  BLOCKERS : %d\n\n", count($passed), count($warnings), count($blockers));

if ($blockers !== []) {
    echo "BLOCKERS -- fix before the site goes up\n" . str_repeat('-', 68) . "\n";
    foreach ($blockers as $b) { echo "  [X] {$b}\n\n"; }
}
if ($warnings !== []) {
    echo "WARNINGS -- check, but they will not take the site down\n" . str_repeat('-', 68) . "\n";
    foreach ($warnings as $w) { echo "  [!] {$w}\n\n"; }
}
echo "PASSED\n" . str_repeat('-', 68) . "\n";
foreach ($passed as $p) { echo "  [OK] {$p}\n"; }

echo "\n{$line}\n";
exit(min(250, count($blockers)));
