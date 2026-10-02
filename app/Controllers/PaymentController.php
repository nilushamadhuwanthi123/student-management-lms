<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

class PaymentController extends BaseController
{
    private \Payment $paymentModel;

    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new \Payment();
    }

    /**
     * Payment listing with filters.
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

        $monthYear = $_GET['month'] ?? date('Y-m');
        $classId   = !empty($_GET['class_id']) ? (int) $_GET['class_id'] : null;

        // Get revenue stats for the selected month
        $revenueStats = [];
        $overduePayments = [];
        $payments = [];
        try {
            $revenueStats = $this->paymentModel->getRevenueStats($monthYear);
            $overduePayments = $this->paymentModel->getOverdue($classId);
            $payments = $this->paymentModel->getAllPayments($monthYear, $classId);
        } catch (\Throwable $e) {
            error_log('Payment stats error: ' . $e->getMessage());
        }

        $overdueSum = 0.0;
        foreach ($overduePayments as $op) {
            $overdueSum += (float) ($op['amount_due'] ?? $op['amount'] ?? 0) - (float) ($op['amount_paid'] ?? 0);
        }

        $summary = [
            'collected' => $revenueStats['total_collected'] ?? 0,
            'pending'   => $revenueStats['total_pending'] ?? 0,
            'overdue'   => $overdueSum,
        ];

        // Load classes for filter dropdown
        $classes = [];
        if ($this->db) {
            try {
                $classModel = new \ClassModel();
                $classes = $classModel->all('class_name ASC');
                foreach ($classes as &$cls) {
                    $cls['name'] = $cls['name'] ?? $cls['class_name'] ?? '';
                }
            } catch (\Throwable $e) {
                error_log('Failed to load classes: ' . $e->getMessage());
            }
        }

        $this->view('payments/index', [
            'pageTitle'       => 'Payments',
            'monthYear'       => $monthYear,
            'currentMonth'    => $monthYear,
            'revenueStats'    => $revenueStats,
            'overduePayments' => $overduePayments,
            'payments'        => $payments,
            'summary'         => $summary,
            'classes'         => $classes,
            'selectedClassId' => $classId,
            'filters'         => [
                'month'    => $monthYear,
                'class_id' => $classId,
            ],
            'currentPage'     => 1,
            'totalPages'      => 1,
        ]);
    }

    /**
     * View payments for a specific student.
     */
    public function studentPayments(string $studentId): void
    {
        AuthMiddleware::requireAuth();

        $payments = [];
        $student = null;
        try {
            $studentModel = new \Student();
            $student = $studentModel->find((int) $studentId);
            if ($student) {
                $payments = $this->paymentModel->getByStudent((int) $studentId);
            }
        } catch (\Throwable $e) {
            error_log('Student payment fetch error: ' . $e->getMessage());
        }

        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect('/payments');
        }

        $studentName = $student['full_name'] ?? trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

        $this->view('payments/student', [
            'pageTitle' => 'Payments - ' . $studentName,
            'student'   => $student,
            'payments'  => $payments,
        ]);
    }

    /**
     * Mark a payment as paid by URL id param.
     */
    public function markPaidById(string $id): void
    {
        $_POST['payment_id'] = $id;
        $this->markPaid();
    }

    /**
     * Generate payments view form.
     */
    public function generateView(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);

        $this->view('payments/generate', [
            'pageTitle'    => 'Generate Monthly Payments',
            'currentMonth' => date('Y-m'),
        ]);
    }

    /**
     * Mark a payment as paid (POST).
     */
    public function markPaid(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $paymentId = (int) ($_POST['payment_id'] ?? 0);

        if ($paymentId <= 0) {
            $this->flash('error', 'Invalid payment ID.');
            $this->back();
        }

        $userId = (int) session_get('user_id');

        try {
            $result = $this->paymentModel->markPaid($paymentId, $userId);

            if ($result) {
                // Audit log
                $this->logAudit('mark_payment_paid', 'payment', $paymentId);
                $this->flash('success', 'Payment marked as paid.');
            } else {
                $this->flash('error', 'Payment could not be updated. It may already be paid.');
            }
        } catch (\Throwable $e) {
            error_log('Mark paid error: ' . $e->getMessage());
            $this->flash('error', 'An error occurred while updating the payment.');
        }

        $this->back();
    }

    /**
     * Generate monthly payments for all active enrollments (POST).
     */
    public function generateMonthly(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
        ]);
        verifyCsrfToken();

        $monthYear = trim($_POST['month_year'] ?? date('Y-m'));

        try {
            $count = $this->paymentModel->generateMonthlyPayments($monthYear);
            $this->logAudit('generate_monthly_payments', 'payment', 0, [
                'month_year' => $monthYear,
                'count'      => $count,
            ]);
            $this->flash('success', "Generated {$count} payment records for {$monthYear}.");
        } catch (\Throwable $e) {
            error_log('Generate payments error: ' . $e->getMessage());
            $this->flash('error', 'Failed to generate monthly payments.');
        }

        $this->redirect('/payments?month=' . urlencode($monthYear));
    }

    /**
     * Log an audit entry.
     */
    private function logAudit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        try {
            $auditLog = new \AuditLog();
            $user = $this->getCurrentUser();
            $auditLog->log(
                (int) ($user['id'] ?? 0),
                'user',
                $user['role'] ?? 'unknown',
                $action,
                $entityType,
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
