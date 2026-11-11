<?php
/**
 * 1. Attendance Report View
 */

$reportData = $reportData ?? [];
$classes    = $classes ?? [];
$institutes = $institutes ?? [];
$startDate  = $startDate ?? date('Y-m-01');
$endDate    = $endDate ?? date('Y-m-d');
$filters    = $filters ?? [];

// Calculate summary totals
$totPresent = array_sum(array_column($reportData, 'present_count'));
$totLate    = array_sum(array_column($reportData, 'late_count'));
$totAbsent  = array_sum(array_column($reportData, 'absent_count'));
$totRedFlag = array_sum(array_column($reportData, 'red_flag_count'));
$totLogged  = array_sum(array_column($reportData, 'total_logged'));
$rate       = ($totLogged > 0) ? round(($totPresent / $totLogged) * 100, 1) : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Attendance Analytics Report') ?></h1>
        <p class="text-sm text-muted"><?= te('Period:') ?> <strong><?= date('d M Y', strtotime($startDate)) ?></strong> to <strong><?= date('d M Y', strtotime($endDate)) ?></strong></p>
    </div>
    <div class="page-header__actions d-flex gap-1">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost btn--sm">
            &larr; <?= te('Reports Hub') ?>
        </a>
        <button type="button" class="btn btn--secondary btn--sm" onclick="window.print()">
            &#128424; <?= te('Print Report') ?>
        </button>
        <a href="<?= BASE_URL ?>/reports/attendance?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn--primary btn--sm">
            &#128190; <?= te('Export to CSV') ?>
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-3 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/attendance" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
        <div>
            <label class="form-label text-xs"><?= te('Start Date') ?></label>
            <input type="date" name="start_date" class="form-control form-control--sm" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div>
            <label class="form-label text-xs"><?= te('End Date') ?></label>
            <input type="date" name="end_date" class="form-control form-control--sm" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div style="min-width:180px;">
            <label class="form-label text-xs"><?= te('Class Filter') ?></label>
            <select name="class_id" class="form-control form-control--sm">
                <option value=""><?= te('All Classes') ?></option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= ($filters['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:180px;">
            <label class="form-label text-xs"><?= te('Institute Filter') ?></label>
            <select name="institute_id" class="form-control form-control--sm">
                <option value=""><?= te('All Institutes') ?></option>
                <?php foreach ($institutes as $i): ?>
                    <option value="<?= (int) $i['id'] ?>" <?= ($filters['institute_id'] ?? '') == $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="d-flex gap-1">
            <button type="submit" class="btn btn--primary btn--sm"><?= te('Apply Filters') ?></button>
            <a href="<?= BASE_URL ?>/reports/attendance" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
        </div>
    </form>
</div>

<!-- Summary Metric Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Total Check-Ins') ?></span>
        <span class="kpi-card__value"><?= number_format($totLogged) ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Present Total') ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= number_format($totPresent) ?></span>
        <span class="text-xs text-muted"><?= $rate ?>% <?= te('Average Rate') ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid #F5B301;">
        <span class="kpi-card__label"><?= te('Late Arrivals') ?></span>
        <span class="kpi-card__value" style="color:#D99A00;"><?= number_format($totLate) ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid var(--danger);">
        <span class="kpi-card__label"><?= te('Red Flags') ?></span>
        <span class="kpi-card__value" style="color:var(--danger);"><?= number_format($totRedFlag) ?></span>
        <span class="text-xs text-muted"><?= te('Deactivated Scans') ?></span>
    </div>
</div>

<!-- Report Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= te('Date') ?></th>
                    <th><?= te('Class Name') ?></th>
                    <th><?= te('Subject') ?></th>
                    <th><?= te('Institute') ?></th>
                    <th style="text-align:center;"><?= te('Present') ?></th>
                    <th style="text-align:center;"><?= te('Late') ?></th>
                    <th style="text-align:center;"><?= te('Red Flags') ?></th>
                    <th style="text-align:center;"><?= te('Total Logged') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reportData)): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><strong><?= date('d M Y', strtotime($row['date'])) ?></strong></td>
                            <td><?= htmlspecialchars($row['class_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['subject'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align:center;"><span class="badge badge--success"><?= (int) $row['present_count'] ?></span></td>
                            <td style="text-align:center;"><span class="badge badge--warning"><?= (int) $row['late_count'] ?></span></td>
                            <td style="text-align:center;">
                                <?php if ($row['red_flag_count'] > 0): ?>
                                    <span class="badge badge--danger">&#9873; <?= (int) $row['red_flag_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><strong><?= (int) $row['total_logged'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state__icon">&#128197;</div>
                                <div class="empty-state__title"><?= te('No attendance records for selected period') ?></div>
                                <div class="empty-state__text"><?= te('Change the date range or filters above.') ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
