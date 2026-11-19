<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * What a student still owes, and whether that is late yet.
 *
 * The office asked for this because of a pattern they could see but not
 * act on: fees are collected in the first week or two of the month, and
 * a family that misses that window often keeps attending anyway. By the
 * time anyone notices, several months have gone by. Nobody at the desk
 * can hold thirty students' fee history in their head while a queue is
 * forming, so the system has to say it at the moment the student is in
 * front of them -- at the scanner, and on the two profiles.
 *
 * What counts as late
 * -------------------
 * A month is only "late" once the grace period for that month has run
 * out. Classes differ -- some collect in week one, some in week two --
 * so the cut-off is a setting (payments_due_day), not a constant, and
 * it only applies to the month now in progress. Any earlier unpaid
 * month is late regardless: its grace period is long past.
 *
 * This means a family that has simply not paid yet on the 3rd is not
 * accused of anything, which matters -- the alert is meant to carry
 * weight, and it only does that if it is never wrong.
 *
 * Waived fees (a free card, a scholarship) are not debts and never
 * appear here.
 */
final class PaymentDueService
{
    /** Day of the month by which that month's fee is expected. */
    public const SETTING_DUE_DAY = 'payments_due_day';
    private const DEFAULT_DUE_DAY = 10;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * The day of the month a fee becomes late, 1-28.
     *
     * Capped at 28 so every month has the day; past that, February would
     * silently never reach its own deadline.
     */
    public function dueDay(): int
    {
        try {
            $raw = (new \Setting())->get(self::SETTING_DUE_DAY);
        } catch (\Throwable $e) {
            $raw = null;
        }

        $day = (int) ($raw ?? self::DEFAULT_DUE_DAY);

        return max(1, min(28, $day === 0 ? self::DEFAULT_DUE_DAY : $day));
    }

    /**
     * Every unpaid month for a student that is already late, oldest
     * first.
     *
     * @return list<array{month: string, label: string, class_name: string, balance: float}>
     */
    public function overdueMonths(int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }

        // The newest month that can be counted late. Before the cut-off
        // day, the month in progress is still within its grace period,
        // so the newest countable month is the one before it.
        $cutoff = (int) date('j') >= $this->dueDay()
            ? date('Y-m-01')
            : date('Y-m-01', strtotime('first day of last month'));

        $sql = "SELECT p.month_year,
                       COALESCE(c.class_name, '') AS class_name,
                       (p.amount_due - p.amount_paid) AS balance
                  FROM payments p
             LEFT JOIN classes c ON c.id = p.class_id
                 WHERE p.student_id = :sid
                   AND p.payment_status IN ('pending', 'overdue')
                   AND p.month_year <= :cutoff
                   AND (p.amount_due - p.amount_paid) > 0
              ORDER BY p.month_year ASC, c.class_name ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':sid' => $studentId, ':cutoff' => $cutoff]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // A reporting feature must never block the desk: a scan still
            // has to register the student even if this cannot be read.
            error_log('PaymentDueService::overdueMonths failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $month = (string) $r['month_year'];
            $out[] = [
                'month'      => $month,
                'label'      => $this->monthLabel($month),
                'class_name' => (string) $r['class_name'],
                'balance'    => round((float) $r['balance'], 2),
            ];
        }

        return $out;
    }

    /**
     * The same thing boiled down for a banner or an alert.
     *
     * @return array{overdue: bool, count: int, total: float, months: list<string>, detail: list<array{month: string, label: string, class_name: string, balance: float}>}
     */
    public function summary(int $studentId): array
    {
        $detail = $this->overdueMonths($studentId);

        $months = [];
        $total = 0.0;
        foreach ($detail as $d) {
            $total += $d['balance'];
            if (!in_array($d['label'], $months, true)) {
                $months[] = $d['label'];
            }
        }

        return [
            'overdue' => $detail !== [],
            'count'   => count($months),
            'total'   => round($total, 2),
            'months'  => $months,
            'detail'  => $detail,
        ];
    }

    /** "2026-09-01" -> "September 2026", translated where the site is. */
    private function monthLabel(string $monthYear): string
    {
        $ts = strtotime($monthYear);
        if ($ts === false) {
            return $monthYear;
        }

        if (function_exists('lang_date')) {
            return (string) lang_date('F Y', $monthYear);
        }

        return date('F Y', $ts);
    }
}
