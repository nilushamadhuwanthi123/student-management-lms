<?php
/**
 * 3. User & Staff Directory Report View
 */

$users  = $users ?? [];
$kpi    = $kpi ?? [];
$role   = $role ?? '';
$status = $status ?? '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('User & Staff Directory Reports') ?></h1>
        <p class="text-sm text-muted"><?= te('Administrative roster, active credentials, and role breakdown') ?></p>
    </div>
    <div class="page-header__actions d-flex gap-1">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost btn--sm">&larr; <?= te('Reports Hub') ?></a>
        <button type="button" class="btn btn--secondary btn--sm" onclick="window.print()">&#128424; <?= te('Print Report') ?></button>
        <a href="<?= BASE_URL ?>/reports/users?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn--primary btn--sm">&#128190; <?= te('Export to CSV') ?></a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3 no-print">
    <form method="GET" action="<?= BASE_URL ?>/reports/users" style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
        <div>
            <label class="form-label text-xs"><?= te('Role') ?></label>
            <select name="role" class="form-control form-control--sm" onchange="this.form.submit()">
                <option value=""><?= te('All Roles') ?></option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>><?= te('Administrators') ?></option>
                <option value="coordinator" <?= $role === 'coordinator' ? 'selected' : '' ?>><?= te('Coordinators') ?></option>
                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>><?= te('Staff Members') ?></option>
                <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>><?= te('Teachers') ?></option>
            </select>
        </div>

        <div>
            <label class="form-label text-xs"><?= te('Status') ?></label>
            <select name="status" class="form-control form-control--sm" onchange="this.form.submit()">
                <option value=""><?= te('All Statuses') ?></option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= te('Active') ?></option>
                <option value="pending_first_login" <?= $status === 'pending_first_login' ? 'selected' : '' ?>><?= te('Pending First Login') ?></option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?></option>
            </select>
        </div>

        <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
        <a href="<?= BASE_URL ?>/reports/users" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
    </form>
</div>

<!-- KPI Cards -->
<div class="kpi-grid mb-3">
    <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Total Staff / Teachers') ?></span>
        <span class="kpi-card__value"><?= (int) ($kpi['total'] ?? 0) ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Active Users') ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= (int) ($kpi['active_users'] ?? 0) ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563eb;">
        <span class="kpi-card__label"><?= te('Teachers (Active/Tot)') ?></span>
        <span class="kpi-card__value"><?= $kpi['teachers']['active'] ?? 0 ?> / <?= $kpi['teachers']['total'] ?? 0 ?></span>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Coordinators (Active/Tot)') ?></span>
        <span class="kpi-card__value"><?= $kpi['coordinators']['active'] ?? 0 ?> / <?= $kpi['coordinators']['total'] ?? 0 ?></span>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= te('User ID') ?></th>
                    <th><?= te('Full Name') ?></th>
                    <th><?= te('Role') ?></th>
                    <th><?= te('Email') ?></th>
                    <th><?= te('Phone (1)') ?></th>
                    <th><?= te('WhatsApp') ?></th>
                    <th><?= te('Campus') ?></th>
                    <th><?= te('Status') ?></th>
                    <th><?= te('Joined') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><code style="font-weight:700;"><?= htmlspecialchars($u['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><strong><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="badge badge--neutral"><?= te(ucfirst($u['role'])) ?></span></td>
                            <td><?= htmlspecialchars($u['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['whatsapp'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['coordinated_institute_name'] ?? $u['primary_institute_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= ($u['status'] ?? '') === 'active' ? 'badge--success' : 'badge--warning' ?>">
                                    <?= te(ucfirst(str_replace('_', ' ', $u['status'] ?? 'pending'))) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted"><?= te('No users found.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
