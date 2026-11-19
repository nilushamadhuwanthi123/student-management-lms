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
        // Marking attendance is a write, so it needs the same CSRF proof as
        // every other POST. The terminal sends the token in X-CSRF-Token,
        // which also makes this answer 403 rather than redirecting -- a
        // redirect would be meaningless to the fetch() waiting on JSON.
        verifyCsrfToken();

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

        // Unpaid months, for the alert at the desk. The scan itself has
        // already been recorded by this point and nothing below can undo
        // it: a student is never turned away over a fee by this code,
        // the office is simply told while the family is still there.
        $scannedId = (int) ($result['student']['id'] ?? 0);
        if ($scannedId > 0) {
            $due = (new \App\Services\PaymentDueService($this->db))->summary($scannedId);
            $result['payment_due'] = $due;

            if ($due['overdue']) {
                $this->logAudit('scan_payment_overdue_alert', 'student', $scannedId, [
                    'months' => $due['count'],
                    'total'  => $due['total'],
                ]);
            }
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
        $date      = self::validDate((string) ($_POST['date'] ?? '')) ?? date('Y-m-d');
        $status    = trim($_POST['status'] ?? 'present');
        if (!in_array($status, ['present', 'late', 'absent'], true)) {
            $this->flash('error', t('Choose present, late or absent.'));
            $this->back();
        }
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
        if ($status === 'absent') {
            (new \App\Services\AbsenceAlertService())->alert($studentId, $classId, $date, $markedBy);
        }
        $this->flash('success', 'Attendance record saved manually.');
        $this->back();
    }

    /**
     * GET /attendance/register?class_id=&date=
     * One class, one day: every enrolled student with their mark. Students
     * who scanned in show as present; the rest can be marked absent, which
     * alerts their family (once).
     */
    public function register(): void
    {
        $this->requireRegisterRole();
        $scope = BranchScope::current();

        $classes = $this->db->query(
            "SELECT id, class_name, grade, start_time FROM classes WHERE status = 'active'{$scope->classSql('id')} ORDER BY class_name"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $classId = (int) ($_GET['class_id'] ?? 0);
        $date = self::validDate((string) ($_GET['date'] ?? '')) ?? date('Y-m-d');
        if ($date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        $students = [];
        $class = null;
        if ($classId > 0) {
            if (!$scope->allowsClass($classId)) {
                http_response_code(403);
                $this->flash('error', t('That class is not in your branch.'));
                $this->redirect('/attendance/register');
            }
            $class = $this->classModel->find($classId);
            $stmt = $this->db->prepare(
                "SELECT s.id, s.student_id_display, s.full_name,
                        COALESCE(
                          (SELECT a.status FROM attendance a WHERE a.student_id = s.id AND a.class_id = :c1 AND a.date = :d1 ORDER BY a.id DESC LIMIT 1),
                          (SELECT a.status FROM attendance a WHERE a.student_id = s.id AND a.class_id IS NULL AND a.date = :d2 AND a.status IN ('present','late') LIMIT 1)
                        ) AS status,
                        (SELECT COUNT(*) FROM absence_alerts aa WHERE aa.student_id = s.id AND aa.class_id = :c2 AND aa.date = :d3) AS alerted
                   FROM enrollments e JOIN students s ON s.id = e.student_id
                  WHERE e.class_id = :c3 AND e.enrollment_status = 'active' AND s.status = 'active'
               ORDER BY s.full_name"
            );
            $stmt->execute([':c1' => $classId, ':c2' => $classId, ':c3' => $classId, ':d1' => $date, ':d2' => $date, ':d3' => $date]);
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $this->view('attendance/register', [
            'pageTitle'  => t('Class register'),
            'classes'    => $classes,
            'class'      => $class,
            'date'       => $date,
            'students'   => $students,
            'alertsLive' => \App\Services\AbsenceAlertService::dateIsRecent($date),
        ]);
    }

    /** POST /attendance/register */
    public function saveRegister(): void
    {
        $this->requireRegisterRole();
        verifyCsrfToken();

        $classId = (int) ($_POST['class_id'] ?? 0);
        $date = self::validDate((string) ($_POST['date'] ?? ''));
        $back = '/attendance/register?class_id=' . $classId . '&date=' . ($date ?? '');
        if ($classId <= 0 || $date === null || $date > date('Y-m-d')) {
            $this->flash('error', t('Choose a class and a date that is not in the future.'));
            $this->redirect('/attendance/register');
        }
        if (!BranchScope::current()->allowsClass($classId)) {
            http_response_code(403);
            $this->flash('error', t('That class is not in your branch.'));
            $this->redirect('/attendance/register');
        }

        $enrolled = $this->db->prepare("SELECT student_id FROM enrollments WHERE class_id = :c AND enrollment_status = 'active'");
        $enrolled->execute([':c' => $classId]);
        $allowed = array_flip(array_map('intval', $enrolled->fetchAll(\PDO::FETCH_COLUMN)));

        $by = (int) session_get('user_id');
        $alerts = new \App\Services\AbsenceAlertService($this->db);
        $saved = 0; $absent = 0; $families = 0;
        foreach ((array) ($_POST['status'] ?? []) as $sid => $status) {
            $sid = (int) $sid;
            if (!isset($allowed[$sid]) || !in_array($status, ['present', 'late', 'absent'], true)) {
                continue;
            }
            $this->attendanceModel->markManual($sid, $classId, $date, $status, $by);
            $saved++;
            if ($status === 'absent') {
                $absent++;
                if ($alerts->alert($sid, $classId, $date, $by)['created']) {
                    $families++;
                }
            }
        }

        $this->logAudit('save_register', 'class', $classId, ['date' => $date, 'saved' => $saved, 'absent' => $absent, 'alerts' => $families]);
        $this->flash('success', t('Register saved: {n} students, {a} absent, {f} families alerted.', ['n' => $saved, 'a' => $absent, 'f' => $families]));
        $this->redirect($back);
    }

    private function requireRegisterRole(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF, AuthMiddleware::ROLE_TEACHER]);
    }

    private static function validDate(string $value): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : null;
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
