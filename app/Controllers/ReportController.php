<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\BranchScope;

class ReportController extends BaseController
{
    private \Attendance $attendanceModel;
    private \ClassModel $classModel;
    private \Institute $instituteModel;
    private \User $userModel;
    private \Student $studentModel;

    public function __construct()
    {
        parent::__construct();
        $this->attendanceModel = new \Attendance();
        $this->classModel = new \ClassModel();
        $this->instituteModel = new \Institute();
        $this->userModel = new \User();
        $this->studentModel = new \Student();
    }

    /**
     * Reports Hub Index
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);

        $this->view('reports/index', [
            'pageTitle' => 'System Reports Hub',
        ], 'layouts/admin');
    }

    /**
     * 1. Attendance Report
     */
    public function attendance(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Reports hub; students and parents were able to open this
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $startDate   = trim($_GET['start_date'] ?? date('Y-m-01'));
        $endDate     = trim($_GET['end_date'] ?? date('Y-m-d'));
        $classId     = !empty($_GET['class_id']) ? (int) $_GET['class_id'] : null;
        $instituteId = !empty($_GET['institute_id']) ? (int) $_GET['institute_id'] : null;

        $filters = [
            'class_id'     => $classId,
            'institute_id' => $instituteId,
        ];

        $scope = BranchScope::current();
        $reportData = $this->attendanceModel->getAttendanceReport($startDate, $endDate, $filters + ['class_ids' => $scope->classIds()]);
        $classes    = array_values(array_filter($this->classModel->getActiveClasses(), static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
        $institutes = array_values(array_filter($this->instituteModel->getActiveInstitutes(), static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));

        // Export to CSV if requested
        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('attendance_report_' . date('Ymd') . '.csv', [
                t('Date'), t('Class Name'), t('Subject'), t('Institute'), t('Present Count'), t('Late Count'), t('Absent Count'), t('Red Flags'), t('Total Logged')
            ], array_map(function($r) {
                return [
                    $r['date'], $r['class_name'] ?? '-', $r['subject'] ?? '-', $r['institute_name'] ?? '-',
                    $r['present_count'], $r['late_count'], $r['absent_count'], $r['red_flag_count'], $r['total_logged']
                ];
            }, $reportData));
        }

        $this->view('reports/attendance', [
            'pageTitle'  => 'Attendance Reports',
            'reportData' => $reportData,
            'classes'    => $classes,
            'institutes' => $institutes,
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'filters'    => $filters,
        ], 'layouts/admin');
    }

    /**
     * 2. Class Report
     */
    public function classes(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Reports hub
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $instituteId = !empty($_GET['institute_id']) ? (int) $_GET['institute_id'] : null;
        $scope = BranchScope::current();
        $classes = array_values(array_filter($this->classModel->getGridList(['institute_id' => $instituteId]), static fn (array $c): bool => $scope->allowsClass((int) $c['id'])));
        $institutes = array_values(array_filter($this->instituteModel->getActiveInstitutes(), static fn (array $i): bool => $scope->allowsInstitute((int) $i['id'])));

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('class_report_' . date('Ymd') . '.csv', [
                t('Class Name'), t('Subject'), t('Medium'), t('Grade'), t('Teacher'), t('Institute'), t('Room'), t('Monthly Fee'), t('Students Count'), t('Status')
            ], array_map(function($c) {
                return [
                    $c['class_name'], $c['subject'], $c['medium'] ?? '-', $c['grade'] ?? '-',
                    $c['teacher_name'] ?? '-', $c['institute_name'] ?? '-', $c['room'] ?? '-',
                    $c['monthly_fee'], $c['student_count'] ?? 0, t(ucfirst($c['status'] ?? 'active'))
                ];
            }, $classes));
        }

        $this->view('reports/classes', [
            'pageTitle'   => 'Class & Enrollment Reports',
            'classes'     => $classes,
            'institutes'  => $institutes,
            'instituteId' => $instituteId,
        ], 'layouts/admin');
    }

    /**
     * 3. User Report
     */
    public function users(): void
    {
        AuthMiddleware::requireAuth();
        // access: the staff directory is an admin report (it carries phone
        // numbers and roles); a branch admin sees their own branch's people.
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN]);

        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $users = $this->userModel->getFilteredUsers(['role' => $role, 'status' => $status]);
        $kpi = $this->userModel->getStatistics();
        $scope = BranchScope::current();
        if (!$scope->isGlobal()) {
            $users = array_values(array_filter($users, static fn (array $u): bool => $scope->allowsUser((int) $u['id'])));
            $kpi = $this->kpiFrom($users);
        }

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('user_report_' . date('Ymd') . '.csv', [
                t('User ID'), t('Role'), t('Full Name'), t('Email'), t('Phone 1'), t('WhatsApp'), t('Institute'), t('Status'), t('Joined Date')
            ], array_map(function($u) {
                return [
                    $u['user_id_display'], t(ucfirst($u['role'])), $u['full_name'], $u['email'] ?? '-',
                    $u['phone1'], $u['whatsapp'] ?? '-', $u['coordinated_institute_name'] ?? $u['primary_institute_name'] ?? '-',
                    t(ucfirst($u['status'])), date('Y-m-d', strtotime($u['created_at']))
                ];
            }, $users));
        }

        $this->view('reports/users', [
            'pageTitle' => 'User & Staff Directory Reports',
            'users'     => $users,
            'kpi'       => $kpi,
            'role'      => $role,
            'status'    => $status,
        ], 'layouts/admin');
    }

    /**
     * 4. Payment Summary Report
     */
    public function payments(): void
    {
        AuthMiddleware::requireAuth();
        // access: financial summary (with the teacher's revenue share) is for
        // the super admin and branch admins, each seeing their own scope.
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN]);

        $month = trim($_GET['month'] ?? date('Y-m'));
        // The month used to be pasted into the SQL text. Only YYYY-MM is
        // accepted now, and it travels as a bound parameter.
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $db = \Database::connect();

        $sql = "SELECT 
                    c.id AS class_id,
                    c.class_name,
                    c.subject,
                    i.name AS institute_name,
                    t.full_name AS teacher_name,
                    c.monthly_fee,
                    c.teacher_revenue_split,
                    -- Each figure is its own subquery. Joining enrollments and
                    -- payments together multiplied every payment by the number
                    -- of students in the class, so totals were overstated.
                    (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e
                      WHERE e.class_id = c.id AND e.enrollment_status = 'active') AS enrolled_students,
                    COALESCE((SELECT SUM(p.amount_paid) FROM payments p
                      WHERE p.class_id = c.id AND p.payment_status = 'paid'
                        AND (DATE_FORMAT(p.month_year, '%Y-%m') = :m1 OR DATE_FORMAT(p.paid_at, '%Y-%m') = :m2)), 0) AS total_collected,
                    COALESCE((SELECT SUM(p.amount_due - p.amount_paid) FROM payments p
                      WHERE p.class_id = c.id AND p.payment_status IN ('pending', 'overdue')
                        AND DATE_FORMAT(p.month_year, '%Y-%m') = :m3), 0) AS total_pending
                FROM classes c
                LEFT JOIN institutes i ON c.institute_id = i.id
                LEFT JOIN users t ON c.teacher_id = t.id
                WHERE 1 = 1" . BranchScope::current()->classSql('c.id') . "
                ORDER BY total_collected DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':m1' => $month, ':m2' => $month, ':m3' => $month]);
        $reportData = $stmt->fetchAll();

        // Calculate totals
        $totCollected = 0.0;
        $totPending = 0.0;
        $totTeacherSplit = 0.0;
        $totInstituteSplit = 0.0;

        foreach ($reportData as $row) {
            $col = (float) $row['total_collected'];
            $split = (float) ($row['teacher_revenue_split'] ?? 70);
            $totCollected += $col;
            $totPending += (float) $row['total_pending'];
            $totTeacherSplit += ($col * ($split / 100));
            $totInstituteSplit += ($col * ((100 - $split) / 100));
        }

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('payment_summary_' . $month . '.csv', [
                t('Class Name'), t('Subject'), t('Institute'), t('Teacher'), t('Enrolled Students'), t('Collected (Rs.)'), t('Pending (Rs.)'), t('Teacher Share (Rs.)'), t('Institute Share (Rs.)')
            ], array_map(function($r) {
                $col = (float) $r['total_collected'];
                $split = (float) ($r['teacher_revenue_split'] ?? 70);
                return [
                    $r['class_name'], $r['subject'], $r['institute_name'] ?? '-', $r['teacher_name'] ?? '-',
                    $r['enrolled_students'], $col, $r['total_pending'],
                    ($col * ($split / 100)), ($col * ((100 - $split) / 100))
                ];
            }, $reportData));
        }

        $this->view('reports/payments', [
            'pageTitle'         => 'Payment Summary Reports',
            'reportData'        => $reportData,
            'month'             => $month,
            'totCollected'      => $totCollected,
            'totPending'        => $totPending,
            'totTeacherSplit'   => $totTeacherSplit,
            'totInstituteSplit' => $totInstituteSplit,
        ], 'layouts/admin');
    }

    /**
     * 5. Student Status Report
     */
    public function students(): void
    {
        AuthMiddleware::requireAuth();
        // access: role-checked -- same as the Reports hub
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $db = \Database::connect();

        // Status counts
        $statSql = "SELECT 
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count,
                        SUM(CASE WHEN status = 'deactivated' THEN 1 ELSE 0 END) AS deactivated_count
                    FROM students s
                    WHERE 1 = 1" . BranchScope::current()->studentSql('s.id');
        $stats = $db->query($statSql)->fetch();

        // Students list with deactivation status and class counts
        $statusFilter = trim($_GET['status'] ?? '');
        $where = 'WHERE 1 = 1' . BranchScope::current()->studentSql('s.id');
        if ($statusFilter !== '') {
            $where .= " AND s.status = " . $db->quote($statusFilter);
        }

        $sql = "SELECT 
                    s.*,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id AND e.enrollment_status = 'active') AS enrolled_classes_count,
                    (SELECT GROUP_CONCAT(cl.class_name SEPARATOR ', ') FROM enrollments e JOIN classes cl ON e.class_id = cl.id WHERE e.student_id = s.id) AS enrolled_classes
                FROM students s
                {$where}
                ORDER BY s.id DESC";

        $students = $db->query($sql)->fetchAll();

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('student_status_report_' . date('Ymd') . '.csv', [
                t('Student ID'), t('Full Name'), t('Email'), t('Phone 1'), t('WhatsApp'), t('Status'), t('Classes Count'), t('Joined Date')
            ], array_map(function($s) {
                return [
                    $s['student_id_display'], $s['full_name'], $s['email'] ?? '-',
                    $s['phone1'] ?? '-', $s['whatsapp'] ?? '-', t(ucfirst($s['status'])),
                    $s['enrolled_classes_count'], date('Y-m-d', strtotime($s['created_at']))
                ];
            }, $students));
        }

        $this->view('reports/students', [
            'pageTitle'    => 'Student Status & Deactivation Reports',
            'students'     => $students,
            'stats'        => $stats,
            'statusFilter' => $statusFilter,
        ], 'layouts/admin');
    }

    /**
     * Helper to export data as CSV download
     */
    /**
     * The staff-directory headline numbers, counted from the rows a
     * branch admin is allowed to see rather than the whole system.
     *
     * @param array<int, array<string, mixed>> $users
     */
    private function kpiFrom(array $users): array
    {
        $count = static function (array $roles, bool $activeOnly) use ($users): int {
            return count(array_filter($users, static fn (array $u): bool =>
                in_array($u['role'], $roles, true) && (!$activeOnly || $u['status'] === 'active')));
        };

        return [
            'total'        => count($users),
            'active_users' => count(array_filter($users, static fn (array $u): bool => $u['status'] === 'active')),
            'admins'       => ['active' => $count(['admin', 'super_admin'], true), 'total' => $count(['admin', 'super_admin'], false)],
            'coordinators' => ['active' => $count(['coordinator'], true), 'total' => $count(['coordinator'], false)],
            'staff'        => ['active' => $count(['staff'], true), 'total' => $count(['staff'], false)],
            'teachers'     => ['active' => $count(['teacher'], true), 'total' => $count(['teacher'], false)],
        ];
    }

    /** Report exports are recorded in the audit log. */
    private function logExport(string $filename): void
    {
        try {
            $user = $this->getCurrentUser();
            (new \AuditLog())->log((int) ($user['id'] ?? 0), 'user', $user['role'] ?? 'unknown', 'export_report', 'report', null, ['file' => $filename]);
        } catch (\Throwable $e) {
            error_log('Export audit failed: ' . $e->getMessage());
        }
    }

    private function exportCsv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            // A cell starting with = + - @ would run as a formula when the
            // file is opened in Excel; prefix it so it stays text.
            fputcsv($out, array_map(static function ($v) {
                if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($v)) {
                    return "'" . $v;
                }
                return $v;
            }, $row));
        }
        $this->logExport($filename);

        fclose($out);
        exit;
    }
}
