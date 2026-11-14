<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

/**
 * One calendar built from three different kinds of truth.
 *
 * The teacher thinks of her month as one thing: classes on Saturday and
 * Sunday, a quiz open on the 12th, a term test on the 24th. The database
 * does not store it that way, and should not:
 *
 *   - a weekly class is a RULE ("Saturdays, 08:00-09:30") in
 *     `classes.schedule_days`, with no row per week;
 *   - a quiz is a WINDOW in `quizzes.opens_at` / `closes_at`;
 *   - a term test is an EVENT, and only those live in `calendar_events`.
 *
 * This service expands the rule, slices the window and reads the events,
 * then merges them into one sorted list of dated occurrences. Nothing is
 * copied: move a class to Wednesday and next month's calendar follows,
 * because there was never a second copy to forget to update.
 *
 * Every occurrence carries `class_id`, which is what lets the caller
 * apply the fee gate per row without this service knowing anything about
 * money.
 */
class CalendarService
{
    private PDO $db;

    /**
     * The weekday names stored in `classes.schedule_days`, mapped to the
     * ISO numbers PHP's `N` format gives. Stored as names rather than
     * numbers, so a mapping has to exist somewhere; here is better than
     * in three views.
     */
    private const DAY_NUMBERS = [
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
        'sunday'    => 7,
    ];

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * Everything happening between two dates, sorted by when.
     *
     * $scope narrows what the viewer is allowed to see:
     *   ['student_id' => n]  only classes that student is enrolled in
     *   ['class_ids' => []]  an explicit whitelist
     *   ['institute_id'=>n]  one branch
     *   []                   everything
     *
     * @param array<string, mixed> $scope
     * @return array<int, array<string, mixed>>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to, array $scope = []): array
    {
        // A viewer scoped to classes they have none of sees an empty
        // calendar, which is correct. Resolving to null instead would
        // quietly widen it to every class in the school.
        $classIds = $this->resolveClassIds($scope);

        if ($classIds !== null && $classIds === []) {
            return [];
        }

        $occurrences = array_merge(
            $this->classSessions($from, $to, $classIds),
            $this->quizWindows($from, $to, $classIds),
            $this->events($from, $to, $classIds),
            $this->homeworkDue($from, $to, $classIds),
            $this->seminars($from, $to, $scope, $classIds)
        );

        usort($occurrences, static function (array $a, array $b): int {
            return [$a['starts_at'], $a['title']] <=> [$b['starts_at'], $b['title']];
        });

        return $occurrences;
    }

    /**
     * The same list, grouped by date, for drawing a month grid.
     *
     * @param array<string, mixed> $scope
     * @return array<string, array<int, array<string, mixed>>> 'Y-m-d' => occurrences
     */
    public function byDate(DateTimeImmutable $from, DateTimeImmutable $to, array $scope = []): array
    {
        $grouped = [];

        foreach ($this->between($from, $to, $scope) as $occurrence) {
            $grouped[$occurrence['date']][] = $occurrence;
        }

        return $grouped;
    }

    /**
     * Expand the weekly rule into one row per actual session.
     *
     * A `cancelled_class` event for the same class and date removes the
     * session from the result. That event is the only record anywhere
     * that a particular Saturday did not happen, so without this the
     * calendar would keep insisting on a class the teacher called off.
     *
     * @param array<int, int>|null $classIds
     * @return array<int, array<string, mixed>>
     */
    private function classSessions(DateTimeImmutable $from, DateTimeImmutable $to, ?array $classIds): array
    {
        $sql = "SELECT c.id, c.class_name, c.grade, c.room, c.schedule_days,
                       c.start_time, c.end_time,
                       c.delivery_mode, c.meeting_url,
                       i.name AS institute_name
                  FROM classes c
                  LEFT JOIN institutes i ON i.id = c.institute_id
                 WHERE c.status = 'active'
                   AND c.schedule_days IS NOT NULL
                   AND c.start_time IS NOT NULL";

        [$sql, $params] = $this->applyClassFilter($sql, 'c.id', $classIds);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($classes === []) {
            return [];
        }

        $cancelled = $this->cancelledSessions($from, $to);
        $sessions = [];

        foreach ($classes as $class) {
            $wanted = $this->scheduleDayNumbers($class['schedule_days']);

            if ($wanted === []) {
                continue;
            }

            $day = $from;

            while ($day <= $to) {
                if (in_array((int) $day->format('N'), $wanted, true)) {
                    $date = $day->format('Y-m-d');
                    $key = $class['id'] . '|' . $date;

                    if (!isset($cancelled[$key])) {
                        $sessions[] = $this->occurrence(
                            type: 'class_session',
                            sourceId: (int) $class['id'],
                            classId: (int) $class['id'],
                            date: $date,
                            startTime: $class['start_time'],
                            endTime: $class['end_time'],
                            title: $class['class_name'],
                            detail: 'Class',
                            room: $class['room'],
                            extra: [
                                'grade'          => $class['grade'],
                                'institute_name' => $class['institute_name'],
                                // Carried so a student looking at an online
                                // class can open it. Only ever shown to
                                // someone already scoped to this class.
                                'delivery_mode'  => $class['delivery_mode'] ?? 'physical',
                                'meeting_url'    => $class['meeting_url'] ?? null,
                            ]
                        );
                    }
                }

                $day = $day->modify('+1 day');
            }
        }

        return $sessions;
    }

    /**
     * Dates a class was called off, keyed "classId|Y-m-d".
     *
     * @return array<string, true>
     */
    private function cancelledSessions(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT class_id, event_date
               FROM calendar_events
              WHERE event_type = 'cancelled_class'
                AND event_status = 'scheduled'
                AND class_id IS NOT NULL
                AND event_date BETWEEN :from AND :to"
        );
        $stmt->execute([':from' => $from->format('Y-m-d'), ':to' => $to->format('Y-m-d')]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['class_id'] . '|' . $row['event_date']] = true;
        }

        return $map;
    }

    /**
     * Read `schedule_days` whichever way it was written.
     *
     * It is a JSON array in the seeded data, but this column is a
     * longtext that older rows filled by hand, so a comma-separated
     * string has to be tolerated. Unrecognised day names are dropped
     * rather than guessed at.
     *
     * @return array<int, int> ISO day numbers
     */
    private function scheduleDayNumbers(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $decoded = array_map('trim', explode(',', $raw));
        }

        $numbers = [];

        foreach ($decoded as $day) {
            if (!is_scalar($day)) {
                continue;
            }

            $key = strtolower(trim((string) $day));

            if (isset(self::DAY_NUMBERS[$key])) {
                $numbers[] = self::DAY_NUMBERS[$key];
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Quizzes as two occurrences: when they open, and when they close.
     *
     * The closing one is what a student actually needs on a calendar --
     * a deadline is the thing you miss -- but the opening one matters
     * too, because a quiz that opened and was never started is the
     * failure mode this is meant to prevent. Both are shown; a quiz
     * that opens and closes the same day appears twice, deliberately.
     *
     * @param array<int, int>|null $classIds
     * @return array<int, array<string, mixed>>
     */
    private function quizWindows(DateTimeImmutable $from, DateTimeImmutable $to, ?array $classIds): array
    {
        $sql = "SELECT q.id, q.title, q.opens_at, q.closes_at, q.time_limit_minutes,
                       q.class_id, c.class_name, c.grade, i.name AS institute_name
                  FROM quizzes q
                  JOIN classes c ON c.id = q.class_id AND c.status = 'active'
                  LEFT JOIN institutes i ON i.id = c.institute_id
                 WHERE q.quiz_status = 'published'
                   AND (DATE(q.opens_at) BETWEEN :open_from AND :open_to
                        OR DATE(q.closes_at) BETWEEN :close_from AND :close_to)";

        // Four names for two values, on purpose. PDO with native
        // prepares refuses a named placeholder used twice in one
        // statement ("Invalid parameter number"), so each occurrence
        // gets its own name.
        [$sql, $params] = $this->applyClassFilter($sql, 'q.class_id', $classIds);
        $params[':open_from'] = $params[':close_from'] = $from->format('Y-m-d');
        $params[':open_to'] = $params[':close_to'] = $to->format('Y-m-d');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');
        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $quiz) {
            $extra = [
                'grade'          => $quiz['grade'],
                'institute_name' => $quiz['institute_name'],
                'class_name'     => $quiz['class_name'],
                'time_limit'     => $quiz['time_limit_minutes'],
            ];

            $opensDate = substr((string) $quiz['opens_at'], 0, 10);
            $closesDate = substr((string) $quiz['closes_at'], 0, 10);

            if ($opensDate >= $fromDate && $opensDate <= $toDate) {
                $out[] = $this->occurrence(
                    type: 'quiz_opens',
                    sourceId: (int) $quiz['id'],
                    classId: (int) $quiz['class_id'],
                    date: $opensDate,
                    startTime: substr((string) $quiz['opens_at'], 11, 8),
                    endTime: null,
                    title: $quiz['title'],
                    detail: 'Quiz opens',
                    room: null,
                    extra: $extra
                );
            }

            if ($closesDate >= $fromDate && $closesDate <= $toDate) {
                $out[] = $this->occurrence(
                    type: 'quiz_closes',
                    sourceId: (int) $quiz['id'],
                    classId: (int) $quiz['class_id'],
                    date: $closesDate,
                    startTime: substr((string) $quiz['closes_at'], 11, 8),
                    endTime: null,
                    title: $quiz['title'],
                    detail: 'Quiz due',
                    room: null,
                    extra: $extra
                );
            }
        }

        return $out;
    }

    /**
     * The one-off events: exams, tests, extra classes, holidays, notices.
     *
     * `cancelled_class` rows are excluded -- they are not something that
     * happens, they are the absence of something, and classSessions()
     * has already used them.
     *
     * A null class_id means school-wide, so it survives any class
     * filter: a public holiday applies to a student whatever they are
     * enrolled in.
     *
     * @param array<int, int>|null $classIds
     * @return array<int, array<string, mixed>>
     */
    private function events(DateTimeImmutable $from, DateTimeImmutable $to, ?array $classIds): array
    {
        $sql = "SELECT e.*, c.class_name, c.grade, i.name AS institute_name
                  FROM calendar_events e
                  LEFT JOIN classes c ON c.id = e.class_id
                  LEFT JOIN institutes i ON i.id = COALESCE(e.institute_id, c.institute_id)
                 WHERE e.event_type <> 'cancelled_class'
                   AND e.event_status = 'scheduled'
                   AND e.event_date BETWEEN :from AND :to";

        $params = [':from' => $from->format('Y-m-d'), ':to' => $to->format('Y-m-d')];

        if ($classIds !== null) {
            $placeholders = [];
            foreach (array_values($classIds) as $index => $id) {
                $key = ":cal_class{$index}";
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $in = implode(', ', $placeholders);

            // An event for one class reaches that class. An event with no
            // class reaches a whole branch -- only the branch it names, or
            // every branch when it names none (a public holiday). Without
            // the branch check, one branch's notice showed on every other
            // branch's calendar.
            $instPlaceholders = [];
            foreach (array_values($classIds) as $index => $id) {
                $key = ":cal_inst{$index}";
                $instPlaceholders[] = $key;
                $params[$key] = $id;
            }
            $sql .= " AND (e.class_id IN ({$in})
                       OR (e.class_id IS NULL
                           AND (e.institute_id IS NULL
                                OR e.institute_id IN (SELECT ci.institute_id FROM classes ci WHERE ci.id IN (" . implode(', ', $instPlaceholders) . ")))))";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $this->occurrence(
                type: 'event',
                sourceId: (int) $row['id'],
                classId: $row['class_id'] !== null ? (int) $row['class_id'] : null,
                date: $row['event_date'],
                startTime: $row['start_time'],
                endTime: $row['end_time'],
                title: $row['title'],
                detail: $this->eventLabel($row['event_type']),
                room: $row['room'],
                extra: [
                    'event_type'     => $row['event_type'],
                    'description'    => $row['description'],
                    'syllabus'       => $row['syllabus'],
                    'remind'         => (bool) $row['remind'],
                    'class_name'     => $row['class_name'],
                    'grade'          => $row['grade'],
                    'institute_name' => $row['institute_name'],
                ]
            );
        }

        return $out;
    }

    /**
     * Published homework, on the day it is due, for the classes in view.
     *
     * @param array<int, int>|null $classIds
     * @return array<int, array<string, mixed>>
     */
    private function homeworkDue(DateTimeImmutable $from, DateTimeImmutable $to, ?array $classIds): array
    {
        $sql = "SELECT a.id, a.title, a.class_id, a.due_at, c.class_name
                  FROM assignments a
                  JOIN classes c ON c.id = a.class_id
                 WHERE a.status = 'published'
                   AND a.due_at BETWEEN :from AND :to";
        [$sql, $params] = $this->applyClassFilter($sql, 'a.class_id', $classIds);
        $params[':from'] = $from->format('Y-m-d') . ' 00:00:00';
        $params[':to'] = $to->format('Y-m-d') . ' 23:59:59';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Calendar homework unavailable: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $due = new DateTimeImmutable((string) $row['due_at']);
            $time = $due->format('H:i:s');
            $out[] = $this->occurrence(
                type: 'homework',
                sourceId: (int) $row['id'],
                classId: (int) $row['class_id'],
                date: $due->format('Y-m-d'),
                startTime: $time === '00:00:00' || $time === '23:59:59' ? null : $time,
                endTime: null,
                title: t('Homework due: {title}', ['title' => $row['title']]),
                detail: (string) $row['class_name'],
                room: null,
                extra: ['class_name' => $row['class_name']]
            );
        }

        return $out;
    }

    /**
     * Published seminars and events, for exactly the people each one is
     * for -- the same rule as the seminar lists: a student sees what is
     * for students of their classes, branch or grade; a parent (scope
     * as_parent) what is for parents; staff what is for their branch,
     * their classes, or everyone. Cancelled ones drop off.
     *
     * @param array<string, mixed> $scope
     * @param array<int, int>|null $classIds
     * @return array<int, array<string, mixed>>
     */
    private function seminars(DateTimeImmutable $from, DateTimeImmutable $to, array $scope, ?array $classIds): array
    {
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        try {
            if (isset($scope['student_id'])) {
                $rows = array_filter(
                    (new SeminarService($this->db))->forStudent((int) $scope['student_id'], !empty($scope['as_parent']), 200),
                    static fn (array $s): bool => $s['event_date'] >= $fromDate && $s['event_date'] <= $toDate
                );
            } else {
                $sql = "SELECT s.*, c.class_name, i.name AS institute_name
                          FROM seminars s
                          LEFT JOIN classes c ON c.id = s.class_id
                          LEFT JOIN institutes i ON i.id = s.institute_id
                         WHERE s.status = 'published' AND s.event_date BETWEEN :from AND :to";
                $params = [':from' => $fromDate, ':to' => $toDate];
                if ($classIds !== null) {
                    // Each IN(...) below needs its own set of placeholder
                    // names -- PDO's native prepares reject the same named
                    // parameter appearing more than once in a query, so the
                    // same class id list is bound three times under three
                    // distinct prefixes rather than reusing one.
                    $values = array_values($classIds);
                    $buildIn = static function (array $values, string $prefix) use (&$params): string {
                        $p = [];
                        foreach ($values as $index => $id) {
                            $name = ":{$prefix}{$index}";
                            $p[] = $name;
                            $params[$name] = $id;
                        }
                        return implode(', ', $p);
                    };
                    $inA = $buildIn($values, 'semA');
                    $inB = $buildIn($values, 'semB');
                    $inC = $buildIn($values, 'semC');
                    $sql .= " AND ((s.target_scope = 'all' AND s.for_staff = 1)
                               OR (s.target_scope = 'class' AND s.class_id IN ({$inA}))
                               OR (s.target_scope IN ('branch', 'grade') AND s.institute_id IN (SELECT cs.institute_id FROM classes cs WHERE cs.id IN ({$inB})))
                               OR (s.target_scope = 'grade' AND s.institute_id IS NULL AND s.grade IN (SELECT cg.grade FROM classes cg WHERE cg.id IN ({$inC}))))";
                }
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            error_log('Calendar seminars unavailable: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->occurrence(
                type: 'seminar',
                sourceId: (int) $row['id'],
                classId: null,
                date: (string) $row['event_date'],
                startTime: $row['start_time'] ?? null,
                endTime: $row['end_time'] ?? null,
                title: (string) $row['title'],
                detail: t('Seminar / event'),
                room: $row['venue'] ?? null,
                extra: [
                    'event_type'     => 'seminar',
                    'description'    => $row['description'] ?? null,
                    'class_name'     => $row['class_name'] ?? null,
                    'institute_name' => $row['institute_name'] ?? null,
                ]
            );
        }

        return $out;
    }

    private function eventLabel(string $type): string
    {
        return match ($type) {
            'exam'        => 'Exam',
            'test'        => 'Test',
            'extra_class' => 'Extra class',
            'holiday'     => 'Holiday',
            'notice'      => 'Notice',
            default       => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * One occurrence in the shape every caller can rely on.
     *
     * `starts_at` is the sort key and the thing reminders are measured
     * against. An all-day entry gets 00:00, which puts holidays at the
     * top of their day rather than scattered through it.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function occurrence(
        string $type,
        int $sourceId,
        ?int $classId,
        string $date,
        ?string $startTime,
        ?string $endTime,
        string $title,
        string $detail,
        ?string $room,
        array $extra = []
    ): array {
        $time = $startTime !== null && $startTime !== '' ? substr($startTime, 0, 5) : null;

        return [
            'type'       => $type,
            'source_id'  => $sourceId,
            'class_id'   => $classId,
            'date'       => $date,
            'start_time' => $time,
            'end_time'   => $endTime !== null && $endTime !== '' ? substr($endTime, 0, 5) : null,
            'all_day'    => $time === null,
            'starts_at'  => $date . ' ' . ($time !== null ? $time . ':00' : '00:00:00'),
            'title'      => $title,
            'detail'     => $detail,
            'room'       => $room,
        ] + $extra;
    }

    /**
     * Turn a viewer scope into the class ids they may see.
     *
     * Returns null for "no restriction". An empty array means the viewer
     * legitimately has no classes, and between() stops there.
     *
     * @param array<string, mixed> $scope
     * @return array<int, int>|null
     */
    private function resolveClassIds(array $scope): ?array
    {
        if (isset($scope['class_ids'])) {
            return array_values(array_map('intval', (array) $scope['class_ids']));
        }

        if (isset($scope['student_id'])) {
            $stmt = $this->db->prepare(
                "SELECT class_id
                   FROM enrollments
                  WHERE student_id = :student_id
                    AND enrollment_status = 'active'"
            );
            $stmt->execute([':student_id' => (int) $scope['student_id']]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if (isset($scope['institute_id'])) {
            $stmt = $this->db->prepare(
                "SELECT id FROM classes WHERE institute_id = :institute_id AND status = 'active'"
            );
            $stmt->execute([':institute_id' => (int) $scope['institute_id']]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        return null;
    }

    /**
     * Append an IN (...) filter with bound placeholders.
     *
     * @param array<int, int>|null $classIds
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function applyClassFilter(string $sql, string $column, ?array $classIds): array
    {
        if ($classIds === null) {
            return [$sql, []];
        }

        $params = [];
        $placeholders = [];

        foreach (array_values($classIds) as $index => $id) {
            $key = ":flt{$index}";
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [$sql . ' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }
}
