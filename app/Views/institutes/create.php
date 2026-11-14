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
    <form method="POST" action="<?= BASE_URL ?>/institutes/create" enctype="multipart/form-data">
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

            <div class="form-group">
                <label class="form-label" for="sort_order"><?= te('Display order on website') ?></label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="0" step="10">
                <div class="form-hint text-xs"><?= te('Lower numbers show first on the public Classes page, the home page and the footer. Branches with the same number fall back to the order they were added.') ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="banner_size"><?= te('Photo/video banner size (Classes page)') ?></label>
                <select id="banner_size" name="banner_size" class="form-control">
                    <option value="small"><?= te('Small') ?></option>
                    <option value="medium" selected><?= te('Medium (default)') ?></option>
                    <option value="large"><?= te('Large') ?></option>
                </select>
                <div class="form-hint text-xs"><?= te('How tall the branch photo/video banner shows on the public Classes page.') ?></div>
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

        <!-- Branch photo (public site) -->
        <div class="form-group">
            <label class="form-label" for="photo"><?= te('Branch photo (shown on the public website)') ?></label>
            <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
            <div class="form-hint text-xs"><?= te('JPG, PNG or WEBP, up to 3MB. Shown on the public branches/classes pages.') ?></div>
        </div>

        <!-- Public description -->
        <div class="form-group">
            <label class="form-label" for="public_description"><?= te('Short public description') ?></label>
            <textarea id="public_description" name="public_description" class="form-control" rows="2" maxlength="500" placeholder="<?= te('e.g. Science classes for Grades 6-11, physical and online.') ?>"></textarea>
            <div class="form-hint text-xs"><?= te('Optional. Shown under the branch photo on the public website.') ?></div>
        </div>

        <!-- Branch video (public site) -->
        <div class="form-group">
            <label class="form-label" for="video_url"><?= te('Branch video link') ?></label>
            <input type="url" id="video_url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
            <div class="form-hint text-xs"><?= te('Paste a YouTube, Facebook or other video link -- OR upload a video file below. Shown as a "Watch video" button on the public branches/classes pages.') ?></div>
            <label class="form-label mt-2" for="video"><?= te('...or upload a video file') ?></label>
            <input type="file" id="video" name="video" class="form-control" accept="video/mp4,video/webm,video/quicktime,video/ogg">
            <div class="form-hint text-xs"><?= te('MP4, WEBM, MOV or OGG, up to 35MB. If both a link and a file are given, the link is used.') ?></div>
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
