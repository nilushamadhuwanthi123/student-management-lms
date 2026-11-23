<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class DashboardController extends BaseController
{
    private \Setting $settingModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new \Setting();
    }

    /**
     * Show the Dashboard with all 16 widgets as specified in Document 1 Page 1
     * Configurable per role via Super Admin -> Settings -> Dashboard Items
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        // access: role-checked -- this dashboard is the institute's: every
        // student, the month's takings, everyone's unpaid fees. Students
        // and parents were landing here after login and could read all of
        // it. They have their own home pages, scoped to themselves.
        $userType = session_get('user_type');
        if ($userType === 'student') {
            $this->redirect('/my/home');
        }
        if ($userType === 'parent') {
            $this->redirect('/family');
        }

        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'student';

        // Load widget visibility permissions for this role
        $widgetVisibility = $this->settingModel->getDashboardWidgets($role);

        // Gather all 16 widgets data
        $data = $this->gatherDashboardData($role, (int) ($user['id'] ?? 0));
        $data['work_queue'] = $this->workQueue($role, (int) ($user['id'] ?? 0));
        $data['is_global'] = \App\Services\BranchScope::current()->isGlobal();

        $this->view('dashboard/index', [
            'pageTitle'        => 'Dashboard',
            'user'             => $user,
            'role'             => $role,
            'widgets'          => $data,
            'widgetVisibility' => $widgetVisibility,
        ], 'layouts/admin');
    }

    /**
     * "Today's work": real counts of things waiting for this person, each
     * linked to the page where it is dealt with. Only items the role can act
     * on are listed, and every count is limited to the viewer's branches.
     *
     * Every item added here must be a page THIS role may open. An item
     * whose page refuses the role is worse than no item: the count looks
     * like work waiting, and the click lands on the dashboard again.
     * tests/role_crawl.php checks this by walking the dashboard as each
     * role in turn.
     *
     * @return list<array{label: string, count: int, url: string, tone: string}>
     */
    private function workQueue(string $role, int $userId): array
    {
        if (!in_array($role, ['super_admin', 'admin', 'coordinator', 'staff'], true)) {
            return [];
        }
        $db = \Database::connect();
        $scope = \App\Services\BranchScope::current();
        $inP = $scope->classSql('p.class_id');
        $inClass = $scope->classSql('a.class_id');
        $inInst = $scope->instituteSql('institute_id');
        $month = date('Y-m-01');
        $items = [];
        $count = static function (string $sql, array $params = []) use ($db): int {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                return (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                error_log('Work queue count failed: ' . $e->getMessage());
                return 0;
            }
        };

        if (in_array($role, ['super_admin', 'admin', 'staff'], true)) {
            $items[] = ['label' => t('Payment slips to check'), 'url' => '/payments/slips', 'tone' => 'warning',
                'count' => $count("SELECT COUNT(*) FROM payment_slips ps JOIN payments p ON p.id = ps.payment_id WHERE ps.status = 'pending'{$inP}")];
        }

        // Both of these pages refuse anyone below coordinator, so listing
        // them for staff put a count on the dashboard that bounced off
        // its own page when clicked.
        if (in_array($role, ['super_admin', 'admin', 'coordinator'], true)) {
            $items[] = ['label' => t('New enrolment inquiries'), 'url' => '/inquiries', 'tone' => 'primary',
                'count' => $scope->isGlobal()
                    ? $count("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")
                    : $count("SELECT COUNT(*) FROM contact_messages WHERE status = 'new' AND kind = 'enrollment'{$inInst}")];

            $items[] = ['label' => t('Enrolment requests awaiting review'), 'url' => '/enrollment-requests', 'tone' => 'primary',
                'count' => $scope->isGlobal()
                    ? $count("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending'")
                    : $count("SELECT COUNT(*) FROM enrollment_requests WHERE status = 'pending'{$inInst}")];
        }

        $openHelp = "status IN ('open','in_progress')";
        if ($scope->isGlobal()) {
            $help = $count("SELECT COUNT(*) FROM help_threads WHERE $openHelp");
        } elseif ($role === 'staff') {
            $help = $count("SELECT COUNT(*) FROM help_threads WHERE $openHelp AND kind = 'support'{$inInst}");
        } else {
            $help = $count("SELECT COUNT(*) FROM help_threads WHERE $openHelp AND ((kind = 'support'{$inInst}) OR (kind = 'question'{$scope->classSql('class_id')}))");
        }
        $items[] = ['label' => t('Open help requests'), 'url' => '/help', 'tone' => 'primary', 'count' => $help];

        $items[] = ['label' => t('Approvals waiting for you'), 'url' => '/workflows', 'tone' => 'warning',
            'count' => $count("SELECT COUNT(*) FROM workflow_inbox WHERE recipient_id = :me AND status = 'pending'", [':me' => $userId])];

        $items[] = ['label' => t('Unpaid fees this month'), 'url' => '/payments', 'tone' => 'danger',
            'count' => $count("SELECT COUNT(*) FROM payments p WHERE p.month_year = :m AND p.payment_status IN ('pending','overdue'){$inP}", [':m' => $month])];

        $items[] = ['label' => t('Marked absent today'), 'url' => '/attendance', 'tone' => 'danger',
            'count' => $count("SELECT COUNT(*) FROM attendance a WHERE a.date = :d AND a.status = 'absent'{$inClass}", [':d' => date('Y-m-d')])];

        return $items;
    }

    /**
     * Gather comprehensive data for all 16 widgets
     */
    private function gatherDashboardData(string $role, int $userId): array
    {
        $db = \Database::connect();
        $data = [];

        $today = date('Y-m-d');
        $currentMonth = date('Y-m');
        $dayOfWeek = date('l'); // e.g. "Monday"

        // Every figure below is limited to the viewer's branch (or, for a
        // teacher, their classes). For the super admin these are all "".
        $scope = \App\Services\BranchScope::current();
        $inStudents = $scope->studentSql('students.id');
        $inClassId  = $scope->classSql('class_id');
        $inC        = $scope->classSql('c.id');
        $inP        = $scope->classSql('p.class_id');
        $inTeachers = $scope->isGlobal() ? '' : " AND id IN (SELECT teacher_id FROM classes WHERE teacher_id IS NOT NULL{$scope->classSql('id')})";
        $inW        = $scope->studentSql('w.entity_id');

        try {
            // 1. Active Students
            $stmt = $db->query("SELECT COUNT(*) AS cnt FROM students WHERE status = 'active'{$inStudents}");
            $data['active_students'] = (int) ($stmt->fetch()['cnt'] ?? 0);

            // 2. Today's Attendance
            $stmt = $db->query("SELECT 
                                    COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_count,
                                    COUNT(*) AS total_records
                                FROM attendance 
                                WHERE date = '{$today}'{$inClassId}");
            $attRow = $stmt->fetch();
            $data['today_attendance'] = (int) ($attRow['present_count'] ?? 0);
            $totalTodayAtt = (int) ($attRow['total_records'] ?? 0);

            // 3. Active Classes
            $stmt = $db->query("SELECT COUNT(*) AS cnt FROM classes WHERE status = 'active'{$scope->classSql('id')}");
            $data['active_classes'] = (int) ($stmt->fetch()['cnt'] ?? 0);

            // 4. Today's Revenue
            $stmt = $db->query("SELECT COALESCE(SUM(amount_paid), 0) AS rev FROM payments WHERE DATE(paid_at) = '{$today}' AND payment_status = 'paid'{$inClassId}");
            $data['today_revenue'] = (float) ($stmt->fetch()['rev'] ?? 0);

            // 5. Monthly Revenue
            $stmt = $db->query("SELECT COALESCE(SUM(amount_paid), 0) AS rev FROM payments WHERE payment_status = 'paid' AND (month_year LIKE '{$currentMonth}%' OR DATE_FORMAT(paid_at, '%Y-%m') = '{$currentMonth}'){$inClassId}");
            $data['monthly_revenue'] = (float) ($stmt->fetch()['rev'] ?? 0);

            // 6. Pending Payments
            $stmt = $db->query("SELECT COALESCE(SUM(amount_due - amount_paid), 0) AS pending FROM payments WHERE payment_status IN ('pending', 'overdue'){$inClassId}");
            $data['pending_payments'] = (float) ($stmt->fetch()['pending'] ?? 0);

            // 7. Today's Attendance Overview
            $rate = ($data['active_students'] > 0) ? round(($data['today_attendance'] / $data['active_students']) * 100, 1) : 0;
            $data['today_attendance_overview'] = [
                'present' => $data['today_attendance'],
                'total'   => $data['active_students'],
                'rate'    => $rate,
            ];

            // 8. Active Teachers
            $stmt = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'teacher' AND status = 'active'{$inTeachers}");
            $data['active_teachers'] = (int) ($stmt->fetch()['cnt'] ?? 0);

            // 9. Attendance Trend (7 Days) [Daily present vs total]
            $trend = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $dayLabel = date('D', strtotime($d));
                $stmt = $db->query("SELECT 
                                        COUNT(CASE WHEN status = 'present' THEN 1 END) AS pres,
                                        COUNT(*) AS tot
                                    FROM attendance WHERE date = '{$d}'{$inClassId}");
                $tRow = $stmt->fetch();
                $trend[] = [
                    'date'    => $d,
                    'day'     => $dayLabel,
                    'present' => (int) ($tRow['pres'] ?? 0),
                    'total'   => (int) ($tRow['tot'] ?? 0),
                ];
            }
            $data['attendance_trend_7d'] = $trend;

            // 10. Students by Class [Active student distribution]
            $stmt = $db->query("SELECT 
                                    c.id, c.class_name, c.subject, c.grade,
                                    COUNT(e.student_id) AS student_count
                                FROM classes c
                                LEFT JOIN enrollments e ON c.id = e.class_id AND e.enrollment_status = 'active'
                                WHERE c.status = 'active'{$inC}
                                GROUP BY c.id
                                ORDER BY student_count DESC LIMIT 5");
            $data['students_by_class'] = $stmt->fetchAll();

            // 11. All Active Classes
            $stmt = $db->query("SELECT 
                                    c.*, 
                                    i.name AS institute_name, 
                                    t.full_name AS teacher_name,
                                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.enrollment_status = 'active') AS student_count
                                FROM classes c
                                LEFT JOIN institutes i ON c.institute_id = i.id
                                LEFT JOIN users t ON c.teacher_id = t.id
                                WHERE c.status = 'active'{$inC}
                                ORDER BY c.class_name ASC LIMIT 8");
            $data['all_active_classes'] = $stmt->fetchAll();

            // 12. Today Classes
            $stmt = $db->query("SELECT 
                                    c.*, i.name AS institute_name, t.full_name AS teacher_name 
                                FROM classes c
                                LEFT JOIN institutes i ON c.institute_id = i.id
                                LEFT JOIN users t ON c.teacher_id = t.id
                                WHERE c.status = 'active'{$inC} AND (c.schedule_days LIKE '%{$dayOfWeek}%' OR c.schedule_days IS NULL)
                                ORDER BY c.start_time ASC");
            $data['today_classes'] = $stmt->fetchAll();

            // 13. Today New Students
            $stmt = $db->query("SELECT COUNT(*) AS cnt FROM students WHERE DATE(created_at) = '{$today}'{$inStudents}");
            $data['today_new_students'] = (int) ($stmt->fetch()['cnt'] ?? 0);

            // 14. This Month New Students
            $stmt = $db->query("SELECT COUNT(*) AS cnt FROM students WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$currentMonth}'{$inStudents}");
            $data['this_month_new_students'] = (int) ($stmt->fetch()['cnt'] ?? 0);

            // 15. Upcoming Classes
            $nowTime = date('H:i:s');
            $stmt = $db->query("SELECT 
                                    c.*, i.name AS institute_name, t.full_name AS teacher_name
                                FROM classes c
                                LEFT JOIN institutes i ON c.institute_id = i.id
                                LEFT JOIN users t ON c.teacher_id = t.id
                                WHERE c.status = 'active'{$inC} AND c.schedule_days LIKE '%{$dayOfWeek}%' AND c.start_time >= '{$nowTime}'
                                ORDER BY c.start_time ASC LIMIT 4");
            $data['upcoming_classes'] = $stmt->fetchAll();

            // 16. Recent Messages & Workflow Approvals
            $stmt = $db->prepare("SELECT * FROM notifications WHERE recipient_type = 'user' AND recipient_id = :me ORDER BY id DESC LIMIT 5");
            $stmt->execute([':me' => $userId]);
            $data['recent_messages'] = $stmt->fetchAll();

            $stmt = $db->query("SELECT 
                                    w.*, s.full_name AS student_name, s.student_id_display
                                FROM workflows w
                                LEFT JOIN students s ON w.entity_id = s.id
                                WHERE w.status = 'pending'{$inW}
                                ORDER BY w.id DESC LIMIT 5");
            $data['recent_workflows'] = $stmt->fetchAll();

            // 17. This Month Income (Institutes & Teachers)
            $stmt = $db->query("SELECT 
                                    COALESCE(SUM(p.amount_paid), 0) AS total_paid,
                                    COALESCE(SUM(p.amount_paid * (c.teacher_revenue_split / 100)), 0) AS teacher_share,
                                    COALESCE(SUM(p.amount_paid * ((100 - c.teacher_revenue_split) / 100)), 0) AS institute_share
                                FROM payments p
                                JOIN classes c ON p.class_id = c.id
                                WHERE p.payment_status = 'paid' AND (p.month_year LIKE '{$currentMonth}%' OR DATE_FORMAT(p.paid_at, '%Y-%m') = '{$currentMonth}'){$inP}");
            $incRow = $stmt->fetch();
            $data['this_month_income'] = [
                'total'           => (float) ($incRow['total_paid'] ?? 0),
                'teacher_share'   => (float) ($incRow['teacher_share'] ?? 0),
                'institute_share' => (float) ($incRow['institute_share'] ?? 0),
            ];

        } catch (\Throwable $e) {
            error_log('Dashboard data gather error: ' . $e->getMessage());
        }

        return $data;
    }
}
