<?php
/**
 * Audit Log - User Activity History
 *
 * Variables: $entries, $userId, $userType, $pageTitle
 */

$entries = $entries ?? [];
$userId = $userId ?? 0;
$userType = $userType ?? 'user';
?>

<div class="breadcrumb mb-2">
    <a href="/dashboard">Dashboard</a>
    <span class="breadcrumb__sep">/</span>
    <a href="/audit">Audit Log</a>
    <span class="breadcrumb__sep">/</span>
    <span>User #<?= (int) $userId ?> History</span>
</div>

<div class="page-header d-flex justify-between items-center mb-3">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($pageTitle ?? 'User Activity History', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted">Showing chronological timeline of actions performed by <?= htmlspecialchars(ucfirst($userType), ENT_QUOTES, 'UTF-8') ?> ID #<?= (int) $userId ?></p>
    </div>
    <div class="d-flex gap-1">
        <a href="/audit" class="btn btn--ghost btn--sm">&larr; Back to Audit Log</a>
    </div>
</div>

<div class="table-wrapper">
    <div class="table-toolbar d-flex justify-between items-center">
        <h3 style="margin:0; font-size:1.1rem; font-weight:600;">Activity Trail</h3>
        <span class="text-xs text-muted"><?= count($entries) ?> Action(s) Recorded</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th data-sort>Timestamp</th>
                <th data-sort>Action</th>
                <th>Role</th>
                <th>Target Entity</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($entries)): ?>
                <?php foreach ($entries as $log): ?>
                    <tr>
                        <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge--info"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="badge badge--neutral"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['user_role'] ?? 'user')), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="text-sm">
                            <?php if (!empty($log['entity_type'])): ?>
                                <?= htmlspecialchars(ucfirst($log['entity_type']), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($log['entity_id'])): ?>
                                    <span class="font-medium">#<?= (int) $log['entity_id'] ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-sm truncate" style="max-width:250px;" title="<?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($log['details'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128220;</div>
                            <div class="empty-state__title">No activity logs recorded</div>
                            <div class="empty-state__text">There are no audit log events recorded for this user yet.</div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
