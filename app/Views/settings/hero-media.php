<?php
/**
 * Settings - Homepage Background
 *
 * The photos/videos that crossfade behind the home page hero heading
 * (HeroMediaService, migration 035). If the list below is empty, the
 * page falls back to the four original fixed photos, so this screen is
 * entirely optional.
 *
 * Variables: $slides (every row, display order)
 */
$slides = $slides ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Homepage Background') ?></h1>
        <p class="text-sm text-muted"><?= te('The photos or short videos that crossfade behind the heading on the public home page. Add as many as you like; they play in the order below. Leave this empty to keep the original fixed photos.') ?></p>
    </div>
</div>

<?php if ($slides !== []): ?>
<div class="card mb-3" style="padding:0;">
    <?php foreach ($slides as $slide): ?>
        <div style="display:flex; gap:1rem; align-items:center; padding:.9rem 1.1rem; border-top:1px solid var(--border-color,#eee);">
            <?php if ($slide['media_type'] === 'video'): ?>
                <video src="<?= BASE_URL ?><?= $e($slide['file_path']) ?>" style="width:96px; height:56px; object-fit:cover; border-radius:.4rem; flex:0 0 auto;" muted autoplay loop playsinline></video>
            <?php else: ?>
                <img src="<?= BASE_URL ?><?= $e($slide['file_path']) ?>" alt="" style="width:96px; height:56px; object-fit:cover; border-radius:.4rem; flex:0 0 auto;">
            <?php endif; ?>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600;"><?= $slide['media_type'] === 'video' ? te('Video') : te('Photo') ?> &middot; <span class="text-xs text-muted"><?= $e($slide['object_position']) ?></span></div>
                <div class="text-xs text-muted"><?= te('Order: {n}', ['n' => (int) $slide['sort_order']]) ?></div>
            </div>
            <span class="badge <?= $slide['is_active'] ? 'badge--success' : 'badge--neutral' ?>"><?= $slide['is_active'] ? te('Active') : te('Inactive') ?></span>
            <div class="d-flex gap-1">
                <form method="POST" action="<?= BASE_URL ?>/settings/hero-media/<?= (int) $slide['id'] ?>/toggle" style="margin:0;">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <button type="submit" class="btn btn--ghost btn--xs"><?= $slide['is_active'] ? te('Deactivate') : te('Activate') ?></button>
                </form>
                <button type="button" class="btn btn--ghost btn--xs" onclick="document.getElementById('editSlide<?= (int) $slide['id'] ?>').style.display='block'; this.closest('div').style.display='none';"><?= te('Edit') ?></button>
                <form method="POST" action="<?= BASE_URL ?>/settings/hero-media/<?= (int) $slide['id'] ?>/delete" style="margin:0;" data-confirm="<?= $e(t('Delete this slide? This cannot be undone.')) ?>">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <button type="submit" class="btn btn--danger btn--xs"><?= te('Delete') ?></button>
                </form>
            </div>
        </div>
        <div id="editSlide<?= (int) $slide['id'] ?>" style="display:none; padding:1rem 1.1rem; border-top:1px dashed var(--border-color,#ddd); background:var(--surface-sunken);">
            <form method="POST" action="<?= BASE_URL ?>/settings/hero-media/<?= (int) $slide['id'] ?>/update" enctype="multipart/form-data">
                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label"><?= te('Replace photo/video (optional)') ?></label>
                        <input type="file" name="media" class="form-control" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,video/ogg">
                        <div class="form-hint text-xs"><?= te('Leave blank to keep the current file.') ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Focus point (e.g. 50% 50%)') ?></label>
                        <input type="text" name="object_position" class="form-control" value="<?= $e($slide['object_position']) ?>" placeholder="50% 50%">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Order (lower shows first)') ?></label>
                        <input type="number" name="sort_order" class="form-control" value="<?= (int) $slide['sort_order'] ?>">
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
                    <input type="checkbox" name="is_active" value="1" <?= $slide['is_active'] ? 'checked' : '' ?>>
                    <?= te('Active') ?>
                </label>
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Save changes') ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;"><?= te('Add a new slide') ?></h3>
    <form method="POST" action="<?= BASE_URL ?>/settings/hero-media" enctype="multipart/form-data">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
            <div class="form-group">
                <label class="form-label"><?= te('Photo or video') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="file" name="media" class="form-control" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,video/ogg" required>
                <div class="form-hint text-xs"><?= te('Photo: JPG/PNG/WEBP up to 5MB. Video: MP4/WEBM/MOV/OGG up to 35MB.') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Focus point (optional)') ?></label>
                <input type="text" name="object_position" class="form-control" placeholder="50% 50%">
                <div class="form-hint text-xs"><?= te('Which part of the photo/video stays visible when the screen crops it, e.g. "70% 35%". Leave blank to center it.') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Order (lower shows first)') ?></label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
        </div>
        <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
            <input type="checkbox" name="is_active" value="1" checked>
            <?= te('Active') ?>
        </label>
        <button type="submit" class="btn btn--primary"><?= te('Add slide') ?></button>
    </form>
</div>
