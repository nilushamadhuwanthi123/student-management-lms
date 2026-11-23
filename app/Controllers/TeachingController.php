<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ContentAccessService;
use PDO;

/**
 * The teaching hub: every class on one page, with what has been posted
 * to it and what is waiting.
 *
 * Material and quizzes both live under a class, so without this page
 * the only way to reach either is to remember a class id. This is the
 * one screen the teacher actually starts her day on.
 */
class TeachingController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::requireTeacher();

        $role = (string) session_get('user_role');
        $userId = (int) session_get('user_id');
        $month = ContentAccessService::monthFromRequest($_GET['month'] ?? null);

        // A teacher sees her own classes; coordinators and above see all.
        // Written as one query with a conditional clause rather than two
        // near-identical ones, so the shape of the result cannot drift.
        $seesEverything = in_array($role, ['super_admin', 'admin', 'coordinator'], true);

        $sql = "SELECT c.id, c.class_name, c.grade, c.subject, c.monthly_fee,
                       c.schedule_days, c.start_time,
                       i.name AS institute_name,
                       (SELECT COUNT(*) FROM enrollments e
                         WHERE e.class_id = c.id AND e.enrollment_status = 'active') AS students,
                       (SELECT COUNT(*) FROM class_materials m
                         WHERE m.class_id = c.id AND m.status = 'published'
                           AND m.month_year = :month1) AS materials_this_month,
                       (SELECT COUNT(*) FROM quizzes q
                         WHERE q.class_id = c.id AND q.quiz_status = 'published'
                           AND q.month_year = :month2) AS quizzes_this_month,
                       (SELECT COUNT(*) FROM quizzes q
                         JOIN quiz_attempts a ON a.quiz_id = q.id
                        WHERE q.class_id = c.id AND a.needs_marking = 1) AS awaiting_marking,
                       (SELECT COUNT(*) FROM payments p
                         WHERE p.class_id = c.id AND p.month_year = :month3
                           AND p.payment_status IN ('pending','overdue')) AS unpaid_this_month
                  FROM classes c
                  LEFT JOIN institutes i ON i.id = c.institute_id
                 WHERE c.status = 'active'";

        $params = [':month1' => $month, ':month2' => $month, ':month3' => $month];

        if (!$seesEverything) {
            $sql .= " AND c.teacher_id = :teacher";
            $params[':teacher'] = $userId;
        } else {
            // Admins and coordinators: their own branch's classes.
            $sql .= \App\Services\BranchScope::current()->classSql('c.id');
        }

        $sql .= " ORDER BY i.name ASC, c.grade ASC, c.class_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Grouped by branch, because the teacher's week is organised by
        // which city she is in rather than by grade.
        $byBranch = [];
        foreach ($classes as $class) {
            $byBranch[$class['institute_name'] ?? 'Unassigned'][] = $class;
        }

        // The two charts. Scoped to exactly the classes already listed
        // above, so a chart can never widen what this teacher can see.
        $charts = new \App\Services\TeachingChartService($this->db);
        $classIds = array_map(static fn (array $c): int => (int) $c['id'], $classes);

        $this->view('teaching/index', [
            'pageTitle'  => 'Teaching',
            'activePage' => 'teaching',
            'byBranch'   => $byBranch,
            'month'      => $month,
            'months'     => $this->monthOptions(),
            'totals'     => [
                'classes'   => count($classes),
                'students'  => array_sum(array_column($classes, 'students')),
                'materials' => array_sum(array_column($classes, 'materials_this_month')),
                'quizzes'   => array_sum(array_column($classes, 'quizzes_this_month')),
                'marking'   => array_sum(array_column($classes, 'awaiting_marking')),
                'unpaid'    => array_sum(array_column($classes, 'unpaid_this_month')),
            ],
            'participation' => $charts->participation($classIds, $month),
            'marksByGrade'  => $charts->marksByGrade($classIds, $month),
        ]);
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
