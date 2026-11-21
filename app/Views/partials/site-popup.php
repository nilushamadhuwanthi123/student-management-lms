<?php
/**
 * Sitewide ad popup: super admin marks an ad "show as popup" in
 * Settings > Website Ads (SiteAdService::popups()) -- a class event, a
 * seminar poster, or any announcement -- and it appears as a
 * dismissible overlay on every public page, to every visitor, once per
 * browser session. All of today's popup ads are shown together in one
 * grid card (not one-at-a-time); closing the overlay reveals the site.
 */
$__popups = \App\Services\PublicSite::popupAds(6);
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php if ($__popups !== []): ?>
<style>
.sp-overlay { position: fixed; inset: 0; z-index: 9000; display: none; align-items: center; justify-content: center;
    background: rgba(8, 12, 26, .78); padding: 1.2rem; }
.sp-overlay.is-open { display: flex; }
.sp-card { position: relative; background: #fff; border-radius: 18px; max-width: 760px; width: 100%;
    max-height: 90vh; overflow: auto; box-shadow: 0 30px 70px rgba(0,0,0,.4); padding: 1.4rem; }
.sp-close { position: absolute; top: .7rem; right: .7rem; width: 36px; height: 36px; border-radius: 50%;
    border: none; background: rgba(15,23,42,.08); color: #0F172A; font-size: 1.4rem; cursor: pointer;
    display: grid; place-items: center; z-index: 2; }
.sp-close:hover { background: rgba(15,23,42,.16); }
.sp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-top: .3rem; }
.sp-item { display: block; text-decoration: none; color: inherit; border-radius: 12px; overflow: hidden;
    background: #F8FAFC; border: 1px solid #E2E8F0; transition: transform .2s ease, box-shadow .2s ease; }
.sp-item:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,.12); }
.sp-item img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; }
.sp-item__body { padding: .7rem .8rem .85rem; }
.sp-item__title { font-weight: 700; font-size: .92rem; color: #0F172A; line-height: 1.35; }
.sp-item--design { background: none; border: none; padding: 0; }
.sp-item--design:hover { transform: none; box-shadow: none; }
<?= \App\Services\SiteAdDesign::css() ?>
</style>
<div class="sp-overlay" id="spOverlay" role="dialog" aria-modal="true" aria-label="<?= te('Announcements') ?>">
    <div class="sp-card">
        <button type="button" class="sp-close" id="spClose" aria-label="<?= te('Close') ?>">&times;</button>
        <div class="sp-grid">
            <?php foreach ($__popups as $p): ?>
                <?php $tag = $p['href'] !== null ? 'a' : 'div'; ?>
                <?php if (($p['design'] ?? '') !== ''): ?>
                    <?php /* Designed here: it already carries its own words,
                             colours and button, so it is shown as it is
                             rather than wrapped in the picture card. */ ?>
                    <div class="sp-item sp-item--design"><?= $p['design'] ?></div>
                <?php else: ?>
                <<?= $tag ?> class="sp-item"<?= $p['href'] !== null ? ' href="' . $e($p['href']) . '" target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <?php if ($p['image'] !== ''): ?><img src="<?= $e($p['image']) ?>" alt="" loading="lazy" onerror="this.remove();"><?php endif; ?>
                    <div class="sp-item__body"><div class="sp-item__title"><?= $e($p['title']) ?></div></div>
                </<?= $tag ?>>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var seen = false;
    try { seen = window.sessionStorage.getItem('spPopupSeen') === '1'; } catch (e) { seen = false; }
    if (seen) { return; }

    var overlay = document.getElementById('spOverlay');
    if (!overlay) { return; }

    function markSeen() { try { window.sessionStorage.setItem('spPopupSeen', '1'); } catch (e) {} }

    function open() {
        overlay.classList.add('is-open');
        markSeen();
    }
    function close() { overlay.classList.remove('is-open'); }

    document.getElementById('spClose').addEventListener('click', close);
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) { close(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { close(); } });

    // Opens after the opening-titles sheet (if any) has had its moment,
    // so the two never compete for attention at once.
    window.setTimeout(open, 1200);
})();
</script>
<?php endif; ?>
