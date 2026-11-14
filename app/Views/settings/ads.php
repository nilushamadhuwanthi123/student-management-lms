<?php
/**
 * Settings - Website Ads
 *
 * Standalone promotional banners for the public home page (an
 * enrolment call, a scholarship notice, an offer) -- separate from a
 * seminar's "show on the website" flag, which advertises a specific
 * dated event. Both kinds end up in the same rotating banner, but
 * this is the only screen for one with no event behind it.
 *
 * Variables: $ads (every row, newest first)
 */
$ads = $ads ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Website Ads') ?></h1>
        <p class="text-sm text-muted"><?= te('Promotional banners shown on the public home page, with no event or class behind them. For a specific seminar or event, use the "Show on the website" option when creating it instead.') ?></p>
    </div>
</div>

<?php if ($ads !== []): ?>
<div class="card mb-3" style="padding:0;">
    <?php foreach ($ads as $ad): ?>
        <div style="display:flex; gap:1rem; align-items:center; padding:.9rem 1.1rem; border-top:1px solid var(--border-color,#eee);">
            <?php if (!empty($ad['image_path'])): ?>
                <img src="<?= BASE_URL ?><?= $e($ad['image_path']) ?>" alt="" style="width:72px; height:52px; object-fit:cover; border-radius:.4rem; flex:0 0 auto;">
            <?php else: ?>
                <div style="width:72px; height:52px; border-radius:.4rem; border:1px dashed var(--border-color,#ccc); flex:0 0 auto; display:grid; place-items:center; font-size:1.2rem;">&#128247;</div>
            <?php endif; ?>
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600;"><?= $e($ad['title']) ?></div>
                <div class="text-xs text-muted">
                    <?php if (!empty($ad['link_url'])): ?><?= $e($ad['link_url']) ?> &middot; <?php endif; ?>
                    <?php if (!empty($ad['starts_on']) || !empty($ad['ends_on'])): ?>
                        <?= $e($ad['starts_on'] ?: te('Always')) ?> &rarr; <?= $e($ad['ends_on'] ?: te('No end date')) ?>
                    <?php else: ?>
                        <?= te('No date limit') ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge <?= $ad['is_active'] ? 'badge--success' : 'badge--neutral' ?>"><?= $ad['is_active'] ? te('Active') : te('Inactive') ?></span>
            <div class="d-flex gap-1">
                <form method="POST" action="<?= BASE_URL ?>/settings/ads/<?= (int) $ad['id'] ?>/toggle" style="margin:0;">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <button type="submit" class="btn btn--ghost btn--xs"><?= $ad['is_active'] ? te('Deactivate') : te('Activate') ?></button>
                </form>
                <button type="button" class="btn btn--ghost btn--xs" onclick="document.getElementById('editAd<?= (int) $ad['id'] ?>').style.display='block'; this.closest('div').style.display='none';"><?= te('Edit') ?></button>
                <form method="POST" action="<?= BASE_URL ?>/settings/ads/<?= (int) $ad['id'] ?>/delete" style="margin:0;" data-confirm="<?= $e(t('Delete the ad {title}? This cannot be undone.', ['title' => $ad['title']])) ?>">
                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                    <button type="submit" class="btn btn--danger btn--xs"><?= te('Delete') ?></button>
                </form>
            </div>
        </div>
        <div id="editAd<?= (int) $ad['id'] ?>" style="display:none; padding:1rem 1.1rem; border-top:1px dashed var(--border-color,#ddd); background:var(--surface-sunken);">
            <form method="POST" action="<?= BASE_URL ?>/settings/ads/<?= (int) $ad['id'] ?>/update" enctype="multipart/form-data">
                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label"><?= te('Title') ?></label>
                        <input type="text" name="title" class="form-control" value="<?= $e($ad['title']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Link (optional)') ?></label>
                        <input type="url" name="link_url" class="form-control" placeholder="https://..." value="<?= $e($ad['link_url'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Starts on (optional)') ?></label>
                        <input type="date" name="starts_on" class="form-control" value="<?= $e($ad['starts_on'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Ends on (optional)') ?></label>
                        <input type="date" name="ends_on" class="form-control" value="<?= $e($ad['ends_on'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Photo (optional)') ?></label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Order (lower shows first)') ?></label>
                        <input type="number" name="sort_order" class="form-control" value="<?= (int) $ad['sort_order'] ?>">
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
                    <input type="checkbox" name="is_active" value="1" <?= $ad['is_active'] ? 'checked' : '' ?>>
                    <?= te('Active') ?>
                </label>
                <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
                    <input type="checkbox" name="show_as_popup" value="1" <?= !empty($ad['show_as_popup']) ? 'checked' : '' ?>>
                    <?= te('Also show as a popup on every public page (once per visit)') ?>
                </label>
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Save changes') ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;"><?= te('Add a new ad') ?></h3>
    <form method="POST" action="<?= BASE_URL ?>/settings/ads" enctype="multipart/form-data">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
            <div class="form-group">
                <label class="form-label"><?= te('Title') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="<?= te('e.g. 2027 scholarship class enrolment now open') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Link (optional)') ?></label>
                <input type="url" name="link_url" class="form-control" placeholder="https://...">
                <div class="form-hint text-xs"><?= te('Leave blank for an informational banner with no link.') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Starts on (optional)') ?></label>
                <input type="date" name="starts_on" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Ends on (optional)') ?></label>
                <input type="date" name="ends_on" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Photo (optional)') ?></label>
                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-hint text-xs"><?= te('JPG, PNG or WEBP, up to 3MB.') ?></div>
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
        <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
            <input type="checkbox" name="show_as_popup" value="1">
            <?= te('Also show as a popup on every public page (once per visit)') ?>
        </label>
        <button type="submit" class="btn btn--primary"><?= te('Create ad') ?></button>
    </form>
</div>
