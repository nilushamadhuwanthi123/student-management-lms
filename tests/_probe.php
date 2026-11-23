<?php
declare(strict_types=1);
require __DIR__ . '/_crawl_boot.php';
$db = crawl_db();
$base = crawl_base_url();
$role = $argv[1] ?? 'staff';
$needles = array_slice($argv, 2);

$stmt = $db->prepare("SELECT id, user_id_display FROM users WHERE role = :r AND status='active' ORDER BY id LIMIT 1");
$stmt->execute([':r' => $role]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
$old = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
$old->execute([':id' => $u['id']]);
$orig = (string) $old->fetchColumn();
$pw = 'ProbeOnce!2026';
$db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
   ->execute([':h' => password_hash($pw, PASSWORD_DEFAULT), ':id' => $u['id']]);

$jar = sys_get_temp_dir() . '/probe.cookies'; @unlink($jar);
$fetch = function (string $url, ?array $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_USERAGENT=>'role-crawl/1.0']);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = (string) curl_exec($ch); $uu = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); curl_close($ch);
    return [$b, $uu];
};
try {
    [$lb] = $fetch($base . '/login');
    preg_match('/name="_csrf_token" value="([^"]+)"/', $lb, $m);
    [$body, $url] = $fetch($base . '/login', ['_csrf_token'=>$m[1]??'', 'user_id'=>$u['user_id_display'], 'password'=>$pw]);
    echo "landed: $url\n";
    foreach ($needles as $n) {
        $hits = [];
        foreach (explode("\n", $body) as $i => $line) {
            if (str_contains($line, $n)) { $hits[] = ($i + 1) . ': ' . trim(substr($line, 0, 170)); }
        }
        printf("\n-- %s (%d hit%s)\n", $n, count($hits), count($hits) === 1 ? '' : 's');
        foreach (array_slice($hits, 0, 6) as $h) { echo "   $h\n"; }
    }
} finally {
    $db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")->execute([':h'=>$orig, ':id'=>$u['id']]);
    echo "\nhash restored\n";
}
