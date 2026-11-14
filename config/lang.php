<?php

declare(strict_types=1);

/**
 * English / Sinhala for every role on the site -- students, parents,
 * teachers and office staff alike.
 *
 * Sinhala is the default (the client's own instruction); anyone can still
 * switch to English with the EN/Si toggle on any page, and the choice is
 * remembered on that device.
 *
 * How it works, deliberately simple:
 *
 *  - The English sentence IS the key. A view writes te('My classes'),
 *    which reads naturally in the template and needs no lookup table to
 *    understand. lang/si.php maps each English sentence to its Sinhala.
 *  - A sentence with no Sinhala yet falls back to the English, never to
 *    a blank or a key name. A missing translation is a gap, not a fault.
 *  - Values that change -- a name, a number, a date -- go in {braces},
 *    so word order can differ between the languages: Sinhala often puts
 *    the number somewhere English does not.
 *  - The choice is a cookie, so it holds on the login page before anyone
 *    is signed in, and survives logging out and back in on that device.
 */

const LANG_SUPPORTED = ['en', 'si'];
const LANG_COOKIE = 'lms_lang';

/**
 * The language for this request: 'en' or 'si'.
 *
 * Once someone has picked a language on this device (the cookie is set),
 * that choice is respected everywhere, same as always. Before that choice
 * exists, the DEFAULT differs by who is looking: the public website and
 * the student/parent portal default to Sinhala (the client's own
 * instruction -- families come first), but the staff/admin area (any
 * logged-in 'user' -- super admin, admin, coordinator, teacher, staff)
 * defaults to English, their own working language. Anyone can still
 * switch either way with the EN/Si toggle; this only decides what shows
 * before they have.
 */
function lang_current(): string
{
    static $current = null;

    if ($current === null) {
        if (isset($_COOKIE[LANG_COOKIE]) && in_array($_COOKIE[LANG_COOKIE], LANG_SUPPORTED, true)) {
            $current = $_COOKIE[LANG_COOKIE];
        } else {
            $isStaff = function_exists('session_get') && session_get('user_type') === 'user';
            $current = $isStaff ? 'en' : 'si';
        }
    }

    return $current;
}

/**
 * Remember a language choice on this browser for a year.
 */
function lang_set(string $code): void
{
    if (!in_array($code, LANG_SUPPORTED, true)) {
        return;
    }

    $path = defined('BASE_URL') && BASE_URL !== '' ? BASE_URL . '/' : '/';

    setcookie(LANG_COOKIE, $code, [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => $path,
        'samesite' => 'Lax',
        'httponly' => true,
    ]);
}

/**
 * @return array<string, string>
 */
function lang_dictionary(): array
{
    static $dictionary = null;

    if ($dictionary === null) {
        $file = BASE_PATH . '/lang/si.php';
        $dictionary = is_file($file) ? (array) require $file : [];
    }

    return $dictionary;
}

/**
 * Translate a sentence into a named language rather than the one the
 * page is being shown in. Only the website-content editor needs this,
 * to show what a field will say in the other language while the person
 * editing it is working in this one. Anything with no entry, and any
 * language that is not translated, comes back unchanged.
 */
function lang_translate_to(string $text, string $code): string
{
    if ($code !== 'si') {
        return $text;
    }
    return lang_dictionary()[$text] ?? $text;
}

/**
 * Admin-entered overrides for any t()/te() text, keyed by the exact source
 * text (the English sentence passed to t()/te() in the view) -- the "whole
 * site editable from the CMS" mechanism. Every fixed piece of wording across
 * the entire site, every role, already passes through t()/te(), so letting
 * the super admin override right here (Settings > Translations) covers all
 * of it from one screen, with no need to touch each view individually.
 *
 * Loaded once per request and cached. Any failure (migration not run yet,
 * DB hiccup) is swallowed and treated as "no overrides" -- a problem here
 * must never be able to take the whole site's text down with it.
 *
 * @return array<string, array<string, string>> [lang => [source_text => value]]
 */
function lang_overrides(): array
{
    static $overrides = null;
    if ($overrides !== null) {
        return $overrides;
    }

    $overrides = ['en' => [], 'si' => []];
    try {
        if (class_exists('Database')) {
            $rows = Database::connect()
                ->query('SELECT source_text, lang, value FROM translation_overrides')
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $overrides[$row['lang']][$row['source_text']] = $row['value'];
            }
        }
    } catch (\Throwable $e) {
        // Table not migrated yet, DB not reachable, etc. -- fall back to the
        // static dictionary/source text only, exactly as before this existed.
    }

    return $overrides;
}

/**
 * Translate a sentence, then fill its {placeholders}.
 *
 * Returns plain text. Use te() when writing straight into HTML.
 *
 * @param array<string, scalar|null> $vars
 */
function t(string $text, array $vars = []): string
{
    $lang = lang_current();
    $override = lang_overrides()[$lang][$text] ?? null;

    if ($override !== null) {
        $text = $override;
    } elseif ($lang === 'si') {
        $text = lang_dictionary()[$text] ?? $text;
    }

    if ($vars !== []) {
        $replace = [];
        foreach ($vars as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }
        $text = strtr($text, $replace);
    }

    return $text;
}

/**
 * Translate and HTML-escape, for output. The placeholders are escaped
 * along with everything else -- a class name typed by staff goes into
 * a page through here, so it is treated as untrusted.
 *
 * @param array<string, scalar|null> $vars
 */
function te(string $text, array $vars = []): string
{
    return htmlspecialchars(t($text, $vars), ENT_QUOTES, 'UTF-8');
}

/**
 * Month and day names in the current language.
 *
 * PHP's date() only speaks English, so the names it produces are
 * swapped afterwards. Anything that is not a month or day name --
 * numbers, separators -- passes through untouched.
 */
function lang_date(string $format, int|string|null $time = null): string
{
    $timestamp = is_int($time) ? $time : ($time === null ? time() : (int) strtotime((string) $time));
    return lang_names(date($format, $timestamp));
}

/**
 * Month names, day names and am/pm inside any ready-made English text,
 * such as a class schedule ("Saturday & Sunday 8:00 am"), in the
 * current language.
 */
function lang_names(string $out): string
{
    if (lang_current() !== 'si') {
        return $out;
    }

    // Longest first, so "Saturday" is replaced before "Sat" could match it.
    static $names = [
        'January' => 'ජනවාරි', 'February' => 'පෙබරවාරි', 'March' => 'මාර්තු', 'April' => 'අප්‍රේල්',
        'May' => 'මැයි', 'June' => 'ජූනි', 'July' => 'ජූලි', 'August' => 'අගෝස්තු',
        'September' => 'සැප්තැම්බර්', 'October' => 'ඔක්තෝබර්', 'November' => 'නොවැම්බර්', 'December' => 'දෙසැම්බර්',
        'Wednesday' => 'බදාදා', 'Thursday' => 'බ්‍රහස්පතින්දා', 'Saturday' => 'සෙනසුරාදා',
        'Tuesday' => 'අඟහරුවාදා', 'Monday' => 'සඳුදා', 'Friday' => 'සිකුරාදා', 'Sunday' => 'ඉරිදා',
        'Jan' => 'ජන', 'Feb' => 'පෙබ', 'Mar' => 'මාර්', 'Apr' => 'අප්‍රේ', 'Jun' => 'ජූනි', 'Jul' => 'ජූලි',
        'Aug' => 'අගෝ', 'Sep' => 'සැප්', 'Oct' => 'ඔක්', 'Nov' => 'නොවැ', 'Dec' => 'දෙසැ',
        'Mon' => 'සඳු', 'Tue' => 'අඟ', 'Wed' => 'බදා', 'Thu' => 'බ්‍රහ', 'Fri' => 'සිකු', 'Sat' => 'සෙන', 'Sun' => 'ඉරි',
    ];

    // Whole words only: "Mar" must not bite into "Marks".
    $out = (string) preg_replace_callback(
        '/\b(' . implode('|', array_map('preg_quote', array_keys($names))) . ')\b/',
        static fn (array $m): string => $names[$m[1]],
        $out
    );

    // am/pm sit straight after a digit ("4:00pm"), where there is no word
    // boundary for the rule above to find, so they get their own pass.
    return (string) preg_replace_callback(
        '/(?<=\d)\s?(am|pm)\b/i',
        static fn (array $m): string => strtolower($m[1]) === 'am' ? ' පෙ.ව.' : ' ප.ව.',
        $out
    );
}

/**
 * The address that switches to a language and comes back here.
 */
function lang_switch_url(string $code): string
{
    return (defined('BASE_URL') ? BASE_URL : '') . '/lang/' . $code;
}
