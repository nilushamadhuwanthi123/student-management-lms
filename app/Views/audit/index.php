<?php
/**
 * Audit Log - Index
 *
 * Variables: $logs, $filters, $actionTypes, $entityTypes, $users,
 *            $currentPage, $totalPages
 */

$logs        = $logs ?? [];
$filters     = $filters ?? [];
$actionTypes = $actionTypes ?? [];
$entityTypes = $entityTypes ?? [];
$users       = $users ?? [];
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages ?? 1;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Audit Log') ?></h1>
    <div class="page-header__actions">
        <form method="GET" action="<?= BASE_URL ?>/audit/export" style="display:inline;">
            <?php foreach ($filters as $k => $v): ?>
                <?php if ($v !== '' && $v !== null): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit" class="btn btn--secondary btn--sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?= te('Export CSV') ?>
            </button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/audit" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Date From') ?></label>
                <input type="date" name="date_from" class="form-control" style="width:auto;" value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Date To') ?></label>
                <input type="date" name="date_to" class="form-control" style="width:auto;" value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Action') ?></label>
                <select name="action_type" class="form-control" style="width:auto;min-width:140px;">
                    <option value=""><?= te('All Actions') ?></option>
                    <?php foreach ($actionTypes as $at): ?>
                        <option value="<?= htmlspecialchars($at, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['action_type'] ?? '') === $at ? 'selected' : '' ?>>
                            <?= te(ucwords(str_replace('_', ' ', $at))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('Entity') ?></label>
                <select name="entity_type" class="form-control" style="width:auto;min-width:130px;">
                    <option value=""><?= te('All Entities') ?></option>
                    <?php foreach ($entityTypes as $et): ?>
                        <option value="<?= htmlspecialchars($et, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['entity_type'] ?? '') === $et ? 'selected' : '' ?>>
                            <?= te(ucfirst($et)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label text-xs" style="margin-bottom:0.15rem;"><?= te('User') ?></label>
                <select name="user_id" class="form-control" style="width:auto;min-width:150px;">
                    <option value=""><?= te('All Users') ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= ($filters['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name'] ?? $u['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
                <a href="<?= BASE_URL ?>/audit" class="btn btn--ghost btn--sm"><?= te('Clear') ?></a>
            </div>
        </form>
    </div>

    <!-- Audit Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('Timestamp') ?></th>
                <th data-sort><?= te('User') ?></th>
                <th><?= te('Role') ?></th>
                <th data-sort><?= te('Action') ?></th>
                <th><?= te('Entity') ?></th>
                <th><?= te('Details') ?></th>
                <th><?= te('IP') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-sm" style="white-space:nowrap;"><?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="font-medium"><?= htmlspecialchars($log['user_name'] ?? te('System'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge--neutral"><?= te(ucwords(str_replace('_', ' ', $log['user_role'] ?? ''))) ?></span>
                        </td>
                        <td>
                            <span class="badge badge--info"><?= te(ucwords(str_replace('_', ' ', $log['action'] ?? ''))) ?></span>
                        </td>
                        <td class="text-sm"><?= te(ucfirst($log['entity_type'] ?? '')) ?> #<?= (int) ($log['entity_id'] ?? 0) ?></td>
                        <td class="text-sm truncate" style="max-width:200px;" title="<?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($log['details'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128209;</div>
                            <div class="empty-state__title"><?= te('No audit entries') ?></div>
                            <div class="empty-state__text"><?= te('Audit log entries matching your filters will appear here.') ?></div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $baseUrl = '/audit?' . http_build_query(array_filter($filters));
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>
