<?php
/**
 * 1.2 Edit Institute View
 */

$institute    = $institute ?? [];
$coordinators = $coordinators ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Edit Institute') ?> - <?= htmlspecialchars($institute['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted"><?= te('Update campus contact details and assigned coordinator') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/institutes" class="btn btn--ghost">
            &larr; <?= te('Back to Institutes') ?>
        </a>
    </div>
</div>

<div class="card" style="max-width:800px;">
    <form method="POST" action="<?= BASE_URL ?>/institutes/<?= (int) $institute['id'] ?>/edit">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Institute Name* -->
        <div class="form-group mb-2">
            <label class="form-label" for="instName"><?= te('Institute Name') ?> <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="instName" name="name" class="form-control" value="<?= htmlspecialchars($institute['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Coordinator -->
            <div class="form-group">
                <label class="form-label" for="coordinator"><?= te('Assigned Coordinator') ?></label>
                <select id="coordinator" name="coordinator_id" class="form-control">
                    <option value=""><?= te('-- Select Coordinator (Optional) --') ?></option>
                    <?php foreach ($coordinators as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ((int) ($institute['coordinator_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($c['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label class="form-label" for="status"><?= te('Status') ?></label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= ($institute['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= te('Active') ?></option>
                    <option value="inactive" <?= ($institute['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?></option>
                </select>
            </div>
        </div>

        <!-- Location* -->
        <div class="form-group mt-2">
            <label class="form-label" for="location"><?= te('Location / Address') ?> <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($institute['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <!-- Phone 1 & Phone 2 -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;" class="mt-2">
            <div class="form-group">
                <label class="form-label" for="phone1"><?= te('Phone No 1') ?></label>
                <input type="tel" id="phone1" name="phone1" class="form-control" value="<?= htmlspecialchars($institute['phone1'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="phone2"><?= te('Phone No 2') ?></label>
                <input type="tel" id="phone2" name="phone2" class="form-control" value="<?= htmlspecialchars($institute['phone2'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group mt-2">
            <?php if (session_get('user_role') === 'super_admin'): ?>
            <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin:0 0 1rem;">
                <label style="display:flex; gap:.45rem; align-items:center;">
                    <input type="checkbox" name="is_public" value="1" <?= !empty($institute['is_public']) ? 'checked' : '' ?>>
                    <?= te('Show this branch and its classes on the public website') ?>
                </label>
                <label style="display:flex; gap:.45rem; align-items:center;">
                    <input type="checkbox" name="public_show_fees" value="1" <?= !empty($institute['public_show_fees']) ? 'checked' : '' ?>>
                    <?= te('Show monthly fees publicly') ?>
                </label>
            </div>
            <?php endif; ?>
            <label class="form-label" for="notes"><?= te('Notes') ?></label>
            <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($institute['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="d-flex justify-end gap-1 mt-4 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/institutes" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary">
                <?= te('Save Changes') ?>
            </button>
        </div>
    </form>
</div>
