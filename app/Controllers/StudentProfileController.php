<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\MotivationService;
use App\Services\ProgressService;
use App\Services\StudentRecordService;
use PDO;

/**
 * A student's profile, their receipts, and the reports they download.
 *
 * Whose record it is comes from the session only: a student sees their
 * own; a parent sees their own child's receipts and reports. No route
 * here takes a student id, and a receipt id from the URL is only ever
 * looked up together with that student, so another family's receipt is
 * a 404, not a leak.
 */
class StudentProfileController extends BaseController
{
    private const CSV_TYPES = ['marks', 'attendance', 'payments'];
    private const SECTIONS = ['summary', 'marks', 'attendance', 'payments'];

    private StudentRecordService $records;

    public function __construct()
    {
        parent::__construct();
        $this->records = new StudentRecordService();
    }

    /**
     * The profile page. Students only -- a parent's equivalent is /family.
     */
    public function profile(): void
    {
        $studentId = $this->resolveStudent(false);

        $student = $this->records->student($studentId);
        $marks = $this->records->marks($studentId);
        $attendance = $this->records->attendance($studentId);
        $payments = $this->records->payments($studentId);
        $progress = (new ProgressService())->forStudent($studentId);

        $this->view('student/profile', [
            'pageTitle'  => t('My Profile'),
            // The same unpaid-months figure the QR terminal announces, so
            // a student who heard the alert at the desk sees the identical
            // months and total here rather than a second opinion.
            'feeDue'     => (new \App\Services\PaymentDueService())->summary($studentId),
            'student'    => $student,
            'guardians'  => $this->records->guardians($studentId),
            'classes'    => $this->records->classes($studentId),
            'payments'   => $payments,
            'marks'      => array_slice($marks, 0, 5),
            'summary'    => StudentRecordService::summarise($marks, $attendance, $payments),
            'progress'   => $progress,
            'motivation' => (new MotivationService())->forStudent($studentId, $progress),
            'report'     => $this->reportDefaults(),
        ]);
    }

    /**
     * A printable receipt for one settled payment.
     */
    public function receipt(string $id): void
    {
        $studentId = $this->resolveStudent(true);
        $receipt = $this->records->receipt($studentId, (int) $id);

        if ($receipt === null) {
            http_response_code(404);
            $this->flash('error', t('That receipt was not found.'));
            $this->redirect(session_get('user_type') === 'parent' ? '/family/fees' : '/my/profile');
        }

        // Its own page, without the site's layout: it is meant to be
        // printed or saved as a PDF and handed over.
        $this->view('student/receipt', ['receipt' => $receipt], null);
    }

    /**
     * The report, built from the options chosen, as a printable page.
     */
    public function report(): void
    {
        $studentId = $this->resolveStudent(true);
        $opts = $this->reportOptions($studentId);

        $marks = in_array('marks', $opts['sections'], true) || in_array('summary', $opts['sections'], true)
            ? $this->records->marks($studentId, $opts['from'], $opts['to'], $opts['class_id']) : [];
        $attendance = in_array('attendance', $opts['sections'], true) || in_array('summary', $opts['sections'], true)
            ? $this->records->attendance($studentId, $opts['from'], $opts['to'], $opts['class_id']) : [];
        $payments = in_array('payments', $opts['sections'], true) || in_array('summary', $opts['sections'], true)
            ? $this->records->payments($studentId, $opts['from'], $opts['to'], $opts['class_id']) : [];

        $this->view('student/report', [
            'student'    => $this->records->student($studentId),
            'opts'       => $opts,
            'classes'    => $this->records->classes($studentId),
            'marks'      => $marks,
            'attendance' => $attendance,
            'payments'   => $payments,
            'summary'    => StudentRecordService::summarise($marks, $attendance, $payments),
            'generated'  => (string) $this->db->query('SELECT NOW()')->fetchColumn(),
        ], null);
    }

    /**
     * The same data as a spreadsheet: one table per download.
     */
    public function reportCsv(): void
    {
        $studentId = $this->resolveStudent(true);
        $opts = $this->reportOptions($studentId);
        $type = in_array($_GET['type'] ?? '', self::CSV_TYPES, true) ? $_GET['type'] : 'marks';
        $student = $this->records->student($studentId);

        [$headers, $rows] = match ($type) {
            'attendance' => [
                [t('Date'), t('Class'), t('Status'), t('Arrived at')],
                array_map(static fn ($a) => [$a['date'], $a['class_name'], t(ucfirst((string) $a['status'])), (string) $a['check_in_time']],
                    $this->records->attendance($studentId, $opts['from'], $opts['to'], $opts['class_id'])),
            ],
            'payments' => [
                [t('Month'), t('Class'), t('Amount due'), t('Amount paid'), t('Status'), t('Paid on'), t('Receipt number')],
                array_map(static fn ($p) => [
                    date('Y-m', strtotime((string) $p['month_year'])), $p['class_name'],
                    number_format((float) $p['amount_due'], 2, '.', ''), number_format((float) $p['amount_paid'], 2, '.', ''),
                    t(ucfirst((string) $p['payment_status'])), (string) $p['paid_at'],
                    in_array($p['payment_status'], ['paid', 'waived'], true)
                        ? StudentRecordService::receiptNumber((int) $p['id'], (string) $p['month_year']) : '',
                ], $this->records->payments($studentId, $opts['from'], $opts['to'], $opts['class_id'])),
            ],
            default => [
                [t('Quiz'), t('Class'), t('Month'), t('Score'), t('Out of'), t('Percentage'), t('Result'), t('Handed in')],
                array_map(static fn ($m) => [
                    $m['title'], $m['class_name'], date('Y-m', strtotime((string) $m['month_year'])),
                    (string) $m['score'], (string) $m['total_marks'],
                    $m['percentage'] !== null ? (string) round((float) $m['percentage'], 1) : '',
                    $m['attempt_status'] !== 'graded' ? t('Waiting to be marked')
                        : ((float) $m['percentage'] >= (float) $m['pass_mark'] ? t('Passed') : t('Not passed')),
                    (string) $m['submitted_at'],
                ], $this->records->marks($studentId, $opts['from'], $opts['to'], $opts['class_id'])),
            ],
        };

        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($student['full_name'] ?? 'student'));
        $filename = trim((string) $name, '-') . "_{$type}_" . substr($opts['from'], 0, 7) . '_to_' . substr($opts['to'], 0, 7) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        $out = fopen('php://output', 'wb');
        // A byte-order mark, or Excel opens UTF-8 as gibberish -- and the
        // headings are Sinhala when the site is.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], $row));
        }
        fclose($out);
        exit;
    }

    /**
     * Neutralise a cell that a spreadsheet would run as a formula.
     *
     * Quiz and class names are typed by staff. One that began with "="
     * would execute when the file is opened in Excel, so such cells get
     * a leading apostrophe and are shown as the plain text they are.
     */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)
            ? "'" . $value
            : $value;
    }

    // ---------- helpers ----------

    /**
     * Whose record this is, from the session and nothing else.
     */
    private function resolveStudent(bool $allowParent): int
    {
        AuthMiddleware::requireAuth();
        $type = session_get('user_type');

        if ($type === 'student') {
            return (int) session_get('user_id');
        }

        if ($type === 'parent' && $allowParent) {
            $stmt = $this->db->prepare("SELECT student_id FROM parents WHERE id = :id AND status = 'active' LIMIT 1");
            $stmt->execute([':id' => (int) session_get('user_id')]);
            $child = $stmt->fetchColumn();
            if ($child !== false) {
                return (int) $child;
            }
        }

        $this->redirect('/dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function reportDefaults(): array
    {
        $to = date('Y-m-01');

        return [
            'from'     => date('Y-m-01', strtotime($to . ' -2 months')),
            'to'       => $to,
            'class_id' => null,
            'sections' => self::SECTIONS,
        ];
    }

    /**
     * Read and validate the report options. Anything malformed falls
     * back to the default; a class the student is not in is ignored
     * rather than trusted.
     *
     * @return array<string, mixed>
     */
    private function reportOptions(int $studentId): array
    {
        $opts = $this->reportDefaults();

        foreach (['from', 'to'] as $key) {
            $value = (string) ($_GET[$key] ?? '');
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
                $opts[$key] = $value . '-01';
            }
        }

        if ($opts['from'] > $opts['to']) {
            [$opts['from'], $opts['to']] = [$opts['to'], $opts['from']];
        }

        $classId = (int) ($_GET['class'] ?? 0);
        $mine = array_map(static fn ($c) => (int) $c['id'], $this->records->classes($studentId));
        $opts['class_id'] = in_array($classId, $mine, true) ? $classId : null;

        $sections = array_values(array_intersect(self::SECTIONS, (array) ($_GET['sections'] ?? [])));
        if ($sections !== []) {
            $opts['sections'] = $sections;
        }

        return $opts;
    }
}
