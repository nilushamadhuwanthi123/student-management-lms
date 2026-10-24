<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

/**
 * Everything a student's class card needs, one class at a time.
 *
 * The My classes page used to show a name, a timetable and a button.
 * What a student actually wants from it is: when is the next one, what
 * is in this month's folder and what haven't I opened, which quizzes are
 * waiting, and how am I doing in this class specifically. This gathers
 * exactly that, from the same tables the rest of the portal reads.
 *
 * Every query is keyed on the student passed in, which the controller
 * takes from the session. Nothing here accepts a student from a request.
 *
 * For a class whose month is locked, the folder is still counted -- "3
 * recordings and 2 sets of notes are waiting" is the honest, useful thing
 * to show -- but no titles and no links are handed out.
 */
class ClassOverviewService
{
    private PDO $db;
    private CalendarService $calendar;
    private ProgressService $progress;

    public function __construct()
    {
        $this->db = \Database::connect();
        $this->calendar = new CalendarService();
        $this->progress = new ProgressService();
    }

    /**
     * @param array<int, int>  $classIds
     * @param array<int, bool> $openMap class_id => open this month
     * @return array<int, array<string, mixed>> class_id => overview
     */
    public function forStudent(int $studentId, string $month, array $classIds, array $openMap): array
    {
        if ($classIds === []) {
            return [];
        }

        $now = new DateTimeImmutable((string) $this->db->query('SELECT NOW()')->fetchColumn());

        $sessions = $this->sessions($studentId, $now);
        $tests = $this->tests($studentId, $now);
        $library = $this->library($studentId, $month, $classIds);
        $quizzes = $this->quizzes($studentId, $month, $classIds, $now);

        $out = [];

        foreach ($classIds as $classId) {
            $open = $openMap[$classId] ?? false;
            $lib = $library[$classId] ?? ['counts' => [], 'total' => 0, 'unopened' => 0, 'next' => null];

            if (!$open) {
                // Counted, never listed: what is waiting, not what it is.
                $lib['next'] = null;
            }

            $out[$classId] = [
                'next'     => $sessions[$classId][0] ?? null,
                'week'     => $sessions[$classId] ?? [],
                'test'     => $tests[$classId] ?? null,
                'library'  => $lib,
                'quizzes'  => $quizzes[$classId] ?? ['open' => [], 'upcoming' => 0, 'done' => [], 'missed' => 0],
                'standing' => $this->progress->forStudent($studentId, $classId),
            ];
        }

        return $out;
    }

    /**
     * Class sessions in the next seven days, grouped by class.
     *
     * Seven days rather than "this week" so a Saturday-and-Sunday class
     * seen on a Sunday evening still shows next Saturday.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function sessions(int $studentId, DateTimeImmutable $now): array
    {
        $rows = $this->calendar->between($now->setTime(0, 0), $now->modify('+7 days'), ['student_id' => $studentId]);
        $nowString = $now->format('Y-m-d H:i:s');
        $grouped = [];

        foreach ($rows as $row) {
            $isSession = $row['type'] === 'class_session' || ($row['event_type'] ?? '') === 'extra_class';

            if ($isSession && $row['class_id'] !== null && $row['starts_at'] > $nowString) {
                $grouped[(int) $row['class_id']][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * The next exam or test for each class, within six weeks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tests(int $studentId, DateTimeImmutable $now): array
    {
        $rows = $this->calendar->between($now->setTime(0, 0), $now->modify('+42 days'), ['student_id' => $studentId]);
        $nowString = $now->format('Y-m-d H:i:s');
        $first = [];

        foreach ($rows as $row) {
            if ($row['type'] !== 'event' || !in_array($row['event_type'] ?? '', ['exam', 'test'], true)) {
                continue;
            }
            if ($row['class_id'] === null || $row['starts_at'] <= $nowString) {
                continue;
            }
            $first[(int) $row['class_id']] ??= $row;
        }

        return $first;
    }

    /**
     * This month's folder per class: how much of each kind, how much the
     * student has not opened, and the newest unopened item.
     *
     * @param array<int, int> $classIds
     * @return array<int, array<string, mixed>>
     */
    private function library(int $studentId, string $month, array $classIds): array
    {
        [$in, $params] = $this->inList($classIds, 'lc');
        $params[':sid'] = $studentId;
        $params[':month'] = $month;

        $stmt = $this->db->prepare(
            "SELECT m.id, m.class_id, m.title, m.material_type, m.created_at, m.duration_minutes,
                    (v.material_id IS NOT NULL) AS seen
               FROM class_materials m
               LEFT JOIN material_views v ON v.material_id = m.id AND v.student_id = :sid
              WHERE m.class_id IN ({$in})
                AND m.month_year = :month
                AND m.status = 'published'
              ORDER BY m.created_at DESC, m.id DESC"
        );
        $stmt->execute($params);

        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $c = (int) $m['class_id'];
            $out[$c] ??= ['counts' => [], 'total' => 0, 'unopened' => 0, 'next' => null];

            $out[$c]['counts'][$m['material_type']] = ($out[$c]['counts'][$m['material_type']] ?? 0) + 1;
            $out[$c]['total']++;

            if (!(int) $m['seen']) {
                $out[$c]['unopened']++;
                // Newest first, so the first unopened one met is the newest.
                $out[$c]['next'] ??= $m;
            }
        }

        return $out;
    }

    /**
     * This month's quizzes per class, sorted into what the student can do
     * about each: sit it now, wait for it, see the mark, or note it was
     * missed.
     *
     * @param array<int, int> $classIds
     * @return array<int, array<string, mixed>>
     */
    private function quizzes(int $studentId, string $month, array $classIds, DateTimeImmutable $now): array
    {
        [$in, $params] = $this->inList($classIds, 'qc');
        $params[':sid'] = $studentId;
        $params[':month'] = $month;

        $stmt = $this->db->prepare(
            "SELECT q.id, q.class_id, q.title, q.opens_at, q.closes_at, q.time_limit_minutes,
                    MAX(CASE WHEN a.attempt_status = 'graded' THEN a.percentage END) AS best,
                    SUM(CASE WHEN a.attempt_status IN ('submitted', 'graded') THEN 1 ELSE 0 END) AS finished
               FROM quizzes q
               LEFT JOIN quiz_attempts a ON a.quiz_id = q.id AND a.student_id = :sid
              WHERE q.class_id IN ({$in})
                AND q.month_year = :month
                AND q.quiz_status IN ('published', 'closed')
              GROUP BY q.id, q.class_id, q.title, q.opens_at, q.closes_at, q.time_limit_minutes
              ORDER BY q.closes_at"
        );
        $stmt->execute($params);

        $nowString = $now->format('Y-m-d H:i:s');
        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
            $c = (int) $q['class_id'];
            $out[$c] ??= ['open' => [], 'upcoming' => 0, 'done' => [], 'missed' => 0];

            if ((int) $q['finished'] > 0) {
                $out[$c]['done'][] = $q;
            } elseif ($q['opens_at'] > $nowString) {
                $out[$c]['upcoming']++;
            } elseif ($q['closes_at'] > $nowString) {
                $out[$c]['open'][] = $q;
            } else {
                $out[$c]['missed']++;
            }
        }

        return $out;
    }

    /**
     * @param array<int, int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private function inList(array $ids, string $prefix): array
    {
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $params[":{$prefix}{$i}"] = (int) $id;
        }

        return [implode(', ', array_keys($params)), $params];
    }
}
