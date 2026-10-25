<?php

/**
 * Create the first super admin on a new server.
 *
 *   php bin/create_admin.php --name="Full Name" [--email=someone@example.com]
 *
 * Prints a login ID and a temporary password ONCE on this screen; it is not
 * logged anywhere. The password must be changed at the first login.
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$name = trim((string) cli_option('name'));
$email = trim((string) cli_option('email'));
if ($name === '') {
    fwrite(STDERR, "Usage: php bin/create_admin.php --name=\"Full Name\" [--email=address]\n");
    exit(2);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "That email address is not valid.\n");
    exit(2);
}

$db = Database::connect();
$year = date('Y');
$last = $db->query("SELECT user_id_display FROM users WHERE user_id_display LIKE 'SA{$year}%' ORDER BY user_id_display DESC LIMIT 1")->fetchColumn();
$seq = $last ? ((int) substr((string) $last, 6)) + 1 : 1;
$loginId = sprintf('SA%s%04d', $year, $seq);
$password = App\Services\AccountService::temporaryPassword();

$db->prepare(
    "INSERT INTO users (user_id_display, role, full_name, email, password_hash, status, created_at, updated_at)
     VALUES (:id, 'super_admin', :n, :e, :h, 'pending_first_login', NOW(), NOW())"
)->execute([':id' => $loginId, ':n' => $name, ':e' => $email !== '' ? $email : null, ':h' => password_hash($password, PASSWORD_DEFAULT)]);

echo "Super admin created.\n";
echo "  Login ID:           $loginId\n";
echo "  Temporary password: $password\n";
echo "Write these down now. They are not shown again; the password must be changed at the first login.\n";

// Migration 001 seeds a built-in super admin with a default password.
$defaults = 0;
foreach ($db->query("SELECT password_hash FROM users WHERE status <> 'inactive'")->fetchAll(PDO::FETCH_COLUMN) as $hash) {
    if (App\Services\AccountService::hashIsKnownDefault((string) $hash)) {
        $defaults++;
    }
}
if ($defaults > 0) {
    echo "\nWARNING: $defaults staff account(s) still use a default password (the built-in account from the first migration counts).\n";
    echo "Run:  php bin/accounts_audit.php --disable --yes\n";
}
