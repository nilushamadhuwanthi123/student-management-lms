<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

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

        $reportData = $this->attendanceModel->getAttendanceReport($startDate, $endDate, $filters);
        $classes    = $this->classModel->getActiveClasses();
        $institutes = $this->instituteModel->getActiveInstitutes();

        // Export to CSV if requested
        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('attendance_report_' . date('Ymd') . '.csv', [
                'Date', 'Class Name', 'Subject', 'Institute', 'Present Count', 'Late Count', 'Absent Count', 'Red Flags', 'Total Logged'
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
        $classes = $this->classModel->getGridList(['institute_id' => $instituteId]);
        $institutes = $this->instituteModel->getActiveInstitutes();

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('class_report_' . date('Ymd') . '.csv', [
                'Class Name', 'Subject', 'Medium', 'Grade', 'Teacher', 'Institute', 'Room', 'Monthly Fee', 'Students Count', 'Status'
            ], array_map(function($c) {
                return [
                    $c['class_name'], $c['subject'], $c['medium'] ?? '-', $c['grade'] ?? '-',
                    $c['teacher_name'] ?? '-', $c['institute_name'] ?? '-', $c['room'] ?? '-',
                    $c['monthly_fee'], $c['student_count'] ?? 0, ucfirst($c['status'] ?? 'active')
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
        // access: role-checked -- same as the Reports hub; this lists every staff member
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $users = $this->userModel->getFilteredUsers(['role' => $role, 'status' => $status]);
        $kpi = $this->userModel->getStatistics();

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportCsv('user_report_' . date('Ymd') . '.csv', [
                'User ID', 'Role', 'Full Name', 'Email', 'Phone 1', 'WhatsApp', 'Institute', 'Status', 'Joined Date'
            ], array_map(function($u) {
                return [
                    $u['user_id_display'], ucfirst($u['role']), $u['full_name'], $u['email'] ?? '-',
                    $u['phone1'], $u['whatsapp'] ?? '-', $u['coordinated_institute_name'] ?? $u['primary_institute_name'] ?? '-',
                    ucfirst($u['status']), date('Y-m-d', strtotime($u['created_at']))
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
        // access: role-checked -- same as the Reports hub; this is everyone's payments
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN, AuthMiddleware::ROLE_COORDINATOR, AuthMiddleware::ROLE_STAFF]);

        $month = trim($_GET['month'] ?? date('Y-m'));
        $db = \Database::connect();

        $sql = "SELECT 
                    c.id AS class_id,
                    c.class_name,
                    c.subject,
                    i.name AS institute_name,
                    t.full_name AS teacher_name,
                    c.monthly_fee,
                    c.teacher_revenue_split,
                    COUNT(DISTINCT e.student_id) AS enrolled_students,
                    COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount_paid ELSE 0 END), 0) AS total_collected,
                    COALESCE(SUM(CASE WHEN p.payment_status IN ('pending', 'overdue') THEN (p.amount_due - p.amount_paid) ELSE 0 END), 0) AS total_pending
                FROM classes c
                LEFT JOIN institutes i ON c.institute_id = i.id
                LEFT JOIN users t ON c.teacher_id = t.id
                LEFT JOIN enrollments e ON c.id = e.class_id AND e.enrollment_status = 'active'
                LEFT JOIN payments p ON c.id = p.class_id AND (p.month_year LIKE '{$month}%' OR DATE_FORMAT(p.paid_at, '%Y-%m') = '{$month}')
                GROUP BY c.id
                ORDER BY total_collected DESC";

        $reportData = $db->query($sql)->fetchAll();

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
                'Class Name', 'Subject', 'Institute', 'Teacher', 'Enrolled Students', 'Collected (Rs.)', 'Pending (Rs.)', 'Teacher Share (Rs.)', 'Institute Share (Rs.)'
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
                    FROM students";
        $stats = $db->query($statSql)->fetch();

        // Students list with deactivation status and class counts
        $statusFilter = trim($_GET['status'] ?? '');
        $where = '';
        if ($statusFilter !== '') {
            $where = "WHERE s.status = " . $db->quote($statusFilter);
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
                'Student ID', 'Full Name', 'Email', 'Phone 1', 'WhatsApp', 'Status', 'Classes Count', 'Joined Date'
            ], array_map(function($s) {
                return [
                    $s['student_id_display'], $s['full_name'], $s['email'] ?? '-',
                    $s['phone1'] ?? '-', $s['whatsapp'] ?? '-', ucfirst($s['status']),
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
    private function exportCsv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }
}
