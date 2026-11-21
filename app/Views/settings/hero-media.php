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
                    <?php
                    $fpSrc   = BASE_URL . $slide['file_path'];
                    $fpType  = $slide['media_type'];
                    $fpValue = (string) $slide['object_position'];
                    $fpName  = 'object_position';
                    $fpUid   = 'e' . (int) $slide['id'];
                    $fpFile  = '';
                    include __DIR__ . '/_focus-point.php';
                    ?>
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
                <input type="file" name="media" id="heroNewFile" class="form-control" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,video/ogg" required>
                <div class="form-hint text-xs"><?= te('Photo: JPG/PNG/WEBP up to 5MB. Video: MP4/WEBM/MOV/OGG up to 35MB.') ?></div>
            </div>
            <?php
            $fpSrc   = '';
            $fpType  = 'image';
            $fpValue = '50% 50%';
            $fpName  = 'object_position';
            $fpUid   = 'new';
            $fpFile  = 'heroNewFile';
            include __DIR__ . '/_focus-point.php';
            ?>
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

<style>
.fp-stage { position: relative; width: 100%; max-width: 420px; aspect-ratio: 16 / 7;
            border-radius: 10px; overflow: hidden; background: #0F172A; cursor: crosshair;
            border: 1px solid var(--border-color, #E2E8F0); }
.fp-stage:focus-visible { outline: 2px solid #1D4ED8; outline-offset: 2px; }
.fp-media { width: 100%; height: 100%; object-fit: cover; display: block; }
.fp-empty { width: 100%; height: 100%; display: grid; place-items: center; text-align: center;
            color: #94A3B8; font-size: .8rem; padding: 0 1rem; cursor: default; }
.fp-mark { position: absolute; width: 26px; height: 26px; margin: -13px 0 0 -13px;
           border: 2px solid #fff; border-radius: 50%; pointer-events: none;
           box-shadow: 0 0 0 2px rgba(0,0,0,.45), inset 0 0 0 2px rgba(0,0,0,.35); }
.fp-mark::after { content: ''; position: absolute; inset: 0; margin: auto;
                  width: 4px; height: 4px; border-radius: 50%; background: #fff; }
.fp-foot { display: flex; align-items: center; gap: .5rem; margin-top: .45rem; }
.fp-foot .fp-value { width: 130px; font-family: ui-monospace, monospace; font-size: .8rem; }
</style>

<script>
(function () {
    document.querySelectorAll('.fp').forEach(function (box) {
        var stage = box.querySelector('.fp-stage');
        var mark  = box.querySelector('.fp-mark');
        var value = box.querySelector('.fp-value');
        var reset = box.querySelector('.fp-reset');
        if (!stage || !mark || !value) { return; }

        function media() { return stage.querySelector('.fp-media'); }

        function apply(x, y, writeField) {
            x = Math.max(0, Math.min(100, Math.round(x)));
            y = Math.max(0, Math.min(100, Math.round(y)));
            mark.style.left = x + '%';
            mark.style.top  = y + '%';
            var m = media();
            if (m) { m.style.objectPosition = x + '% ' + y + '%'; }
            if (writeField) { value.value = x + '% ' + y + '%'; }
        }

        function fromPointer(ev) {
            if (!media()) { return; }          // nothing chosen yet
            var r = stage.getBoundingClientRect();
            apply(((ev.clientX - r.left) / r.width) * 100,
                  ((ev.clientY - r.top) / r.height) * 100, true);
        }

        var dragging = false;
        stage.addEventListener('pointerdown', function (ev) {
            dragging = true;
            stage.setPointerCapture(ev.pointerId);
            fromPointer(ev);
        });
        stage.addEventListener('pointermove', function (ev) { if (dragging) { fromPointer(ev); } });
        stage.addEventListener('pointerup', function () { dragging = false; });
        stage.addEventListener('pointercancel', function () { dragging = false; });

        // Arrow keys, for anyone not using a mouse and for a nudge that
        // a click cannot land precisely.
        stage.addEventListener('keydown', function (ev) {
            var step = ev.shiftKey ? 10 : 2;
            var cur = (value.value.match(/(\d{1,3})%\s+(\d{1,3})%/) || [0, 50, 50]);
            var x = parseInt(cur[1], 10), y = parseInt(cur[2], 10);
            if (ev.key === 'ArrowLeft')       { x -= step; }
            else if (ev.key === 'ArrowRight') { x += step; }
            else if (ev.key === 'ArrowUp')    { y -= step; }
            else if (ev.key === 'ArrowDown')  { y += step; }
            else { return; }
            ev.preventDefault();
            apply(x, y, true);
        });

        // Typing in the box still works and still drives the picture:
        // the text field is what submits, so it stays the source of truth.
        value.addEventListener('input', function () {
            var m = value.value.match(/^\s*(\d{1,3})%\s+(\d{1,3})%\s*$/);
            if (m) { apply(parseInt(m[1], 10), parseInt(m[2], 10), false); }
        });

        if (reset) {
            reset.addEventListener('click', function () { apply(50, 50, true); });
        }

        // On the "add a slide" form there is nothing to look at until a
        // file is chosen, so the chosen file is shown straight from the
        // browser -- no upload, no round trip.
        var fileId = box.getAttribute('data-file');
        if (fileId) {
            var input = document.getElementById(fileId);
            if (input) {
                input.addEventListener('change', function () {
                    var f = input.files && input.files[0];
                    if (!f) { return; }
                    var url = URL.createObjectURL(f);
                    var isVideo = /^video\//.test(f.type);
                    var el = document.createElement(isVideo ? 'video' : 'img');
                    el.className = 'fp-media';
                    el.src = url;
                    if (isVideo) { el.muted = true; el.autoplay = true; el.loop = true; el.playsInline = true; }
                    var old = media();
                    var empty = stage.querySelector('.fp-empty');
                    if (old) { old.remove(); }
                    if (empty) { empty.remove(); }
                    stage.insertBefore(el, mark);
                    var m = value.value.match(/^\s*(\d{1,3})%\s+(\d{1,3})%\s*$/);
                    apply(m ? parseInt(m[1], 10) : 50, m ? parseInt(m[2], 10) : 50, false);
                });
            }
        }
    });
})();
</script>
