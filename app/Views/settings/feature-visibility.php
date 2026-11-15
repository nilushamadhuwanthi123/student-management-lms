<?php
/**
 * Settings - Role-Based Feature Visibility (Super Admin only).
 *
 * This only ever controls whether a nav link renders for a role. It is
 * a convenience, never an authorization check -- every route behind
 * these links keeps its own independent guard (branch, ownership,
 * role), completely unchanged by this page. Hiding a link here never
 * makes the page itself unreachable.
 *
 * Settings is deliberately not one of the toggleable features, and
 * Super Admin is not one of the roles offered here, so there is no way
 * to use this page to lock Super Admin out of the page that manages it.
 */

$roleLabels = [
    'admin'       => 'Admin',
    'coordinator' => 'Coordinator',
    'staff'       => 'Staff',
    'teacher'     => 'Teacher',
    'student'     => 'Student',
    'parent'      => 'Parent / Guardian',
];

$featureList = \Setting::FEATURE_MODULES;
$featureRoles = \Setting::FEATURE_ROLES;

$selectedRole = $_GET['role'] ?? 'student';
if (!array_key_exists($selectedRole, $roleLabels)) {
    $selectedRole = 'student';
}

$currentVisibility = $visibilityByRole[$selectedRole] ?? [];
$applicable = array_filter(
    $featureList,
    static fn ($label, $key) => in_array($selectedRole, $featureRoles[$key] ?? [], true),
    ARRAY_FILTER_USE_BOTH
);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Feature Visibility') ?></h1>
        <p class="text-sm text-muted"><?= te("Show or hide a feature's nav link per role. The underlying page stays as protected as it already was — this only changes what shows up in the sidebar.") ?></p>
    </div>
</div>

<div class="card mb-3">
    <div class="d-flex items-center justify-between flex-wrap gap-2">
        <div style="flex:1;min-width:280px;">
            <label class="form-label" for="roleSelector"><strong><?= te('Select Role to Configure:') ?></strong></label>
            <select id="roleSelector" class="form-control" onchange="window.location.href='<?= BASE_URL ?>/settings/feature-visibility?role=' + this.value;">
                <?php foreach ($roleLabels as $rKey => $rLabel): ?>
                    <option value="<?= $rKey ?>" <?= $selectedRole === $rKey ? 'selected' : '' ?>>
                        <?= te($rLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-sm text-muted" style="align-self:flex-end;">
            <?= te('Currently editing:') ?> <strong class="text-primary"><?= te($roleLabels[$selectedRole]) ?></strong>
        </div>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/feature-visibility">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
    <input type="hidden" name="active_role" value="<?= htmlspecialchars($selectedRole, ENT_QUOTES, 'UTF-8') ?>">

    <div class="card mb-3">
        <div class="d-flex items-center justify-between mb-2">
            <h3><?= te('Features for {role}', ['role' => $roleLabels[$selectedRole]]) ?></h3>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn--ghost btn--sm" onclick="toggleAllFeatures(true)"><?= te('Show All') ?></button>
                <button type="button" class="btn btn--ghost btn--sm" onclick="toggleAllFeatures(false)"><?= te('Hide All') ?></button>
            </div>
        </div>

        <?php if (empty($applicable)): ?>
            <p class="text-sm text-muted"><?= te('No role-specific features are offered for this role.') ?></p>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th><?= te('Feature') ?></th>
                        <th style="width:160px; text-align:center;"><?= te('Visibility') ?></th>
                        <th style="width:140px; text-align:center;"><?= te('Status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $index = 1;
                    foreach ($applicable as $featureKey => $featureLabel):
                        $isVisible = $currentVisibility[$featureKey] ?? true;
                    ?>
                        <tr>
                            <td><?= $index++ ?></td>
                            <td>
                                <strong><?= te($featureLabel) ?></strong>
                                <div class="text-xs text-muted">feature_key: <code><?= $featureKey ?></code></div>
                            </td>
                            <td style="text-align:center;">
                                <select
                                    name="features[<?= $featureKey ?>]"
                                    class="form-control form-control--sm feature-toggle-select"
                                    onchange="updateFeatureBadge(this)"
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
            <span class="text-xs text-muted"><?= te('Changes take effect immediately upon saving, and only affect what this role sees in the sidebar.') ?></span>
            <button type="submit" class="btn btn--primary"><?= te('Save Feature Visibility') ?></button>
        </div>
        <?php endif; ?>
    </div>
</form>

<script>
var TL = <?= json_encode(['visible' => t('Visible'), 'hidden' => t('Hidden')], JSON_UNESCAPED_UNICODE) ?>;
function toggleAllFeatures(show) {
    document.querySelectorAll('.feature-toggle-select').forEach(function(sel) {
        sel.value = show ? '1' : '0';
        updateFeatureBadge(sel);
    });
}

function updateFeatureBadge(selectEl) {
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
