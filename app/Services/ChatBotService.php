<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The free FAQ bot for the public site widget.
 *
 * Same spirit as the study assistant: no language model, nothing sent
 * off this server, no running cost. It recognises what a visitor is
 * asking and answers from the site's own public data (CMS text,
 * branches, class fees, the FAQ the super admin edits) -- so it can
 * never say something the site itself does not already say. When it
 * cannot place the question, it hands the visitor to the contact page
 * or WhatsApp instead of guessing.
 */
class ChatBotService
{
    public const SUGGESTIONS = [
        'Class timetable',
        'How do I join a class?',
        'Where are the branches?',
    ];

    /** Intent => words/phrases that suggest it (English, Sinhala, Singlish). */
    private const INTENTS = [
        'greeting' => ['ayubowan', 'hi', 'hello', 'suba', 'kohomada', 'ඇයුබෝවන්', 'හායි', 'සුභ'],
        'fees' => ['fee', 'fees', 'price', 'cost', 'salli', 'gaana', 'gana', 'මුදල', 'ගාන', 'ගාස්තු'],
        'timetable' => ['timetable', 'schedule', 'time table', 'kal sat', 'welawa', 'කාලසටහන', 'වෙලාව'],
        'join' => ['join', 'enroll', 'enrol', 'register', 'admission', 'join wenn', 'ඇතුළත්', 'ලියාපදිංචි'],
        'branches' => ['branch', 'location', 'address', 'where', 'kohe', 'branches', 'කොහෙද', 'ස්ථානය'],
        'contact' => ['contact', 'phone', 'whatsapp', 'number', 'email', 'call', 'අමතන්න', 'දුරකථන'],
        'forgot_password' => ['forgot', 'reset password', 'password amathaka', "can't login", 'cannot login', 'මුරපදය', 'අමතක'],
        'online_class' => ['zoom', 'online class', 'meeting link', 'join class', 'live class', 'ඔන්ලයින්'],
        'teacher' => ['teacher', 'harshani', 'guru', 'ගුරුතුමිය', 'ටීචර්'],
        'portal' => ['marks', 'attendance', 'progress', 'homework', 'report', 'ලකුණු', 'පැමිණීම'],
        'thanks' => ['thank', 'thanks', 'bohoma', 'istuti', 'ස්තූතියි', 'බොහොම'],
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * @return array{answer: string, intent: string, answered: bool,
     *               links: array<int, array{label: string, href: string}>,
     *               suggestions: array<int, string>, suggestion_labels: array<int, string>}
     */
    public function ask(string $message): array
    {
        $msg = $this->normalise($message);

        if ($msg === '') {
            return $this->reply(t('Ask me about fees, class times, branches, joining, or your password — or tap one of the quick questions below.'), 'help');
        }

        $faq = $this->matchFaq($msg);
        if ($faq !== null) {
            return $this->reply($faq, 'faq');
        }

        $intent = $this->detectIntent($msg);

        return match ($intent) {
            'greeting'        => $this->reply(t('Ayubowan! I can help with fees, class times, branches, joining a class, or resetting a password. What would you like to know?'), 'greeting'),
            'thanks'          => $this->reply(t("You're welcome! Ask me anything else about the classes."), 'thanks'),
            'fees'            => $this->fees(),
            'timetable'       => $this->reply(t('Full class days and times, by branch and grade, are on the timetable page.'), 'timetable', [['label' => t('View timetable'), 'href' => BASE_URL . '/timetable']]),
            'join'            => $this->reply(t('Two ways: fill the enrol form yourself (pick a branch and class, upload your bank deposit slip) and the branch office approves it, or send an inquiry and the office will call you back. Either way you get a student ID and password for the portal.'), 'join', [['label' => t('Enrol now'), 'href' => BASE_URL . '/enroll'], ['label' => t('Contact a branch'), 'href' => BASE_URL . '/contact']]),
            'branches'        => $this->branches(),
            'contact'         => $this->contact(),
            'forgot_password' => $this->reply(t('Tap "Forgot password" on the login page. We text a one-time code to the phone number on file, then you can set a new password right away.'), 'forgot_password', [['label' => t('Reset password'), 'href' => BASE_URL . '/forgot-password']]),
            'online_class'    => $this->reply(t('If a class is delivered online, the join link appears on your dashboard a little before the session starts — log in and look for the "Join class" button.'), 'online_class', [['label' => t('Log in'), 'href' => BASE_URL . '/login']]),
            'teacher'         => $this->teacher(),
            'portal'          => $this->reply(t("Marks, attendance, homework and progress are all in the student/parent portal. Log in with the student ID or the parent's phone number."), 'portal', [['label' => t('Log in'), 'href' => BASE_URL . '/login']]),
            default           => $this->fallback(),
        };
    }

    // ------------------------------------------------------------------

    private function normalise(string $message): string
    {
        $m = mb_strtolower(trim($message));
        return preg_replace('/\s+/u', ' ', $m) ?? $m;
    }

    /**
     * Pick the intent with the most matching keywords; ties go to the
     * earlier-listed intent. A Latin word must match on a word
     * boundary; Sinhala matches as a plain substring, since Sinhala is
     * often typed with no clean word spacing.
     */
    private function detectIntent(string $msg): string
    {
        $best = 'unknown';
        $bestScore = 0;
        foreach (self::INTENTS as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                $isLatin = (bool) preg_match('/^[a-z0-9\' ]+$/u', $kw);
                if ($isLatin) {
                    if (preg_match('/(?<![a-z])' . preg_quote($kw, '/') . '(?![a-z])/u', $msg)) {
                        $score += substr_count($kw, ' ') + 1;
                    }
                } elseif (str_contains($msg, $kw)) {
                    $score += 1;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }
        return $bestScore > 0 ? $best : 'unknown';
    }

    private function matchFaq(string $msg): ?string
    {
        for ($i = 1; $i <= 5; $i++) {
            $q = trim(PublicSite::content("home_faq_{$i}_q"));
            $a = trim(PublicSite::content("home_faq_{$i}_a"));
            if ($q === '' || $a === '') {
                continue;
            }
            $qWords = array_values(array_filter(
                preg_split('/\s+/u', mb_strtolower($q)) ?: [],
                static fn (string $w): bool => mb_strlen($w) > 3
            ));
            if ($qWords === []) {
                continue;
            }
            $hits = 0;
            foreach ($qWords as $w) {
                if (str_contains($msg, $w)) {
                    $hits++;
                }
            }
            if ($hits / count($qWords) >= 0.5) {
                return trim(strip_tags($a));
            }
        }
        return null;
    }

    /**
     * @param array<int, array{label: string, href: string}> $links
     */
    private function reply(string $answer, string $intent, array $links = []): array
    {
        $showSuggestions = in_array($intent, ['greeting', 'help', 'unknown'], true);
        return [
            'answer'            => $answer,
            'intent'            => $intent,
            'answered'          => $intent !== 'unknown',
            'links'             => $links,
            'suggestions'       => $showSuggestions ? self::SUGGESTIONS : [],
            'suggestion_labels' => $showSuggestions ? array_map(static fn (string $s): string => t($s), self::SUGGESTIONS) : [],
        ];
    }

    private function fees(): array
    {
        $range = null;
        try {
            $row = $this->db->query(
                "SELECT MIN(monthly_fee) AS lo, MAX(monthly_fee) AS hi FROM classes WHERE status = 'active' AND monthly_fee > 0"
            )->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['lo'] !== null) {
                $range = $row;
            }
        } catch (\Throwable $e) {
            error_log('ChatBot fees lookup failed: ' . $e->getMessage());
        }

        if ($range !== null) {
            $lo = (int) $range['lo'];
            $hi = (int) $range['hi'];
            $text = $lo === $hi
                ? t('The monthly fee is Rs. {amount}, paid at the branch office or by bank slip upload in the portal.', ['amount' => number_format($lo)])
                : t('Monthly fees range from Rs. {lo} to Rs. {hi} depending on the grade/class, paid at the branch or by bank slip upload in the portal.', ['lo' => number_format($lo), 'hi' => number_format($hi)]);
            return $this->reply($text, 'fees', [['label' => t('View timetable & fees'), 'href' => BASE_URL . '/timetable']]);
        }

        return $this->reply(
            t('Fees vary by grade — please check the timetable page or ask your branch.'),
            'fees',
            [['label' => t('View timetable'), 'href' => BASE_URL . '/timetable']]
        );
    }

    private function branches(): array
    {
        $branches = PublicSite::branches();
        if ($branches === []) {
            return $this->reply(t('Please see the contact page for our branch addresses.'), 'branches', [['label' => t('Contact page'), 'href' => BASE_URL . '/contact']]);
        }
        $names = array_map(static fn (array $b): string => (string) $b['name'], $branches);
        $text = t('We have classes at: {names}.', ['names' => implode(', ', $names)]);
        return $this->reply($text, 'branches', [['label' => t('View branches & map'), 'href' => BASE_URL . '/contact']]);
    }

    private function contact(): array
    {
        $wa = trim((string) PublicSite::content('contact_whatsapp'));
        $email = trim((string) PublicSite::content('contact_email'));
        $parts = [];
        if ($wa !== '') {
            $parts[] = t('WhatsApp: {wa}', ['wa' => $wa]);
        }
        if ($email !== '') {
            $parts[] = t('Email: {email}', ['email' => $email]);
        }
        if ($parts === []) {
            return $this->reply(t('Please see the contact page for phone numbers for each branch.'), 'contact', [['label' => t('Contact page'), 'href' => BASE_URL . '/contact']]);
        }
        return $this->reply(implode(' · ', $parts), 'contact', [['label' => t('Contact page'), 'href' => BASE_URL . '/contact']]);
    }

    private function teacher(): array
    {
        $name = PublicSite::teacherName();
        if ($name === null) {
            return $this->reply(t('Please see the About page for teacher details.'), 'teacher', [['label' => t('About'), 'href' => BASE_URL . '/about']]);
        }
        return $this->reply(
            t('Classes are taught by {name}.', ['name' => $name]),
            'teacher',
            [['label' => t('Read more'), 'href' => BASE_URL . '/about']]
        );
    }

    private function fallback(): array
    {
        $wa = trim((string) PublicSite::content('contact_whatsapp'));
        $text = $wa !== ''
            ? t('Sorry, I did not quite catch that. You can ask about fees, timetable, branches, joining, or a forgotten password — or message us directly on WhatsApp: {wa}.', ['wa' => $wa])
            : t('Sorry, I did not quite catch that. You can ask about fees, timetable, branches, joining, or a forgotten password — or use the contact page.');
        return $this->reply($text, 'unknown', [['label' => t('Contact page'), 'href' => BASE_URL . '/contact']]);
    }
}
