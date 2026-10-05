<?php

declare(strict_types=1);

namespace App\Services;

class PaymentService
{
    private \Payment $paymentModel;
    private \AuditLog $auditLog;

    public function __construct()
    {
        $this->paymentModel = new \Payment();
        $this->auditLog = new \AuditLog();
    }

    /**
     * Generate monthly payment records for all active enrollments.
     *
     * @param  string $monthYear  e.g. "2026-09"
     * @return array{count: int, month_year: string}
     */
    public function generateMonthlyPayments(string $monthYear): array
    {
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            throw new \InvalidArgumentException('Invalid month format. Expected YYYY-MM.');
        }

        $count = $this->paymentModel->generateMonthlyPayments($monthYear);

        // Audit log
        $userId = (int) session_get('user_id', 0);
        $userRole = session_get('user_role', 'unknown');
        $this->auditLog->log(
            $userId,
            'user',
            $userRole,
            'generate_monthly_payments',
            'payment',
            0,
            ['month_year' => $monthYear, 'records_created' => $count]
        );

        return [
            'count'      => $count,
            'month_year' => $monthYear,
        ];
    }

    /**
     * Mark a specific payment as paid.
     *
     * @param  int $paymentId
     * @param  int $userId     The user marking the payment
     * @return bool
     */
    public function markAsPaid(int $paymentId, int $userId): bool
    {
        $result = $this->paymentModel->markPaid($paymentId, $userId);

        if ($result) {
            $userRole = session_get('user_role', 'unknown');
            $this->auditLog->log(
                $userId,
                'user',
                $userRole,
                'mark_payment_paid',
                'payment',
                $paymentId
            );
        }

        return $result;
    }

    /**
     * Check whether a student has overdue payments.
     *
     * @param  int $studentId
     * @return array{has_overdue: bool, overdue_count: int, overdue_payments: array}
     */
    public function checkOverdue(int $studentId): array
    {
        $currentMonth = date('Y-m-01');

        // Get all payments for student — month_year is DATE (first of month)
        $allPayments = $this->paymentModel->getByStudent($studentId);
        $overdue = [];

        foreach ($allPayments as $payment) {
            if ($payment['payment_status'] === 'pending' && $payment['month_year'] < $currentMonth) {
                $overdue[] = $payment;
            }
        }

        return [
            'has_overdue'      => !empty($overdue),
            'overdue_count'    => count($overdue),
            'overdue_payments' => $overdue,
        ];
    }

    /**
     * Calculate the fee after applying a scholarship discount.
     *
     * Scholarship ENUM values: 'full_payment', 'half_payment', 'free_card'
     *
     * @param  float  $baseFee         The class monthly fee
     * @param  string $scholarshipType
     * @return float  The amount due
     */
    public function calculateFeeWithScholarship(float $baseFee, string $scholarshipType): float
    {
        return $this->paymentModel->calculateAmountDue($baseFee, $scholarshipType);
    }

    /**
     * Get a payment summary for a student for the current month.
     *
     * @return array{total_due: float, total_paid: float, payments: array}
     */
    public function getStudentMonthSummary(int $studentId, string $monthYear): array
    {
        $payments = $this->paymentModel->getByStudent($studentId, $monthYear);

        $totalDue = 0.0;
        $totalPaid = 0.0;

        foreach ($payments as $payment) {
            $totalDue += (float) ($payment['amount_due'] ?? 0);
            $totalPaid += (float) ($payment['amount_paid'] ?? 0);
        }

        return [
            'total_due'  => $totalDue,
            'total_paid' => $totalPaid,
            'payments'   => $payments,
        ];
    }
}
