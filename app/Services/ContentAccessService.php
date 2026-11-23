<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Decides whether a student may reach a class's material and quizzes.
 *
 * The rule the teacher asked for is simple to say -- "only students who
 * paid this month's fee" -- and easy to get subtly wrong in a dozen
 * places, so it lives here and nowhere else. Every controller that
 * serves class content asks this service, and no controller re-derives
 * it from `payments` on its own.
 *
 * Three things unlock a month, in this order:
 *
 *   1. A free_card scholarship. The student never owes a fee, so there
 *      is no payment row to look for and waiting for one would lock
 *      out exactly the students the teacher chose to help.
 *   2. A payment row for that student, class and month marked paid or
 *      waived.
 *   3. Nothing -- which is a lock, not an error.
 *
 * Staff are not students and are never gated; that check belongs to the
 * caller's role middleware, not here.
 */
class ContentAccessService
{
    private PDO $db;

    /** @var array<string, bool> Per-request memo: the same student and month get asked about repeatedly. */
    private array $memo = [];

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * Normalise any month input to the first of the month.
     *
     * Accepts "2026-10", "2026-10-17" or a DateTime-parsable string.
     * `payments.month_year` stores the first of the month, so a lookup
     * built from today's date would otherwise never match.
     */
    public static function normaliseMonth(?string $monthYear = null): string
    {
        if ($monthYear === null || trim($monthYear) === '') {
            return date('Y-m-01');
        }

        $value = trim($monthYear);

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value . '-01';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new \InvalidArgumentException("Unrecognised month: {$monthYear}");
        }

        return date('Y-m-01', $timestamp);
    }

    /**
     * The same, for a month that came off the URL.
     *
     * normaliseMonth() throws on nonsense, which is right when the
     * value comes from our own code: it is a programming mistake worth
     * hearing about. It is wrong for ?month= in the address bar, where
     * anyone can type anything and the answer should be a page, not a
     * 500. Nonsense falls back to the current month.
     */
    public static function monthFromRequest(?string $monthYear = null): string
    {
        try {
            return self::normaliseMonth($monthYear);
        } catch (\InvalidArgumentException) {
            return date('Y-m-01');
        }
    }

    /**
     * Is this student's fee settled for this class in this month?
     */
    public function hasPaidFor(int $studentId, int $classId, ?string $monthYear = null): bool
    {
        $month = self::normaliseMonth($monthYear);
        $key = "{$studentId}:{$classId}:{$month}";

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolveAccess($studentId, $classId, $month);
    }

    /**
     * The uncached decision.
     */
    private function resolveAccess(int $studentId, int $classId, string $month): bool
    {
        // An inactive enrolment is not access, whatever was paid before.
        $sql = "SELECT scholarship_type
                  FROM enrollments
                 WHERE student_id = :student_id
                   AND class_id = :class_id
                   AND enrollment_status = 'active'
                 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId, ':class_id' => $classId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($enrollment === false) {
            return false;
        }

        if (($enrollment['scholarship_type'] ?? '') === 'free_card') {
            return true;
        }

        $sql = "SELECT 1
                  FROM payments
                 WHERE student_id = :student_id
                   AND class_id = :class_id
                   AND month_year = :month_year
                   AND payment_status IN ('paid', 'waived')
                 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':class_id'   => $classId,
            ':month_year' => $month,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * The same decision, but with the reason attached.
     *
     * A locked page that cannot say why is a support call. This returns
     * enough for the view to say "your October fee is outstanding" and
     * show the amount, rather than an unexplained empty shelf.
     *
     * @return array{allowed: bool, reason: string, message: string, amount_due: float|null, month_year: string}
     */
    public function gateStatus(int $studentId, int $classId, ?string $monthYear = null): array
    {
        $month = self::normaliseMonth($monthYear);
        $label = date('F Y', strtotime($month));

        $sql = "SELECT scholarship_type
                  FROM enrollments
                 WHERE student_id = :student_id
                   AND class_id = :class_id
                   AND enrollment_status = 'active'
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId, ':class_id' => $classId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($enrollment === false) {
            return [
                'allowed'    => false,
                'reason'     => 'not_enrolled',
                'message'    => 'You are not enrolled in this class.',
                'amount_due' => null,
                'month_year' => $month,
            ];
        }

        if (($enrollment['scholarship_type'] ?? '') === 'free_card') {
            return [
                'allowed'    => true,
                'reason'     => 'free_card',
                'message'    => 'Open on a free card.',
                'amount_due' => 0.0,
                'month_year' => $month,
            ];
        }

        $sql = "SELECT payment_status, amount_due, amount_paid
                  FROM payments
                 WHERE student_id = :student_id
                   AND class_id = :class_id
                   AND month_year = :month_year
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':class_id'   => $classId,
            ':month_year' => $month,
        ]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // No row yet means the month's fees have not been generated.
        // That is the office's doing, not the student's, so the message
        // must not read as an accusation.
        if ($payment === false) {
            return [
                'allowed'    => false,
                'reason'     => 'not_generated',
                'message'    => "The {$label} fee has not been issued yet. This class opens once it is.",
                'amount_due' => null,
                'month_year' => $month,
            ];
        }

        $status = $payment['payment_status'] ?? 'pending';
        $due = (float) ($payment['amount_due'] ?? 0);

        if (in_array($status, ['paid', 'waived'], true)) {
            return [
                'allowed'    => true,
                'reason'     => $status,
                'message'    => $status === 'waived'
                    ? "Your {$label} fee was waived."
                    : "Your {$label} fee is settled.",
                'amount_due' => $due,
                'month_year' => $month,
            ];
        }

        return [
            'allowed'    => false,
            'reason'     => $status === 'overdue' ? 'overdue' : 'unpaid',
            'message'    => $status === 'overdue'
                ? "Your {$label} fee is overdue. Settle it to reopen this class."
                : "Your {$label} fee is not settled yet.",
            'amount_due' => $due,
            'month_year' => $month,
        ];
    }

    /**
     * Which of a student's classes are open this month.
     *
     * One query rather than one per class: the student dashboard lists
     * every enrolled class at once, and asking per row turns a six-class
     * student into thirteen round trips.
     *
     * @return array<int, bool> class_id => allowed
     */
    public function openClassMap(int $studentId, ?string $monthYear = null): array
    {
        $month = self::normaliseMonth($monthYear);

        $sql = "SELECT e.class_id,
                       e.scholarship_type,
                       p.payment_status
                  FROM enrollments e
                  LEFT JOIN payments p
                         ON p.student_id = e.student_id
                        AND p.class_id   = e.class_id
                        AND p.month_year = :month_year
                 WHERE e.student_id = :student_id
                   AND e.enrollment_status = 'active'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':student_id' => $studentId, ':month_year' => $month]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classId = (int) $row['class_id'];
            $map[$classId] = $row['scholarship_type'] === 'free_card'
                || in_array($row['payment_status'] ?? '', ['paid', 'waived'], true);

            // Warm the memo so a later per-class check costs nothing.
            $this->memo["{$studentId}:{$classId}:{$month}"] = $map[$classId];
        }

        return $map;
    }
}
