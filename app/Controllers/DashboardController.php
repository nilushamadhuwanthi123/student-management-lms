<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class DashboardController extends BaseController
{
    /**
     * Show the role-appropriate dashboard.
     */
    public function index(): void
    {
        AuthMiddleware::requireAuth();

        $user = $this->getCurrentUser();
        $role = $user['role'] ?? 'student';

        // Prepare basic stats based on role
        $stats = $this->getStatsForRole($role);

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'user'      => $user,
            'role'      => $role,
            'stats'     => $stats,
        ]);
    }

    /**
     * Gather dashboard statistics based on user role.
     *
     * @return array<string, mixed>
     */
    private function getStatsForRole(string $role): array
    {
        $stats = [];

        if (!$this->db) {
            return $stats;
        }

        try {
            switch ($role) {
                case 'super_admin':
                case 'admin':
                    $studentModel = new \Student();
                    $stats = $studentModel->getStats();

                    // Count pending workflows
                    $workflowModel = new \Workflow();
                    $pending = $workflowModel->getPending();
                    $stats['pending_workflows'] = count($pending);

                    // Revenue for current month
                    $paymentModel = new \Payment();
                    $revenue = $paymentModel->getRevenueStats(date('Y-m'));
                    $stats['revenue_collected'] = $revenue['total_collected'];
                    $stats['revenue_pending'] = $revenue['total_pending'];
                    break;

                case 'coordinator':
                case 'staff':
                    $studentModel = new \Student();
                    $stats = $studentModel->getStats();

                    // Pending workflows assigned to this user
                    $workflowModel = new \Workflow();
                    $userId = (int) session_get('user_id');
                    $myPending = $workflowModel->getByApprover($userId);
                    $stats['my_pending_approvals'] = count($myPending);
                    break;

                case 'teacher':
                    // Teacher sees their class stats
                    $userId = (int) session_get('user_id');
                    $userModel = new \User();
                    $classes = $userModel->getAssignedClasses($userId);
                    $stats['assigned_classes'] = count($classes);
                    $stats['classes'] = $classes;
                    break;

                case 'student':
                case 'parent':
                    // Minimal stats for student/parent
                    $stats['role_display'] = ucfirst($role);
                    break;
            }
        } catch (\Throwable $e) {
            error_log('Dashboard stats error: ' . $e->getMessage());
        }

        return $stats;
    }
}
