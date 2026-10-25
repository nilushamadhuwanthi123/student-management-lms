<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

class AttendanceController extends BaseController
{
    private \Attendance $attendanceModel;
    private \ClassModel $classModel;
    private \Institute $instituteModel;

    public function __construct()
    {
        parent::__construct();
        $this->attendanceModel = new \Attendance();
        $this->classModel = new \ClassModel();
        $this->instituteModel = new \Institute();
    }

    /**
     * Daily Attendance Overview & Logs
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

        $date        = trim($_GET['date'] ?? date('Y-m-d'));
        $classId     = !empty($_GET['class_id']) ? (int) $_GET['class_id'] : null;
        $status      = trim($_GET['status'] ?? '');
        $search      = trim($_GET['search'] ?? '');

        $filters = [
            'class_id' => $classId,
            'status'   => $status,
            'search'   => $search,
        ];

        $scope = BranchScope::current();
        $records = $this->attendanceModel->getDailyAttendance($date, $filters + ['class_ids' => $scope->classIds()]);
        $summary = $this->attendanceModel->getSummary($date, $scope->classIds());
        $classes = array_values(array_filter($this->classModel->getActiveClasses(), static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));

        $this->view('attendance/index', [
            'pageTitle' => 'Attendance Management',
            'records'   => $records,
            'summary'   => $summary,
            'classes'   => $classes,
            'date'      => $date,
            'filters'   => $filters,
        ], 'layouts/admin');
    }

    /**
     * Dedicated QR Scan Terminal View
     * Fullscreen interactive scanner with Camera feed, barcode scanner input,
     * and audio alerts (including 5-second loud alarm on deactivated scan).
     */
    public function terminal(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
            AuthMiddleware::ROLE_TEACHER,
        ]);

        $classes = array_values(array_filter($this->classModel->getActiveClasses(), static fn (array $c): bool => BranchScope::current()->allowsClass((int) $c['id'])));

        $this->view('attendance/terminal', [
            'pageTitle' => 'QR Scan Terminal',
            'classes'   => $classes,
        ], 'layouts/admin');
    }

    /**
     * Process Scan Request (AJAX endpoint)
     */
    public function scan(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as Attendance; a student could otherwise mark attendance
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF, AuthMiddleware::ROLE_TEACHER]);

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data) {
            $data = $_POST;
        }

        $code = trim($data['code'] ?? '');
        $classId = !empty($data['class_id']) ? (int) $data['class_id'] : null;
        $currentUserId = (int) (session_get('user_id') ?? 1);

        if (empty($code)) {
            $this->json([
                'success' => false,
                'message' => 'Please scan or enter a valid Student QR code or ID.',
            ], 400);
        }

        // A branch's scanner records its own classes and students only.
        $scope = BranchScope::current();
        if ($classId !== null && !$scope->allowsClass($classId)) {
            $this->json(['success' => false, 'status' => 'not_allowed', 'message' => 'That class is not in your branch.'], 403);
        }
        if (!$scope->isGlobal()) {
            $lookup = $this->db->prepare('SELECT id FROM students WHERE student_id_display = :c OR id = :n LIMIT 1');
            $lookup->execute([':c' => $code, ':n' => ctype_digit($code) ? (int) $code : 0]);
            $found = $lookup->fetchColumn();
            if ($found !== false && !$scope->allowsStudent((int) $found)) {
                $this->json(['success' => false, 'status' => 'not_allowed', 'message' => 'That student is not in your branch.'], 403);
            }
        }

        $result = $this->attendanceModel->recordScan($code, $classId, $currentUserId);

        if ($result['status'] === 'red_flag') {
            // Log audit for flagged entry attempt
            $this->logAudit('scan_deactivated_student_red_flag', 'student', (int) ($result['student']['id'] ?? 0), [
                'identifier' => $code,
                'class_id'   => $classId,
            ]);
        }

        $this->json($result);
    }

    /**
     * Manual Attendance Marking
     */
    public function markManual(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as Attendance
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF, AuthMiddleware::ROLE_TEACHER]);
        verifyCsrfToken();

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $classId   = (int) ($_POST['class_id'] ?? 0);
        $date      = trim($_POST['date'] ?? date('Y-m-d'));
        $status    = trim($_POST['status'] ?? 'present');
        $markedBy  = (int) (session_get('user_id') ?? 1);

        if ($studentId <= 0 || $classId <= 0) {
            $this->flash('error', 'Invalid student or class specified.');
            $this->back();
        }

        $scope = BranchScope::current();
        if (!$scope->allowsClass($classId) || !$scope->allowsStudent($studentId)) {
            http_response_code(403);
            $this->flash('error', 'That class or student is not in your branch.');
            $this->back();
        }

        $this->attendanceModel->markManual($studentId, $classId, $date, $status, $markedBy);
        $this->flash('success', 'Attendance record saved manually.');
        $this->back();
    }

    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'staff',
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
