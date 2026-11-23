<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\ContentAccessService;
use PDO;

/**
 * The owner's view across all three branches.
 *
 * The existing reports answer "list me the X" one entity at a time.
 * This one answers the questions the person paying for the place
 * actually asks: which branch is carrying the business, who is not
 * collecting, and where are the marks slipping.
 *
 * Everything is computed over a chosen month so the three panels agree
 * with each other. A revenue figure for October beside a pass rate for
 * "all time" is two different stories printed side by side.
 */
class InsightsController extends BaseController
{
    public function index(): void
    {
        // Money across every branch is the owner's business, not a
        // branch coordinator's, so this sits above the coordinator line.
        AuthMiddleware::requireAdmin();

        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        $this->view('reports/insights', [
            'pageTitle'   => 'Branch insights',
            'activePage'  => 'reports',
            'month'       => $month,
            'months'      => $this->monthOptions(),
            'branches'    => $this->branchFinancials($month),
            'totals'      => $this->monthTotals($month),
            'trend'       => $this->revenueTrend(6),
            'classes'     => $this->classPerformance($month),
            'strugglers'  => $this->studentsNeedingAttention(),
            'topStudents' => $this->topStudents(),
            'lastSatMonth' => $this->lastMonthWithPapers(),
        ]);
    }

    /**
     * Revenue, collection and headcount per branch for one month.
     *
     * Collection rate is the figure that matters more than revenue: a
     * branch billing a lot and collecting little looks healthy on a
     * revenue line and is not.
     *
     * @return array<int, array<string, mixed>>
     */
    private function branchFinancials(string $month): array
    {
        // Each figure is aggregated in its own subquery, deliberately.
        //
        // Joining classes, enrolments and payments in one GROUP BY looks
        // tidier and silently lies: a class with 20 enrolments repeats
        // every payment row 20 times, so SUM(amount_due) comes back
        // twentyfold. The branch table was reporting Rs. 187,125 billed
        // against a true month total of Rs. 82,625 before this was split
        // out. COUNT(DISTINCT ...) hid the same fan-out on the counts.
        $sql = "SELECT i.id, i.name,
                       COALESCE(cl.classes, 0)        AS classes,
                       COALESCE(en.students, 0)       AS students,
                       COALESCE(pay.billed, 0)        AS billed,
                       COALESCE(pay.collected, 0)     AS collected,
                       COALESCE(pay.paid_count, 0)    AS paid_count,
                       COALESCE(pay.overdue_count, 0) AS overdue_count,
                       COALESCE(pay.pending_count, 0) AS pending_count
                  FROM institutes i
                  LEFT JOIN (
                            SELECT c.institute_id, COUNT(*) AS classes
                              FROM classes c
                             WHERE c.status = 'active'
                             GROUP BY c.institute_id
                       ) cl ON cl.institute_id = i.id
                  LEFT JOIN (
                            SELECT c.institute_id,
                                   COUNT(DISTINCT e.student_id) AS students
                              FROM enrollments e
                              JOIN classes c ON c.id = e.class_id AND c.status = 'active'
                             WHERE e.enrollment_status = 'active'
                             GROUP BY c.institute_id
                       ) en ON en.institute_id = i.id
                  LEFT JOIN (
                            SELECT c.institute_id,
                                   SUM(p.amount_due)  AS billed,
                                   SUM(p.amount_paid) AS collected,
                                   SUM(CASE WHEN p.payment_status = 'paid'    THEN 1 ELSE 0 END) AS paid_count,
                                   SUM(CASE WHEN p.payment_status = 'overdue' THEN 1 ELSE 0 END) AS overdue_count,
                                   SUM(CASE WHEN p.payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_count
                              FROM payments p
                              JOIN classes c ON c.id = p.class_id AND c.status = 'active'
                             WHERE p.month_year = :month
                             GROUP BY c.institute_id
                       ) pay ON pay.institute_id = i.id
                 WHERE i.status = 'active'" . BranchScope::current()->instituteSql('i.id') . "
                 ORDER BY COALESCE(pay.collected, 0) DESC, i.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':month' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $billed = (float) $row['billed'];
            $row['collection_rate'] = $billed > 0
                ? round(((float) $row['collected'] / $billed) * 100, 1)
                : null;
            $row['outstanding'] = $billed - (float) $row['collected'];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function monthTotals(string $month): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount_due), 0)  AS billed,
                    COALESCE(SUM(amount_paid), 0) AS collected,
                    COUNT(*)                      AS invoices,
                    SUM(CASE WHEN payment_status IN ('pending','overdue') THEN 1 ELSE 0 END) AS unpaid
               FROM payments WHERE month_year = :month" . BranchScope::current()->classSql('class_id')
        );
        $stmt->execute([':month' => $month]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $billed = (float) ($row['billed'] ?? 0);

        return [
            'billed'      => $billed,
            'collected'   => (float) ($row['collected'] ?? 0),
            'outstanding' => $billed - (float) ($row['collected'] ?? 0),
            'invoices'    => (int) ($row['invoices'] ?? 0),
            'unpaid'      => (int) ($row['unpaid'] ?? 0),
            'rate'        => $billed > 0 ? round(((float) $row['collected'] / $billed) * 100, 1) : null,
        ];
    }

    /**
     * Billed against collected for the last N months.
     *
     * Both lines, not just revenue: the gap between them is the part
     * worth watching, and a single collected line hides a month where
     * billing doubled and collection did not.
     *
     * @return array<int, array<string, mixed>>
     */
    private function revenueTrend(int $months): array
    {
        $stmt = $this->db->prepare(
            "SELECT month_year,
                    COALESCE(SUM(amount_due), 0)  AS billed,
                    COALESCE(SUM(amount_paid), 0) AS collected
               FROM payments
              WHERE month_year >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :months MONTH)" . BranchScope::current()->classSql('class_id') . "
              GROUP BY month_year
              ORDER BY month_year ASC"
        );
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Per class: how many sat the month's quizzes, and how they did.
     *
     * @return array<int, array<string, mixed>>
     */
    private function classPerformance(string $month): array
    {
        // Same fan-out trap as the branch query: enrolments multiply the
        // attempt rows, which leaves AVG() unharmed but inflates the
        // attempt and pass counts. Marks and headcount are therefore
        // aggregated apart and joined on class id.
        $sql = "SELECT c.id, c.class_name, c.grade,
                       i.name AS institute_name,
                       COALESCE(en.enrolled, 0) AS enrolled,
                       COALESCE(qz.sat, 0)      AS sat,
                       qz.average               AS average,
                       COALESCE(qz.passed, 0)   AS passed,
                       COALESCE(qz.attempts, 0) AS attempts
                  FROM classes c
                  LEFT JOIN institutes i ON i.id = c.institute_id
                  LEFT JOIN (
                            SELECT e.class_id, COUNT(DISTINCT e.student_id) AS enrolled
                              FROM enrollments e
                             WHERE e.enrollment_status = 'active'
                             GROUP BY e.class_id
                       ) en ON en.class_id = c.id
                  LEFT JOIN (
                            SELECT q.class_id,
                                   COUNT(DISTINCT a.student_id) AS sat,
                                   AVG(a.percentage)            AS average,
                                   COUNT(a.id)                  AS attempts,
                                   SUM(CASE WHEN a.percentage >= COALESCE(q.pass_mark, 50) THEN 1 ELSE 0 END) AS passed
                              FROM quizzes q
                              JOIN quiz_attempts a
                                   ON a.quiz_id = q.id AND a.attempt_status = 'graded'
                             WHERE q.month_year = :month AND q.quiz_status = 'published'
                             GROUP BY q.class_id
                       ) qz ON qz.class_id = c.id
                 WHERE c.status = 'active'
                   AND COALESCE(en.enrolled, 0) > 0" . BranchScope::current()->classSql('c.id') . "
                 -- Classes with no sat quizzes sort last: an empty class is
                 -- not the weakest class. These are plain columns from the
                 -- subquery now, not aggregates, so they order directly.
                 ORDER BY (qz.average IS NULL), qz.average ASC, c.class_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':month' => $month]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Students whose marks are low across everything they have sat.
     *
     * Deliberately not "lowest single score" -- one bad paper is a bad
     * day, and a list built from it sends the teacher after the wrong
     * children. Two or more sat quizzes is the threshold for an average
     * to mean anything.
     *
     * @return array<int, array<string, mixed>>
     */
    private function studentsNeedingAttention(): array
    {
        $sql = "SELECT s.id, s.full_name, s.student_id_display,
                       COUNT(a.id)       AS quizzes_sat,
                       AVG(a.percentage) AS average,
                       GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ') AS classes
                  FROM students s
                  JOIN quiz_attempts a ON a.student_id = s.id AND a.attempt_status = 'graded'
                  JOIN quizzes q ON q.id = a.quiz_id
                  JOIN classes c ON c.id = q.class_id
                 WHERE s.status = 'active'" . BranchScope::current()->classSql('q.class_id') . "
                 GROUP BY s.id, s.full_name, s.student_id_display
                HAVING quizzes_sat >= 2 AND average < 50
                 ORDER BY average ASC
                 LIMIT 15";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topStudents(): array
    {
        $sql = "SELECT s.id, s.full_name, s.student_id_display,
                       COUNT(a.id)       AS quizzes_sat,
                       AVG(a.percentage) AS average
                  FROM students s
                  JOIN quiz_attempts a ON a.student_id = s.id AND a.attempt_status = 'graded'
                  JOIN quizzes q ON q.id = a.quiz_id
                 WHERE s.status = 'active'" . BranchScope::current()->classSql('q.class_id') . "
                 GROUP BY s.id, s.full_name, s.student_id_display
                HAVING quizzes_sat >= 2
                 ORDER BY average DESC
                 LIMIT 10";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The most recent month anyone actually sat a paper in.
     *
     * The page opens on the current month, which early on contains fees
     * but no marks yet -- every class reads "nobody sat", which looks
     * like a broken report rather than a month in progress. The view
     * uses this to say so plainly and offer the last month with marks.
     * Quietly showing a different month's marks under this month's
     * heading would be the dishonest fix.
     */
    private function lastMonthWithPapers(): ?string
    {
        $month = $this->db->query(
            "SELECT q.month_year
               FROM quizzes q
               JOIN quiz_attempts a
                    ON a.quiz_id = q.id AND a.attempt_status = 'graded'
              WHERE q.quiz_status = 'published'" . BranchScope::current()->classSql('q.class_id') . "
              ORDER BY q.month_year DESC
              LIMIT 1"
        )->fetchColumn();

        return $month !== false ? (string) $month : null;
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(): array
    {
        $options = [];

        for ($i = 0; $i < 6; $i++) {
            $month = date('Y-m-01', strtotime("-{$i} month"));
            $options[$month] = date('F Y', strtotime($month));
        }

        return $options;
    }
}
