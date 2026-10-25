<?php

/**
 * Find accounts that still use a demo or old default password
 * (Demo@12345, Password@123), and optionally switch them off.
 *
 *   php bin/accounts_audit.php                  report only (changes nothing)
 *   php bin/accounts_audit.php --disable --yes  deactivate every one found
 *
 * Deactivated people get access back through the branch office: the office
 * confirms who they are, reactivates the account and issues a reset code.
 * Passwords are never printed.
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use App\Services\AccountService;

$db = Database::connect();
$disable = cli_flag('disable');
if ($disable && !cli_flag('yes')) {
    fwrite(STDERR, "Add --yes to confirm deactivation (run without --disable first to see the list).\n");
    exit(2);
}

$sets = [
    'user'    => ["SELECT id, user_id_display AS login, role AS kind, status, password_hash FROM users WHERE status <> 'inactive'", "UPDATE users SET status = 'inactive', credential_version = credential_version + 1 WHERE id = :id"],
    'student' => ["SELECT id, student_id_display AS login, 'student' AS kind, status, password_hash FROM students WHERE status <> 'deactivated'", "UPDATE students SET status = 'deactivated', credential_version = credential_version + 1 WHERE id = :id"],
    'parent'  => ["SELECT id, phone1 AS login, 'parent' AS kind, status, password_hash FROM parents WHERE status <> 'deactivated'", "UPDATE parents SET status = 'deactivated', credential_version = credential_version + 1 WHERE id = :id"],
];

$found = 0;
$unusable = 0;
foreach ($sets as $type => [$select, $update]) {
    $rows = $db->query($select)->fetchAll(PDO::FETCH_ASSOC);
    $hits = [];
    foreach ($rows as $r) {
        if ((string) $r['password_hash'] === '') {
            $unusable++;
            continue;
        }
        if (AccountService::hashIsKnownDefault((string) $r['password_hash'])) {
            $hits[] = $r;
        }
    }
    echo sprintf("%-8s %4d checked, %4d with a default password\n", $type, count($rows), count($hits));
    foreach ($hits as $h) {
        echo "         - {$h['login']} ({$h['kind']}, {$h['status']})\n";
        if ($disable) {
            $db->prepare($update)->execute([':id' => $h['id']]);
        }
    }
    $found += count($hits);
}

if ($unusable > 0) {
    echo "$unusable account(s) have no password at all and cannot log in; issue them a reset code.\n";
}
echo $disable ? "Deactivated $found account(s).\n" : "$found account(s) found. Nothing was changed.\n";
exit($found > 0 && !$disable ? 3 : 0);
