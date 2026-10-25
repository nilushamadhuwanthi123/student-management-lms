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

    public function __construct()
    {
        parent::__construct();
        $this->classModel = new \ClassModel();
        $this->instituteModel = new \Institute();
        $this->userModel = new \User();
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
            $this->flash('success', "Class '{$className}' updated successfully.");
            $this->redirect('/classes');
        } catch (\Throwable $e) {
            error_log('Class update error: ' . $e->getMessage());
            $this->flash('error', 'Failed to update class.');
            $this->redirect(BASE_URL . "/classes/{$id}/edit");
        }
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
