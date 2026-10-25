<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

/**
 * The study assistant: answers a student's (or a parent's) questions
 * about their own classes, from the site's own records.
 *
 * ## What it is, honestly
 *
 * It is not a language model. Nothing leaves this server, nothing is
 * paid for per message, and it cannot be talked into anything, because
 * it does not generate text -- it recognises what is being asked and
 * answers from the database. "When is my next class?", "have I paid?",
 * "what did I get for the last quiz?" are questions with one right
 * answer that is already sitting in a table, and a model would only add
 * a chance of getting it wrong.
 *
 * What it will not do is teach science. Asked "what is photosynthesis",
 * it says so and points the student at the notes and at the teacher,
 * rather than improvising an answer that might be wrong in an exam.
 *
 * ## Who it answers about
 *
 * Exactly one student, decided by the controller from the session and
 * passed in. Nothing in the question can widen that: there is no
 * parsing of names or IDs out of the text, so "what are Kasun's marks?"
 * gets the asker's own marks or nothing. A parent's assistant answers
 * about their own child, in the third person.
 *
 * ## Languages
 *
 * Students here write in English, in Sinhala, and most of all in
 * Singlish ("ilanga panthiya kawadada"). The keyword lists carry all
 * three. Answers are in plain English.
 */
class AssistantService
{
    /**
     * Intent => words that suggest it. Latin words match on word
     * boundaries; Sinhala ones as substrings, since Sinhala is often
     * typed without the spacing a boundary test relies on.
     *
     * Order matters only for ties: earlier intents win.
     */
    private const INTENTS = [
        'fees' => [
            'fee', 'fees', 'payment', 'payments', 'paid', 'pay', 'unpaid', 'owe', 'due amount',
            'gewala', 'gewwa', 'gewuwa', 'gewanna', 'gewapu', 'salli', 'mudal', 'ganna',
            'ගාස්තු', 'ගෙව්වා', 'ගෙවලා', 'ගෙවන්න', 'සල්ලි', 'මුදල්',
        ],
        'quizzes' => [
            'quiz', 'quizzes', 'mcq', 'online test', 'attempt',
            'prashna', 'quiz eka', 'quiz ek',
            'ප්‍රශ්න', 'ක්විස්',
        ],
        'exams' => [
            'exam', 'exams', 'test', 'tests', 'paper', 'term test', 'unit test',
            'wibhagaya', 'vibhagaya', 'pariksha', 'test eka', 'test ek',
            'විභාග', 'පරීක්ෂණ', 'ප්‍රශ්න පත්‍ර',
        ],
        'marks' => [
            'mark', 'marks', 'result', 'results', 'score', 'scores', 'grade i got', 'got for',
            'lakunu', 'lakuna', 'results mona',
            'ලකුණු', 'ප්‍රතිඵල',
        ],
        'progress' => [
            'progress', 'level', 'how am i doing', 'improve', 'improving', 'doing well', 'rank',
            'kohomada mama', 'mama kohomada', 'diyunuwa', 'level eka',
            'දියුණුව', 'මට්ටම',
        ],
        'materials' => [
            'note', 'notes', 'recording', 'recordings', 'video', 'videos', 'material', 'materials',
            'reading', 'pdf', 'zoom', 'lesson', 'tute', 'tutes',
            'sataham', 'saṭahan', 'recording eka', 'notes tika',
            'සටහන්', 'පටිගත', 'වීඩියෝ', 'නිබන්ධන',
        ],
        'next_class' => [
            'next class', 'next lesson', 'when is class', 'when is my class', 'class kawada', 'class eka kawada',
            'ilanga panthiya', 'ilanga class', 'panthiya kawada', 'panthi kawada', 'next panthiya',
            'ඊළඟ පන්තිය', 'පන්තිය කවදා', 'ඊළඟ',
        ],
        'schedule' => [
            'timetable', 'schedule', 'today', 'tomorrow', 'this week', 'calendar', 'classes this',
            'ada', 'heta', 'me sathiye', 'sathiye', 'panthi', 'panthiya',
            'අද', 'හෙට', 'සතියේ', 'කාලසටහන', 'පන්ති',
        ],
        'teacher' => [
            'teacher', 'miss', 'madam', 'sir', 'contact', 'harshani', 'call',
            'guru', 'guruthuma', 'teacher ta',
            'ගුරු', 'ටීචර්',
        ],
        'subject_question' => [
            'what is', 'explain', 'define', 'why does', 'how does', 'meaning of', 'formula',
            'mokakda', 'kiyanne mokakda', 'paehadili', 'explain karanna',
            'යනු කුමක්ද', 'පැහැදිලි',
        ],
        'thanks' => [
            'thanks', 'thank you', 'thank', 'thx', 'ty', 'sthuthi', 'isthuthi', 'stuti',
            'ස්තූතියි',
        ],
        'greeting' => [
            'hi', 'hello', 'hey', 'good morning', 'good evening', 'ayubowan', 'kohomada',
            'ආයුබෝවන්', 'හායි',
        ],
        'help' => [
            'help', 'what can you do', 'commands', 'options', 'udaw', 'udav',
            'උදව්',
        ],
    ];

    /** Suggested questions, shown as chips. Phrased the way a student would type them. */
    public const SUGGESTIONS = [
        'When is my next class?',
        'Any quizzes due?',
        'My latest marks',
        'Have I paid this month?',
        'New notes or recordings?',
        'Any exams coming up?',
    ];

    private PDO $db;
    private CalendarService $calendar;
    private ContentAccessService $access;

    private int $studentId = 0;
    private string $audience = 'student';
    private string $name = '';
    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->db = \Database::connect();
        $this->calendar = new CalendarService();
        $this->access = new ContentAccessService();
    }

    /**
     * Answer one question about one student.
     *
     * @param string $audience 'student' answers in the second person,
     *                         'parent' in the third, about the child.
     * @return array{answer: string, intent: string, answered: bool,
     *               links: array<int, array{label: string, href: string}>,
     *               suggestions: array<int, string>}
     */
    public function ask(string $question, int $studentId, string $audience = 'student'): array
    {
        $this->studentId = $studentId;
        $this->audience = $audience === 'parent' ? 'parent' : 'student';
        $this->now = new DateTimeImmutable((string) $this->db->query('SELECT NOW()')->fetchColumn());

        $stmt = $this->db->prepare('SELECT full_name FROM students WHERE id = :id');
        $stmt->execute([':id' => $studentId]);
        $this->name = (string) ($stmt->fetchColumn() ?: '');

        $intent = $this->detectIntent($question);

        $reply = match ($intent) {
            'greeting'         => $this->greeting(),
            'help'             => $this->help(),
            'thanks'           => $this->reply(t("You're welcome! Ask me anything else about your classes."), 'thanks'),
            'next_class'       => $this->nextClass(),
            'schedule'         => $this->schedule($question),
            'exams'            => $this->exams(),
            'quizzes'          => $this->quizzes(),
            'marks'            => $this->marks(),
            'progress'         => $this->progress(),
            'fees'             => $this->fees(),
            'materials'        => $this->materials(),
            'teacher'          => $this->teacher(),
            'subject_question' => $this->subjectQuestion(),
            default            => $this->fallback(),
        };

        return $reply;
    }

    // ------------------------------------------------------------------
    // Understanding the question
    // ------------------------------------------------------------------

    /**
     * Pick the intent with the most matching keywords.
     *
     * A longer phrase scores more than a single word, so "next class"
     * beats the bare "class" inside a schedule question, and "term test"
     * outranks a stray "test". Public so it can be tested on its own.
     */
    public function detectIntent(string $question): string
    {
        $text = $this->normalise($question);

        if ($text === '') {
            return 'help';
        }

        $best = 'unknown';
        $bestScore = 0;

        foreach (self::INTENTS as $intent => $words) {
            $score = 0;

            foreach ($words as $word) {
                if ($this->contains($text, $word)) {
                    // Weight by length of the phrase, in words.
                    $score += 1 + substr_count($word, ' ');
                }
            }

            if ($score > $bestScore) {
                $best = $intent;
                $bestScore = $score;
            }
        }

        // "hi, when is my class" is a schedule question with manners,
        // not a greeting. A greeting only wins when it is all there is.
        if ($best === 'greeting' && str_word_count($text) > 3) {
            $without = $this->detectWithout($text, ['greeting', 'thanks']);
            if ($without !== 'unknown') {
                return $without;
            }
        }

        // "what is on tomorrow" opens like a science question, but it asks
        // about the timetable. "What is" alone outscores "tomorrow", so a
        // subject question only wins when nothing about the site matched.
        if ($best === 'subject_question') {
            $without = $this->detectWithout($text, ['subject_question', 'greeting', 'thanks', 'help']);
            if ($without !== 'unknown') {
                return $without;
            }
        }

        return $best;
    }

    private function detectWithout(string $text, array $skip): string
    {
        $best = 'unknown';
        $bestScore = 0;

        foreach (self::INTENTS as $intent => $words) {
            if (in_array($intent, $skip, true)) {
                continue;
            }
            $score = 0;
            foreach ($words as $word) {
                if ($this->contains($text, $word)) {
                    $score += 1 + substr_count($word, ' ');
                }
            }
            if ($score > $bestScore) {
                $best = $intent;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // Keep letters (any script), digits, spaces, and the Sinhala
        // joiners that zero-width characters would otherwise break.
        $text = preg_replace('/[^\p{L}\p{M}\p{N}\s\x{200D}]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function contains(string $text, string $word): bool
    {
        // Sinhala: substring. Latin: whole-word, so "ty" does not fire
        // inside "safety" and "test" does not fire inside "latest".
        if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $word) === 1) {
            return mb_strpos($text, $word, 0, 'UTF-8') !== false;
        }

        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/u', $text) === 1;
    }

    // ------------------------------------------------------------------
    // Answers
    // ------------------------------------------------------------------

    private function greeting(): array
    {
        return $this->reply(
            $this->say(
                'Hi! I can answer questions about your classes -- the next class, quizzes, marks, fees and new notes. Try one of these:',
                "Hi! I can answer questions about {name}'s classes -- the next class, quizzes, marks, fees and new notes. Try one of these:"
            ),
            'greeting'
        );
    }

    private function help(): array
    {
        return $this->reply(
            t('I know about:') . "\n"
            . '- ' . t('when classes, quizzes and exams are') . "\n"
            . '- ' . t('marks and progress') . "\n"
            . '- ' . t("this month's fees") . "\n"
            . '- ' . t('new notes, recordings and videos') . "\n\n"
            . t("I can't explain science topics -- for that, use the class notes or ask the teacher."),
            'help'
        );
    }

    private function nextClass(): array
    {
        $upcoming = $this->upcoming(21, ['class_session', 'event']);
        $next = null;

        foreach ($upcoming as $item) {
            if ($item['type'] === 'class_session' || in_array($item['event_type'] ?? '', ['extra_class'], true)) {
                $next = $item;
                break;
            }
        }

        if ($next === null) {
            return $this->reply(
                $this->say('You have no classes on the calendar for the next three weeks.', '{name} has no classes on the calendar for the next three weeks.'),
                'next_class',
                [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]
            );
        }

        $text = t($next['room'] ? 'The next class is {title} {when}, room {room} -- {countdown}.' : 'The next class is {title} {when} -- {countdown}.', [
            'title'     => $next['title'],
            'when'      => $this->when($next, true),
            'room'      => (string) $next['room'],
            'countdown' => $this->countdown($next),
        ]);

        if ($next['class_id'] !== null && !$this->isOpen((int) $next['class_id'])) {
            $text .= "\n\n" . t("This month's fee for that class isn't settled yet, so the notes and recordings are locked. The class itself still runs.");
        }

        return $this->reply($text, 'next_class', [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]);
    }

    private function schedule(string $question): array
    {
        $text = $this->normalise($question);
        $today = $this->now->setTime(0, 0);

        if ($this->contains($text, 'tomorrow') || $this->contains($text, 'heta') || $this->contains($text, 'හෙට')) {
            $from = $today->modify('+1 day');
            $to = $from;
            $label = t('Tomorrow ({date})', ['date' => lang_date('l j M', $from->getTimestamp())]);
        } elseif ($this->contains($text, 'today') || $this->contains($text, 'ada') || $this->contains($text, 'අද')) {
            $from = $today;
            $to = $today;
            $label = t('Today');
        } else {
            $from = $today;
            $to = $today->modify('+6 days');
            $label = t('The next 7 days');
        }

        $items = $this->calendar->between($from, $to, ['student_id' => $this->studentId]);
        $nowString = $this->now->format('Y-m-d H:i:s');
        $items = array_values(array_filter(
            $items,
            static fn (array $i): bool => $i['type'] !== 'quiz_opens' && ($i['all_day'] || $i['starts_at'] >= $nowString || $i['date'] > substr($nowString, 0, 10))
        ));

        if ($items === []) {
            return $this->reply(t('{label}: nothing on the calendar.', ['label' => $label]), 'schedule',
                [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]);
        }

        $lines = array_map(fn (array $i): string => '- ' . $this->shortWhen($i, $from == $to) . ' ' . $i['title']
            . ($i['detail'] !== 'Class' ? ' (' . t((string) $i['detail']) . ')' : ''), array_slice($items, 0, 10));

        $more = count($items) > 10 ? "\n" . t('...and {n} more on the calendar.', ['n' => count($items) - 10]) : '';

        return $this->reply("{$label}:\n" . implode("\n", $lines) . $more, 'schedule',
            [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]);
    }

    private function exams(): array
    {
        $items = array_values(array_filter(
            $this->upcoming(60, ['event']),
            static fn (array $i): bool => in_array($i['event_type'] ?? '', ['exam', 'test'], true)
        ));

        if ($items === []) {
            return $this->reply(t('No exams or tests are scheduled for the next two months.'), 'exams',
                [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]);
        }

        $lines = [];
        foreach (array_slice($items, 0, 5) as $i) {
            $line = '- ' . $i['title'] . ' -- ' . $this->when($i) . ' (' . $this->countdown($i) . ')';
            if (!empty($i['syllabus'])) {
                $line .= "\n  " . t('Covers: {what}', ['what' => $i['syllabus']]);
            }
            $lines[] = $line;
        }

        return $this->reply(t('Coming up') . ":\n" . implode("\n", $lines), 'exams',
            [['label' => t('Open the calendar'), 'href' => $this->calendarLink()]]);
    }

    private function quizzes(): array
    {
        $stmt = $this->db->prepare(
            "SELECT q.id, q.title, q.class_id, q.opens_at, q.closes_at, c.class_name,
                    (SELECT COUNT(*) FROM quiz_attempts a
                      WHERE a.quiz_id = q.id AND a.student_id = :sid1
                        AND a.attempt_status IN ('submitted', 'graded')) AS done
               FROM quizzes q
               JOIN classes c ON c.id = q.class_id
               JOIN enrollments e ON e.class_id = q.class_id
                                 AND e.student_id = :sid2
                                 AND e.enrollment_status = 'active'
              WHERE q.quiz_status = 'published'
                AND q.closes_at > :now
              ORDER BY q.closes_at"
        );
        $stmt->execute([
            ':sid1' => $this->studentId,
            ':sid2' => $this->studentId,
            ':now'  => $this->now->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $open = [];
        $soon = [];
        $locked = 0;

        foreach ($rows as $row) {
            if ((int) $row['done'] > 0) {
                continue;
            }
            if (!$this->isOpen((int) $row['class_id'])) {
                $locked++;
                continue;
            }
            if ($row['opens_at'] <= $this->now->format('Y-m-d H:i:s')) {
                $open[] = $row;
            } else {
                $soon[] = $row;
            }
        }

        if ($open === [] && $soon === []) {
            $text = $this->say("You're all caught up -- no quizzes waiting.", 'No quizzes are waiting for {name} right now.');
            if ($locked > 0) {
                $text .= "\n\n" . t($locked === 1 ? "{n} more is in a class whose fee isn't settled this month." : "{n} more are in a class whose fee isn't settled this month.", ['n' => $locked]);
            }

            return $this->reply($text, 'quizzes', [['label' => t('My classes'), 'href' => $this->classesLink()]]);
        }

        $lines = [];
        foreach ($open as $q) {
            $lines[] = '- ' . $q['title'] . ' (' . $q['class_name'] . ') -- ' . t('open now, closes {when}', ['when' => $this->dateTime($q['closes_at'])]);
        }
        foreach (array_slice($soon, 0, 3) as $q) {
            $lines[] = '- ' . $q['title'] . ' (' . $q['class_name'] . ') -- ' . t('opens {when}', ['when' => $this->dateTime($q['opens_at'])]);
        }

        $head = count($open) > 0
            ? (count($open) === 1 ? t('1 quiz is open now:') : t('{n} quizzes are open now:', ['n' => count($open)]))
            : t('Nothing open right now, but coming up:');

        if ($locked > 0) {
            $lines[] = "\n" . t($locked === 1 ? "{n} more is locked until this month's fee is settled." : "{n} more are locked until this month's fee is settled.", ['n' => $locked]);
        }

        return $this->reply($head . "\n" . implode("\n", $lines), 'quizzes',
            [['label' => t('Go to my classes'), 'href' => $this->classesLink()]]);
    }

    private function marks(): array
    {
        $stmt = $this->db->prepare(
            "SELECT q.title, c.class_name, a.percentage, q.pass_mark, q.total_marks, q.closes_at
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :sid
                AND a.attempt_status = 'graded'
              ORDER BY a.id DESC
              LIMIT 5"
        );
        $stmt->execute([':sid' => $this->studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->reply(
                $this->say("You haven't had a quiz marked yet. Marks show up here as soon as one is.", "{name} hasn't had a quiz marked yet. Marks show up here as soon as one is."),
                'marks'
            );
        }

        $lines = [];
        $sum = 0.0;
        foreach ($rows as $r) {
            $pct = (float) $r['percentage'];
            $sum += $pct;
            // pass_mark is already a percentage -- the quiz form labels it
            // "Pass mark (%)" and the results page compares it straight
            // against the score. Dividing it by total_marks, as the first
            // version did, turned a 50% pass mark on a 50-mark paper into
            // 100% and called an 80% a fail.
            $passMark = $r['pass_mark'] !== null ? (float) $r['pass_mark'] : 50.0;
            $lines[] = '- ' . $r['title'] . ' -- ' . round($pct) . '%' . ($pct >= $passMark ? '' : ' (' . t('below the pass mark') . ')');
        }

        $avg = round($sum / count($rows));

        return $this->reply(
            t('Latest marks:') . "\n" . implode("\n", $lines) . "\n\n" . t('Average over these: {n}%.', ['n' => $avg]),
            'marks',
            [['label' => t('All marks'), 'href' => $this->marksLink()]]
        );
    }

    private function progress(): array
    {
        $summary = (new ProgressService())->forStudent($this->studentId);

        // ProgressService says "nothing yet" with has_data = false and a
        // score of 0, not with a null. Reading the score alone would tell
        // a brand-new student they are at 0 out of 100.
        if (empty($summary['has_data'])) {
            return $this->reply(
                t("There isn't enough yet to measure progress -- it starts once there are quiz marks, attendance or material opened."),
                'progress'
            );
        }

        $level = (string) $summary['level'];
        $text = $this->say(
            "You're at {level} -- an overall score of {score} out of 100.",
            '{name} is at {level} -- an overall score of {score} out of 100.',
            ['level' => t($level), 'score' => round((float) $summary['score'])]
        );

        if (!empty($summary['summary'])) {
            $text .= "\n\n" . t((string) $summary['summary']);
        }

        $text .= "\n\n" . t('It combines quiz marks (60%), attendance (25%) and material opened (15%).');

        return $this->reply($text, 'progress', [['label' => t('See the full progress page'), 'href' => $this->progressLink()]]);
    }

    private function fees(): array
    {
        $month = ContentAccessService::normaliseMonth($this->now->format('Y-m-d'));
        $label = lang_date('F', $month);

        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, e.scholarship_type,
                    p.payment_status, p.amount_due, p.amount_paid
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN payments p ON p.student_id = e.student_id
                                   AND p.class_id = e.class_id
                                   AND p.month_year = :month
              WHERE e.student_id = :sid
                AND e.enrollment_status = 'active'
              ORDER BY c.class_name"
        );
        $stmt->execute([':sid' => $this->studentId, ':month' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return $this->reply($this->say('You are not enrolled in any classes right now.', '{name} is not enrolled in any classes right now.'), 'fees');
        }

        $lines = [];
        $owed = 0.0;

        foreach ($rows as $r) {
            if (($r['scholarship_type'] ?? '') === 'free_card') {
                $lines[] = '- ' . $r['class_name'] . ': ' . t('free card, nothing to pay');
                continue;
            }

            $status = $r['payment_status'];

            if ($status === null) {
                $lines[] = '- ' . $r['class_name'] . ': ' . t("the {month} fee hasn't been issued yet", ['month' => $label]);
                continue;
            }

            if (in_array($status, ['paid', 'waived'], true)) {
                $lines[] = '- ' . $r['class_name'] . ': ' . t($status === 'waived' ? 'waived' : 'paid') . ' ✓';
                continue;
            }

            $due = (float) $r['amount_due'] - (float) $r['amount_paid'];
            $owed += $due;
            $lines[] = '- ' . $r['class_name'] . ': ' . t($status === 'overdue' ? 'Rs. {n} overdue' : 'Rs. {n} to pay', ['n' => number_format($due, 2)]);
        }

        $head = t('{month} fees:', ['month' => $label]);
        $foot = $owed > 0
            ? "\n\n" . t("Outstanding: Rs. {n}. The notes, recordings and quizzes for those classes open again once it's paid.", ['n' => number_format($owed, 2)])
            : "\n\n" . t('Everything for {month} is settled.', ['month' => $label]);

        return $this->reply($head . "\n" . implode("\n", $lines) . $foot, 'fees',
            $this->audience === 'parent' ? [['label' => t('Fee history'), 'href' => BASE_URL . '/family/fees']] : []);
    }

    private function materials(): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.title, m.material_type, m.created_at, m.class_id, c.class_name
               FROM class_materials m
               JOIN classes c ON c.id = m.class_id
               JOIN enrollments e ON e.class_id = m.class_id
                                 AND e.student_id = :sid
                                 AND e.enrollment_status = 'active'
              WHERE m.status = 'published'
              ORDER BY m.created_at DESC, m.id DESC
              LIMIT 12"
        );
        $stmt->execute([':sid' => $this->studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $open = array_values(array_filter($rows, fn (array $r): bool => $this->isOpen((int) $r['class_id'])));
        $lockedCount = count($rows) - count($open);

        if ($open === []) {
            $text = t('Nothing new to open right now.');
            if ($lockedCount > 0) {
                $text .= "\n\n" . t("There is material in classes whose fee isn't settled this month -- it opens once it is.");
            }
            return $this->reply($text, 'materials', [['label' => t('My classes'), 'href' => $this->classesLink()]]);
        }

        $kinds = ['recording' => 'Recording', 'reading' => 'Notes', 'video' => 'Video', 'link' => 'Link'];
        $lines = [];
        foreach (array_slice($open, 0, 5) as $r) {
            $lines[] = '- ' . t($kinds[$r['material_type']] ?? 'Material') . ': ' . $r['title']
                . ' (' . $r['class_name'] . ', ' . lang_date('j M', (string) $r['created_at']) . ')';
        }

        return $this->reply(t('Latest:') . "\n" . implode("\n", $lines), 'materials',
            [['label' => t('Open my classes'), 'href' => $this->classesLink()]]);
    }

    private function teacher(): array
    {
        $name = $this->db->query(
            "SELECT full_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();

        $who = $name !== false ? (string) $name : t('the teacher');

        return $this->reply(
            t('Your science teacher is {who}.', ['who' => $who]) . "\n\n" . t('For a question about the lesson, ask at the next class, or contact the office at your branch.'),
            'teacher'
        );
    }

    private function subjectQuestion(): array
    {
        return $this->reply(
            t("That's a science question, and I'm only here for things like classes, marks and fees -- I don't explain lessons, because a wrong answer could cost you marks.") . "\n\n"
            . t('Check the notes and recordings for that lesson, or ask the teacher at the next class.'),
            'subject_question',
            [['label' => t('Open my classes'), 'href' => $this->classesLink()]],
            true
        );
    }

    private function fallback(): array
    {
        return $this->reply(
            t("Sorry, I didn't understand that one. I can tell you about classes, quizzes, exams, marks, progress, fees and new notes. Try one of these:"),
            'unknown',
            [],
            false
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<int, array{label: string, href: string}> $links
     */
    private function reply(string $answer, string $intent, array $links = [], bool $answered = true): array
    {
        return [
            'answer'      => $answer,
            'intent'      => $intent,
            'answered'    => $answered && $intent !== 'unknown',
            'links'       => $links,
            'suggestions' => in_array($intent, ['greeting', 'help', 'unknown'], true) ? self::SUGGESTIONS : [],
            // What each chip shows, in the reader's language. The chip still
            // sends the English question in `suggestions`, which is what
            // the intent matcher was written for.
            'suggestion_labels' => in_array($intent, ['greeting', 'help', 'unknown'], true)
                ? array_map(static fn (string $s): string => t($s), self::SUGGESTIONS)
                : [],
        ];
    }

    /**
     * One sentence in two versions: to the student ("You have ...") or
     * to a parent about their child ("{name} has ..."), translated.
     *
     * @param array<string, string|int|float> $vars
     */
    private function say(string $toStudent, string $toParent, array $vars = []): string
    {
        return $this->audience === 'parent'
            ? t($toParent, $vars + ['name' => $this->firstName()])
            : t($toStudent, $vars);
    }

    /**
     * @param array<int, string> $types
     * @return array<int, array<string, mixed>>
     */
    private function upcoming(int $days, array $types): array
    {
        $today = $this->now->setTime(0, 0);
        $items = $this->calendar->between($today, $today->modify("+{$days} days"), ['student_id' => $this->studentId]);
        $nowString = $this->now->format('Y-m-d H:i:s');

        return array_values(array_filter(
            $items,
            static fn (array $i): bool => in_array($i['type'], $types, true) && $i['starts_at'] > $nowString
        ));
    }

    /** @var array<int, bool>|null */
    private ?array $openMap = null;

    private function isOpen(int $classId): bool
    {
        if ($this->openMap === null) {
            $this->openMap = $this->access->openClassMap(
                $this->studentId,
                ContentAccessService::normaliseMonth($this->now->format('Y-m-d'))
            );
        }

        return $this->openMap[$classId] ?? false;
    }

    /**
     * "today at 08:00", "tomorrow at 08:00", or "Saturday 31 Oct at 08:00".
     *
     * With $withPreposition, a weekday gets its "on" -- "is on Saturday"
     * -- while today and tomorrow do not: "is on tomorrow" was what the
     * first version produced.
     */
    private function when(array $item, bool $withPreposition = false): string
    {
        $date = new DateTimeImmutable($item['date']);
        $today = $this->now->setTime(0, 0);
        $diff = (int) $today->diff($date)->format('%r%a');

        $day = match ($diff) {
            0       => t('today'),
            1       => t('tomorrow'),
            default => $withPreposition
                ? t('on {date}', ['date' => lang_date('l j M', $date->getTimestamp())])
                : lang_date('l j M', $date->getTimestamp()),
        };

        return $item['start_time'] !== null ? t('{day} at {time}', ['day' => $day, 'time' => $item['start_time']]) : $day;
    }

    private function shortWhen(array $item, bool $sameDay): string
    {
        $time = $item['start_time'] ?? t('All day');
        return $sameDay ? $time : lang_date('D', (new DateTimeImmutable($item['date']))->getTimestamp()) . ' ' . $time;
    }

    private function countdown(array $item): string
    {
        $at = new DateTimeImmutable($item['starts_at']);
        $minutes = (int) floor(($at->getTimestamp() - $this->now->getTimestamp()) / 60);

        return match (true) {
            $minutes < 1     => t('starting now'),
            $minutes < 60    => t('in {n} minutes', ['n' => $minutes]),
            $minutes < 120   => t('in about an hour'),
            $minutes < 1440  => t('in {n} hours', ['n' => intdiv($minutes, 60)]),
            $minutes < 2880  => t('tomorrow'),
            default          => t('in {n} days', ['n' => intdiv($minutes, 1440)]),
        };
    }

    private function dateTime(string $value): string
    {
        return lang_date('D j M, g:ia', $value);
    }

    private function firstName(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        return $parts[0] ?? t('your child');
    }

    /** "You have" / "Nimal has", depending on who is asking. */
    private function you(string $secondPerson, string $thirdPerson): string
    {
        return $this->audience === 'parent'
            ? $this->firstName() . ' ' . $thirdPerson
            : 'You ' . $secondPerson;
    }

    private function calendarLink(): string
    {
        return BASE_URL . ($this->audience === 'parent' ? '/family/calendar' : '/my/calendar');
    }

    private function classesLink(): string
    {
        return BASE_URL . ($this->audience === 'parent' ? '/family' : '/my/classes');
    }

    private function marksLink(): string
    {
        return BASE_URL . ($this->audience === 'parent' ? '/family/marks' : '/my/marks');
    }

    private function progressLink(): string
    {
        return BASE_URL . ($this->audience === 'parent' ? '/family' : '/my/progress');
    }
}
