<?php
/**
 * Settings - General (Placeholder)
 *
 * Variables: $settings
 */

$settings = $settings ?? [];
?>

<div class="page-header">
    <h1 class="page-header__title">General Settings</h1>
</div>

<div class="card" style="max-width:700px;">
    <h3 style="margin-bottom:1rem;">System Configuration</h3>

    <form method="POST" action="/settings">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="form-group">
            <label class="form-label" for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? 'EduManager', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-row form-row--2">
            <div class="form-group">
                <label class="form-label" for="timezone">Timezone</label>
                <input type="text" id="timezone" name="timezone" class="form-control" value="<?= htmlspecialchars($settings['timezone'] ?? 'Asia/Colombo', ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                <div class="form-hint">System timezone is fixed to Asia/Colombo</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="currency">Currency</label>
                <input type="text" id="currency" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency'] ?? 'LKR', ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                <div class="form-hint">System currency is fixed to LKR (Sri Lankan Rupees)</div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="admin_email">Admin Contact Email</label>
            <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?= htmlspecialchars($settings['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="records_per_page">Records Per Page</label>
            <select id="records_per_page" name="records_per_page" class="form-control" style="width:auto;">
                <?php foreach ([10, 25, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= (($settings['records_per_page'] ?? 25) == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn--primary">Save Settings</button>
    </form>
</div>
