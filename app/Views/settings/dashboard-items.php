<?php
/**
 * Settings - Dashboard Items Configuration
 * Document 1 Page 1 Specification:
 * Super Admin -> Settings Page -> Dashboard Items -> Select a user role from DDL ->
 * Select Show/Hide for each dashboard item field.
 */

$roles = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'coordinator' => 'Coordinator',
    'staff'       => 'Staff',
    'teacher'     => 'Teacher',
    'student'     => 'Student',
    'parent'      => 'Parent / Guardian',
];

$widgetsList = \Setting::DASHBOARD_WIDGETS;
$selectedRole = $_GET['role'] ?? 'super_admin';
if (!array_key_exists($selectedRole, $roles)) {
    $selectedRole = 'super_admin';
}

$currentVisibility = $widgetsByRole[$selectedRole] ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Dashboard Items Configuration') ?></h1>
        <p class="text-sm text-muted"><?= te('Configure Show/Hide visibility for all 16 dashboard widgets per user role') ?></p>
    </div>
</div>

<!-- Role Selector -->
<div class="card mb-3">
    <div class="d-flex items-center justify-between flex-wrap gap-2">
        <div style="flex:1;min-width:280px;">
            <label class="form-label" for="roleSelector"><strong><?= te('Select Role to Configure:') ?></strong></label>
            <select id="roleSelector" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/settings/dashboard-items?role=' + this.value;">
                <?php foreach ($roles as $rKey => $rLabel): ?>
                    <option value="<?= $rKey ?>" <?= $selectedRole === $rKey ? 'selected' : '' ?>>
                        <?= te($rLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-sm text-muted" style="align-self:flex-end;">
            <?= te('Currently editing:') ?> <strong class="text-primary"><?= te($roles[$selectedRole]) ?></strong>
        </div>
    </div>
</div>

<!-- Widgets Form -->
<form method="POST" action="<?= BASE_URL ?>/settings/dashboard-items">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
    <input type="hidden" name="active_role" value="<?= htmlspecialchars($selectedRole, ENT_QUOTES, 'UTF-8') ?>">

    <div class="card mb-3">
        <div class="d-flex items-center justify-between mb-2">
            <h3><?= te('Dashboard Widgets for {role}', ['role' => $roles[$selectedRole]]) ?></h3>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn--ghost btn--sm" onclick="toggleAllWidgets(true)"><?= te('Show All') ?></button>
                <button type="button" class="btn btn--ghost btn--sm" onclick="toggleAllWidgets(false)"><?= te('Hide All') ?></button>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th><?= te('Dashboard Item / Widget') ?></th>
                        <th style="width:160px; text-align:center;"><?= te('Visibility Option') ?></th>
                        <th style="width:140px; text-align:center;"><?= te('Status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $index = 1;
                    foreach ($widgetsList as $widgetKey => $widgetLabel): 
                        $isVisible = $currentVisibility[$widgetKey] ?? true;
                    ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <strong><?= te($widgetLabel) ?></strong>
                                <div class="text-xs text-muted">widget_key: <code><?= $widgetKey ?></code></div>
                            </td>
                            <td style="text-align:center;">
                                <select 
                                    name="widgets[<?= $selectedRole ?>][<?= $widgetKey ?>]" 
                                    class="form-control form-control--sm widget-toggle-select"
                                    onchange="updateStatusBadge(this)"
                                >
                                    <option value="1" <?= $isVisible ? 'selected' : '' ?>><?= te('Show') ?></option>
                                    <option value="0" <?= !$isVisible ? 'selected' : '' ?>><?= te('Hide') ?></option>
                                </select>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge <?= $isVisible ? 'badge--success' : 'badge--neutral' ?> status-chip">
                                    <?= $isVisible ? te('Visible') : te('Hidden') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-between items-center mt-3 pt-2" style="border-top:1px solid var(--border);">
            <span class="text-xs text-muted"><?= te('Changes take effect immediately upon saving.') ?></span>
            <button type="submit" class="btn btn--primary">
                <?= te('Save Dashboard Items') ?>
            </button>
        </div>
    </div>
</form>

<script>
var TL = <?= json_encode(['visible' => t('Visible'), 'hidden' => t('Hidden')], JSON_UNESCAPED_UNICODE) ?>;
function toggleAllWidgets(show) {
    document.querySelectorAll('.widget-toggle-select').forEach(function(sel) {
        sel.value = show ? '1' : '0';
        updateStatusBadge(sel);
    });
}

function updateStatusBadge(selectEl) {
    var tr = selectEl.closest('tr');
    var badge = tr.querySelector('.status-chip');
    if (selectEl.value === '1') {
        badge.className = 'badge badge--success status-chip';
        badge.textContent = TL.visible;
    } else {
        badge.className = 'badge badge--neutral status-chip';
        badge.textContent = TL.hidden;
    }
}
</script>
