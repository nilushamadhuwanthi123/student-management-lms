<?php
/**
 * 5. Student Status & Deactivation Report View
 */

$students     = $students ?? [];
$stats        = $stats ?? [];
$statusFilter = $statusFilter ?? '';

$totalCount       = (int) ($stats['total'] ?? 0);
$activeCount      = (int) ($stats['active_count'] ?? 0);
$inactiveCount    = (int) ($stats['inactive_count'] ?? 0);
$deactivatedCount = (int) ($stats['deactivated_count'] ?? 0);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Student Status & Deactivation Reports') ?></h1>
        <p class="text-sm text-muted"><?= te('Lifecycle tracking, active enrollments, and deactivated account compliance audit') ?></p>
    </div>
    <div class="page-header__actions d-flex gap-1">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost btn--sm">&larr; <?= te('Reports Hub') ?></a>
        <button type="button" class="btn btn--secondary btn--sm" onclick="window.print()">&#128424; <?= te('Print Report') ?></button>
        <a href="<?= BASE_URL ?>/reports/students?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn--primary btn--sm">&#128190; <?= te('Export to CSV') ?></a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/students" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
        <div style="min-width:180px;">
            <label class="form-label text-xs"><?= te('Status Filter') ?></label>
            <select name="status" class="form-control form-control--sm" onchange="this.form.submit()">
                <option value=""><?= te('All Statuses') ?> (<?= $totalCount ?>)</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>><?= te('Active') ?> (<?= $activeCount ?>)</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?> (<?= $inactiveCount ?>)</option>
                <option value="deactivated" <?= $statusFilter === 'deactivated' ? 'selected' : '' ?>><?= te('Deactivated') ?> (<?= $deactivatedCount ?>)</option>
            </select>
        </div>
        <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
        <a href="<?= BASE_URL ?>/reports/students" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Total Registered') ?></span>
        <span class="kpi-card__value"><?= $totalCount ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Active Students') ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= $activeCount ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #F5B301;">
        <span class="kpi-card__label"><?= te('Inactive') ?></span>
        <span class="kpi-card__value" style="color:#D99A00;"><?= $inactiveCount ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #ef4444;">
        <span class="kpi-card__label"><?= te('Deactivated / Red-Flagged') ?></span>
        <span class="kpi-card__value" style="color:#dc2626;"><?= $deactivatedCount ?></span>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= te('Student ID') ?></th>
                    <th><?= te('Full Name') ?></th>
                    <th><?= te('Contact') ?></th>
                    <th><?= te('Enrolled Classes') ?></th>
                    <th><?= te('Classes Count') ?></th>
                    <th><?= te('Status') ?></th>
                    <th><?= te('Registered Date') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $s): 
                        $status = strtolower($s['status'] ?? 'active');
                        $badgeClass = 'badge--success';
                        if ($status === 'inactive') $badgeClass = 'badge--warning';
                        if ($status === 'deactivated') $badgeClass = 'badge--danger';
                    ?>
                        <tr>
                            <td><code><?= htmlspecialchars($s['student_id_display'] ?? ('STU' . $s['id']), ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><strong><?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>
                                <div><?= htmlspecialchars($s['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-muted"><?= htmlspecialchars($s['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span class="text-xs"><?= $s['enrolled_classes'] ? htmlspecialchars($s['enrolled_classes'], ENT_QUOTES, 'UTF-8') : te('No classes') ?></span>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge--neutral font-bold"><?= (int) ($s['enrolled_classes_count'] ?? 0) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= te(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($s['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted"><?= te('No students matching the filter.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
