<?php
/**
 * 2. Class & Enrollment Report View
 */

$classes     = $classes ?? [];
$institutes  = $institutes ?? [];
$instituteId = $instituteId ?? null;

$totClasses = count($classes);
$totStudents = array_sum(array_column($classes, 'student_count'));
$avgPerClass = ($totClasses > 0) ? round($totStudents / $totClasses, 1) : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class & Enrollment Reports') ?></h1>
        <p class="text-sm text-muted"><?= te('Course capacity, teacher assignment, and student distribution') ?></p>
    </div>
    <div class="page-header__actions d-flex gap-1">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost btn--sm">&larr; <?= te('Reports Hub') ?></a>
        <button type="button" class="btn btn--secondary btn--sm" onclick="window.print()">&#128424; <?= te('Print Report') ?></button>
        <a href="<?= BASE_URL ?>/reports/classes?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn--primary btn--sm">&#128190; <?= te('Export to CSV') ?></a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/classes" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
        <div style="min-width:200px;">
            <label class="form-label text-xs"><?= te('Institute Filter') ?></label>
            <select name="institute_id" class="form-control form-control--sm" onchange="this.form.submit()">
                <option value=""><?= te('All Institutes') ?></option>
                <?php foreach ($institutes as $i): ?>
                    <option value="<?= (int) $i['id'] ?>" <?= $instituteId == $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
        <a href="<?= BASE_URL ?>/reports/classes" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Active Classes') ?></span>
        <span class="kpi-card__value"><?= $totClasses ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Total Enrolled Students') ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= $totStudents ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Average per Class') ?></span>
        <span class="kpi-card__value"><?= $avgPerClass ?></span>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= te('Class Name') ?></th>
                    <th><?= te('Subject & Grade') ?></th>
                    <th><?= te('Teacher') ?></th>
                    <th><?= te('Institute') ?></th>
                    <th><?= te('Room') ?></th>
                    <th><?= te('Monthly Fee') ?></th>
                    <th style="text-align:center;"><?= te('Enrolled Students') ?></th>
                    <th><?= te('Status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($classes)): ?>
                    <?php foreach ($classes as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($c['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['room'] ?? te('N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= te('Rs. {amount}', ['amount' => number_format((float) $c['monthly_fee'], 2)]) ?></td>
                            <td style="text-align:center;"><span class="badge badge--info font-bold"><?= (int) ($c['student_count'] ?? 0) ?></span></td>
                            <td><span class="badge <?= ($c['status'] ?? 'active') === 'active' ? 'badge--success' : 'badge--neutral' ?>"><?= te(ucfirst($c['status'] ?? 'active')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted"><?= te('No classes found.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
