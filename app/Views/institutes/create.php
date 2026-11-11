<?php
/**
 * 1.2 Register Institute View
 * Document 1 Page 5 Specifications:
 * Institute Name*, Coordinator (DDL), Location*, Phone No 1, Phone No 2, Notes, Status (DDL), Created Date
 */

$coordinators = $coordinators ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Register New Institute') ?></h1>
        <p class="text-sm text-muted"><?= te('Add a new campus, education center, or branch to the LMS network') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/institutes" class="btn btn--ghost">
            &larr; <?= te('Back to Institutes') ?>
        </a>
    </div>
</div>

<div class="card" style="max-width:800px;">
    <form method="POST" action="<?= BASE_URL ?>/institutes/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Institute Name* -->
        <div class="form-group mb-2">
            <label class="form-label" for="instName"><?= te('Institute Name') ?> <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="instName" name="name" class="form-control" placeholder="<?= te('e.g. Sepala Institute - Pannipitiya') ?>" required>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Coordinator (DDL - data flow from created coordinators) -->
            <div class="form-group">
                <label class="form-label" for="coordinator"><?= te('Assigned Coordinator') ?></label>
                <select id="coordinator" name="coordinator_id" class="form-control">
                    <option value=""><?= te('-- Select Coordinator (Optional) --') ?></option>
                    <?php foreach ($coordinators as $c): ?>
                        <option value="<?= (int) $c['id'] ?>">
                            <?= htmlspecialchars($c['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($c['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint text-xs"><?= te('Shows active coordinators created in User Management.') ?></div>
            </div>

            <!-- Status (DDL: Active / Inactive) -->
            <div class="form-group">
                <label class="form-label" for="status"><?= te('Status') ?></label>
                <select id="status" name="status" class="form-control">
                    <option value="active" selected><?= te('Active') ?></option>
                    <option value="inactive"><?= te('Inactive') ?></option>
                </select>
            </div>
        </div>

        <!-- Location* -->
        <div class="form-group mt-2">
            <label class="form-label" for="location"><?= te('Location / Address') ?> <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="location" name="location" class="form-control" placeholder="<?= te('e.g. No. 120, High Level Road, Pannipitiya') ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="map_url"><?= te('Google Maps link') ?></label>
            <input type="url" id="map_url" name="map_url" class="form-control" placeholder="https://www.google.com/maps/...">
            <div class="form-hint text-xs"><?= te('Optional. Paste the Google Maps link for the building so the contact page pins it exactly. Left blank, the map searches for the branch name and town.') ?></div>
        </div>

        <!-- Phone 1 & Phone 2 -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;" class="mt-2">
            <div class="form-group">
                <label class="form-label" for="phone1"><?= te('Phone No 1') ?></label>
                <input type="tel" id="phone1" name="phone1" class="form-control" placeholder="e.g. +94112853456">
            </div>
            <div class="form-group">
                <label class="form-label" for="phone2"><?= te('Phone No 2') ?></label>
                <input type="tel" id="phone2" name="phone2" class="form-control" placeholder="e.g. +94112853457">
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group mt-2">
            <label class="form-label" for="notes"><?= te('Notes') ?></label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="<?= te('Optional details, campus facilities, notes...') ?>"></textarea>
        </div>

        <div class="d-flex justify-end gap-1 mt-4 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/institutes" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary">
                <?= te('Save Institute') ?>
            </button>
        </div>
    </form>
</div>
