<?php
/**
 * Settings - Deletion Path Configuration
 *
 * Variables: $settings, $paths (array of 5 deletion paths with approval chains)
 */

$settings      = $settings ?? [];
$paths         = $paths ?? [];
$approvalChain = $settings['deletion_approval_chain'] ?? 'teacher_coordinator_admin';

// Default 5 paths if none provided
if (empty($paths)) {
    $paths = [
        1 => ['label' => 'Path 1: Standard Deletion',     'description' => 'Teacher initiates, follows normal approval chain'],
        2 => ['label' => 'Path 2: Coordinator Initiated',  'description' => 'Coordinator initiates, admin approves'],
        3 => ['label' => 'Path 3: Admin Direct Deletion',  'description' => 'Admin initiates, super admin approves'],
        4 => ['label' => 'Path 4: Bulk Deletion',          'description' => 'For batch student removal after academic year'],
        5 => ['label' => 'Path 5: Emergency Deletion',     'description' => 'Urgent removal with expedited approval'],
    ];
}
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Deletion Settings') ?></h1>
</div>

<!-- Current Configuration -->
<div class="card mb-3">
    <h3 style="margin-bottom:0.75rem;"><?= te('Current Approval Chain') ?></h3>
    <div class="d-flex items-center gap-1 flex-wrap">
        <?php if ($approvalChain === 'teacher_coordinator_admin'): ?>
            <span class="badge badge--info"><?= te('Teacher') ?></span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info"><?= te('Coordinator') ?></span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info"><?= te('Admin') ?></span>
        <?php else: ?>
            <span class="badge badge--info"><?= te('Teacher') ?></span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info"><?= te('Admin') ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Deletion Paths Display -->
<div class="card mb-3" style="max-width:900px;">
    <h3 style="margin-bottom:1rem;"><?= te('Deletion Paths') ?></h3>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($paths as $num => $path): ?>
            <div style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-sunken);">
                <div class="d-flex items-center justify-between flex-wrap gap-1">
                    <div>
                        <h4 style="margin-bottom:0.15rem;"><?= te($path['label']) ?></h4>
                        <p class="text-sm text-muted" style="margin-bottom:0.5rem;"><?= te($path['description']) ?></p>
                    </div>
                    <span class="badge badge--neutral"><?= te('Path {n}', ['n' => (int) $num]) ?></span>
                </div>
                <div class="d-flex items-center gap-1 flex-wrap" style="margin-top:0.5rem;">
                    <?php
                    $chain = $path['chain'] ?? ($approvalChain === 'teacher_coordinator_admin'
                        ? ['Teacher', 'Coordinator', 'Admin']
                        : ['Teacher', 'Admin']);
                    foreach ($chain as $j => $step):
                    ?>
                        <?php if ($j > 0): ?><span class="text-muted">&rarr;</span><?php endif; ?>
                        <span class="badge badge--info"><?= te($step) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Configuration Form -->
<div class="card" style="max-width:900px;">
    <h3 style="margin-bottom:1rem;"><?= te('Configure Approval Chain') ?></h3>

    <form method="POST" action="<?= BASE_URL ?>/settings/deletion">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="form-group">
            <label class="form-label"><?= te('Approval Chain') ?></label>
            <div class="d-flex flex-wrap gap-2">
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:220px;cursor:pointer;<?= $approvalChain === 'teacher_coordinator_admin' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="approval_chain" value="teacher_coordinator_admin" <?= $approvalChain === 'teacher_coordinator_admin' ? 'checked' : '' ?>>
                    <div>
                        <strong><?= te('Teacher') ?> &rarr; <?= te('Coordinator') ?> &rarr; <?= te('Admin') ?></strong>
                        <div class="text-sm text-muted"><?= te('Three-step approval with coordinator review') ?></div>
                    </div>
                </label>
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:220px;cursor:pointer;<?= $approvalChain === 'teacher_admin' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="approval_chain" value="teacher_admin" <?= $approvalChain === 'teacher_admin' ? 'checked' : '' ?>>
                    <div>
                        <strong><?= te('Teacher') ?> &rarr; <?= te('Admin') ?></strong>
                        <div class="text-sm text-muted"><?= te('Two-step approval, skipping coordinator') ?></div>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn--primary"><?= te('Save Configuration') ?></button>
    </form>
</div>
