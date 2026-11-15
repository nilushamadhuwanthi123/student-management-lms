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
    <a href="<?= BASE_URL ?>/dashboard"><?= te('Dashboard') ?></a>
    <span class="breadcrumb__sep">/</span>
    <a href="<?= BASE_URL ?>/audit"><?= te('Audit Log') ?></a>
    <span class="breadcrumb__sep">/</span>
    <span><?= te('User #{id} History', ['id' => (int) $userId]) ?></span>
</div>

<div class="page-header d-flex justify-between items-center mb-3">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($pageTitle ?? te('User Activity History'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted"><?= te('Showing chronological timeline of actions performed by {type} ID #{id}', ['type' => ucfirst($userType), 'id' => (int) $userId]) ?></p>
    </div>
    <div class="d-flex gap-1">
        <a href="<?= BASE_URL ?>/audit" class="btn btn--ghost btn--sm">&larr; <?= te('Back to Audit Log') ?></a>
    </div>
</div>

<div class="table-wrapper">
    <div class="table-toolbar d-flex justify-between items-center">
        <h3 style="margin:0; font-size:1.1rem; font-weight:600;"><?= te('Activity Trail') ?></h3>
        <span class="text-xs text-muted"><?= te('{n} Action(s) Recorded', ['n' => count($entries)]) ?></span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('Timestamp') ?></th>
                <th data-sort><?= te('Action') ?></th>
                <th><?= te('Role') ?></th>
                <th><?= te('Target Entity') ?></th>
                <th><?= te('Details') ?></th>
                <th><?= te('IP Address') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($entries)): ?>
                <?php foreach ($entries as $log): ?>
                    <tr>
                        <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge--info"><?= te(ucwords(str_replace('_', ' ', $log['action'] ?? ''))) ?></span>
                        </td>
                        <td>
                            <span class="badge badge--neutral"><?= te(ucwords(str_replace('_', ' ', $log['user_role'] ?? 'user'))) ?></span>
                        </td>
                        <td class="text-sm">
                            <?php if (!empty($log['entity_type'])): ?>
                                <?= te(ucfirst($log['entity_type'])) ?>
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
                            <div class="empty-state__title"><?= te('No activity logs recorded') ?></div>
                            <div class="empty-state__text"><?= te('There are no audit log events recorded for this user yet.') ?></div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
