<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * One student's record, for their profile, their receipts and the
 * reports they download.
 *
 * Every method takes the student id as its first argument and filters
 * on it in SQL. The controller decides that id from the session -- the
 * student themselves, or a parent's own child -- and nothing a request
 * carries can change it. A receipt id from the URL is only ever looked
 * up together with that student id, so someone else's receipt is simply
 * not found.
 */
class StudentRecordService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function student(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, student_id_display, full_name, email, phone1, phone2, whatsapp,
                    date_of_birth, address, province, district, profile_photo, status, created_at
               FROM students WHERE id = :id"
        );
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function guardians(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT parent_name, phone1, whatsapp FROM parents WHERE student_id = :id AND status = 'active' ORDER BY id"
        );
        $stmt->execute([':id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function classes(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.schedule_days, c.start_time, c.room,
                    i.name AS institute_name, u.full_name AS teacher_name,
                    e.scholarship_type, e.enrolled_at
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               LEFT JOIN users u ON u.id = c.teacher_id
              WHERE e.student_id = :id AND e.enrollment_status = 'active'
              ORDER BY c.grade, c.class_name"
        );
        $stmt->execute([':id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Payments, newest month first, optionally narrowed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payments(int $studentId, ?string $from = null, ?string $to = null, ?int $classId = null): array
    {
        [$where, $params] = $this->range('p.month_year', $studentId, $from, $to, $classId, 'p.class_id');

        $stmt = $this->db->prepare(
            "SELECT p.id, p.month_year, p.amount_due, p.amount_paid, p.payment_status, p.paid_at,
                    p.scholarship_type, c.class_name, i.name AS institute_name
               FROM payments p
               JOIN classes c ON c.id = p.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE p.student_id = :sid {$where}
              ORDER BY p.month_year DESC, c.class_name"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * One receipt -- only if it is this student's, and actually settled.
     *
     * @return array<string, mixed>|null
     */
    public function receipt(int $studentId, int $paymentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.month_year, p.amount_due, p.amount_paid, p.payment_status, p.paid_at,
                    p.scholarship_type, p.notes,
                    c.class_name, c.grade,
                    i.name AS institute_name, i.location AS institute_location, i.phone1 AS institute_phone,
                    u.full_name AS received_by,
                    s.full_name AS student_name, s.student_id_display
               FROM payments p
               JOIN students s ON s.id = p.student_id
               JOIN classes c ON c.id = p.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               LEFT JOIN users u ON u.id = p.marked_by
              WHERE p.id = :pid
                AND p.student_id = :sid
                AND p.payment_status IN ('paid', 'waived')
              LIMIT 1"
        );
        $stmt->execute([':pid' => $paymentId, ':sid' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['receipt_number'] = self::receiptNumber((int) $row['id'], (string) $row['month_year']);

        return $row;
    }

    /**
     * A stable receipt number. The payments table has none of its own,
     * so it is built from the month and the record id -- the same
     * payment always prints the same number, and no two collide.
     */
    public static function receiptNumber(int $paymentId, string $monthYear): string
    {
        return 'R-' . date('Ym', strtotime($monthYear)) . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function marks(int $studentId, ?string $from = null, ?string $to = null, ?int $classId = null): array
    {
        [$where, $params] = $this->range('q.month_year', $studentId, $from, $to, $classId, 'q.class_id');

        $stmt = $this->db->prepare(
            "SELECT a.id, q.title, q.month_year, c.class_name, a.score, a.total_marks, a.percentage,
                    COALESCE(q.pass_mark, 50) AS pass_mark, a.attempt_status, a.submitted_at
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :sid
                AND a.attempt_status IN ('submitted', 'graded') {$where}
              ORDER BY a.submitted_at DESC, a.id DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attendance(int $studentId, ?string $from = null, ?string $to = null, ?int $classId = null): array
    {
        $where = '';
        $params = [':sid' => $studentId];

        if ($from !== null) {
            $where .= ' AND a.date >= :from';
            $params[':from'] = $from;
        }
        if ($to !== null) {
            // Through the end of the "to" month.
            $where .= ' AND a.date <= LAST_DAY(:to)';
            $params[':to'] = $to;
        }
        if ($classId !== null) {
            $where .= ' AND a.class_id = :cid';
            $params[':cid'] = $classId;
        }

        $stmt = $this->db->prepare(
            "SELECT a.date, a.status, a.check_in_time, c.class_name
               FROM attendance a
               JOIN classes c ON c.id = a.class_id
              WHERE a.student_id = :sid {$where}
              ORDER BY a.date DESC, a.id DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Headline numbers for a set of rows already fetched.
     *
     * @param array<int, array<string, mixed>> $marks
     * @param array<int, array<string, mixed>> $attendance
     * @param array<int, array<string, mixed>> $payments
     * @return array<string, mixed>
     */
    public static function summarise(array $marks, array $attendance, array $payments): array
    {
        $graded = array_values(array_filter($marks, static fn ($m) => $m['attempt_status'] === 'graded' && $m['percentage'] !== null));
        $pcts = array_map(static fn ($m) => (float) $m['percentage'], $graded);
        $present = count(array_filter($attendance, static fn ($a) => $a['status'] !== 'absent'));

        $due = 0.0;
        $paid = 0.0;
        foreach ($payments as $p) {
            $due += (float) $p['amount_due'];
            $paid += (float) $p['amount_paid'];
        }

        return [
            'quizzes'       => count($graded),
            'average'       => $pcts ? round(array_sum($pcts) / count($pcts), 1) : null,
            'best'          => $pcts ? max($pcts) : null,
            'passed'        => count(array_filter($graded, static fn ($m) => (float) $m['percentage'] >= (float) $m['pass_mark'])),
            'sessions'      => count($attendance),
            'present'       => $present,
            'attendance'    => $attendance ? round($present / count($attendance) * 100, 1) : null,
            'fees_due'      => $due,
            'fees_paid'     => $paid,
            'fees_owing'    => max(0.0, $due - $paid),
        ];
    }

    /**
     * Month range and class filter, with bound parameters.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function range(string $monthColumn, int $studentId, ?string $from, ?string $to, ?int $classId, string $classColumn): array
    {
        $where = '';
        $params = [':sid' => $studentId];

        if ($from !== null) {
            $where .= " AND {$monthColumn} >= :from";
            $params[':from'] = $from;
        }
        if ($to !== null) {
            $where .= " AND {$monthColumn} <= :to";
            $params[':to'] = $to;
        }
        if ($classId !== null) {
            $where .= " AND {$classColumn} = :cid";
            $params[':cid'] = $classId;
        }

        return [$where, $params];
    }
}
