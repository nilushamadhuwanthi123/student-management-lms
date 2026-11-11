<?php
/**
 * Attendance Management View — Daily Sheet & Attendance Logs
 */

$records = $records ?? [];
$summary = $summary ?? [];
$classes = $classes ?? [];
$date    = $date ?? date('Y-m-d');
$filters = $filters ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Daily Attendance Management') ?></h1>
        <p class="text-sm text-muted"><?= te('Daily check-in logs, verification status, and red-flag alerts') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/attendance/qr" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></svg>
            <?= te('Launch QR Scan Terminal') ?>
        </a>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Total Check-Ins') ?></span>
        <span class="kpi-card__value"><?= (int) ($summary['total'] ?? 0) ?></span>
        <span class="text-xs text-muted"><?= date('d M Y', strtotime($date)) ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Present') ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= (int) ($summary['present'] ?? 0) ?></span>
        <span class="text-xs text-muted"><?= te('Normal Entry') ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid #F5B301;">
        <span class="kpi-card__label"><?= te('Late') ?></span>
        <span class="kpi-card__value" style="color:#D99A00;"><?= (int) ($summary['late'] ?? 0) ?></span>
        <span class="text-xs text-muted"><?= te('After Start Time') ?></span>
    </div>

    <div class="kpi-card" style="border-left: 4px solid var(--danger);">
        <span class="kpi-card__label"><?= te('Red Flag Alerts') ?></span>
        <span class="kpi-card__value" style="color:var(--danger);"><?= (int) ($summary['red_flag'] ?? 0) ?></span>
        <span class="text-xs text-muted"><?= te('Deactivated Scans') ?></span>
    </div>
</div>

<div class="card mb-3">
    <!-- Filter Toolbar -->
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/attendance" style="display:flex; flex-wrap:wrap; gap:0.75rem; width:100%; align-items:center;">
            <!-- Date Picker -->
            <div style="width:160px;">
                <input type="date" name="date" class="form-control form-control--sm" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
            </div>

            <!-- Class Filter -->
            <select name="class_id" class="form-control form-control--sm" style="width:auto; min-width:160px;" onchange="this.form.submit()">
                <option value=""><?= te('All Classes') ?></option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cls['class_name'] ?? $cls['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Status Filter -->
            <select name="status" class="form-control form-control--sm" style="width:auto; min-width:120px;" onchange="this.form.submit()">
                <option value=""><?= te('All Statuses') ?></option>
                <option value="present" <?= ($filters['status'] ?? '') === 'present' ? 'selected' : '' ?>><?= te('Present') ?></option>
                <option value="late" <?= ($filters['status'] ?? '') === 'late' ? 'selected' : '' ?>><?= te('Late') ?></option>
                <option value="red_flag" <?= ($filters['status'] ?? '') === 'red_flag' ? 'selected' : '' ?>><?= te('Red Flag') ?></option>
            </select>

            <!-- Search -->
            <div style="flex:1; min-width:180px;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control form-control--sm" 
                    placeholder="<?= te('Search student name or ID...') ?>" 
                    value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
            <a href="<?= BASE_URL ?>/attendance" class="btn btn--ghost btn--sm"><?= te('Today') ?></a>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= te('Student ID') ?></th>
                    <th><?= te('Student Name') ?></th>
                    <th><?= te('Class') ?></th>
                    <th><?= te('Check-In Time') ?></th>
                    <th><?= te('Method') ?></th>
                    <th><?= te('Status') ?></th>
                    <th><?= te('Notes / Flag Reason') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $r): 
                        $status = $r['status'] ?? 'present';
                    ?>
                        <tr style="<?= $status === 'red_flag' ? 'background:#fff1f2;' : '' ?>">
                            <td>
                                <strong style="font-family:monospace;"><?= htmlspecialchars($r['student_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <?php if (!empty($r['profile_photo'])): ?>
                                        <img src="<?= htmlspecialchars($r['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="avatar avatar--sm" style="width:28px; height:28px; font-size:0.75rem;">
                                            <?= strtoupper(mb_substr($r['student_name'] ?? 'S', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <span style="font-weight:600;"><?= htmlspecialchars($r['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($r['class_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-muted"><?= htmlspecialchars($r['institute_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <strong><?= date('g:i:s A', strtotime($r['check_in_time'])) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge--neutral" style="font-size:0.7rem;">
                                    <?= te(ucfirst(str_replace('_', ' ', $r['scan_method'] ?? 'qr_scan'))) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($status === 'present'): ?>
                                    <span class="badge badge--success"><?= te('Present') ?></span>
                                <?php elseif ($status === 'late'): ?>
                                    <span class="badge badge--warning"><?= te('Late') ?></span>
                                <?php elseif ($status === 'red_flag'): ?>
                                    <span class="badge badge--danger" style="animation:pulse 2s infinite;">&#9873; <?= te('RED FLAG') ?></span>
                                <?php else: ?>
                                    <span class="badge badge--neutral"><?= te(ucfirst($status)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['flagged_reason'])): ?>
                                    <span class="text-xs" style="color:var(--danger); font-weight:600;">
                                        <?= htmlspecialchars($r['flagged_reason'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state__icon">&#128197;</div>
                                <div class="empty-state__title"><?= htmlspecialchars(t('No attendance records for {date}', ['date' => date('d M Y', strtotime($date))]), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="empty-state__text"><?= te('Launch the QR Scan Terminal to begin scanning student QR entrance passes.') ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
