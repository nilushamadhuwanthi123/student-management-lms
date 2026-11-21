<?php

declare(strict_types=1);

/**
 * Which sentences have no Sinhala yet.
 *
 * A gap here is not a crash -- t() hands back the English -- so the only
 * way anyone finds out is a parent opening the Sinhala site and reading
 * half of it in English. That is exactly the complaint this exists to
 * stop: it lists every string the code asks t() for that lang/si.php
 * has no entry for, so the gaps can be closed deliberately instead of
 * discovered by a visitor.
 *
 * Run:  php tests/lang_coverage.php            (public pages -- what visitors see)
 *       php tests/lang_coverage.php --all      (every view, admin included)
 *       php tests/lang_coverage.php --all --list
 *
 * Exit code is the number of gaps, capped at 250, so CI can fail on it.
 */

$root = dirname(__DIR__);
$all  = in_array('--all', $argv, true);
$list = in_array('--list', $argv, true);

/** The pages a visitor or a family can reach without being staff. */
$publicDirs = [
    $root . '/app/Views/public',
    $root . '/app/Views/auth',
    $root . '/app/Views/layouts',
    $root . '/app/Views/portal',
    $root . '/app/Views/student',
    $root . '/app/Views/components',
];

$dirs = $all ? [$root . '/app'] : $publicDirs;

$files = [];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
}
// The CMS defaults are wording too: they are what the site says before
// the owner has typed anything, and they go through t() the same way.
$files[] = $root . '/app/Services/SiteContentService.php';
$files[] = $root . '/app/Services/HomeFeatureCards.php';

$wanted = [];   // string => list of files that ask for it
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $rel = ltrim(str_replace($root, '', $file), '/\\');

    // t('...') and te('...'), single-quoted, which is how the codebase
    // writes them. A double-quoted or variable argument is skipped on
    // purpose: it is not a fixed sentence to translate.
    if (preg_match_all("/\\b(?:te|t)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m)) {
        foreach ($m[1] as $raw) {
            $s = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
            // A fragment that is only a placeholder ('{n} ') comes from a
            // concatenation, not a sentence anyone translates.
            $bare = preg_replace('/\{[a-z_]+\}/i', '', $s) ?? '';
            if (trim($bare) === '' || !preg_match('/[A-Za-z]/', $bare)) {
                continue;
            }
            $wanted[$s][$rel] = true;
        }
    }

    // The CMS field registry also holds labels, group headings, theme
    // names and notes, and the choice lists -- all of which reach the
    // screen through te(). They are not written as t('...') literals,
    // so a scan for those alone reports full coverage while half the
    // settings screen is still in English.
    if (str_ends_with($rel, 'SiteContentService.php')
        || str_ends_with($rel, 'SiteAdDesign.php')
        || str_ends_with($rel, 'HeroIconLibrary.php')
        || str_ends_with($rel, 'HomeFeatureCards.php')) {
        $patterns = [
            "/'label'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/",
            "/'name'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/",
            "/'note'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/",
            "/'suffix'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/",
            "/'help'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/",
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $src, $mL)) {
                foreach ($mL[1] as $raw) {
                    $sTxt = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
                    if ($sTxt === '' || !preg_match('/[A-Za-z]/', $sTxt)) {
                        continue;
                    }
                    $wanted[$sTxt]['CMS labels'] = true;
                }
            }
        }
    }

    // TEMPLATES and COLORWAYS are positional: 'key' => ['Name', 'Note'].
    // Nothing above matches those either, and they are the labels on the
    // ad designer's own buttons.
    if (str_ends_with($rel, 'SiteAdDesign.php')
        && preg_match_all("/=>\s*\[\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $mT, PREG_SET_ORDER)) {
        foreach ($mT as $set) {
            foreach ([$set[1], $set[2]] as $raw) {
                $sTxt = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
                if ($sTxt !== '' && preg_match('/[A-Za-z]/', $sTxt)
                    && preg_match('/^#[0-9A-Fa-f]{6}$/', $sTxt) !== 1) {
                    $wanted[$sTxt]['ad designer'] = true;
                }
            }
        }
    }

    // GROUPS is written as 'key' => 'Heading', which none of the
    // patterns above matches, so it is read from its own block.
    if (str_ends_with($rel, 'SiteContentService.php')
        && preg_match('/const GROUPS = \[(.*?)\n    \];/s', $src, $mG)) {
        if (preg_match_all("/=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $mG[1], $mGL)) {
            foreach ($mGL[1] as $raw) {
                $sTxt = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
                if ($sTxt !== '' && preg_match('/[A-Za-z]/', $sTxt)) {
                    $wanted[$sTxt]['CMS groups'] = true;
                }
            }
        }
    }

    // 'default' => 'Some English wording', in the CMS field registry.
    if (str_ends_with($rel, 'SiteContentService.php')
        && preg_match_all("/'default'\\s*=>\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m2)) {
        foreach ($m2[1] as $raw) {
            $s = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
            // Colours, numbers, slugs and icon lists are not wording.
            if ($s === '' || !preg_match('/[A-Za-z]/', $s)) {
                continue;
            }
            if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $s)) {
                continue;
            }
            if (!str_contains($s, ' ')) {
                continue;   // a single token is a setting value, not a sentence
            }
            $wanted[$s]['CMS defaults'] = true;
        }
    }
}

$si = require $root . '/lang/si.php';
if (!is_array($si)) {
    fwrite(STDERR, "lang/si.php did not return an array\n");
    exit(255);
}

$missing = [];
foreach ($wanted as $s => $where) {
    if (!array_key_exists($s, $si) || trim((string) $si[$s]) === '') {
        $missing[$s] = array_keys($where);
    }
}

$scope = $all ? 'every view' : 'public and family pages';
printf("Sinhala coverage (%s)\n", $scope);
printf("  asked for : %d sentences\n", count($wanted));
printf("  have si   : %d\n", count($wanted) - count($missing));
printf("  MISSING   : %d\n\n", count($missing));

if ($missing !== [] && ($list || !$all)) {
    ksort($missing);
    foreach ($missing as $s => $where) {
        printf("  - %s\n      (%s)\n", $s, implode(', ', array_slice($where, 0, 3)));
    }
}

exit(min(250, count($missing)));
