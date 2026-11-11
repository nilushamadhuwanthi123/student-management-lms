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
    <h1 class="page-header__title"><?= te('Payments') ?></h1>
</div>

<!-- Revenue Summary -->
<div class="kpi-grid mb-3">
    <div class="kpi-card animate-slide-up delay-1">
        <span class="kpi-card__label"><?= te('Collected this month') ?></span>
        <span class="kpi-card__value" style="color:var(--success);">LKR <?= number_format((float) ($summary['collected'] ?? 0), 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-2">
        <span class="kpi-card__label"><?= te('Pending') ?></span>
        <span class="kpi-card__value" style="color:var(--warning);">LKR <?= number_format((float) ($summary['pending'] ?? 0), 2) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-3">
        <span class="kpi-card__label"><?= te('Overdue') ?></span>
        <span class="kpi-card__value" style="color:var(--danger);">LKR <?= number_format((float) ($summary['overdue'] ?? 0), 2) ?></span>
    </div>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/payments" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:center;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Month') ?></label>
                <input type="month" name="month" class="form-control" style="width:auto;" value="<?= htmlspecialchars($filters['month'] ?? $currentMonth, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Class') ?></label>
                <select name="class_id" class="form-control" style="width:auto;min-width:150px;">
                    <option value=""><?= te('All classes') ?></option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls['class_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="align-self:flex-end;">
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
                <a href="<?= BASE_URL ?>/payments" class="btn btn--ghost btn--sm"><?= te('Clear') ?></a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('Student') ?></th>
                <th data-sort><?= te('Class') ?></th>
                <th data-sort><?= te('Month') ?></th>
                <th data-sort><?= te('Amount (LKR)') ?></th>
                <th><?= te('Status') ?></th>
                <th><?= te('Action') ?></th>
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
                        <td><?= !empty($pmt['month']) && strtotime((string) $pmt['month']) ? htmlspecialchars(lang_date('F Y', (string) $pmt['month']), ENT_QUOTES, 'UTF-8') : '' ?></td>
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
                            <span class="badge <?= $pmtBadge ?>"><?= te(ucfirst((string) $pmtStatus)) ?></span>
                        </td>
                        <td>
                            <?php if ($pmtStatus !== 'paid'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/payments/<?= (int) $pmt['id'] ?>/mark-paid" style="display:inline;">
                                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                    <button type="submit" class="btn btn--primary btn--sm" data-confirm="<?= te('Mark this payment as paid?') ?>">
                                        <?= te('Mark paid') ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="btn btn--ghost btn--sm disabled" style="color:var(--success);">
                                    <?= te('Paid') ?> &#10003;
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
                            <div class="empty-state__title"><?= te('No payment records') ?></div>
                            <div class="empty-state__text"><?= te('Payments for the chosen month and class will appear here.') ?></div>
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
