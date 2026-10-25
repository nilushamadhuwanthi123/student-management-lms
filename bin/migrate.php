<?php

/**
 * Apply database migrations that have not been applied yet.
 *
 *   php bin/migrate.php --status      list applied and pending files
 *   php bin/migrate.php --dry-run     show what would run, change nothing
 *   php bin/migrate.php               apply pending files in number order
 *   php bin/migrate.php --baseline=012
 *        For a database that already has 001-012 applied by hand (an existing
 *        install): record them as applied WITHOUT running them. Do this once.
 *
 * Take a backup first (docs/BACKUP_AND_RESTORE.md). MariaDB/MySQL commit
 * schema changes immediately, so a failed file cannot be rolled back
 * automatically: the run stops at the first error and records nothing for
 * that file, and the backup is the way back.
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$db = Database::connect();
$db->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(190) NOT NULL PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$applied = [];
foreach ($db->query("SELECT filename, checksum FROM schema_migrations") as $row) {
    $applied[$row['filename']] = $row['checksum'];
}

$version = (string) $db->query('SELECT VERSION()')->fetchColumn();
echo "Database: " . env('DB_NAME', '?') . " on " . $version . "\n";

if (($upTo = cli_option('baseline')) !== null) {
    if (!preg_match('/^\d{3}$/', $upTo)) {
        fwrite(STDERR, "--baseline needs a three-digit number, e.g. --baseline=012\n");
        exit(2);
    }
    $stmt = $db->prepare("INSERT IGNORE INTO schema_migrations (filename, checksum) VALUES (:f, :c)");
    foreach ($files as $path) {
        $name = basename($path);
        if (strcmp(substr($name, 0, 3), $upTo) <= 0) {
            $stmt->execute([':f' => $name, ':c' => hash_file('sha256', $path)]);
            echo "  recorded as applied: $name\n";
        }
    }
    exit(0);
}

$pending = [];
foreach ($files as $path) {
    $name = basename($path);
    $sum = hash_file('sha256', $path);
    if (isset($applied[$name])) {
        if (cli_flag('status')) {
            echo "  applied  $name" . ($applied[$name] !== $sum ? "   (file changed since it was applied)" : '') . "\n";
        }
        continue;
    }
    $pending[] = [$name, $path, $sum];
    if (cli_flag('status') || cli_flag('dry-run')) {
        echo "  PENDING  $name\n";
    }
}

if (cli_flag('status') || cli_flag('dry-run')) {
    echo count($pending) . " pending.\n";
    exit(0);
}
if ($pending === []) {
    echo "Nothing to apply.\n";
    exit(0);
}

$record = $db->prepare("INSERT INTO schema_migrations (filename, checksum) VALUES (:f, :c)");
foreach ($pending as [$name, $path, $sum]) {
    echo "Applying $name ... ";
    $statements = split_sql((string) file_get_contents($path));
    try {
        foreach ($statements as $sql) {
            $db->exec($sql);
        }
    } catch (Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "  " . $e->getMessage() . "\n  Stopped. Nothing was recorded for $name. Fix the cause, or restore the backup.\n");
        exit(1);
    }
    $record->execute([':f' => $name, ':c' => $sum]);
    echo "ok (" . count($statements) . " statements)\n";
}
echo "Done.\n";
exit(0);

/**
 * Split a SQL file on semicolons that are not inside quotes or comments.
 *
 * @return list<string>
 */
function split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';
        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === '\\' && $quote !== '`') {
                $buf .= $next;
                $i++;
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end;
            $buf .= "\n";
            continue;
        }
        if ($ch === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $len : $end;
            $buf .= "\n";
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            $buf .= ' ';
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';') {
            if (trim($buf) !== '') {
                $out[] = trim($buf);
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}
