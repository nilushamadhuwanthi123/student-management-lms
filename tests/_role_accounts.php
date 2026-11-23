<?php
/**
 * Inventory: one usable login per role, for the role crawler.
 *
 * CLI only. Reports what exists; changes nothing.
 */
declare(strict_types=1);

require __DIR__ . '/_crawl_boot.php';
$db = crawl_db();

echo "users table\n";
foreach ($db->query("SELECT role, status, COUNT(*) n FROM users GROUP BY role, status ORDER BY role, status") as $r) {
    printf("  %-14s %-12s %d\n", $r['role'], $r['status'], $r['n']);
}

echo "\none active account per role\n";
$stmt = $db->query(
    "SELECT u.role, u.id, u.user_id_display, u.credential_version,
            (SELECT GROUP_CONCAT(ui.institute_id) FROM user_institutes ui WHERE ui.user_id = u.id) AS insts
       FROM users u WHERE u.status = 'active' ORDER BY u.role, u.id"
);
$seen = [];
foreach ($stmt as $r) {
    if (isset($seen[$r['role']])) { continue; }
    $seen[$r['role']] = true;
    printf("  %-14s id=%-4d %-14s inst=%-5s cv=%s\n",
        $r['role'], $r['id'], (string) $r['user_id_display'],
        (string) ($r['insts'] ?? '-'), (string) $r['credential_version']);
}

foreach (['students' => 'student_id_display', 'parents' => 'phone1'] as $tbl => $col) {
    try {
        $r = $db->query("SELECT id, $col AS disp, status FROM $tbl WHERE status = 'active' LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC);
        printf("  %-14s %s\n", rtrim($tbl, 's'), $r ? "id={$r['id']} {$r['disp']}" : 'none active');
    } catch (Throwable $e) {
        printf("  %-14s could not read: %s\n", rtrim($tbl, 's'), $e->getMessage());
    }
}
