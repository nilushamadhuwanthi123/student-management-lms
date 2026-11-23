<?php
/**
 * php -l across the whole project, in one run.
 *
 * Written as a PHP script rather than a shell loop so that it behaves
 * the same on the developer's Windows machine and on the server.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dirs = ['app', 'config', 'routes', 'public', 'tests', 'database', 'bin'];
$php  = PHP_BINARY;
$bad  = 0;
$n    = 0;

foreach ($dirs as $d) {
    $path = "$root/$d";
    if (!is_dir($path)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') { continue; }
        $n++;
        exec(escapeshellarg($php) . ' -l ' . escapeshellarg($f->getPathname()) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $bad++;
            echo implode("\n", $out), "\n";
        }
        $out = [];
    }
}

printf("%d PHP files checked, %d with syntax errors\n", $n, $bad);
exit($bad === 0 ? 0 : 1);
