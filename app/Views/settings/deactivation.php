<?php
/**
 * Settings - Deactivation Configuration
 *
 * Variables: $settings, $options (array of 4 period options), $activeOption
 */

$settings     = $settings ?? [];
$options      = $options ?? [];
$activeOption = $activeOption ?? ($settings['active_deactivation_option'] ?? 1);
$targetType   = $settings['deactivation_target_type'] ?? 'A';

// Default options if none provided
if (empty($options)) {
    $options = [
        1 => ['normal_days' => 30, 'grace_days' => 14, 'deactivation_days' => 7],
        2 => ['normal_days' => 45, 'grace_days' => 21, 'deactivation_days' => 14],
        3 => ['normal_days' => 60, 'grace_days' => 30, 'deactivation_days' => 21],
        4 => ['normal_days' => 90, 'grace_days' => 45, 'deactivation_days' => 30],
    ];
}
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('Deactivation Settings') ?></h1>
</div>

<!-- Current Settings Display -->
<div class="card mb-3">
    <h3 style="margin-bottom:0.75rem;"><?= te('Current Configuration') ?></h3>
    <div class="d-flex flex-wrap gap-2">
        <div>
            <span class="text-xs text-muted"><?= te('Active Option') ?></span>
            <div class="font-bold text-primary"><?= te('Option {n}', ['n' => (int) $activeOption]) ?></div>
        </div>
        <div>
            <span class="text-xs text-muted"><?= te('Normal Access') ?></span>
            <div class="font-bold"><?= te('{n} days', ['n' => (int) ($options[$activeOption]['normal_days'] ?? 0)]) ?></div>
        </div>
        <div>
            <span class="text-xs text-muted"><?= te('Grace Period') ?></span>
            <div class="font-bold"><?= te('{n} days', ['n' => (int) ($options[$activeOption]['grace_days'] ?? 0)]) ?></div>
        </div>
        <div>
            <span class="text-xs text-muted"><?= te('Deactivation Period') ?></span>
            <div class="font-bold"><?= te('{n} days', ['n' => (int) ($options[$activeOption]['deactivation_days'] ?? 0)]) ?></div>
        </div>
        <div>
            <span class="text-xs text-muted"><?= te('Target Type') ?></span>
            <div class="font-bold"><?= $targetType === 'A' ? te('Type A (Sequential)') : te('Type B (Simultaneous)') ?></div>
        </div>
    </div>
</div>

<!-- Configuration Form -->
<div class="card" style="max-width:900px;">
    <h3 style="margin-bottom:1rem;"><?= te('Configure Deactivation Periods') ?></h3>

    <form method="POST" action="<?= BASE_URL ?>/settings/deactivation">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Active Option Selector -->
        <div class="form-group">
            <label class="form-label"><?= te('Active Period Option') ?></label>
            <select name="active_option" class="form-control" style="width:auto;min-width:200px;">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= $i ?>" <?= $activeOption == $i ? 'selected' : '' ?>><?= te('Option {n}', ['n' => $i]) ?></option>
                <?php endfor; ?>
            </select>
            <div class="form-hint"><?= te('Select which period configuration to use system-wide.') ?></div>
        </div>

        <!-- Period Options Table -->
        <div class="table-wrapper mb-2">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= te('Option') ?></th>
                        <th><?= te('Normal Access Days') ?></th>
                        <th><?= te('Grace Period Days') ?></th>
                        <th><?= te('Deactivation Period Days') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <tr style="<?= $activeOption == $i ? 'background:var(--primary-light);' : '' ?>">
                            <td class="font-medium">
                                <?= te('Option {n}', ['n' => $i]) ?>
                                <?php if ($activeOption == $i): ?>
                                    <span class="badge badge--success"><?= te('Active') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" name="options[<?= $i ?>][normal_days]" class="form-control" style="width:100px;" min="1" value="<?= (int) ($options[$i]['normal_days'] ?? 30) ?>">
                            </td>
                            <td>
                                <input type="number" name="options[<?= $i ?>][grace_days]" class="form-control" style="width:100px;" min="1" value="<?= (int) ($options[$i]['grace_days'] ?? 14) ?>">
                            </td>
                            <td>
                                <input type="number" name="options[<?= $i ?>][deactivation_days]" class="form-control" style="width:100px;" min="1" value="<?= (int) ($options[$i]['deactivation_days'] ?? 7) ?>">
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Target Type -->
        <div class="form-group">
            <label class="form-label"><?= te('Deactivation Target Type') ?></label>
            <div class="d-flex flex-wrap gap-2">
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:200px;cursor:pointer;<?= $targetType === 'A' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="target_type" value="A" <?= $targetType === 'A' ? 'checked' : '' ?>>
                    <div>
                        <strong><?= te('Type A - Sequential') ?></strong>
                        <div class="text-sm text-muted"><?= te('Student deactivated first, parent deactivated 30 days later') ?></div>
                    </div>
                </label>
                <label class="form-check" style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius);flex:1;min-width:200px;cursor:pointer;<?= $targetType === 'B' ? 'border-color:var(--primary);background:var(--primary-light);' : '' ?>">
                    <input type="radio" name="target_type" value="B" <?= $targetType === 'B' ? 'checked' : '' ?>>
                    <div>
                        <strong><?= te('Type B - Simultaneous') ?></strong>
                        <div class="text-sm text-muted"><?= te('Both student and parent deactivated at the same time') ?></div>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn--primary"><?= te('Save Configuration') ?></button>
    </form>
</div>
