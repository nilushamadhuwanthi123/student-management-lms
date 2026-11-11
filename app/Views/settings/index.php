<?php
/**
 * Settings - General
 *
 * Variables: $settings (site_name, admin_email as saved; '' when unset),
 *            $brandPreview (what the portal shows today)
 */

$settings = $settings ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <h1 class="page-header__title"><?= te('General Settings') ?></h1>
</div>

<div class="card" style="max-width:700px;">
    <h3 style="margin-bottom:1rem;"><?= te('System Configuration') ?></h3>

    <form method="POST" action="<?= BASE_URL ?>/settings">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="form-group">
            <label class="form-label" for="site_name"><?= te('Site name') ?></label>
            <input type="text" id="site_name" name="settings[site_name]" class="form-control" maxlength="80"
                   value="<?= $e($settings['site_name'] ?? '') ?>" placeholder="<?= $e($brandPreview ?? '') ?>">
            <div class="form-hint"><?= te('Shown in the portal header, browser tab and student ID cards. Leave blank to use the teacher\'s name.') ?></div>
        </div>

        <div class="form-row form-row--2">
            <div class="form-group">
                <label class="form-label" for="timezone"><?= te('Timezone') ?></label>
                <input type="text" id="timezone" class="form-control" value="Asia/Colombo" readonly disabled>
                <div class="form-hint"><?= te('System timezone is fixed to Asia/Colombo') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="currency"><?= te('Currency') ?></label>
                <input type="text" id="currency" class="form-control" value="LKR" readonly disabled>
                <div class="form-hint"><?= te('System currency is fixed to LKR (Sri Lankan Rupees)') ?></div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="admin_email"><?= te('Admin contact email') ?></label>
            <input type="email" id="admin_email" name="settings[admin_email]" class="form-control" maxlength="255"
                   value="<?= $e($settings['admin_email'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn--primary"><?= te('Save Settings') ?></button>
    </form>
</div>
