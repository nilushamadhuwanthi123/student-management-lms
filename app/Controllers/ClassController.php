<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

class ClassController extends BaseController
{
    private \ClassModel $classModel;
    private \Institute $instituteModel;
    private \User $userModel;
    private \Notification $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->classModel = new \ClassModel();
        $this->instituteModel = new \Institute();
        $this->userModel = new \User();
        $this->notificationModel = new \Notification();
    }

    /**
     * 1.3 Classes Page: 13-Column Management Grid
     * Columns: CLASS NAME + Subject + Grade, TEACHER, INSTITUTE, COORDINATOR, ROOM,
     *          STAFF, SCHEDULE, TIME, FEE, STUDENTS (Count), STATUS, SPLIT, ACTIONS
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
            AuthMiddleware::ROLE_TEACHER,
        ]);

        $search      = trim($_GET['search'] ?? '');
        $instFilter  = trim($_GET['institute_id'] ?? '');
        $medium      = trim($_GET['medium'] ?? '');
        $grade       = trim($_GET['grade'] ?? '');
        $status      = trim($_GET['status'] ?? '');

        $filters = [
            'search'       => $search,
            'institute_id' => $instFilter,
            'medium'       => $medium,
            'grade'        => $grade,
            'status'       => $status,
        ];

        $classes = $this->classModel->getGridList($filters);
        $institutes = $this->instituteModel->getActiveInstitutes();
        $scope = BranchScope::current();
        $classes = array_values(array_filter($classes, static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
        $institutes = array_values(array_filter($institutes, static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));

        $this->view('classes/index', [
            'pageTitle'  => 'Class Management',
            'classes'    => $classes,
            'institutes' => $institutes,
            'filters'    => $filters,
        ], 'layouts/admin');
    }


    /**
     * Hall/Room arrangement board: every active class in one branch,
     * grouped by the physical room it uses, so an admin can see at a
     * glance which halls are free and which are already taken -- and so
     * any real double-booking (same room, same branch, overlapping day
     * and time) is surfaced immediately instead of only being caught the
     * next time someone happens to edit one of the two classes.
     */
    public function rooms(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $scope = BranchScope::current();
        $institutes = $this->instituteModel->getActiveInstitutes();
        $institutes = array_values(array_filter($institutes, static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));

        $instituteId = !empty($_GET['institute_id']) ? (int) $_GET['institute_id'] : null;
        if (($instituteId === null || !$scope->allowsInstitute($instituteId)) && $institutes !== []) {
            $instituteId = (int) $institutes[0]['id'];
        }

        $rooms = [];
        $conflicts = [];
        $unassigned = [];

        if ($instituteId !== null && $scope->allowsInstitute($instituteId)) {
            $stmt = $this->classModel->query(
                "SELECT id, class_name, subject, grade, room, teacher_id, schedule_days, start_time, end_time
                   FROM classes
                  WHERE institute_id = :inst AND status = 'active'
                  ORDER BY room ASC, start_time ASC",
                [':inst' => $instituteId]
            );
            $classRows = $stmt->fetchAll();

            $teacherNames = [];
            foreach ($this->userModel->getByRole('teacher') as $t) {
                $teacherNames[(int) $t['id']] = $t['name'] ?? '';
            }

            foreach ($classRows as $row) {
                $room = trim((string) ($row['room'] ?? ''));
                $days = json_decode((string) ($row['schedule_days'] ?? '[]'), true) ?: [];
                $booking = [
                    'id'      => (int) $row['id'],
                    'name'    => $row['class_name'],
                    'subject' => $row['subject'],
                    'grade'   => $row['grade'],
                    'days'    => is_array($days) ? $days : [],
                    'start'   => $row['start_time'],
                    'end'     => $row['end_time'],
                    'teacher' => $teacherNames[(int) ($row['teacher_id'] ?? 0)] ?? '',
                ];
                if ($room === '') {
                    $unassigned[] = $booking;
                    continue;
                }
                $rooms[$room][] = $booking;
            }
            ksort($rooms, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($rooms as $roomName => $bookings) {
                $n = count($bookings);
                for ($a = 0; $a < $n; $a++) {
                    for ($b = $a + 1; $b < $n; $b++) {
                        if ($bookings[$a]['start'] === null || $bookings[$a]['end'] === null
                            || $bookings[$b]['start'] === null || $bookings[$b]['end'] === null) {
                            continue;
                        }
                        if (array_intersect($bookings[$a]['days'], $bookings[$b]['days']) === []) {
                            continue;
                        }
                        if ($bookings[$a]['start'] < $bookings[$b]['end'] && $bookings[$b]['start'] < $bookings[$a]['end']) {
                            $conflicts[] = [
                                'room' => $roomName,
                                'a'    => $bookings[$a]['name'],
                                'b'    => $bookings[$b]['name'],
                            ];
                        }
                    }
                }
            }
        }

        $this->view('classes/rooms', [
            'pageTitle'   => 'Hall Arrangement',
            'institutes'  => $institutes,
            'instituteId' => $instituteId,
            'rooms'       => $rooms,
            'conflicts'   => $conflicts,
            'unassigned'  => $unassigned,
        ], 'layouts/admin');
    }

    /**
     * Quick inline hall/room reassignment from the Hall Arrangement
     * board itself -- super admin and every branch's own admin or
     * coordinator can move a class to a different hall without going
     * through the full Edit Class form. Blocks a save that would double
     * -book a hall (same branch, same room, an overlapping day & time).
     */
    public function updateRoom(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();

        $classId = (int) $id;
        $scope = BranchScope::current();
        if (!$scope->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', 'You do not have access to that class.');
            $this->back();
        }

        $row = $this->classModel->find($classId);
        if ($row === null) {
            $this->flash('error', 'Class not found.');
            $this->back();
        }

        $newRoom = trim($_POST['room'] ?? '');

        if ($newRoom !== '') {
            $days = json_decode((string) ($row['schedule_days'] ?? '[]'), true) ?: [];
            $stmt = $this->classModel->query(
                "SELECT id, class_name, schedule_days, start_time, end_time
                   FROM classes
                  WHERE institute_id = :inst AND room = :room AND status = 'active' AND id != :id",
                [':inst' => $row['institute_id'], ':room' => $newRoom, ':id' => $classId]
            );
            foreach ($stmt->fetchAll() as $other) {
                if ($row['start_time'] === null || $row['end_time'] === null
                    || $other['start_time'] === null || $other['end_time'] === null) {
                    continue;
                }
                $otherDays = json_decode((string) ($other['schedule_days'] ?? '[]'), true) ?: [];
                if (array_intersect($days, is_array($otherDays) ? $otherDays : []) === []) {
                    continue;
                }
                if ($row['start_time'] < $other['end_time'] && $other['start_time'] < $row['end_time']) {
                    $this->flash('error', 'That hall is already booked for "' . $other['class_name'] . '" at an overlapping time. Choose a different hall or time.');
                    $this->back();
                }
            }
        }

        $this->classModel->query(
            "UPDATE classes SET room = :room, updated_at = NOW() WHERE id = :id",
            [':room' => ($newRoom !== '' ? $newRoom : null), ':id' => $classId]
        );

        $this->flash('success', 'Hall updated.');
        $this->back();
    }

    /**
     * Show Create Class Form
     */
    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $institutes = $this->instituteModel->getActiveInstitutes();
        $institutes = array_values(array_filter($institutes, static fn (array $i): bool => BranchScope::current()->allowsInstitute((int) $i['id'])));
        $teachers   = $this->userModel->getByRole('teacher');
        $staff      = $this->userModel->getByRole('staff');

        $this->view('classes/create', [
            'pageTitle'  => 'Create New Class',
            'institutes' => $institutes,
            'teachers'   => $teachers,
            'staff'      => $staff,
            'daysOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'grades'     => ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11 (O/L)', 'Grade 12 (A/L)', 'Grade 13 (A/L)'],
            'mediums'    => ['Sinhala', 'English', 'Tamil'],
        ], 'layouts/admin');
    }

    /**
     * Store new Class
     */
    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();

        $className   = trim($_POST['class_name'] ?? '');
        $subject     = trim($_POST['subject'] ?? '');
        $medium      = trim($_POST['medium'] ?? '');
        $grade       = trim($_POST['grade'] ?? '');
        $instituteId = !empty($_POST['institute_id']) ? (int) $_POST['institute_id'] : null;
        $room        = trim($_POST['room'] ?? '');
        $teacherId   = !empty($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : null;
        $staffId     = !empty($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
        $scheduleDays= $_POST['schedule_days'] ?? [];
        $startTime   = trim($_POST['start_time'] ?? '') ?: null;
        $endTime     = trim($_POST['end_time'] ?? '') ?: null;
        $monthlyFee  = (float) ($_POST['monthly_fee'] ?? 0);
        $split       = !empty($_POST['teacher_revenue_split']) ? (float) $_POST['teacher_revenue_split'] : null;
        $notes       = trim($_POST['notes'] ?? '');
        // Delivery: physical, online or both. The meeting link is private
        // (only paid, enrolled students see it) and must be a real https link.
        $deliveryMode = in_array($_POST['delivery_mode'] ?? '', ['physical', 'online', 'hybrid'], true) ? $_POST['delivery_mode'] : 'physical';
        $meetingUrl  = trim($_POST['meeting_url'] ?? '');
        if ($meetingUrl !== '' && (!filter_var($meetingUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($meetingUrl), 'https://'))) {
            $this->flash('error', 'The meeting link must be a full https:// address.');
            $this->back();
        }
        $capacity    = ($_POST['capacity'] ?? '') !== '' ? max(0, min(65000, (int) $_POST['capacity'])) : null;
        $status      = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        $errors = [];
        if (empty($className)) {
            $errors[] = 'Class Name is required.';
        }
        if (empty($subject)) {
            $errors[] = 'Subject is required.';
        }
        if (empty($instituteId)) {
            $errors[] = 'Institute selection is required.';
        } elseif (!BranchScope::current()->allowsInstitute($instituteId)) {
            $errors[] = 'You can only create classes in your own branch.';
        }
        if ($monthlyFee <= 0) {
            $errors[] = 'Valid Monthly Fee (Rs.) is required.';
        }

        if (!empty($errors)) {
            $this->flash('error', implode('<br>', $errors));
            $this->redirect('/classes/create');
        }

        $clash = $this->scheduleClash(null, $teacherId, $instituteId, $room, (array) $scheduleDays, $startTime, $endTime);
        if ($clash !== null) {
            $this->flash('error', $clash);
            $this->redirect('/classes/create');
        }

        try {
            $id = $this->classModel->create([
                'class_name'            => $className,
                'subject'               => $subject,
                'medium'                => $medium,
                'grade'                 => $grade,
                'institute_id'          => $instituteId,
                'room'                  => $room,
                'teacher_id'            => $teacherId,
                'staff_id'              => $staffId,
                'schedule_days'         => json_encode(array_values($scheduleDays)),
                'start_time'            => $startTime,
                'end_time'              => $endTime,
                'monthly_fee'           => $monthlyFee,
                'teacher_revenue_split' => $split,
                'delivery_mode'         => $deliveryMode,
                'meeting_url'           => $meetingUrl !== '' ? $meetingUrl : null,
                'capacity'              => $capacity,
                'notes'                 => $notes,
                'status'                => $status,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            // Also attach to teacher and staff in user_classes
            if ($teacherId) {
                $this->userModel->query("INSERT IGNORE INTO user_classes (user_id, class_id) VALUES (:uid, :cid)", [
                    ':uid' => $teacherId,
                    ':cid' => $id,
                ]);
            }
            if ($staffId) {
                $this->userModel->query("INSERT IGNORE INTO user_classes (user_id, class_id) VALUES (:uid, :cid)", [
                    ':uid' => $staffId,
                    ':cid' => $id,
                ]);
            }

            $this->logAudit('create_class', 'class', $id, ['name' => $className]);
            $this->flash('success', "Class '{$className}' created successfully.");
            $this->redirect('/classes');
        } catch (\Throwable $e) {
            error_log('Class create error: ' . $e->getMessage());
            $this->flash('error', 'Failed to create class: ' . $e->getMessage());
            $this->redirect('/classes/create');
        }
    }

    /**
     * Show Edit Class Form
     */
    public function edit(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);

        $class = $this->classModel->find((int) $id);
        if (!$class) {
            $this->flash('error', 'Class not found.');
            $this->redirect('/classes');
        }
        $this->requireClassInScope((int) $class['id']);

        $institutes = $this->instituteModel->getActiveInstitutes();
        $institutes = array_values(array_filter($institutes, static fn (array $i): bool => BranchScope::current()->allowsInstitute((int) $i['id'])));
        $teachers   = $this->userModel->getByRole('teacher');
        $staff      = $this->userModel->getByRole('staff');

        $scheduleDays = [];
        if (!empty($class['schedule_days'])) {
            $decoded = json_decode($class['schedule_days'], true);
            if (is_array($decoded)) {
                $scheduleDays = $decoded;
            }
        }

        $this->view('classes/edit', [
            'pageTitle'    => 'Edit Class - ' . ($class['class_name'] ?? ''),
            'class'        => $class,
            'scheduleDays' => $scheduleDays,
            'institutes'   => $institutes,
            'teachers'     => $teachers,
            'staff'        => $staff,
            'daysOfWeek'   => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'grades'       => ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11 (O/L)', 'Grade 12 (A/L)', 'Grade 13 (A/L)'],
            'mediums'      => ['Sinhala', 'English', 'Tamil'],
        ], 'layouts/admin');
    }

    /**
     * Process Class Update
     */
    public function update(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();

        $class = $this->classModel->find((int) $id);
        if (!$class) {
            $this->flash('error', 'Class not found.');
            $this->redirect('/classes');
        }
        $this->requireClassInScope((int) $class['id']);

        $className   = trim($_POST['class_name'] ?? '');
        $subject     = trim($_POST['subject'] ?? '');
        $medium      = trim($_POST['medium'] ?? '');
        $grade       = trim($_POST['grade'] ?? '');
        $instituteId = !empty($_POST['institute_id']) ? (int) $_POST['institute_id'] : null;
        $room        = trim($_POST['room'] ?? '');
        $teacherId   = !empty($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : null;
        $staffId     = !empty($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
        $scheduleDays= $_POST['schedule_days'] ?? [];
        $startTime   = trim($_POST['start_time'] ?? '') ?: null;
        $endTime     = trim($_POST['end_time'] ?? '') ?: null;
        $monthlyFee  = (float) ($_POST['monthly_fee'] ?? 0);
        $split       = !empty($_POST['teacher_revenue_split']) ? (float) $_POST['teacher_revenue_split'] : null;
        $notes       = trim($_POST['notes'] ?? '');
        // Delivery: physical, online or both. The meeting link is private
        // (only paid, enrolled students see it) and must be a real https link.
        $deliveryMode = in_array($_POST['delivery_mode'] ?? '', ['physical', 'online', 'hybrid'], true) ? $_POST['delivery_mode'] : 'physical';
        $meetingUrl  = trim($_POST['meeting_url'] ?? '');
        if ($meetingUrl !== '' && (!filter_var($meetingUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($meetingUrl), 'https://'))) {
            $this->flash('error', 'The meeting link must be a full https:// address.');
            $this->back();
        }
        $capacity    = ($_POST['capacity'] ?? '') !== '' ? max(0, min(65000, (int) $_POST['capacity'])) : null;
        $status      = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : $class['status'];

        // Moving a class into a branch the user does not manage is refused.
        if ($instituteId === null || !BranchScope::current()->allowsInstitute($instituteId)) {
            $this->flash('error', 'You can only place classes in your own branch.');
            $this->redirect(BASE_URL . "/classes/{$id}/edit");
        }

        $clash = $this->scheduleClash((int) $id, $teacherId, $instituteId, $room, (array) $scheduleDays, $startTime, $endTime);
        if ($clash !== null) {
            $this->flash('error', $clash);
            $this->redirect(BASE_URL . "/classes/{$id}/edit");
        }

        try {
            $this->classModel->update((int) $id, [
                'class_name'            => $className,
                'subject'               => $subject,
                'medium'                => $medium,
                'grade'                 => $grade,
                'institute_id'          => $instituteId,
                'room'                  => $room,
                'teacher_id'            => $teacherId,
                'staff_id'              => $staffId,
                'schedule_days'         => json_encode(array_values($scheduleDays)),
                'start_time'            => $startTime,
                'end_time'              => $endTime,
                'monthly_fee'           => $monthlyFee,
                'teacher_revenue_split' => $split,
                'delivery_mode'         => $deliveryMode,
                'meeting_url'           => $meetingUrl !== '' ? $meetingUrl : null,
                'capacity'              => $capacity,
                'notes'                 => $notes,
                'status'                => $status,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            $this->logAudit('update_class', 'class', (int) $id, ['name' => $className]);
            $this->notifyTeacherOfClassChanges($class, [
                'class_name' => $className,
                'room'       => $room,
                'teacher_id' => $teacherId,
                'schedule_days' => (array) $scheduleDays,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);
            $this->flash('success', "Class '{$className}' updated successfully.");
            $this->redirect('/classes');
        } catch (\Throwable $e) {
            error_log('Class update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update class.');
            $this->redirect(BASE_URL . "/classes/{$id}/edit");
        }
    }

    /**
     * After an admin/coordinator edits a class, tell the teacher(s) affected
     * so nobody shows up expecting the old hall or the old timetable.
     * - If the hall/room changed, the (new) teacher is told the new room.
     * - If the teacher assigned to the class changed, the old teacher is
     *   told they were unassigned and the new teacher is told they were
     *   assigned, each with the current room/schedule.
     */
    private function notifyTeacherOfClassChanges(array $before, array $after): void
    {
        $oldRoom      = trim((string) ($before['room'] ?? ''));
        $newRoom      = trim((string) ($after['room'] ?? ''));
        $oldTeacherId = !empty($before['teacher_id']) ? (int) $before['teacher_id'] : null;
        $newTeacherId = !empty($after['teacher_id']) ? (int) $after['teacher_id'] : null;
        $className    = (string) ($after['class_name'] ?? $before['class_name'] ?? '');
        $scheduleText = $this->formatScheduleText((array) ($after['schedule_days'] ?? []), $after['start_time'] ?? null, $after['end_time'] ?? null);

        $roomChanged    = $oldRoom !== $newRoom;
        $teacherChanged = $oldTeacherId !== $newTeacherId;

        if ($roomChanged && $newTeacherId !== null) {
            $body = $newRoom !== ''
                ? "The hall/room for '{$className}' has been changed to '{$newRoom}'." . ($scheduleText !== '' ? " Schedule: {$scheduleText}." : '')
                : "The hall/room for '{$className}' has been removed. Please check with the office.";
            $this->notificationModel->send(
                $newTeacherId,
                'user',
                'Class hall changed',
                $body,
                'class_update',
                ['type' => 'class', 'id' => (int) $before['id']]
            );
        }

        if ($teacherChanged) {
            if ($oldTeacherId !== null) {
                $this->notificationModel->send(
                    $oldTeacherId,
                    'user',
                    'Class reassigned',
                    "You have been unassigned from '{$className}'.",
                    'class_update',
                    ['type' => 'class', 'id' => (int) $before['id']]
                );
            }
            if ($newTeacherId !== null) {
                $roomText = $newRoom !== '' ? " in {$newRoom}" : '';
                $this->notificationModel->send(
                    $newTeacherId,
                    'user',
                    'Class assigned to you',
                    "You have been assigned to '{$className}'{$roomText}." . ($scheduleText !== '' ? " Schedule: {$scheduleText}." : ''),
                    'class_update',
                    ['type' => 'class', 'id' => (int) $before['id']]
                );
            }
        }
    }

    private function formatScheduleText(array $days, ?string $start, ?string $end): string
    {
        $dayText = implode(', ', array_map('trim', $days));
        if ($start && $end) {
            $timeText = date('g:i A', strtotime($start)) . ' - ' . date('g:i A', strtotime($end));
            return trim($dayText . ' ' . $timeText);
        }
        return $dayText;
    }

    /**
     * Toggle Class Status
     */
    public function toggleStatus(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
        ]);
        verifyCsrfToken();
        $this->requireClassInScope((int) $id);
        $newStatus = $this->classModel->toggleStatus((int) $id);
        $this->logAudit('toggle_class_status', 'class', (int) $id, ['new_status' => $newStatus]);

        $this->flash('success', "Class status updated to " . ucfirst($newStatus) . ".");
        $this->redirect('/classes');
    }

    /**
     * Delete Class
     */
    public function delete(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $classId = (int) $id;
        $class = $this->classModel->find($classId);

        if (!$class) {
            $this->flash('error', 'Class not found.');
            $this->redirect('/classes');
        }
        $this->requireClassInScope((int) $class['id']);

        $studentCount = $this->classModel->getStudentCount($classId);
        if ($studentCount > 0) {
            $this->flash('error', "Cannot delete class '{$class['class_name']}' because it has {$studentCount} active enrolled student(s). Please unenroll students first.");
            $this->redirect('/classes');
        }

        $this->classModel->delete($classId);
        $this->logAudit('delete_class', 'class', $classId, ['name' => $class['class_name']]);

        $this->flash('success', "Class '{$class['class_name']}' has been deleted.");
        $this->redirect('/classes');
    }

    /**
     * Refuses a timetable that double-books the teacher, or the same room
     * at the same branch. Two active classes clash when they share a day
     * and their times overlap. Returns the reason, or null when clear.
     *
     * @param array<int, string> $days
     */
    private function scheduleClash(?int $selfId, ?int $teacherId, ?int $instituteId, string $room, array $days, ?string $start, ?string $end): ?string
    {
        if ($days === [] || $start === null || $end === null) {
            return null;
        }
        if ((strlen($end) === 5 ? $end . ':00' : $end) <= (strlen($start) === 5 ? $start . ':00' : $start)) {
            return 'The class has to finish after it starts.';
        }

        $stmt = $this->classModel->query(
            "SELECT id, class_name, teacher_id, institute_id, room, schedule_days, start_time, end_time
               FROM classes
              WHERE status = 'active' AND start_time IS NOT NULL AND end_time IS NOT NULL
                AND id <> :self",
            [':self' => $selfId ?? 0]
        );
        foreach ($stmt->fetchAll() as $other) {
            $otherDays = json_decode((string) $other['schedule_days'], true) ?: [];
            if (array_intersect($days, $otherDays) === []) {
                continue;
            }
            // Same day: overlapping when each starts before the other ends.
            $t = static fn (string $v): string => strlen($v) === 5 ? $v . ':00' : substr($v, 0, 8);
            if (!($t($start) < $t((string) $other['end_time']) && $t((string) $other['start_time']) < $t($end))) {
                continue;
            }
            if ($teacherId !== null && (int) $other['teacher_id'] === $teacherId) {
                return "The teacher already has '{$other['class_name']}' at that time.";
            }
            if ($room !== '' && $instituteId !== null && (int) $other['institute_id'] === $instituteId
                && strcasecmp(trim((string) $other['room']), trim($room)) === 0) {
                return "Room '{$room}' is already used by '{$other['class_name']}' at that time.";
            }
        }

        return null;
    }

    /**
     * A branch admin or coordinator manages only their own branch's
     * classes, whatever id is put in the URL.
     */
    private function requireClassInScope(int $classId): void
    {
        if (!BranchScope::current()->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', 'That class is not in your branch.');
            $this->redirect('/classes');
        }
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'super_admin',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log error: ' . $e->getMessage());
        }
    }
}
