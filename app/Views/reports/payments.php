<?php
/**
 * 4. Payment Summary Report View
 */

$reportData        = $reportData ?? [];
$month             = $month ?? date('Y-m');
$totCollected      = $totCollected ?? 0.0;
$totPending        = $totPending ?? 0.0;
$totTeacherSplit   = $totTeacherSplit ?? 0.0;
$totInstituteSplit = $totInstituteSplit ?? 0.0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Payment Summary Reports</h1>
        <p class="text-sm text-muted">Monthly revenue collection, pending dues, and teacher vs institute revenue splits</p>
    </div>
    <div class="page-header__actions d-flex gap-1">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost btn--sm">&larr; Reports Hub</a>
        <button type="button" class="btn btn--secondary btn--sm" onclick="window.print()">&#128424; Print Report</button>
        <a href="<?= BASE_URL ?>/reports/payments?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn--primary btn--sm">&#128190; Export to CSV</a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/payments" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
        <div style="min-width:180px;">
            <label class="form-label text-xs">Billing Month</label>
            <input type="month" name="month" class="form-control form-control--sm" value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">Filter</button>
        <a href="<?= BASE_URL ?>/reports/payments" class="btn btn--ghost btn--sm">Current Month</a>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid mb-3" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1rem;">
    <div class="kpi-card" style="border-left: 4px solid #10b981;">
        <span class="kpi-card__label">Total Collected (<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>)</span>
        <span class="kpi-card__value" style="color:#059669;">Rs. <?= number_format($totCollected, 2) ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #ef4444;">
        <span class="kpi-card__label">Total Pending / Overdue</span>
        <span class="kpi-card__value" style="color:#dc2626;">Rs. <?= number_format($totPending, 2) ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #3b82f6;">
        <span class="kpi-card__label">Teacher Net Share</span>
        <span class="kpi-card__value" style="color:#2563eb;">Rs. <?= number_format($totTeacherSplit, 2) ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #8b5cf6;">
        <span class="kpi-card__label">Institute Gross Share</span>
        <span class="kpi-card__value" style="color:#7c3aed;">Rs. <?= number_format($totInstituteSplit, 2) ?></span>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Teacher</th>
                    <th>Institute</th>
                    <th style="text-align:center;">Enrolled</th>
                    <th style="text-align:right;">Collected</th>
                    <th style="text-align:right;">Pending</th>
                    <th style="text-align:center;">Split % (T/I)</th>
                    <th style="text-align:right;">Teacher Share</th>
                    <th style="text-align:right;">Institute Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reportData)): ?>
                    <?php foreach ($reportData as $row): 
                        $col = (float) $row['total_collected'];
                        $split = (float) ($row['teacher_revenue_split'] ?? 70);
                        $tShare = $col * ($split / 100);
                        $iShare = $col * ((100 - $split) / 100);
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['class_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="text-xs text-muted"><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?> (Fee: Rs. <?= number_format((float) $row['monthly_fee'], 2) ?>)</div>
                            </td>
                            <td><?= htmlspecialchars($row['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align:center;"><span class="badge badge--neutral"><?= (int) $row['enrolled_students'] ?></span></td>
                            <td style="text-align:right; font-weight:600; color:#059669;">Rs. <?= number_format($col, 2) ?></td>
                            <td style="text-align:right; color:#dc2626;">Rs. <?= number_format((float) $row['total_pending'], 2) ?></td>
                            <td style="text-align:center;"><span class="badge badge--info"><?= (int)$split ?>% / <?= (100 - (int)$split) ?>%</span></td>
                            <td style="text-align:right; font-weight:600; color:#2563eb;">Rs. <?= number_format($tShare, 2) ?></td>
                            <td style="text-align:right; font-weight:600; color:#7c3aed;">Rs. <?= number_format($iShare, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f8fafc; font-weight:bold; border-top:2px solid var(--border);">
                        <td colspan="4" style="text-align:right;">Totals:</td>
                        <td style="text-align:right; color:#059669;">Rs. <?= number_format($totCollected, 2) ?></td>
                        <td style="text-align:right; color:#dc2626;">Rs. <?= number_format($totPending, 2) ?></td>
                        <td></td>
                        <td style="text-align:right; color:#2563eb;">Rs. <?= number_format($totTeacherSplit, 2) ?></td>
                        <td style="text-align:right; color:#7c3aed;">Rs. <?= number_format($totInstituteSplit, 2) ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted">No payment records found for <?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
