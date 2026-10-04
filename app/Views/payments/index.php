<?php
/**
 * Payments - Overview
 *
 * Variables: $payments, $summary, $filters, $classes,
 *            $currentPage, $totalPages, $currentMonth
 */

$payments    = $payments ?? [];
$summary     = $summary ?? [];
$filters     = $filters ?? [];
$classes     = $classes ?? [];
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages ?? 1;
$currentMonth = $currentMonth ?? date('Y-m');
?>

<div class="page-header">
    <h1 class="page-header__title">Payment Overview</h1>
</div>

<!-- Revenue Summary -->
<div class="kpi-grid mb-3">
    <div class="kpi-card animate-slide-up delay-1">
        <span class="kpi-card__label">Collected This Month</span>
        <span class="kpi-card__value" style="color:var(--success);">LKR <?= number_format((float) ($summary['collected'] ?? 0), 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-2">
        <span class="kpi-card__label">Pending</span>
        <span class="kpi-card__value" style="color:var(--warning);">LKR <?= number_format((float) ($summary['pending'] ?? 0), 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-3">
        <span class="kpi-card__label">Overdue</span>
        <span class="kpi-card__value" style="color:var(--danger);">LKR <?= number_format((float) ($summary['overdue'] ?? 0), 2) ?></span>
    </div>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="/payments" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:center;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;">Month</label>
                <input type="month" name="month" class="form-control" style="width:auto;" value="<?= htmlspecialchars($filters['month'] ?? $currentMonth, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;">Class</label>
                <select name="class_id" class="form-control" style="width:auto;min-width:150px;">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls['class_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="align-self:flex-end;">
                <button type="submit" class="btn btn--primary btn--sm">Filter</button>
                <a href="/payments" class="btn btn--ghost btn--sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort>Student</th>
                <th data-sort>Class</th>
                <th data-sort>Month</th>
                <th data-sort>Amount (LKR)</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $pmt): ?>
                    <tr>
                        <td>
                            <div class="d-flex items-center gap-1">
                                <div class="avatar avatar--sm">
                                    <?= strtoupper(mb_substr($pmt['student_name'] ?? 'S', 0, 1)) ?>
                                </div>
                                <span class="font-medium"><?= htmlspecialchars($pmt['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($pmt['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($pmt['month'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="font-medium">LKR <?= number_format((float) ($pmt['amount'] ?? 0), 2) ?></td>
                        <td>
                            <?php
                            $pmtStatus = $pmt['status'] ?? 'pending';
                            $pmtBadge = match ($pmtStatus) {
                                'paid'    => 'badge--success',
                                'pending' => 'badge--pending',
                                'overdue' => 'badge--danger',
                                default   => 'badge--neutral',
                            };
                            ?>
                            <span class="badge <?= $pmtBadge ?>"><?= ucfirst(htmlspecialchars($pmtStatus, ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td>
                            <?php if ($pmtStatus !== 'paid'): ?>
                                <form method="POST" action="/payments/<?= (int) $pmt['id'] ?>/mark-paid" style="display:inline;">
                                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                    <button type="submit" class="btn btn--primary btn--sm" onclick="return confirm('Mark this payment as paid?')">
                                        Mark Paid
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="btn btn--ghost btn--sm disabled" style="color:var(--success);">
                                    Paid &#10003;
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128176;</div>
                            <div class="empty-state__title">No payment records</div>
                            <div class="empty-state__text">Payment records for the selected period will appear here.</div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $baseUrl = '/payments?' . http_build_query(array_filter($filters));
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>
