<?php
/**
 * 1.2 Institutes Page — Super Admin Institute Management
 * Document 1 Page 5 Specifications:
 * Filter: Search by Institute Name
 * Columns: INSTITUTE, COORDINATOR, PHONE (1), STATUS, ACTIONS (Edit, Activate/Deactivate, Delete)
 */

$institutes   = $institutes ?? [];
$coordinators = $coordinators ?? [];
$search       = $search ?? '';
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Institute Management</h1>
        <p class="text-sm text-muted">Manage affiliated branch campuses, locations, and assigned coordinators</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/institutes/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            + Register Institute
        </a>
    </div>
</div>

<div class="card mb-3">
    <!-- Filter: Search by Institute Name -->
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/institutes" style="display:flex; flex-wrap:wrap; gap:0.75rem; width:100%; align-items:center;">
            <div style="flex:1; min-width:240px; position:relative;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search by Institute Name, location, or coordinator..." 
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>
            <button type="submit" class="btn btn--primary btn--sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= BASE_URL ?>/institutes" class="btn btn--ghost btn--sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Grid: INSTITUTE, COORDINATOR, PHONE (1), STATUS, ACTIONS -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>INSTITUTE</th>
                    <th>COORDINATOR</th>
                    <th>PHONE (1)</th>
                    <th>STATUS</th>
                    <th style="text-align:right;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($institutes)): ?>
                    <?php foreach ($institutes as $inst): 
                        $status = $inst['status'] ?? 'active';
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <div class="avatar avatar--sm" style="background:#E8EEFC; color:#15307E; font-weight:700;">
                                        &#127979;
                                    </div>
                                    <div>
                                        <div style="font-weight:700; font-size:0.9375rem; color:var(--text);">
                                            <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?= htmlspecialchars($inst['location'] ?? 'No address', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($inst['coordinator_name'])): ?>
                                    <div style="font-weight:600; font-size:0.875rem;">
                                        <?= htmlspecialchars($inst['coordinator_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <?= htmlspecialchars($inst['coordinator_display_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($inst['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($inst['phone2'])): ?>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($inst['phone2'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $status === 'active' ? 'badge--success' : 'badge--neutral' ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="d-flex gap-1 justify-end items-center">
                                    <a href="<?= BASE_URL ?>/institutes/<?= (int) $inst['id'] ?>/edit" class="btn btn--ghost btn--xs" title="Edit Institute">
                                        Edit
                                    </a>

                                    <form method="POST" action="<?= BASE_URL ?>/institutes/<?= (int) $inst['id'] ?>/toggle-status" style="margin:0;">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn <?= $status === 'active' ? 'btn--secondary' : 'btn--primary' ?> btn--xs">
                                            <?= $status === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="<?= BASE_URL ?>/institutes/<?= (int) $inst['id'] ?>/delete" style="margin:0;" data-confirm="<?= htmlspecialchars(t('Delete the branch {name}? This cannot be undone.', ['name' => $inst['name']]), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                        <button type="submit" class="btn btn--danger btn--xs" title="Delete Institute">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state__icon">&#127979;</div>
                                <div class="empty-state__title">No institutes found</div>
                                <div class="empty-state__text">Click "+ Register Institute" to add your first branch campus.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
