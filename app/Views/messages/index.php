<?php
/**
 * System Message Inbox View
 * Document 1 & Kavindu Role Guide Specification:
 * - Read/unread status
 * - Filters by role/date/type
 * - Notification details modal view
 * - Broadcast composer for Super Admin / Admin
 */

$notifications = $notifications ?? [];
$unreadCount   = $unreadCount ?? 0;
$filters       = $filters ?? [];
$currentUser   = $currentUser ?? [];
$role          = $currentUser['role'] ?? 'user';
$classOptions  = $classOptions ?? [];
$canBroadcast  = in_array($role, ['super_admin', 'admin', 'coordinator'], true);
$isSuperAdmin  = $role === 'super_admin';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('System Message Inbox') ?></h1>
        <p class="text-sm text-muted"><?= te('Internal system notifications, workflow alerts, and administrative broadcasts') ?></p>
    </div>
    <div class="page-header__actions">
        <?php if ($unreadCount > 0): ?>
            <form method="POST" action="<?= BASE_URL ?>/messages/mark-all-read" style="margin:0;">
                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                <button type="submit" class="btn btn--secondary btn--sm">
                    <?= te('Mark All as Read') ?> (<?= $unreadCount ?>)
                </button>
            </form>
        <?php endif; ?>

        <?php if ($canBroadcast): ?>
            <button type="button" class="btn btn--primary btn--sm" onclick="openBroadcastModal()">
                + <?= te('Compose Broadcast') ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Type Filter Tabs -->
<div class="card mb-3" style="padding:0.75rem 1rem;">
    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
        <span class="text-xs text-muted" style="margin-right:0.5rem; font-weight:700;"><?= te('FILTER BY TYPE:') ?></span>
        <?php 
        $types = [
            ''             => te('All'),
            'info'         => te('Info'),
            'warning'      => te('Warnings'),
            'deactivation' => te('Deactivations'),
            'deletion'     => te('Deletions'),
            'payment'      => te('Payments'),
            'system'       => te('System'),
        ];
        foreach ($types as $tKey => $tLabel): 
            $isActive = ($filters['type'] ?? '') === $tKey;
        ?>
            <a href="<?= BASE_URL ?>/messages?type=<?= $tKey ?>" class="btn <?= $isActive ? 'btn--primary' : 'btn--ghost' ?> btn--xs" style="border-radius:20px;">
                <?= $tLabel ?>
            </a>
        <?php endforeach; ?>

        <div style="margin-left:auto; display:flex; gap:0.5rem; align-items:center;">
            <a href="<?= BASE_URL ?>/messages?status=unread" class="btn <?= ($filters['is_read'] === 0) ? 'btn--secondary' : 'btn--ghost' ?> btn--xs">
                <?= te('Unread Only') ?>
            </a>
            <?php if (!empty($filters['type']) || $filters['is_read'] !== ''): ?>
                <a href="<?= BASE_URL ?>/messages" class="btn btn--ghost btn--xs" style="color:var(--danger);"><?= te('Clear Filters') ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Message List -->
<div class="card">
    <?php if (!empty($notifications)): ?>
        <div style="display:flex; flex-direction:column; gap:0.6rem;">
            <?php foreach ($notifications as $n): 
                $isRead = (bool) $n['is_read'];
                $type = $n['notification_type'] ?? 'info';
                $badgeStyle = match ($type) {
                    'deactivation' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                    'deletion'     => 'background:#fef2f2; color:#b91c1c; border:1px solid #f87171;',
                    'warning'      => 'background:#FFF8E6; color:#8A5B00; border:1px solid #FBE39B;',
                    'payment'      => 'background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;',
                    'system'       => 'background:#F4F7FC; color:#1A3FAF; border:1px solid #D8E2F2;',
                    default        => 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;',
                };
            ?>
                <div 
                    class="notification-item" 
                    style="padding:1rem; border-radius:10px; border:1px solid var(--border); background:<?= $isRead ? 'var(--surface)' : 'var(--surface-sunken)' ?>; border-left:4px solid <?= $isRead ? 'var(--border)' : 'var(--primary)' ?>; display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; cursor:pointer; transition:background 0.2s;"
                    onclick="viewNotification(<?= (int) $n['id'] ?>, '<?= htmlspecialchars(addslashes($n['title']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($n['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>', '<?= $type ?>', <?= $isRead ? 'true' : 'false' ?>)"
                >
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                            <span class="badge" style="<?= $badgeStyle ?> font-size:0.7rem; text-transform:uppercase;">
                                <?= te(ucfirst($type)) ?>
                            </span>
                            <?php if (!$isRead): ?>
                                <span class="badge badge--primary" style="font-size:0.65rem; padding:1px 6px;"><?= te('NEW') ?></span>
                            <?php endif; ?>
                            <strong style="color:var(--text); font-size:0.95rem; font-weight:<?= $isRead ? '600' : '700' ?>;">
                                <?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8') ?>
                            </strong>
                        </div>
                        <div class="text-sm text-muted" style="line-height:1.4;">
                            <?= htmlspecialchars(mb_strimwidth($n['body'] ?? '', 0, 140, '...'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div style="text-align:right; flex-shrink:0;">
                        <span class="text-xs text-muted" style="display:block;"><?= date('d M Y, g:i A', strtotime($n['created_at'])) ?></span>
                        <?php if (!$isRead): ?>
                            <form method="POST" action="<?= BASE_URL ?>/messages/<?= (int) $n['id'] ?>/read" style="margin-top:0.35rem;" onclick="event.stopPropagation();">
                                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                <button type="submit" class="btn btn--ghost btn--xs text-xs" style="color:var(--primary);">
                                    <?= te('Mark Read') ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state__icon">&#128236;</div>
            <div class="empty-state__title"><?= te('Your inbox is clear') ?></div>
            <div class="empty-state__text"><?= te('No messages match the current filter.') ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Notification Detail Modal -->
<div id="msgDetailModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:540px; margin:1rem;">
        <div class="d-flex justify-between items-center mb-2 pb-2" style="border-bottom:1px solid var(--border);">
            <div class="d-flex items-center gap-1">
                <span id="msgModalBadge" class="badge">Type</span>
                <span class="text-xs text-muted" id="msgModalDate">Date</span>
            </div>
            <button type="button" class="btn btn--ghost btn--xs" onclick="closeMsgModal()">&#10005;</button>
        </div>

        <h3 id="msgModalTitle" style="margin-bottom:0.75rem; font-size:1.15rem;">Title</h3>
        <div id="msgModalBody" style="font-size:0.925rem; color:var(--text); line-height:1.6; white-space:pre-wrap; background:var(--surface-sunken); padding:1rem; border-radius:8px; border:1px solid var(--border); margin-bottom:1.25rem;">
            Body
        </div>

        <div class="d-flex justify-end gap-1">
            <button type="button" class="btn btn--primary btn--sm" onclick="closeMsgModal()"><?= te('Close') ?></button>
        </div>
    </div>
</div>

<!-- Compose Broadcast Modal -->
<div id="broadcastModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:540px; margin:1rem;">
        <h3 style="margin-bottom:0.75rem;"><?= te('Compose System Broadcast') ?></h3>
        <p class="text-xs text-muted mb-2"><?= te('Send an internal notification to user inboxes (in-app routing, no SMS/email)') ?></p>

        <form method="POST" action="<?= BASE_URL ?>/messages/send">
            <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

            <div class="form-group mb-2">
                <label class="form-label"><?= te('Send to') ?></label>
                <div style="display:flex; gap:1.25rem;">
                    <label style="display:flex; gap:.4rem; align-items:center; font-weight:400;">
                        <input type="radio" name="target_type" value="class" onchange="toggleBroadcastTarget()" <?= $isSuperAdmin ? '' : 'checked' ?>>
                        <?= te('A class\'s students') ?>
                    </label>
                    <?php if ($isSuperAdmin): ?>
                    <label style="display:flex; gap:.4rem; align-items:center; font-weight:400;">
                        <input type="radio" name="target_type" value="role" checked onchange="toggleBroadcastTarget()">
                        <?= te('Staff, site-wide') ?>
                    </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group mb-2" id="classTargetGroup" style="display:<?= $isSuperAdmin ? 'none' : 'block' ?>;">
                <label class="form-label" for="classId"><?= te('Class') ?></label>
                <select id="classId" name="class_id" class="form-control form-control--sm">
                    <option value=""><?= te('-- Select class --') ?></option>
                    <?php foreach ($classOptions as $c): ?>
                        <option value="<?= (int) $c['id'] ?>">
                            <?= htmlspecialchars($c['institute_name'] . ' - ' . $c['class_name'] . (!empty($c['grade']) ? ' (' . $c['grade'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label style="display:flex; gap:.4rem; align-items:center; font-weight:400; margin-top:.5rem;">
                    <input type="checkbox" name="also_parents" value="1">
                    <?= te('Also notify their parents') ?>
                </label>
            </div>

            <div class="form-group mb-2" id="roleTargetGroup" style="display:<?= $isSuperAdmin ? 'block' : 'none' ?>;">
                <label class="form-label" for="targetRole"><?= te('Target Recipient Audience') ?></label>
                <select id="targetRole" name="target_role" class="form-control form-control--sm">
                    <option value="all"><?= te('All Users (System-wide)') ?></option>
                    <option value="teacher"><?= te('Teachers Only') ?></option>
                    <option value="coordinator"><?= te('Coordinators Only') ?></option>
                    <option value="staff"><?= te('Staff Only') ?></option>
                    <option value="admin"><?= te('Administrators Only') ?></option>
                </select>
            </div>

            <div class="form-group mb-2">
                <label class="form-label" for="msgType"><?= te('Message Classification') ?></label>
                <select id="msgType" name="type" class="form-control form-control--sm">
                    <option value="info"><?= te('Information') ?></option>
                    <option value="warning"><?= te('Important Warning') ?></option>
                    <option value="system"><?= te('System Notice') ?></option>
                </select>
            </div>

            <div class="form-group mb-2">
                <label class="form-label" for="broadcastTitle"><?= te('Subject / Title') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="broadcastTitle" name="title" class="form-control" placeholder="<?= te('e.g. System Maintenance Scheduled') ?>" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="broadcastBody"><?= te('Message Body') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <textarea id="broadcastBody" name="body" class="form-control" rows="4" placeholder="<?= te('Enter notification message details...') ?>" required></textarea>
            </div>

            <div class="d-flex justify-end gap-1">
                <button type="button" class="btn btn--ghost btn--sm" onclick="closeBroadcastModal()"><?= te('Cancel') ?></button>
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Dispatch Notification') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function viewNotification(id, title, body, date, type, isRead) {
    document.getElementById('msgModalTitle').textContent = title;
    document.getElementById('msgModalBody').textContent = body;
    document.getElementById('msgModalDate').textContent = date;
    var badge = document.getElementById('msgModalBadge');
    badge.textContent = type.toUpperCase();
    badge.className = 'badge badge--primary';

    document.getElementById('msgDetailModal').style.display = 'flex';

    if (!isRead) {
        fetch('<?= BASE_URL ?>/messages/' + id + '/read', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }
}

function closeMsgModal() {
    document.getElementById('msgDetailModal').style.display = 'none';
}

function openBroadcastModal() {
    document.getElementById('broadcastModal').style.display = 'flex';
}

function closeBroadcastModal() {
    document.getElementById('broadcastModal').style.display = 'none';
}

function toggleBroadcastTarget() {
    var checked = document.querySelector('input[name="target_type"]:checked');
    var isClass = !checked || checked.value === 'class';
    document.getElementById('classTargetGroup').style.display = isClass ? 'block' : 'none';
    document.getElementById('roleTargetGroup').style.display = isClass ? 'none' : 'block';
}
</script>
