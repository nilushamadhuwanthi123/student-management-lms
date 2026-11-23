<?php

declare(strict_types=1);

/**
 * Crawl the whole site once as every role, and report any page that is
 * offered to a role and then refused to it.
 *
 * This exists because of a bug that no amount of reading found: a
 * teacher was shown her classes and then bounced off every teaching
 * page inside them. The link was on the page, the page redirected, and
 * the explanation flashed past in a message the next request ate. A
 * crawl that remembers which page a link came from is the only thing
 * that catches that shape of bug, so it is a test rather than a
 * one-off script.
 *
 * It logs nobody in. Instead it writes a session file for a real
 * account of each role and sends that cookie, which keeps it out of
 * the users table entirely -- no passwords read, written or reset.
 *
 * Usage:  php tests/role_crawl.php [--role=teacher] [--max=400] [--quiet]
 */

$root = dirname(__DIR__);
require $root . '/bin/_bootstrap.php';

// ---------------------------------------------------------------- options

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    }
}
$maxPages  = (int) ($opts['max'] ?? 400);
$onlyRole  = isset($opts['role']) ? (string) $opts['role'] : null;
$quiet     = isset($opts['quiet']);

$base = rtrim((string) ($opts['base'] ?? 'http://127.0.0.1/student-management-lms'), '/');
$ua   = 'LMS-role-crawl/1.0';

// ------------------------------------------------------------------- db

// The app's own connection, so the crawl cannot drift from the way the
// site itself reaches the database.
try {
    $db = Database::connect();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot reach the database, so the crawl cannot pick accounts: {$e->getMessage()}\n");
    exit(2);
}

/**
 * One usable account per role: active, and not sitting behind a
 * temporary password, because that traps every request at the password
 * form and the crawl would report the whole site as broken.
 */
function pickAccounts(PDO $db, ?string $onlyRole): array
{
    $picked = [];

    $roles = ['super_admin', 'admin', 'coordinator', 'staff', 'teacher'];
    foreach ($roles as $role) {
        $st = $db->prepare(
            "SELECT id, role, COALESCE(credential_version, 1) AS cv
               FROM users
              WHERE role = :r AND status = 'active'
              ORDER BY id LIMIT 1"
        );
        $st->execute([':r' => $role]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $picked[$role] = ['type' => 'user', 'id' => (int) $row['id'], 'role' => $role, 'cv' => (int) $row['cv']];
        }
    }

    $st = $db->query(
        "SELECT id, COALESCE(credential_version, 1) AS cv
           FROM students
          WHERE status = 'active' AND (must_change_password IS NULL OR must_change_password = 0)
          ORDER BY id LIMIT 1"
    );
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $picked['student'] = ['type' => 'student', 'id' => (int) $row['id'], 'role' => 'student', 'cv' => (int) $row['cv']];
    }

    try {
        $st = $db->query(
            "SELECT id, COALESCE(credential_version, 1) AS cv
               FROM parents
              WHERE status = 'active' AND (must_change_password IS NULL OR must_change_password = 0)
              ORDER BY id LIMIT 1"
        );
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $picked['parent'] = ['type' => 'parent', 'id' => (int) $row['id'], 'role' => 'parent', 'cv' => (int) $row['cv']];
        }
    } catch (Throwable) {
        // No parent logins on this install; reported as a gap, not a failure.
    }

    if ($onlyRole !== null) {
        $picked = array_intersect_key($picked, [$onlyRole => true]);
    }

    return $picked;
}

/**
 * Write a session file the web server will pick up, so the crawl needs
 * no password. session_fingerprint is deliberately left out: the
 * middleware only checks it when it is set.
 */
function forgeSession(array $acct, PDO $db): string
{
    $sid = 'crawl' . bin2hex(random_bytes(10));

    $name = 'User';
    if ($acct['type'] === 'user') {
        $st = $db->prepare("SELECT full_name, user_id_display FROM users WHERE id = :i");
        $st->execute([':i' => $acct['id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $data = [
            'user_id'             => $acct['id'],
            'user_name'           => $u['full_name'] ?? $name,
            'user_role'           => $acct['role'],
            'user_status'         => 'active',
            'user_institute_id'   => null, // users carries no column; branches live in user_institutes
            'user_display_id'     => $u['user_id_display'] ?? null,
            'user_type'           => 'user',
        ];
    } else {
        $table = $acct['type'] === 'student' ? 'students' : 'parents';
        $col   = $acct['type'] === 'student' ? 'full_name' : 'parent_name';
        $st = $db->prepare("SELECT {$col} AS nm FROM {$table} WHERE id = :i");
        $st->execute([':i' => $acct['id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $data = [
            'user_id'           => $acct['id'],
            'user_name'         => $u['nm'] ?? $name,
            'user_role'         => $acct['role'],
            'user_status'       => 'active',
            'user_institute_id' => null,
            'user_display_id'   => null,
            'user_type'         => $acct['type'],
        ];
    }

    $data['credential_version'] = $acct['cv'];
    $data['last_activity']      = time();
    $data['lang']               = 'en';

    $encoded = '';
    foreach ($data as $k => $v) {
        $encoded .= $k . '|' . serialize($v);
    }

    $path = session_save_path() ?: sys_get_temp_dir();
    $file = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid;
    if (@file_put_contents($file, $encoded) === false) {
        fwrite(STDERR, "Cannot write a session file to {$path}. Pass --base and run this on the web server's own machine.\n");
        exit(2);
    }

    return $sid;
}

/** One GET, following no redirects, so the bounce itself is visible. */
function get(string $url, string $sid, string $ua): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_COOKIE         => 'STUDENT_MGMT_SESSID=' . $sid . '; lms_lang=en',
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'location' => null, 'body' => '', 'error' => $err];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $hlen);
    $body    = substr($raw, $hlen);
    $location = null;
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
        $location = trim($m[1]);
    }

    return ['status' => $status, 'location' => $location, 'body' => $body, 'error' => null];
}

/** Internal GET links on a page, as absolute paths. */
function linksOn(string $body, string $basePath): array
{
    $out = [];
    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\']/i', $body, $m)) {
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
            if ($href === '' || $href[0] === '#') {
                continue;
            }
            if (preg_match('#^(https?:)?//#i', $href) || preg_match('/^(mailto|tel|javascript|data):/i', $href)) {
                continue;
            }
            if ($href[0] !== '/') {
                continue; // relative links are rare here and ambiguous
            }
            $out[strtok($href, '#')] = true;
        }
    }

    return array_keys($out);
}

/**
 * Every GET form on a page, turned into one URL with its fields filled.
 *
 * A crawl that only follows links never types in a search box, and a
 * search box is exactly where a reused SQL placeholder blows up. Four
 * such 500s were sitting in this codebase behind filter forms nobody
 * had submitted.
 */
function formProbes(string $body, string $currentPath): array
{
    $probes = [];

    if (!preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $body, $forms, PREG_SET_ORDER)) {
        return $probes;
    }

    foreach ($forms as $form) {
        [$all, $attrs, $inner] = $form;

        // POST forms change data; the crawl is read-only.
        if (preg_match('/method\s*=\s*["\']?post/i', $attrs)) {
            continue;
        }

        // Without stripping the query the probe appends a second "?"
        // to a path that already had one, and each round grows the URL
        // by another parameter for ever.
        $action = (string) strtok($currentPath, '?');
        if (preg_match('/action\s*=\s*["\']([^"\']*)["\']/i', $attrs, $m) && trim($m[1]) !== '') {
            $action = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($action === '' || $action[0] !== '/') {
            continue;
        }

        $query = [];

        if (preg_match_all('/<input\b([^>]*)>/i', $inner, $inputs, PREG_SET_ORDER)) {
            foreach ($inputs as $input) {
                $a = $input[1];
                if (!preg_match('/name\s*=\s*["\']([^"\']+)["\']/i', $a, $nm)) {
                    continue;
                }
                $name = $nm[1];
                $type = 'text';
                if (preg_match('/type\s*=\s*["\']?([a-z]+)/i', $a, $tm)) {
                    $type = strtolower($tm[1]);
                }
                if (in_array($type, ['submit', 'button', 'file', 'password', 'checkbox', 'radio'], true)) {
                    continue;
                }
                if (str_contains(strtolower($name), 'csrf') || str_contains(strtolower($name), 'token')) {
                    continue;
                }

                $query[$name] = match ($type) {
                    'number' => '1',
                    'date'   => date('Y-m-d'),
                    'month'  => date('Y-m'),
                    default  => 'a',
                };
            }
        }

        // Selects: take the first option that carries a value, so the
        // filter actually filters instead of falling through.
        if (preg_match_all('/<select\b([^>]*)>(.*?)<\/select>/is', $inner, $selects, PREG_SET_ORDER)) {
            foreach ($selects as $select) {
                if (!preg_match('/name\s*=\s*["\']([^"\']+)["\']/i', $select[1], $nm)) {
                    continue;
                }
                if (preg_match('/<option\b[^>]*value\s*=\s*["\']([^"\']+)["\']/i', $select[2], $om)) {
                    $query[$nm[1]] = html_entity_decode($om[1], ENT_QUOTES, 'UTF-8');
                }
            }
        }

        if ($query === []) {
            continue;
        }

        // Merge into whatever the action already carries, so the result
        // is one well-formed query string.
        $path = (string) strtok($action, '?');
        $existing = [];
        if (str_contains($action, '?')) {
            parse_str((string) substr($action, strpos($action, '?') + 1), $existing);
        }

        $probes[] = $path . '?' . http_build_query(array_merge($existing, $query));
    }

    return $probes;
}

// PHP's own error output, not any sentence containing the word
// "notice". A real announcement titled "TEST NOTICE" was reported as a
// PHP notice until this required the "on line N" that PHP always
// prints and page content never does.
$errorPattern = '/(?:<b>)?(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught [A-Za-z]*Error|Uncaught [A-Za-z]*Exception)(?:<\/b>)?\s*:.{0,400}?on line\s*(?:<b>)?\d+/s';

// Database errors surface without a line number, so they are matched
// on their own.
$dbPattern = '/SQLSTATE\[[A-Z0-9]+\]/';

// Pages whose whole job is to send a signed-in visitor away, plus the
// ones that would end the crawl's own session.
$skip = [
    '/logout', '/lang/en', '/lang/si', '/first-login-password',
    '/login', '/register', '/forgot-password',
];

// Downloads and file streams: fetched, but their bodies are binary and
// nothing is parsed out of them.
$binaryish = '#/(download|file|export|print|invoice|receipt|pdf|csv|xlsx)(/|$|\?)#i';

$basePath = (string) parse_url($base, PHP_URL_PATH);
$accounts = pickAccounts($db, $onlyRole);

if ($accounts === []) {
    fwrite(STDERR, "No usable account found" . ($onlyRole ? " for role {$onlyRole}" : '') . ".\n");
    exit(2);
}

$report  = [];
$totals  = ['pages' => 0, 'bounced' => 0, 'errors' => 0, 'server' => 0];

foreach ($accounts as $label => $acct) {
    $sid = forgeSession($acct, $db);

    // Students land on /my/home and parents on /family, so starting
    // every role at /dashboard crawled exactly one page for them and
    // called the redirect a bug. Follow the start redirect instead.
    $start = '/dashboard';
    $first = get($base . $start, $sid, $ua);
    if ($first['status'] >= 300 && $first['status'] < 400 && $first['location'] !== null) {
        $to = (string) parse_url($first['location'], PHP_URL_PATH);
        if ($basePath !== '' && str_starts_with($to, $basePath)) {
            $to = substr($to, strlen($basePath));
        }
        if ($to !== '' && $to !== '/login') {
            $start = $to;
        }
    }

    $queue   = [$start => '(start)'];
    $seen    = [];
    $findings = ['bounced' => [], 'errors' => [], 'server' => [], 'dead' => []];
    $pages   = 0;

    while ($queue !== [] && $pages < $maxPages) {
        $path = array_key_first($queue);
        $from = $queue[$path];
        unset($queue[$path]);

        if (isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;

        if (in_array($path, $skip, true)) {
            continue;
        }

        $url = $base . $path;
        $r   = get($url, $sid, $ua);
        $pages++;

        if ($r['status'] === 0) {
            $findings['dead'][] = ['path' => $path, 'from' => $from, 'why' => $r['error']];
            continue;
        }

        if ($r['status'] >= 500) {
            $findings['server'][] = ['path' => $path, 'from' => $from, 'why' => 'HTTP ' . $r['status']];
            continue;
        }

        // A refusal shows up as a redirect somewhere the crawler did not
        // ask for. A redirect to login means the session broke, which is
        // the crawl's own problem and is reported as such.
        if ($r['status'] >= 300 && $r['status'] < 400 && $r['location'] !== null) {
            $to = (string) parse_url($r['location'], PHP_URL_PATH);
            if ($basePath !== '' && str_starts_with($to, $basePath)) {
                $to = substr($to, strlen($basePath));
            }
            $to = $to === '' ? '/' : $to;

            if ($to === '/login') {
                $findings['dead'][] = ['path' => $path, 'from' => $from, 'why' => 'bounced to /login -- crawl session rejected'];
                continue;
            }
            $homeRedirect = $path === '/dashboard'
                && in_array($to, ['/my/home', '/family'], true);

            if (!$homeRedirect && rtrim($to, '/') !== rtrim($path, '/')) {
                $findings['bounced'][] = ['path' => $path, 'from' => $from, 'why' => 'redirected to ' . $to];
            }
            continue;
        }

        if ($r['status'] !== 200) {
            $findings['bounced'][] = ['path' => $path, 'from' => $from, 'why' => 'HTTP ' . $r['status']];
            continue;
        }

        if (preg_match($binaryish, $path)) {
            continue;
        }

        foreach ([$errorPattern, $dbPattern] as $pattern) {
            if (preg_match($pattern, $r['body'], $em)) {
                $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($em[0])) ?? '');
                $findings['errors'][] = ['path' => $path, 'from' => $from, 'why' => $snippet];
                break;
            }
        }

        $found = linksOn($r['body'], $basePath);
        foreach (formProbes($r['body'], $path) as $probe) {
            $found[] = $probe;
        }

        foreach ($found as $href) {
            $p = $href;
            if ($basePath !== '' && str_starts_with($p, $basePath)) {
                $p = substr($p, strlen($basePath));
            }
            $p = $p === '' ? '/' : $p;
            if (!isset($seen[$p]) && !isset($queue[$p])) {
                $queue[$p] = $path;
            }
        }
    }

    // Filter combinations multiply, so the queue rarely empties. Say so
    // rather than letting "900 pages, nothing wrong" read as "all of it".
    $report[$label] = [
        'pages'    => $pages,
        'queued'   => count($queue),
        'findings' => $findings,
        'acct'     => $acct,
    ];
    $totals['pages']   += $pages;
    $totals['bounced'] += count($findings['bounced']);
    $totals['errors']  += count($findings['errors']);
    $totals['server']  += count($findings['server']);

    $path = session_save_path() ?: sys_get_temp_dir();
    @unlink(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid);
}

// ---------------------------------------------------------------- report

echo "Role crawl\n";
echo str_repeat('=', 68), "\n\n";

foreach ($report as $label => $r) {
    printf(
        "%-14s user #%-5d %4d pages%s\n",
        $label,
        $r['acct']['id'],
        $r['pages'],
        $r['queued'] > 0 ? sprintf('  (page cap reached, %d still queued)', $r['queued']) : ' (every page reached)'
    );

    $groups = [
        'server'  => 'SERVER ERROR -- the page itself breaks',
        'errors'  => 'PHP NOTICE OR WARNING in the page',
        'bounced' => 'SHOWN BUT REFUSED -- the link is on the page, the page is not',
        'dead'    => 'COULD NOT BE REACHED',
    ];

    $clean = true;
    foreach ($groups as $key => $title) {
        if ($r['findings'][$key] === []) {
            continue;
        }
        $clean = false;
        echo "  ", $title, "\n";
        foreach ($r['findings'][$key] as $f) {
            printf("    %-46s %s\n", $f['path'], $f['why']);
            if (!$quiet) {
                printf("      %s\n", 'linked from ' . $f['from']);
            }
        }
        echo "\n";
    }

    if ($clean) {
        echo "  nothing wrong\n\n";
    }
}

echo str_repeat('=', 68), "\n";
printf(
    "%d pages crawled. %d shown-but-refused, %d page errors, %d server errors.\n",
    $totals['pages'],
    $totals['bounced'],
    $totals['errors'],
    $totals['server']
);

exit($totals['bounced'] + $totals['errors'] + $totals['server'] > 0 ? 1 : 0);
