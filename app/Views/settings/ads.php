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
            <?php elseif (\App\Services\SiteAdDesign::isDesigned($ad)): ?>
                <?php /* Designed here, so the list shows the real thing at
                         small size rather than a camera icon that suggests
                         a missing picture. */ ?>
                <div style="width:160px; flex:0 0 auto; transform:scale(.8); transform-origin:left center;">
                    <?= \App\Services\SiteAdDesign::render($ad, '#', true) ?>
                </div>
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
                <?php $uid = 'e' . (int) $ad['id']; include __DIR__ . '/_ad-designer.php'; ?>

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
        <?php $ad = []; $uid = 'new'; include __DIR__ . '/_ad-designer.php'; ?>

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

<style>
<?= \App\Services\SiteAdDesign::css() ?>

.adx { border: 1px solid var(--border-color, #E2E8F0); border-radius: 12px; padding: .9rem 1rem; margin: 1rem 0; }
.adx-switch { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; cursor: pointer; }
.adx-switch strong { font-size: .95rem; }
.adx-body { margin-top: .9rem; display: grid; gap: 1rem; }
.adx-body[hidden] { display: none; }

.adx-row { display: grid; gap: .45rem; }
.adx-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .5rem; }
.adx-tile { text-align: left; background: #fff; border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 10px; padding: .55rem .65rem; cursor: pointer; display: grid; gap: .15rem; }
.adx-tile.is-on { border-color: #1D4ED8; box-shadow: 0 0 0 2px rgba(29,78,216,.14); }
.adx-tile__name { font-weight: 700; font-size: .85rem; }
.adx-tile__hint { font-size: .72rem; color: #64748B; line-height: 1.35; }

.adx-ways { display: flex; flex-wrap: wrap; gap: .4rem; }
.adx-way { border: none; border-radius: 999px; padding: .3rem .7rem; font-size: .76rem; font-weight: 700;
           cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; }
.adx-way span { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }

.adx-hexes { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: .35rem; }
.adx-hexes label { display: grid; gap: .25rem; font-size: .76rem; font-weight: 600; color: #475569; }
.adx-hexes input[type=color] { width: 100%; height: 30px; padding: 0; border: 1px solid var(--border-color,#E2E8F0);
                               border-radius: 6px; background: none; cursor: pointer; }
.adx-hexes .adx-hex { width: 104px; font-family: ui-monospace, monospace; font-size: .76rem; text-transform: uppercase; }

.adx-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .75rem; }

.adx-preview { border-top: 1px dashed var(--border-color, #DDD); padding-top: .85rem; }
.adx-preview__label { font-size: .74rem; font-weight: 700; color: #15803D; text-transform: uppercase;
                      letter-spacing: .04em; margin-bottom: .5rem; }
.adx-preview__stage { background:
        linear-gradient(45deg, #F1F5F9 25%, transparent 25%, transparent 75%, #F1F5F9 75%),
        linear-gradient(45deg, #F1F5F9 25%, transparent 25%, transparent 75%, #F1F5F9 75%);
    background-size: 16px 16px; background-position: 0 0, 8px 8px;
    padding: 1rem; border-radius: 10px; }
</style>

<script>
(function () {
    // Every designer panel on the page runs independently: the new-ad
    // form and one per ad being edited.
    document.querySelectorAll('.adx').forEach(function (panel) {
        var on       = panel.querySelector('.adx-on');
        var body     = panel.querySelector('.adx-body');
        var hidden   = panel.querySelector('.adx-template');
        var stage    = panel.querySelector('.adx-preview__stage');
        var drawn    = stage ? stage.querySelector('.ad-design') : null;
        if (!on || !body || !hidden || !drawn) { return; }

        // The panel switched off must clear the template, or an ad saved
        // as a picture would keep a design nobody can see or edit.
        function setEnabled(enabled) {
            body.hidden = !enabled;
            hidden.value = enabled ? (currentTemplate() || 'banner') : '';
        }

        function currentTemplate() {
            var tile = panel.querySelector('.adx-tile.is-on');
            return tile ? tile.getAttribute('data-template') : '';
        }

        on.addEventListener('change', function () { setEnabled(on.checked); });

        // The look. Only the class on the drawn element changes, so the
        // preview keeps using the markup the server produced rather than
        // a second copy of the layout written in JavaScript.
        panel.querySelectorAll('.adx-tile').forEach(function (tile) {
            tile.addEventListener('click', function () {
                panel.querySelectorAll('.adx-tile').forEach(function (t) { t.classList.remove('is-on'); });
                tile.classList.add('is-on');
                var key = tile.getAttribute('data-template');
                hidden.value = key;
                drawn.className = 'ad-design ad-design--' + key;
                syncParts();
            });
        });

        function paint(which, value) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(value)) { return; }
            drawn.style.setProperty('--ad-' + (which === 'text' ? 'text' : which), value);
            panel.querySelectorAll('[data-for="' + which + '"]').forEach(function (f) {
                if (f.value.toUpperCase() !== value.toUpperCase()) { f.value = value.toUpperCase(); }
            });
        }

        panel.querySelectorAll('.adx-pick').forEach(function (pick) {
            pick.addEventListener('input', function () { paint(pick.getAttribute('data-for'), pick.value); });
        });
        panel.querySelectorAll('.adx-hex').forEach(function (hex) {
            hex.addEventListener('input', function () { paint(hex.getAttribute('data-for'), hex.value.trim()); });
        });

        panel.querySelectorAll('.adx-way').forEach(function (way) {
            way.addEventListener('click', function () {
                paint('bg', way.getAttribute('data-bg'));
                paint('text', way.getAttribute('data-text'));
                paint('accent', way.getAttribute('data-accent'));
            });
        });

        // The words. textContent, never innerHTML: the office types
        // these, and a typed angle bracket must stay a typed angle
        // bracket here exactly as it does on the published page.
        function titleValue() {
            var form = panel.closest('form');
            var t = form ? form.querySelector('[name="title"]') : null;
            return t ? t.value.trim() : '';
        }

        function syncParts() {
            var head = drawn.querySelector('.ad-design__headline');
            var sub  = drawn.querySelector('.ad-design__sub');
            var btn  = drawn.querySelector('.ad-design__btn');
            var hIn  = panel.querySelector('[data-part="headline"]');
            var sIn  = panel.querySelector('[data-part="sub"]');
            var bIn  = panel.querySelector('[data-part="btn"]');

            if (head && hIn) {
                // Blank headline falls back to the ad's title, same as the
                // published page does.
                head.textContent = hIn.value.trim() || titleValue() || head.textContent;
            }
            if (sub && sIn) {
                var subText = sIn.value.trim();
                // The plain strip is one line by design, so it shows no
                // second line even when one is typed.
                var strip = drawn.classList.contains('ad-design--strip');
                sub.textContent = subText;
                sub.hidden = strip || subText === '';
            }
            if (btn && bIn) {
                var b = bIn.value.trim();
                btn.textContent = b;
                btn.hidden = b === '';
            }
        }

        panel.querySelectorAll('.adx-text').forEach(function (f) {
            f.addEventListener('input', syncParts);
        });
        var form = panel.closest('form');
        var titleField = form ? form.querySelector('[name="title"]') : null;
        if (titleField) { titleField.addEventListener('input', syncParts); }

        setEnabled(on.checked);
    });
})();
</script>
