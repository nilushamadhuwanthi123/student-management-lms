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
.user-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title">User Management</h1>
        <p class="text-sm text-muted">Manage Admins, Coordinators, Staff, and Teachers (Students managed separately)</p>
    </div>
    <div class="page-header__actions">
        <a href="/users/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            + Create User
        </a>
    </div>
</div>

<div class="user-grid-layout">
    <!-- LEFT: Summary KPI Card (Document 1 Page 4) -->
    <aside class="user-kpi-sidebar">
        <h3 style="font-size: 1.1rem; margin-bottom: 0.75rem;">User Statistics</h3>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem;">
            Total <?= (int) ($kpi['total'] ?? 0) ?>
        </div>

        <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Active Users
        </div>

        <div style="display:flex; flex-direction:column; gap: 0.75rem;">
            <!-- Admins -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0.5rem 0.75rem; background: #f5f3ff; border-radius: var(--radius-sm); border-left: 4px solid #6366f1;">
                <span style="font-weight: 600; color: #4338ca;">Admins</span>
                <span class="badge" style="background:#6366f1; color:#fff;">
                    <?= $kpi['admins']['active'] ?? 0 ?> / <?= $kpi['admins']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Coordinators -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0.5rem 0.75rem; background: #f0fdfa; border-radius: var(--radius-sm); border-left: 4px solid #0d9488;">
                <span style="font-weight: 600; color: #0f766e;">Coordinators</span>
                <span class="badge" style="background:#0d9488; color:#fff;">
                    <?= $kpi['coordinators']['active'] ?? 0 ?> / <?= $kpi['coordinators']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Staff -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0.5rem 0.75rem; background: #fffbeb; border-radius: var(--radius-sm); border-left: 4px solid #d97706;">
                <span style="font-weight: 600; color: #b45309;">Staff</span>
                <span class="badge" style="background:#d97706; color:#fff;">
                    <?= $kpi['staff']['active'] ?? 0 ?> / <?= $kpi['staff']['total'] ?? 0 ?>
                </span>
            </div>

            <!-- Teachers -->
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 0.5rem 0.75rem; background: #eff6ff; border-radius: var(--radius-sm); border-left: 4px solid #2563eb;">
                <span style="font-weight: 600; color: #1d4ed8;">Teachers</span>
                <span class="badge" style="background:#2563eb; color:#fff;">
                    <?= $kpi['teachers']['active'] ?? 0 ?> / <?= $kpi['teachers']['total'] ?? 0 ?>
                </span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.8125rem; color: var(--text-muted);">
            Active Rate: <strong><?= $kpi['total'] > 0 ? round(($kpi['active_users'] / $kpi['total']) * 100, 1) : 0 ?>%</strong>
        </div>
    </aside>

    <!-- RIGHT: Cascading Filters + Cards Grid -->
    <div>
        <!-- Cascading Filters Form (Document 1 Page 4) -->
        <div class="card mb-3">
            <form method="GET" action="/users" id="userFilterForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 0.75rem; align-items: flex-end;">
                <!-- i. Search by name or email -->
                <div>
                    <label class="form-label text-xs">Search Name / Email / ID</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control form-control--sm" 
                        placeholder="Search by name, email, ID..."
                        value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <!-- ii. All Roles DDL -->
                <div>
                    <label class="form-label text-xs">All Roles</label>
                    <select name="role" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $rVal => $rLabel): ?>
                            <option value="<?= $rVal ?>" <?= ($filters['role'] ?? '') === $rVal ? 'selected' : '' ?>>
                                <?= $rLabel ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- iii. All Institutes DDL -->
                <div>
                    <label class="form-label text-xs">All Institutes</label>
                    <select name="institute_id" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value="">All Institutes</option>
                        <?php foreach ($institutes as $inst): ?>
                            <option value="<?= (int) $inst['id'] ?>" <?= ($filters['institute_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- iv. All Classes DDL (cascading from institute) -->
                <div>
                    <label class="form-label text-xs">All Classes</label>
                    <select name="class_id" class="form-control form-control--sm" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= (int) $cls['id'] ?>" <?= ($filters['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name'] ?? $cls['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter & Reset Buttons -->
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn--primary btn--sm">Filter</button>
                    <a href="/users" class="btn btn--ghost btn--sm">Reset</a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-between items-center mb-2">
            <span class="text-sm text-muted"><strong><?= count($users) ?></strong> Filtered records</span>
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
                                            <?= $cfg['label'] ?>
                                        </span>
                                    </div>
                                    <div class="user-card__id"><?= htmlspecialchars($u['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>

                            <!-- Details (Email, Phone, WhatsApp, Institute, Status, Joined Date/By) -->
                            <div class="user-card__details">
                                <div class="user-card__detail-item">
                                    <span class="user-card__detail-icon">&#9993;</span>
                                    <span><?= htmlspecialchars($u['email'] ?? 'No email', ENT_QUOTES, 'UTF-8') ?></span>
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
                                    <span><?= htmlspecialchars($u['coordinated_institute_name'] ?? $u['primary_institute_name'] ?? 'Not assigned', ENT_QUOTES, 'UTF-8') ?></span>
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
                                        <span class="badge badge--success">Active</span>
                                    <?php elseif ($status === 'pending_first_login'): ?>
                                        <span class="badge badge--warning">Pending first login</span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Inactive</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Joined info -->
                                <div class="text-xs text-muted mt-1">
                                    Joined: <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                    <?php if (!empty($u['creator_name'])): ?>
                                        by <?= htmlspecialchars($u['creator_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions: View, Edit, ID Card, Inactive toggle, Delete, Reset Password -->
                        <div>
                            <div class="d-flex items-center justify-between mb-2">
                                <span class="text-xs text-muted">Settings: <strong><?= $status === 'active' ? 'Enabled' : 'Disabled' ?></strong></span>
                                <button type="button" class="btn btn--ghost btn--xs" onclick="openResetPasswordModal(<?= (int) $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>')" title="Reset Password">
                                    &#128273; Reset Password
                                </button>
                            </div>

                            <div class="user-card__footer">
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="/users/<?= (int) $u['id'] ?>/edit" class="btn btn--ghost btn--xs">
                                        Edit
                                    </a>
                                    <a href="/users/<?= (int) $u['id'] ?>/id-card" target="_blank" class="btn btn--ghost btn--xs">
                                        ID Card
                                    </a>
                                </div>

                                <div class="d-flex gap-1 items-center">
                                    <!-- Toggle Active / Inactive -->
                                    <form method="POST" action="/users/<?= (int) $u['id'] ?>/toggle-status" style="margin:0;">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn <?= $status === 'active' ? 'btn--secondary' : 'btn--primary' ?> btn--xs">
                                            <?= $status === 'active' ? 'Inactive' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <!-- Delete Button -->
                                    <?php if ($u['role'] !== 'super_admin'): ?>
                                    <form method="POST" action="/users/<?= (int) $u['id'] ?>/delete" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete user <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>?');">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn btn--danger btn--xs">
                                            Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <div class="empty-state__icon">&#128101;</div>
                <div class="empty-state__title">No users found</div>
                <div class="empty-state__text">Try changing your search or filter options.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-backdrop" id="resetPasswordModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:420px; margin:1rem;">
        <h3 style="margin-bottom:0.75rem;">Reset User Password</h3>
        <p class="text-sm text-muted mb-2">Resetting password for: <strong id="resetUserName"></strong></p>
        <form method="POST" id="resetPasswordForm" action="">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
            <div class="form-group mb-2">
                <label class="form-label">New Password</label>
                <input type="text" name="new_password" class="form-control" value="Password@123" required minlength="6">
                <div class="form-hint text-xs">Default password is Password@123. The user will be required to change it on login.</div>
            </div>
            <div class="d-flex justify-end gap-1 mt-3">
                <button type="button" class="btn btn--ghost btn--sm" onclick="closeResetPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn--primary btn--sm">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetPasswordModal(userId, userName) {
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetPasswordForm').action = '/users/' + userId + '/reset-password';
    var m = document.getElementById('resetPasswordModal');
    m.style.display = 'flex';
}
function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}
</script>
