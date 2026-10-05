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
    <h1 class="page-header__title">Deletion Settings</h1>
</div>

<!-- Current Configuration -->
<div class="card mb-3">
    <h3 style="margin-bottom:0.75rem;">Current Approval Chain</h3>
    <div class="d-flex items-center gap-1 flex-wrap">
        <?php if ($approvalChain === 'teacher_coordinator_admin'): ?>
            <span class="badge badge--info">Teacher</span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info">Coordinator</span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info">Admin</span>
        <?php else: ?>
            <span class="badge badge--info">Teacher</span>
            <span class="text-muted">&rarr;</span>
            <span class="badge badge--info">Admin</span>
        <?php endif; ?>
    </div>
</div>

<!-- Deletion Paths Display -->
<div class="card mb-3" style="max-width:900px;">
    <h3 style="margin-bottom:1rem;">Deletion Paths</h3>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($paths as $num => $path): ?>
            <div style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-sunken);">
                <div class="d-flex items-center justify-between flex-wrap gap-1">
                    <div>
                        <h4 style="margin-bottom:0.15rem;"><?= htmlspecialchars($path['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <p class="text-sm text-muted" style="margin-bottom:0.5rem;"><?= htmlspecialchars($path['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="badge badge--neutral">Path <?= (int) $num ?></span>
                </div>
                <div class="d-flex items-center gap-1 flex-wrap" style="margin-top:0.5rem;">
                    <?php
                    $chain = $path['chain'] ?? ($approvalChain === 'teacher_coordinator_admin'
                        ? ['Teacher', 'Coordinator', 'Admin']
                        : ['Teacher', 'Admin']);
                    foreach ($chain as $j => $step):
                    ?>
                        <?php if ($j > 0): ?><span class="text-muted">&rarr;</span><?php endif; ?>
                        <span class="badge badge--info"><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Configuration Form -->
<div class="card" style="max-width:900px;">
    <h3 style="margin-bottom:1rem;">Configure Approval Chain</h3>

    <form method="POST" action="<?= BASE_URL ?>/settings/deletion">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="form-group">
            <label class="form-label">Approval Chain</label>
            <div class="d-flex flex-wrap gap-2">
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:220px;cursor:pointer;<?= $approvalChain === 'teacher_coordinator_admin' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="approval_chain" value="teacher_coordinator_admin" <?= $approvalChain === 'teacher_coordinator_admin' ? 'checked' : '' ?>>
                    <div>
                        <strong>Teacher &rarr; Coordinator &rarr; Admin</strong>
                        <div class="text-sm text-muted">Three-step approval with coordinator review</div>
                    </div>
                </label>
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:220px;cursor:pointer;<?= $approvalChain === 'teacher_admin' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="approval_chain" value="teacher_admin" <?= $approvalChain === 'teacher_admin' ? 'checked' : '' ?>>
                    <div>
                        <strong>Teacher &rarr; Admin</strong>
                        <div class="text-sm text-muted">Two-step approval, skipping coordinator</div>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn--primary">Save Configuration</button>
    </form>
</div>
