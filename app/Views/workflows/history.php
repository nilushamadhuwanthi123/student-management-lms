<?php
/**
 * Workflows - History
 *
 * Variables: $workflows, $filters, $currentPage, $totalPages
 */

$workflows   = $workflows ?? [];
$filters     = $filters ?? [];
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages ?? 1;
$baseUrl     = defined('BASE_URL') ? BASE_URL : '';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Workflow History') ?></h1>
</div>

<!-- Filters -->
<div class="table-wrapper mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= $baseUrl ?>/workflows/history" style="display:flex;flex-wrap:wrap;gap:0.75rem;width:100%;align-items:center;">
            <select name="type" class="form-control" style="width:auto;min-width:150px;">
                <option value=""><?= te('All Types') ?></option>
                <option value="deletion" <?= ($filters['type'] ?? '') === 'deletion' ? 'selected' : '' ?>><?= te('Deletion') ?></option>
                <option value="deactivation" <?= ($filters['type'] ?? '') === 'deactivation' ? 'selected' : '' ?>><?= te('Deactivation') ?></option>
            </select>
            <select name="status" class="form-control" style="width:auto;min-width:140px;">
                <option value=""><?= te('All Statuses') ?></option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>><?= te('Completed') ?></option>
                <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= te('Rejected') ?></option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= te('Pending') ?></option>
            </select>
            <input type="date" name="date_from" class="form-control" style="width:auto;" placeholder="<?= te('From') ?>" value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="date" name="date_to" class="form-control" style="width:auto;" placeholder="<?= te('To') ?>" value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn--primary btn--sm"><?= te('Filter') ?></button>
            <a href="<?= $baseUrl ?>/workflows/history" class="btn btn--ghost btn--sm"><?= te('Clear') ?></a>
        </form>
    </div>

    <!-- History Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th data-sort><?= te('ID') ?></th>
                <th data-sort><?= te('Type') ?></th>
                <th data-sort><?= te('Student') ?></th>
                <th data-sort><?= te('Initiated By') ?></th>
                <th><?= te('Status') ?></th>
                <th><?= te('Steps') ?></th>
                <th data-sort><?= te('Created') ?></th>
                <th data-sort><?= te('Completed') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($workflows)): ?>
                <?php foreach ($workflows as $wf): ?>
                    <tr class="card--clickable" onclick="toggleWorkflowDetails(this)" style="cursor:pointer;">
                        <td class="font-medium">#<?= (int) ($wf['id'] ?? 0) ?></td>
                        <td>
                            <span class="badge badge--info"><?= ucfirst(htmlspecialchars($wf['type'] ?? '', ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td><?= htmlspecialchars($wf['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($wf['initiated_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                            $wfStatus = $wf['status'] ?? 'pending';
                            $wfBadge = match ($wfStatus) {
                                'completed', 'approved' => 'badge--success',
                                'rejected'              => 'badge--danger',
                                'pending'               => 'badge--pending',
                                default                 => 'badge--neutral',
                            };
                            ?>
                            <span class="badge <?= $wfBadge ?>"><?= ucfirst(htmlspecialchars($wfStatus, ENT_QUOTES, 'UTF-8')) ?></span>
                        </td>
                        <td class="text-sm"><?= (int) ($wf['completed_steps'] ?? 0) ?>/<?= (int) ($wf['total_steps'] ?? 0) ?></td>
                        <td class="text-sm"><?= htmlspecialchars($wf['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm"><?= htmlspecialchars($wf['completed_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php if (!empty($wf['steps'])): ?>
                        <tr class="workflow-details" style="display:none;">
                            <td colspan="8" style="background:var(--surface-sunken);padding:1rem;">
                                <h5 style="margin-bottom:0.5rem;"><?= te('Approval Steps') ?></h5>
                                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                    <?php foreach ($wf['steps'] as $step): ?>
                                        <div class="d-flex items-center gap-1" style="padding:0.5rem;background:var(--surface);border-radius:var(--radius-sm);border:1px solid var(--border);">
                                            <?php
                                            $stepAction = $step['action'] ?? 'pending';
                                            $stepBadge = match ($stepAction) {
                                                'approved' => 'badge--success',
                                                'rejected' => 'badge--danger',
                                                default    => 'badge--neutral',
                                            };
                                            ?>
                                            <span class="badge <?= $stepBadge ?>" style="min-width:70px;justify-content:center;">
                                                <?= ucfirst(htmlspecialchars($stepAction, ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                            <span class="text-sm font-medium"><?= htmlspecialchars($step['approver_role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($step['approver_name'])): ?>
                                                <span class="text-sm text-muted">(<?= htmlspecialchars($step['approver_name'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                            <?php endif; ?>
                                            <?php if (!empty($step['acted_at'])): ?>
                                                <span class="text-xs text-muted" style="margin-left:auto;"><?= htmlspecialchars($step['acted_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!empty($wf['reason'])): ?>
                                    <p class="text-sm" style="margin-top:0.75rem;margin-bottom:0;">
                                        <strong><?= te('Reason:') ?></strong> <?= htmlspecialchars($wf['reason'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state__icon">&#128203;</div>
                            <div class="empty-state__title"><?= te('No workflow history') ?></div>
                            <div class="empty-state__text"><?= te('Completed and rejected workflows will appear here.') ?></div>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        // Use $paginationUrl to avoid conflict with the global BASE_URL / $baseUrl
        $paginationUrl = $baseUrl . '/workflows/history?' . http_build_query(array_filter($filters));
        // Re-assign for the pagination component which expects $baseUrl
        $paginationBaseUrl = $paginationUrl;
        include __DIR__ . '/../components/pagination.php';
        ?>
    <?php endif; ?>
</div>

<script>
function toggleWorkflowDetails(row) {
    var details = row.nextElementSibling;
    if (details && details.classList.contains('workflow-details')) {
        details.style.display = details.style.display === 'none' ? 'table-row' : 'none';
    }
}
</script>
