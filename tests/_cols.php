<?php
declare(strict_types=1);
require __DIR__ . '/_crawl_boot.php';
$db = crawl_db();
foreach (['users','students','parents','user_institutes','institutes'] as $t) {
    echo "$t: ";
    try {
        $c = [];
        foreach ($db->query("SHOW COLUMNS FROM $t") as $r) { $c[] = $r['Field']; }
        echo implode(', ', $c), "\n\n";
    } catch (Throwable $e) { echo "n/a\n\n"; }
}
