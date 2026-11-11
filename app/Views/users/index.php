<?php
/**
 * 1.1 All Users Page — Admin / Coordinator / Staff / Teacher Management
 * Document 1 Page 2-5 Specification:
 * - Theme colors per user role
 * - Summary KPI Card: Total, Active, Admins, Coordinators, Staff, Teachers (active/total)
 * - Cascading Filters: Search, All Roles, All Institutes, All Classes
 * - Card Layout with Profile Pic, Name + Role, ID, Email, Phone, WhatsApp, Institute,
 *   Status, Joined Date, Joined By, Actions (View, Edit, ID Card, Inactive, Delete, Reset Password).
 */

$users      = $users ?? [];
$kpi        = $kpi ?? [];
$institutes = $institutes ?? [];
$classes    = $classes ?? [];
$filters    = $filters ?? [];
$roles      = $roles ?? [];
$roleColors = $roleColors ?? \User::ROLE_COLORS;
?>

<style>
/* Role theme card styling */
.user-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    position: relative;
    transition: transform var(--transition), box-shadow var(--transition);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.user-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.user-card__header {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.user-card__avatar {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--surface-sunken);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
    border: 2px solid var(--border);
}
.user-card__title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.user-card__id {
    font-family: monospace;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--primary);
}
.user-card__details {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px dashed var(--divider);
}
.user-card__detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    word-break: break-all;
}
.user-card__detail-icon {
    font-size: 0.875rem;
    width: 18px;
    opacity: 0.75;
}
.user-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.75rem;
}
.user-kpi-sidebar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    height: fit-content;
}
.user-grid-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.5rem;
}
@media (max-width: 992px) {
    .user-grid-layout {
        grid-template-columns: 1fr;
    }
}
.user-kpi-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem .75rem; background:var(--surface-sunken); border-radius:var(--radius-sm); border-left:4px solid var(--primary); }
.user-kpi-row__label { font-weight:600; color:var(--text); }
.user-kpi-row .badge { background:var(--primary) !important; color:#fff !important; }
.user-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
    gap: 1.25rem;
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('User Management') ?></h1>
        <p class="text-sm text-muted"><?= te('Admins, coordinators, staff and teachers. Students are managed separately.') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/users/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?= te('Create user') ?>
        </a>
    </div>
</div>

<div class="user-grid-layout">
    <!-- LEFT: Summary KPI Card (Document 1 Page 4) -->
    <aside class="user-kpi-sidebar">
        <h3 style="font-size: 1.1rem; margin-bottom: 0.75rem;"><?= te('Staff summary') ?></h3>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;">
            <?= te('Total {n}', ['n' => (int) ($kpi['total'] ?? 0)]) ?>
        </div>

        <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
            <?= te('Active / total') ?>
        </div>

        <div style="display:flex; flex-direction:column; gap: 0.75rem;">
            <!-- Admins -->
            <div class="user-kpi-row" style="border-left-color:#2563EB;">
                <span class="user-kpi-row__label"><?= te('Admins') ?></span>
                <span class="badge" style="background:#2563EB; color:#fff;">
                    <?= $kpi['admins']['active'] ?? 0 ?> / <?= $kpi['admins']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Coordinators -->
            <div class="user-kpi-row" style="border-left-color:#0D9488;">
                <span class="user-kpi-row__label"><?= te('Coordinators') ?></span>
                <span class="badge" style="background:#2563EB; color:#fff;">
                    <?= $kpi['coordinators']['active'] ?? 0 ?> / <?= $kpi['coordinators']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Staff -->
            <div class="user-kpi-row" style="border-left-color:#D99A00;">
                <span class="user-kpi-row__label"><?= te('Staff') ?></span>
                <span class="badge" style="background:#D99A00; color:#fff;">
                    <?= $kpi['staff']['active'] ?? 0 ?> / <?= $kpi['staff']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Teachers -->
            <div class="user-kpi-row" style="border-left-color:#7C3AED;">
                <span class="user-kpi-row__label"><?= te('Teachers') ?></span>
                <span class="badge" style="background:#2563eb; color:#fff;">
                    <?= $kpi['teachers']['active'] ?? 0 ?> / <?= $kpi['teachers']['total'] ?? 0 ?>
                </span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.8125rem; color: var(--text-muted);">
            <?= te('Active rate') ?>: <strong><?= $kpi['total'] > 0 ? round(($kpi['active_users'] / $kpi['total']) * 100, 1) : 0 ?>%</strong>
        </div>
    </aside>

    <!-- RIGHT: Cascading Filters + Cards Grid -->
    <div>
        <!-- Cascading Filters Form (Document 1 Page 4) -->
        <div class="card mb-3">
            <form method="GET" action="<?= BASE_URL ?>/users" id="userFilterForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 0.75rem; align-items: flex-end;">
                <!-- i. Search by name or email -->
                <div>
                    <label class="form-label text-xs"><?= te('Search') ?></label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control form-control--sm" 
                        placeholder="<?= te('Name, email or ID') ?>"
                        value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <!-- ii. All Roles DDL -->
                <div>
                    <label class="form-label text-xs"><?= te('Role') ?></label>
                    <select name="role" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value=""><?= te('All roles') ?></option>
                        <?php foreach ($roles as $rVal => $rLabel): ?>
                            <option value="<?= $rVal ?>" <?= ($filters['role'] ?? '') === $rVal ? 'selected' : '' ?>>
                                <?= te($rLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- iii. All Institutes DDL -->
                <div>
                    <label class="form-label text-xs"><?= te('Branch') ?></label>
                    <select name="institute_id" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value=""><?= te('All branches') ?></option>
                        <?php foreach ($institutes as $inst): ?>
                            <option value="<?= (int) $inst['id'] ?>" <?= ($filters['institute_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- iv. All Classes DDL (cascading from institute) -->
                <div>
                    <label class="form-label text-xs"><?= te('Class') ?></label>
                    <select name="class_id" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value=""><?= te('All classes') ?></option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name'] ?? $cls['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter & Reset Buttons -->
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
                    <a href="<?= BASE_URL ?>/users" class="btn btn--ghost btn--sm"><?= te('Reset') ?></a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-between items-center mb-2">
            <span class="text-sm text-muted"><?= te('{n} people', ['n' => count($users)]) ?></span>
        </div>

        <!-- User Cards Container (Document 1 Page 4 Card Layout) -->
        <?php if (!empty($users)): ?>
            <div class="user-cards-container">
                <?php foreach ($users as $u): 
                    $roleKey = $u['role'] ?? 'staff';
                    $cfg = $roleColors[$roleKey] ?? [
                        'primary' => '#64748b',
                        'bg'      => '#f8fafc',
                        'border'  => '#cbd5e1',
                        'label'   => ucfirst($roleKey),
                    ];
                    $status = $u['status'] ?? 'pending_first_login';
                    $initial = strtoupper(mb_substr($u['full_name'] ?? 'U', 0, 1));
                ?>
                    <div class="user-card" style="border-top: 4px solid <?= $cfg['primary'] ?>;">
                        <div>
                            <!-- Header: Avatar + Name + Role -->
                            <div class="user-card__header">
                                <?php if (!empty($u['profile_photo'])): ?>
                                    <img src="<?= htmlspecialchars($u['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="user-card__avatar">
                                <?php else: ?>
                                    <div class="user-card__avatar" style="background: <?= $cfg['bg'] ?>; color: <?= $cfg['primary'] ?>; border-color: <?= $cfg['border'] ?>;">
                                        <?= $initial ?>
                                    </div>
                                <?php endif; ?>

                                <div style="flex:1; min-width:0;">
                                    <div class="user-card__title">
                                        <span title="<?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="badge" style="background:<?= $cfg['bg'] ?>; color:<?= $cfg['primary'] ?>; border:1px solid <?= $cfg['border'] ?>; font-size:0.7rem;">
                                            <?= te((string) $cfg['label']) ?>
                                        </span>
                                    </div>
                                    <div class="user-card__id"><?= htmlspecialchars($u['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>

                            <!-- Details (Email, Phone, WhatsApp, Institute, Status, Joined Date/By) -->
                            <div class="user-card__details">
                                <div class="user-card__detail-item">
                                    <span class="user-card__detail-icon">&#9993;</span>
                                    <span><?= !empty($u['email']) ? htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') : te('No email') ?></span>
                                </div>

                                <div class="user-card__detail-item">
                                    <span class="user-card__detail-icon">&#128222;</span>
                                    <span><?= htmlspecialchars($u['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($u['whatsapp'])): ?>
                                        <span class="text-xs text-muted" style="margin-left:auto;">
                                            <span style="color:#25D366; font-weight:bold;">WA:</span> <?= htmlspecialchars($u['whatsapp'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="user-card__detail-item">
                                    <span class="user-card__detail-icon">&#127979;</span>
                                    <span><?= !empty($u['branch_names']) ? htmlspecialchars(implode(', ', $u['branch_names']), ENT_QUOTES, 'UTF-8') : ($u['role'] === 'super_admin' ? te('All branches') : te('No branch yet')) ?></span>
                                </div>

                                <?php if (!empty($u['assigned_class_names'])): ?>
                                <div class="user-card__detail-item text-xs text-muted">
                                    <span class="user-card__detail-icon">&#128218;</span>
                                    <span title="<?= htmlspecialchars($u['assigned_class_names'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($u['assigned_class_names'], 0, 35, '...'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <!-- Status Badge -->
                                <div class="user-card__detail-item mt-1">
                                    <span class="user-card__detail-icon">&#9679;</span>
                                    <?php if ($status === 'active'): ?>
                                        <span class="badge badge--success"><?= te('Active') ?></span>
                                    <?php elseif ($status === 'pending_first_login'): ?>
                                        <span class="badge badge--warning"><?= te('Waiting for first login') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral"><?= te('Inactive') ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Joined info -->
                                <div class="text-xs text-muted mt-1">
                                    <?= te('Joined {date}', ['date' => lang_date('j M Y', (string) $u['created_at'])]) ?>
                                    <?php if (!empty($u['creator_name'])): ?>
                                        &middot; <?= te('by {name}', ['name' => $u['creator_name']]) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions: View, Edit, ID Card, Inactive toggle, Delete, Reset Password -->
                        <div>
                            <?php $canManage = !empty($u['can_manage']); ?>
                            <div class="d-flex items-center justify-between mb-2">
                                <span class="text-xs text-muted"><?= te('Account') ?>: <strong><?= $status === 'active' ? te('Enabled') : te('Disabled') ?></strong></span>
                                <?php if ($canManage): ?>
                                <form method="POST" action="<?= BASE_URL ?>/users/<?= (int) $u['id'] ?>/reset-password" style="margin:0;">
                                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                    <button type="submit" class="btn btn--ghost btn--xs" title="<?= te('Reset Password') ?>"
                                            data-confirm="<?= htmlspecialchars(t('Create a one-time reset code for {name}? Give it to them only after confirming who is asking. They will choose their own new password with it.', ['name' => $u['full_name']]), ENT_QUOTES, 'UTF-8') ?>">
                                        &#128273; <?= te('Reset Password') ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <?php if (!$canManage && !empty($u['manage_note'])): ?>
                                <p class="text-xs text-muted" style="margin:0 0 .6rem; display:flex; gap:.4rem; align-items:flex-start;">&#128274; <span><?= te($u['manage_note']) ?></span></p>
                            <?php endif; ?>

                            <div class="user-card__footer">
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($canManage): ?>
                                    <a href="<?= BASE_URL ?>/users/<?= (int) $u['id'] ?>/edit" class="btn btn--ghost btn--xs"><?= te('Edit') ?></a>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>/users/<?= (int) $u['id'] ?>/id-card" target="_blank" rel="noopener" class="btn btn--ghost btn--xs"><?= te('ID Card') ?></a>
                                </div>

                                <?php if ($canManage): ?>
                                <div class="d-flex gap-1 items-center">
                                    <form method="POST" action="<?= BASE_URL ?>/users/<?= (int) $u['id'] ?>/toggle-status" style="margin:0;">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn <?= $status === 'active' ? 'btn--secondary' : 'btn--primary' ?> btn--xs"
                                                <?php if ($status === 'active'): ?>data-confirm="<?= htmlspecialchars(t('Switch off {name}\'s account? They will be signed out and cannot log in until it is switched on again.', ['name' => $u['full_name']]), ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                                            <?= $status === 'active' ? te('Make inactive') : te('Activate') ?>
                                        </button>
                                    </form>

                                    <?php if (\App\Services\BranchScope::current()->isGlobal() && $u['role'] !== 'super_admin'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/users/<?= (int) $u['id'] ?>/delete" style="margin:0;">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn btn--danger btn--xs" data-confirm="<?= htmlspecialchars(t('Delete {name} permanently? This cannot be undone. To keep their history, make the account inactive instead.', ['name' => $u['full_name']]), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= te('Delete') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <div class="empty-state__icon">&#128101;</div>
                <div class="empty-state__title"><?= te('No one found') ?></div>
                <div class="empty-state__text"><?= te('Try a different search or filter.') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

