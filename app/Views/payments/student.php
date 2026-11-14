<?php
/**
 * Payments - Student Fee History & Management
 *
 * Variables: $student, $payments, $pageTitle
 */

$student = $student ?? [];
$payments = $payments ?? [];

$totalPaid = 0.0;
$totalPending = 0.0;
$overdueCount = 0;
$currentMonth = date('Y-m');

foreach ($payments as $p) {
    $st = $p['status'] ?? $p['payment_status'] ?? 'pending';
    $due = (float) ($p['amount_due'] ?? $p['amount'] ?? 0);
    $paid = (float) ($p['amount_paid'] ?? 0);

    if ($st === 'paid') {
        $totalPaid += $paid;
    } else {
        $totalPending += ($due - $paid);
        if (($p['month_year'] ?? '') < $currentMonth) {
            $overdueCount++;
        }
    }
}
?>

<div class="breadcrumb mb-2">
    <a href="<?= BASE_URL ?>/dashboard"><?= te('Dashboard') ?></a>
    <span class="breadcrumb__sep">/</span>
    <a href="<?= BASE_URL ?>/payments"><?= te('Payments') ?></a>
    <span class="breadcrumb__sep">/</span>
    <span><?= htmlspecialchars($student['full_name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="page-header d-flex justify-between items-center mb-3">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted"><?= te('ID:') ?> <?= htmlspecialchars($student['student_id_display'] ?? $student['student_id'] ?? '-', ENT_QUOTES, 'UTF-8') ?> | <?= te('Status:') ?> <span class="badge badge--active"><?= ucfirst(htmlspecialchars($student['status'] ?? 'active', ENT_QUOTES, 'UTF-8')) ?></span></p>
    </div>
    <div class="d-flex gap-1">
        <a href="<?= BASE_URL ?>/students/<?= (int) ($student['id'] ?? 0) ?>" class="btn btn--secondary btn--sm"><?= te('View 360 Profile') ?></a>
        <a href="<?= BASE_URL ?>/payments" class="btn btn--ghost btn--sm">&larr; <?= te('Back to Payments') ?></a>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card animate-slide-up delay-1">
        <span class="kpi-card__label"><?= te('Total Fee Paid') ?></span>
        <span class="kpi-card__value" style="color:var(--success);">LKR <?= number_format($totalPaid, 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-2">
        <span class="kpi-card__label"><?= te('Outstanding Balance') ?></span>
        <span class="kpi-card__value" style="color:var(--warning);">LKR <?= number_format($totalPending, 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-3">
        <span class="kpi-card__label"><?= te('Overdue Invoices') ?></span>
        <span class="kpi-card__value" style="color:var(--danger);"><?= $overdueCount ?></span>
    </div>
</div>

<!-- Payments History Table -->
<div class="table-wrapper">
    <div class="table-toolbar d-flex justify-between items-center">
        <h3 style="margin:0; font-size:1.1rem; font-weight:600;"><?= te('Monthly Fee Statement') ?></h3>
        <span class="text-xs text-muted"><?= count($payments) ?> <?= te('Record(s) Found') ?></span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('Month') ?></th>
                <th data-sort><?= te('Class') ?></th>
                <th data-sort><?= te('Amount Due') ?></th>
                <th data-sort><?= te('Amount Paid') ?></th>
                <th><?= te('Scholarship') ?></th>
                <th><?= te('Status') ?></th>
                <th data-sort><?= te('Paid At') ?></th>
                <th><?= te('Action') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $pmt): ?>
                    <?php
                    $pStatus = $pmt['status'] ?? $pmt['payment_status'] ?? 'pending';
                    $badgeClass = match ($pStatus) {
                        'paid'    => 'badge--success',
                        'pending' => 'badge--pending',
                        'overdue' => 'badge--danger',
                        'waived'  => 'badge--info',
                        default   => 'badge--neutral',
                    };
                    $sch = $pmt['scholarship_type'] ?? 'full_payment';
                    $schLabel = match ($sch) {
                        'half_payment', 'half' => te('50% Half Free'),
                        'free_card', 'free'    => te('100% Free Card'),
                        default                => te('Full Payment'),
                    };
                    ?>
                    <tr>
                        <td class="font-medium"><?= htmlspecialchars($pmt['month'] ?? substr((string) ($pmt['month_year'] ?? ''), 0, 7), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($pmt['class_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="font-medium">LKR <?= number_format((float) ($pmt['amount_due'] ?? $pmt['amount'] ?? 0), 2) ?></td>
                        <td>LKR <?= number_format((float) ($pmt['amount_paid'] ?? 0), 2) ?></td>
                        <td>
                            <span class="badge badge--neutral"><?= htmlspecialchars($schLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($pStatus, ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td class="text-xs text-muted"><?= !empty($pmt['paid_at']) ? htmlspecialchars($pmt['paid_at'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
                        <td>
                            <?php if ($pStatus !== 'paid'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/payments/<?= (int) $pmt['id'] ?>/mark-paid" style="display:inline;">
                                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                    <button type="submit" class="btn btn--primary btn--xs" data-confirm="<?= te('Confirm payment collection for this month?') ?>">
                                        <?= te('Mark Paid') ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge--success">&#10003; <?= te('Paid') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128179;</div>
                            <div class="empty-state__title"><?= te('No payment records found') ?></div>
                            <div class="empty-state__text"><?= te('This student does not have any recorded payment invoices yet.') ?></div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
