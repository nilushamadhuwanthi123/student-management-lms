<?php

declare(strict_types=1);

/**
 * Catch a named SQL placeholder that is used more than once in one
 * statement fragment.
 *
 * config/database.php sets PDO::ATTR_EMULATE_PREPARES to false, so the
 * driver -- not PHP -- parses the statement, and there one name stands
 * for one value. Writing
 *
 *     WHERE name LIKE :search OR email LIKE :search
 *
 * and binding :search once is "Invalid parameter number": a 500 the
 * moment anyone types in that search box, and invisible until they do.
 * Four of these shipped unnoticed because a crawl that never fills a
 * search field never reaches them.
 *
 * Usage:  php tests/sql_placeholders.php
 */

$root = dirname(__DIR__);

$files = [];
foreach (['app', 'bin', 'database'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

$sqlish = '/\b(SELECT|UPDATE|INSERT|DELETE|LIKE|WHERE|AND|OR|SET|VALUES)\b/i';
$problems = [];

foreach ($files as $file) {
    $src = (string) file_get_contents($file);

    // PHP's own tokenizer rather than a regex over the text. Quotes
    // inside comments and apostrophes inside double-quoted SQL put a
    // regex scan out of phase and it starts pairing the wrong quotes,
    // which reported two statements that were perfectly fine.
    foreach (token_get_all($src) as $token) {
        if (!is_array($token)) {
            continue;
        }
        [$id, $text, $line] = [$token[0], $token[1], $token[2]];
        if ($id !== T_CONSTANT_ENCAPSED_STRING && $id !== T_ENCAPSED_AND_WHITESPACE) {
            continue;
        }

        $body = trim($text, "'\"");
        if (!preg_match($sqlish, $body)) {
            continue;
        }
        if (!preg_match_all('/(?<![:\w]):([a-zA-Z_]\w*)/', $body, $names)) {
            continue;
        }
        $counts = array_count_values($names[1]);
        $repeated = array_keys(array_filter($counts, static fn ($n) => $n > 1));
        if ($repeated === []) {
            continue;
        }

        $problems[] = [
            'file' => ltrim(str_replace([$root, '\\'], ['', '/'], $file), '/'),
            'line' => $line,
            'names' => $repeated,
        ];
    }
}

echo "Repeated SQL placeholders\n";
echo str_repeat('=', 68), "\n\n";

if ($problems === []) {
    printf("  OK  %d files scanned, no placeholder used twice in one statement.\n", count($files));
    exit(0);
}

foreach ($problems as $p) {
    printf("  %s:%d  reuses %s\n", $p['file'], $p['line'], implode(', ', array_map(static fn ($n) => ':' . $n, $p['names'])));
}
printf("\n%d statement%s to fix: give each use its own placeholder.\n", count($problems), count($problems) === 1 ? '' : 's');

exit(1);
