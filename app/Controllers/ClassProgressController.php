<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;
use App\Services\ProgressService;
use PDO;

/**
 * How a whole class is doing, student by student.
 *
 * The progress figure already existed -- the student sees it, the parent
 * sees it, it is on the student's own profile page. The one person who
 * could act on it could not see it: the teacher had no screen that put
 * her class in front of her at once, so spotting the child who has
 * quietly stopped turning up meant opening thirty profiles one at a
 * time, which nobody does.
 *
 * Same ProgressService as everywhere else, deliberately. If this screen
 * computed its own version, a teacher and a parent would eventually read
 * the same child two different ways, in the same conversation.
 */
class ClassProgressController extends BaseController
{
    public function show(string $classId): void
    {
        AuthMiddleware::requireTeacher();

        $classId = (int) $classId;
        $scope = BranchScope::current();

        if (!$scope->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', t('That class is not yours.'));
            $this->redirect('/teaching');
        }

        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.subject, i.name AS institute_name
               FROM classes c
          LEFT JOIN institutes i ON i.id = c.institute_id
              WHERE c.id = :id"
        );
        $stmt->execute([':id' => $classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            http_response_code(404);
            $this->flash('error', t('That class no longer exists.'));
            $this->redirect('/teaching');
        }

        $stmt = $this->db->prepare(
            "SELECT s.id, s.student_id_display, s.full_name, s.profile_photo
               FROM enrollments e
               JOIN students s ON s.id = e.student_id
              WHERE e.class_id = :cid
                AND e.enrollment_status = 'active'
                AND s.status = 'active'
           ORDER BY s.full_name ASC"
        );
        $stmt->execute([':cid' => $classId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $progress = new ProgressService();
        $rows = [];
        $needAttention = 0;
        $noData = 0;
        $scoreSum = 0.0;
        $scored = 0;

        foreach ($students as $s) {
            // Scoped to this class: a student who is strong in one class
            // and lost in another must not be averaged into "fine".
            $p = $progress->forStudent((int) $s['id'], $classId);

            $rows[] = ['student' => $s, 'progress' => $p];

            if (!$p['has_data']) {
                $noData++;
                continue;
            }
            $scoreSum += (float) $p['score'];
            $scored++;
            if (in_array($p['tone'], ['danger', 'warning'], true)) {
                $needAttention++;
            }
        }

        // Worst first. The reason to open this page is to find the
        // students in trouble, so they should not be below the fold.
        usort($rows, static function (array $a, array $b): int {
            if ($a['progress']['has_data'] !== $b['progress']['has_data']) {
                return $a['progress']['has_data'] ? 1 : -1;   // "nothing yet" is its own kind of warning
            }
            return $a['progress']['score'] <=> $b['progress']['score'];
        });

        $this->view('teaching/class-progress', [
            'pageTitle'     => 'Class progress',
            'activePage'    => 'teaching',
            'class'         => $class,
            'rows'          => $rows,
            'needAttention' => $needAttention,
            'noData'        => $noData,
            'classAverage'  => $scored > 0 ? round($scoreSum / $scored, 1) : null,
            'total'         => count($rows),
        ]);
    }
}
