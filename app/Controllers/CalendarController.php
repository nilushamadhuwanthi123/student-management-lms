<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\CalendarService;
use App\Services\ContentAccessService;
use App\Services\ReminderService;
use DateTimeImmutable;
use PDO;

/**
 * The calendar, and the teacher's own entries on it.
 *
 * Three audiences, one service. Staff see every branch; a student sees
 * the classes they are enrolled in and nothing else; a parent sees their
 * child's. The scoping is passed to CalendarService rather than filtered
 * in the view, so a student's month is narrowed by the query and a
 * guessed class id in the URL has nothing to reach.
 *
 * Only exams, tests, extra classes, cancellations, holidays and notices
 * are created here. Class times live on the class and quizzes on the
 * quiz; this controller never writes either.
 */
class CalendarController extends BaseController
{
    private CalendarService $calendar;

    public function __construct()
    {
        parent::__construct();
        $this->calendar = new CalendarService();
    }

    /**
     * The month grid, for staff.
     */
    public function index(): void
    {
        // requireTeacher, not requireStaff: in this codebase "staff" is
        // a desk role that excludes the teacher, and the calendar is the
        // teacher's tool before it is anyone else's.
        AuthMiddleware::requireTeacher();

        $month = $this->requestedMonth();
        [$from, $to] = $this->gridBounds($month);

        $instituteId = ($_GET['institute_id'] ?? '') !== '' ? (int) $_GET['institute_id'] : null;
        $scope = $instituteId !== null ? ['institute_id' => $instituteId] : [];

        // Branch staff and teachers see their own branch's or classes'
        // calendar; a branch id from elsewhere in the URL is ignored.
        $branch = \App\Services\BranchScope::current();
        if (!$branch->isGlobal()) {
            if ($instituteId !== null && !$branch->allowsInstitute($instituteId)) {
                $instituteId = null;
            }
            $allowed = $branch->classIds() ?? [];
            if ($instituteId !== null) {
                $allowed = array_values(array_filter($allowed, function (int $c) use ($instituteId): bool {
                    $s = $this->db->prepare('SELECT institute_id FROM classes WHERE id = :c');
                    $s->execute([':c' => $c]);
                    return (int) $s->fetchColumn() === $instituteId;
                }));
            }
            $scope = ['class_ids' => $allowed];
        }

        $this->view('calendar/index', [
            'pageTitle'   => 'Calendar',
            'activePage'  => 'calendar',
            'month'       => $month,
            'gridFrom'    => $from,
            'gridTo'      => $to,
            'byDate'      => $this->calendar->byDate($from, $to, $scope),
            'upcoming'    => $this->upcoming($scope, 8),
            'classes'     => $this->classOptions(),
            'institutes'  => $this->instituteOptions(),
            'instituteId' => $instituteId,
            'canEdit'     => AuthMiddleware::hasAnyRole(['super_admin', 'admin', 'coordinator', 'teacher']),
        ]);
    }

    /**
     * Save a new entry, or update one that exists.
     */
    public function store(): void
    {
        // requireTeacher, not requireStaff: in this codebase "staff" is
        // a desk role that excludes the teacher, and the calendar is the
        // teacher's tool before it is anyone else's.
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $id = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;

        $data = [
            'event_type'  => $_POST['event_type'] ?? 'test',
            'title'       => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'syllabus'    => trim($_POST['syllabus'] ?? '') ?: null,
            'event_date'  => $_POST['event_date'] ?? '',
            'start_time'  => ($_POST['start_time'] ?? '') !== '' ? $_POST['start_time'] : null,
            'end_time'    => ($_POST['end_time'] ?? '') !== '' ? $_POST['end_time'] : null,
            'room'        => trim($_POST['room'] ?? '') ?: null,
            'class_id'    => ($_POST['class_id'] ?? '') !== '' ? (int) $_POST['class_id'] : null,
            'remind'      => isset($_POST['remind']) ? 1 : 0,
        ];

        $errors = $this->validate($data, [
            'title'      => 'required|max:255',
            'event_date' => 'required|date',
            'event_type' => 'required|in:exam,test,extra_class,cancelled_class,holiday,notice',
        ]);

        // An end before its start is the kind of typo that silently
        // produces a zero-length exam, so it is refused here rather than
        // stored and puzzled over later.
        if ($data['start_time'] !== null && $data['end_time'] !== null
            && $data['end_time'] <= $data['start_time']) {
            $errors['end_time'] = 'The finish time has to be after the start time.';
        }

        // Cancelling a class means cancelling a particular class, and
        // "everything, everywhere" is not a cancellation anyone meant.
        if ($data['event_type'] === 'cancelled_class' && $data['class_id'] === null) {
            $errors['class_id'] = 'Choose which class was called off.';
        }

        if ($errors !== []) {
            $this->flash('error', reset($errors));
            $this->redirect('/calendar?month=' . substr($data['event_date'] ?: date('Y-m-d'), 0, 7));
        }

        $data['institute_id'] = $data['class_id'] !== null ? null : $this->instituteForRequest();

        // The entry must belong to the poster's own class or branch, and
        // an existing entry can only be edited by someone who could post it.
        if (!$this->mayPostTo($data['class_id'], $data['institute_id'])
            || ($id !== null && !$this->mayTouchEvent($id))) {
            http_response_code(403);
            $this->flash('error', 'You can only add calendar entries for your own classes or branch.');
            $this->redirect('/calendar');
        }

        if ($id !== null) {
            $this->updateEvent($id, $data);
            $this->flash('success', 'The calendar entry was updated.');
        } else {
            $this->insertEvent($data);
            $newId = (int) $this->db->lastInsertId();
            $this->notifyParentsNow($data, $newId);
            $this->flash('success', 'Added to the calendar. Parents and students have been notified.');
        }

        $this->redirect('/calendar?month=' . substr($data['event_date'], 0, 7));
    }

    /**
     * Take an entry off the calendar.
     *
     * Marked cancelled rather than deleted: a test that was announced
     * and called off is part of the record, and reminders already sent
     * referred to it.
     */
    public function cancel(string $id): void
    {
        // requireTeacher, not requireStaff: in this codebase "staff" is
        // a desk role that excludes the teacher, and the calendar is the
        // teacher's tool before it is anyone else's.
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        if (!$this->mayTouchEvent((int) $id)) {
            http_response_code(403);
            $this->flash('error', 'That calendar entry is not in your branch.');
            $this->back();
        }

        $stmt = $this->db->prepare(
            "UPDATE calendar_events SET event_status = 'cancelled' WHERE id = :id"
        );
        $stmt->execute([':id' => (int) $id]);

        $this->flash('success', 'That entry is off the calendar.');
        $this->back();
    }

    /**
     * A student's own calendar.
     */
    public function mine(): void
    {
        AuthMiddleware::requireAuth();

        if (session_get('user_type') !== 'student') {
            $this->flash('error', 'That area is for students.');
            $this->redirect('/dashboard');
        }

        $studentId = (int) session_get('user_id');
        $month = $this->requestedMonth();
        [$from, $to] = $this->gridBounds($month);

        // The timetable is not the paid content. A student behind on the
        // month's fee still sees when their class is -- they simply
        // cannot open the material -- so the gate is passed to the view
        // as a per-class fact rather than used to hide rows.
        $access = new ContentAccessService();

        $this->view('calendar/mine', [
            'pageTitle'  => 'My calendar',
            'activePage' => 'my-calendar',
            'month'      => $month,
            'gridFrom'   => $from,
            'gridTo'     => $to,
            'byDate'     => $this->calendar->byDate($from, $to, ['student_id' => $studentId]),
            'upcoming'   => $this->upcoming(['student_id' => $studentId], 8),
            'openMap'    => $access->openClassMap($studentId, ContentAccessService::normaliseMonth($month . '-01')),
        ]);
    }

    /**
     * A parent's view of their child's calendar.
     */
    public function family(): void
    {
        AuthMiddleware::requireAuth();

        if (session_get('user_type') !== 'parent') {
            $this->flash('error', 'That area is for parents and guardians.');
            $this->redirect('/dashboard');
        }

        $stmt = $this->db->prepare(
            "SELECT s.id, s.full_name
               FROM parents p
               JOIN students s ON s.id = p.student_id
              WHERE p.id = :parent_id
              LIMIT 1"
        );
        $stmt->execute([':parent_id' => (int) session_get('user_id')]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($child === false) {
            $this->flash('error', 'No student is linked to this login. Contact the office.');
            $this->redirect('/logout');
        }

        $month = $this->requestedMonth();
        [$from, $to] = $this->gridBounds($month);

        $this->view('calendar/mine', [
            'pageTitle'  => t("{name}'s calendar", ['name' => $child['full_name']]),
            'activePage' => 'family-calendar',
            'month'      => $month,
            'gridFrom'   => $from,
            'gridTo'     => $to,
            'byDate'     => $this->calendar->byDate($from, $to, ['student_id' => (int) $child['id'], 'as_parent' => true]),
            'upcoming'   => $this->upcoming(['student_id' => (int) $child['id'], 'as_parent' => true], 8),
            'openMap'    => [],
            'childName'  => $child['full_name'],
        ]);
    }

    /**
     * Run the reminder job by hand.
     *
     * The scheduled task is the normal path; this is for the teacher who
     * has just added a test for tomorrow and wants the reminder gone out
     * now rather than at the next tick. Running it twice sends nothing
     * twice, so the button is safe to press repeatedly.
     */
    public function runReminders(): void
    {
        // requireTeacher, not requireStaff: in this codebase "staff" is
        // a desk role that excludes the teacher, and the calendar is the
        // teacher's tool before it is anyone else's.
        AuthMiddleware::requireTeacher();
        verifyCsrfToken();

        $counts = (new ReminderService())->dispatchDue();

        $this->flash(
            'success',
            $counts['sent'] > 0
                ? "{$counts['sent']} reminders sent. {$counts['already_sent']} were already out."
                : 'Nothing new to remind about -- everything due has already gone out.'
        );

        $this->back();
    }

    // ---------- helpers ----------

    /**
     * The next few things coming up, for the panel beside the grid.
     *
     * @param array<string, mixed> $scope
     * @return array<int, array<string, mixed>>
     */
    private function upcoming(array $scope, int $limit): array
    {
        $now = new DateTimeImmutable((string) $this->db->query('SELECT NOW()')->fetchColumn());

        $rows = $this->calendar->between(
            new DateTimeImmutable($now->format('Y-m-d')),
            $now->modify('+30 days'),
            $scope
        );

        $nowString = $now->format('Y-m-d H:i:s');
        $future = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['starts_at'] >= $nowString
        ));

        return array_slice($future, 0, $limit);
    }

    private function requestedMonth(): string
    {
        $month = $_GET['month'] ?? '';

        return preg_match('/^\d{4}-\d{2}$/', (string) $month) === 1
            ? (string) $month
            : date('Y-m');
    }

    /**
     * The six-week window a month grid actually draws.
     *
     * A month view that starts on the 1st leaves a ragged first row and
     * hides the Saturday class that falls on the 31st of the month
     * before, which for a weekend tuition class is the one row that
     * matters. So the grid runs Monday-to-Sunday around the month.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function gridBounds(string $month): array
    {
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');

        $from = $first->modify('monday this week');
        if ($from > $first) {
            $from = $from->modify('-7 days');
        }

        $to = $last->modify('sunday this week');
        if ($to < $last) {
            $to = $to->modify('+7 days');
        }

        return [$from, $to];
    }

    /**
     * A class entry needs that class in scope; a branch-wide entry needs
     * the branch; an entry for every branch is the super admin's only.
     */
    private function mayPostTo(?int $classId, ?int $instituteId): bool
    {
        $branch = \App\Services\BranchScope::current();
        if ($classId !== null) {
            return $branch->allowsClass($classId);
        }
        if ($instituteId !== null) {
            return $branch->allowsInstitute($instituteId);
        }

        return $branch->isGlobal();
    }

    private function mayTouchEvent(int $eventId): bool
    {
        $stmt = $this->db->prepare('SELECT class_id, institute_id FROM calendar_events WHERE id = :id');
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false && $this->mayPostTo(
            $row['class_id'] !== null ? (int) $row['class_id'] : null,
            $row['institute_id'] !== null ? (int) $row['institute_id'] : null
        );
    }

    private function instituteForRequest(): ?int
    {
        return ($_POST['institute_id'] ?? '') !== '' ? (int) $_POST['institute_id'] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertEvent(array $data): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO calendar_events
                 (class_id, institute_id, event_type, title, description, syllabus,
                  event_date, start_time, end_time, room, remind, created_by)
             VALUES (:class_id, :institute_id, :event_type, :title, :description, :syllabus,
                     :event_date, :start_time, :end_time, :room, :remind, :created_by)"
        );

        $stmt->execute($this->bindable($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateEvent(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE calendar_events
                SET class_id = :class_id, institute_id = :institute_id, event_type = :event_type,
                    title = :title, description = :description, syllabus = :syllabus,
                    event_date = :event_date, start_time = :start_time, end_time = :end_time,
                    room = :room, remind = :remind
              WHERE id = :id"
        );

        $params = $this->bindable($data);
        unset($params[':created_by']);
        $params[':id'] = $id;

        $stmt->execute($params);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bindable(array $data): array
    {
        return [
            ':class_id'     => $data['class_id'],
            ':institute_id' => $data['institute_id'],
            ':event_type'   => $data['event_type'],
            ':title'        => $data['title'],
            ':description'  => $data['description'],
            ':syllabus'     => $data['syllabus'],
            ':event_date'   => $data['event_date'],
            ':start_time'   => $data['start_time'],
            ':end_time'     => $data['end_time'],
            ':room'         => $data['room'],
            ':remind'       => $data['remind'],
            ':created_by'   => (int) session_get('user_id'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(): array
    {
        return $this->db->query(
            "SELECT id, class_name, grade FROM classes WHERE status = 'active'"
            . \App\Services\BranchScope::current()->classSql('id') . " ORDER BY grade, class_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instituteOptions(): array
    {
        return $this->db->query(
            "SELECT id, name FROM institutes WHERE status = 'active'"
            . \App\Services\BranchScope::current()->instituteSql('id') . " ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send immediate in-app notification to parents and students
     * of the affected class when the teacher posts a new calendar event.
     */
    private function notifyParentsNow(array $data, int $eventId): void
    {
        $classId = $data['class_id'] ?? null;
        $notification = new \App\Models\Notification();

        $typeLabel = match ($data['event_type']) {
            'test'             => 'Test',
            'exam'             => 'Exam',
            'extra_class'      => 'Extra class',
            'cancelled_class'  => 'Class cancelled',
            'holiday'          => 'Holiday',
            'notice'           => 'Notice',
            default            => 'Event',
        };

        $title = "New {$typeLabel}: {$data['title']}";
        $body  = date('l j F', strtotime($data['event_date']));
        if (!empty($data['start_time'])) {
            $body .= ' at ' . $data['start_time'];
        }
        if (!empty($data['syllabus'])) {
            $body .= ' — ' . $data['syllabus'];
        }

        $recipients = [];

        if ($classId !== null) {
            $stmt = $this->db->prepare(
                "SELECT s.user_id FROM enrollments e
                 JOIN students s ON s.id = e.student_id AND s.status = 'active'
                 WHERE e.class_id = :cid AND e.enrollment_status = 'active'"
            );
            $stmt->execute([':cid' => $classId]);
            $studentUserIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($studentUserIds as $uid) {
                if ($uid) $recipients[] = (int) $uid;
            }

            // Parents of those students
            if ($studentUserIds) {
                $inList = implode(',', array_map('intval', $studentUserIds));
                $parentIds = $this->db->query(
                    "SELECT p.user_id FROM parents p
                     JOIN students s ON s.id = p.student_id
                     WHERE s.user_id IN ({$inList}) AND p.status = 'active' AND p.user_id IS NOT NULL"
                )->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($parentIds as $pid) {
                    if ($pid) $recipients[] = (int) $pid;
                }
            }
        }

        $recipients = array_unique($recipients);

        foreach ($recipients as $userId) {
            try {
                $notification->send(
                    recipientId:   $userId,
                    recipientType: 'user',
                    title:         $title,
                    body:          $body,
                    type:          'info'
                );
            } catch (\Throwable $e) {
                error_log("CalendarController::notifyParentsNow failed for user {$userId}: " . $e->getMessage());
            }
        }
    }
}
