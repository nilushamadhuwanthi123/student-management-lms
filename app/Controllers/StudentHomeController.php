<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\AssistantService;
use App\Services\CalendarService;
use App\Services\ContentAccessService;
use App\Services\GoalService;
use App\Services\MotivationService;
use App\Services\ProgressService;
use DateTimeImmutable;
use PDO;

/**
 * A student's home page.
 *
 * It replaces the institute dashboard that students were landing on,
 * which showed every student, the month's takings and everyone's unpaid
 * fees. Everything on this page is about the one student logged in, and
 * every query below takes that id from the session -- there is no route
 * parameter to tamper with.
 *
 * It answers, top to bottom, the questions a student opens the site
 * with: when is my next class, is there anything I have to do, what is
 * new, how am I doing.
 */
class StudentHomeController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        if (session_get('user_type') !== 'student') {
            $this->redirect('/dashboard');
        }

        $studentId = (int) session_get('user_id');
        $now = new DateTimeImmutable((string) $this->db->query('SELECT NOW()')->fetchColumn());
        $month = ContentAccessService::normaliseMonth($now->format('Y-m-d'));

        $access = new ContentAccessService();
        $openMap = $access->openClassMap($studentId, $month);
        $calendar = new CalendarService();

        $stmt = $this->db->prepare('SELECT id, full_name, student_id_display FROM students WHERE id = :id');
        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'Student', 'student_id_display' => ''];

        // Two weeks ahead is enough to always find the next weekend class.
        $ahead = $calendar->between($now->setTime(0, 0), $now->modify('+14 days'), ['student_id' => $studentId]);
        $nowString = $now->format('Y-m-d H:i:s');
        $future = array_values(array_filter($ahead, static fn (array $i): bool => $i['starts_at'] > $nowString || ($i['all_day'] && $i['date'] >= $now->format('Y-m-d'))));

        $nextClass = null;
        foreach ($future as $item) {
            if ($item['type'] === 'class_session' || ($item['event_type'] ?? '') === 'extra_class') {
                $nextClass = $item;
                break;
            }
        }

        // Things worth a reminder, not the weekly routine.
        $comingUp = array_slice(array_values(array_filter(
            $future,
            static fn (array $i): bool => $i['type'] === 'quiz_closes' || $i['type'] === 'event'
        )), 0, 6);

        $progress = (new ProgressService())->forStudent($studentId);

        $this->view('student/home', [
            'pageTitle'     => 'My Home',
            'activePage'    => 'my-home',
            'student'       => $student,
            'now'           => $now,
            'nextClass'     => $nextClass,
            'nextIsOpen'    => $nextClass !== null && $nextClass['class_id'] !== null ? ($openMap[$nextClass['class_id']] ?? false) : true,
            'comingUp'      => $comingUp,
            'classes'       => $this->classes($studentId, $month, $openMap),
            'quizzes'       => $this->openQuizzes($studentId, $nowString, $openMap),
            'lockedQuizzes' => $this->lockedQuizCount($studentId, $nowString, $openMap),
            'materials'     => $this->newMaterials($studentId, $openMap),
            'marks'         => $this->recentMarks($studentId),
            'progress'      => $progress,
            'notifications' => $this->notifications($studentId),
            'suggestions'   => AssistantService::SUGGESTIONS,
            // Level, achievements, learning streak and goals -- all from
            // this student's own record, never compared with anyone else's.
            'motivation'    => (new MotivationService())->forStudent($studentId, $progress),
            'goalOptions'   => (new GoalService())->formOptions($studentId),
            // Homework still to hand in -- the one thing that was missing
            // from "today's learning": quizzes, new material and marks
            // were already here, but a published assignment never showed
            // up on this page at all.
            'homework'       => $this->pendingHomework($studentId, $openMap),
            'homeworkLocked' => $this->lockedHomeworkCount($studentId, $openMap),
        ]);
    }

    /**
     * Enrolled classes with this month's fee position.
     *
     * @param array<int, bool> $openMap
     * @return array<int, array<string, mixed>>
     */
    private function classes(int $studentId, string $month, array $openMap): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.class_name, c.grade, c.schedule_days, c.start_time, c.end_time, c.room,
                    i.name AS institute_name, e.scholarship_type,
                    p.payment_status, p.amount_due, p.amount_paid
               FROM enrollments e
               JOIN classes c ON c.id = e.class_id
               LEFT JOIN institutes i ON i.id = c.institute_id
               LEFT JOIN payments p ON p.student_id = e.student_id
                                   AND p.class_id = e.class_id
                                   AND p.month_year = :month
              WHERE e.student_id = :sid
                AND e.enrollment_status = 'active'
              ORDER BY c.grade, c.class_name"
        );
        $stmt->execute([':sid' => $studentId, ':month' => $month]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $days = json_decode((string) $row['schedule_days'], true);
            $row['days'] = is_array($days) ? implode(' & ', array_map(static fn ($d) => substr((string) $d, 0, 3), $days)) : '';
            $row['open'] = $openMap[(int) $row['id']] ?? false;
            $row['owed'] = in_array($row['payment_status'], ['pending', 'overdue'], true)
                ? (float) $row['amount_due'] - (float) $row['amount_paid']
                : 0.0;
        }

        return $rows;
    }

    /**
     * Quizzes the student can sit right now and has not finished.
     *
     * Only for classes whose month is open: listing a quiz the student
     * cannot start would just be a button that leads to a padlock.
     *
     * @param array<int, bool> $openMap
     * @return array<int, array<string, mixed>>
     */
    private function openQuizzes(int $studentId, string $now, array $openMap): array
    {
        $stmt = $this->db->prepare(
            "SELECT q.id, q.title, q.class_id, q.closes_at, q.time_limit_minutes, c.class_name,
                    (SELECT COUNT(*) FROM quiz_attempts a
                      WHERE a.quiz_id = q.id AND a.student_id = :sid1
                        AND a.attempt_status IN ('submitted', 'graded')) AS done
               FROM quizzes q
               JOIN classes c ON c.id = q.class_id
               JOIN enrollments e ON e.class_id = q.class_id AND e.student_id = :sid2
                                 AND e.enrollment_status = 'active'
              WHERE q.quiz_status = 'published'
                AND q.opens_at <= :now1 AND q.closes_at > :now2
              ORDER BY q.closes_at"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId, ':now1' => $now, ':now2' => $now]);

        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            static fn (array $q): bool => (int) $q['done'] === 0 && ($openMap[(int) $q['class_id']] ?? false)
        ));
    }

    /**
     * @param array<int, bool> $openMap
     */
    private function lockedQuizCount(int $studentId, string $now, array $openMap): int
    {
        $stmt = $this->db->prepare(
            "SELECT q.class_id
               FROM quizzes q
               JOIN enrollments e ON e.class_id = q.class_id AND e.student_id = :sid
                                 AND e.enrollment_status = 'active'
              WHERE q.quiz_status = 'published'
                AND q.opens_at <= :now1 AND q.closes_at > :now2"
        );
        $stmt->execute([':sid' => $studentId, ':now1' => $now, ':now2' => $now]);

        return count(array_filter(
            $stmt->fetchAll(PDO::FETCH_COLUMN),
            static fn ($classId): bool => !($openMap[(int) $classId] ?? false)
        ));
    }

    /**
     * The newest material in classes the student can open.
     *
     * @param array<int, bool> $openMap
     * @return array<int, array<string, mixed>>
     */
    private function newMaterials(int $studentId, array $openMap): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.title, m.material_type, m.created_at, m.class_id, m.duration_minutes,
                    c.class_name,
                    (SELECT COUNT(*) FROM material_views v
                      WHERE v.material_id = m.id AND v.student_id = :sid1) AS seen
               FROM class_materials m
               JOIN classes c ON c.id = m.class_id
               JOIN enrollments e ON e.class_id = m.class_id AND e.student_id = :sid2
                                 AND e.enrollment_status = 'active'
              WHERE m.status = 'published'
              ORDER BY m.created_at DESC, m.id DESC
              LIMIT 20"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId]);

        $open = array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            static fn (array $m): bool => $openMap[(int) $m['class_id']] ?? false
        );

        // Unopened first: "new for you" means new to this student.
        usort($open, static fn (array $a, array $b): int =>
            [(int) $a['seen'], $b['created_at']] <=> [(int) $b['seen'], $a['created_at']]);

        return array_slice($open, 0, 6);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMarks(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, q.title, c.class_name, a.percentage, a.submitted_at
               FROM quiz_attempts a
               JOIN quizzes q ON q.id = a.quiz_id
               JOIN classes c ON c.id = q.class_id
              WHERE a.student_id = :sid AND a.attempt_status = 'graded'
              ORDER BY a.submitted_at DESC, a.id DESC
              LIMIT 6"
        );
        $stmt->execute([':sid' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Published homework the student has not handed in yet, for a class
     * whose month is open -- the same gate "New for you" and the quiz
     * list already use, so a student never sees a hand-in button for
     * something they could not actually open.
     *
     * @param array<int, bool> $openMap
     * @return array<int, array<string, mixed>>
     */
    private function pendingHomework(int $studentId, array $openMap): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.title, a.due_at, a.class_id, c.class_name
               FROM assignments a
               JOIN classes c ON c.id = a.class_id
               JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = :sid1
                                 AND e.enrollment_status = 'active'
               LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = :sid2
              WHERE a.status = 'published' AND sub.id IS NULL
              ORDER BY a.due_at ASC"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId]);

        $pending = array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            static fn (array $a): bool => $openMap[(int) $a['class_id']] ?? false
        ));

        return array_slice($pending, 0, 6);
    }

    /**
     * How many more are waiting, but locked behind an unpaid month --
     * counted so the empty state can mention them, never listed.
     *
     * @param array<int, bool> $openMap
     */
    private function lockedHomeworkCount(int $studentId, array $openMap): int
    {
        $stmt = $this->db->prepare(
            "SELECT a.class_id
               FROM assignments a
               JOIN enrollments e ON e.class_id = a.class_id AND e.student_id = :sid1
                                 AND e.enrollment_status = 'active'
               LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = :sid2
              WHERE a.status = 'published' AND sub.id IS NULL"
        );
        $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId]);

        return count(array_filter(
            $stmt->fetchAll(PDO::FETCH_COLUMN),
            static fn ($classId): bool => !($openMap[(int) $classId] ?? false)
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notifications(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, title, body, is_read, created_at
               FROM notifications
              WHERE recipient_type = 'student' AND recipient_id = :sid
              ORDER BY created_at DESC, id DESC
              LIMIT 5"
        );
        $stmt->execute([':sid' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
