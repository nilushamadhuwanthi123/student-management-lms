<?php
/**
 * Settings - Website Content
 *
 * The small, fixed set of editable content blocks on the public website.
 * Everything else on those pages (branch names, class counts, the
 * teacher's name) still comes only from real records -- this page
 * can only change the fixed list of text/photo fields below, nothing more.
 *
 * Variables: $fields (SiteContentService::FIELDS), $values
 */

use App\Services\SiteContentService;

$fields = $fields ?? [];
$values = $values ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Website Content') ?></h1>
        <p class="text-sm text-muted"><?= te('The wording and photos on the public home, about and contact pages. Leave a field blank to use the original wording or photo.') ?></p>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/site-content" class="card" enctype="multipart/form-data">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <?php foreach ($fields as $key => $field): ?>
        <div class="form-group" style="margin-bottom:1.5rem;">
            <label class="form-label" for="field_<?= $e($key) ?>"><?= te($field['label']) ?></label>

            <?php if (($field['type'] ?? 'text') === SiteContentService::TYPE_IMAGE): ?>
                <?php $current = $values[$key] ?? ''; ?>
                <div class="site-content-image" style="display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap;">
                    <?php if ($current !== ''): ?>
                        <img src="<?= BASE_URL ?><?= $e($current) ?>" alt="" style="width:140px; height:100px; object-fit:cover; border-radius:.5rem; border:1px solid var(--border-color,#ddd);">
                    <?php else: ?>
                        <div style="width:140px; height:100px; border-radius:.5rem; border:1px dashed var(--border-color,#ccc); display:flex; align-items:center; justify-content:center; font-size:.75rem; color:#888; text-align:center; padding:.5rem;"><?= te('Using the original photo') ?></div>
                    <?php endif; ?>
                    <div style="flex:1; min-width:220px;">
                        <input type="file" id="field_<?= $e($key) ?>" name="images[<?= $e($key) ?>]" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-hint"><?= te('JPG, PNG or WEBP, up to 3MB.') ?></div>
                        <?php if ($current !== ''): ?>
                        <label style="display:flex; align-items:center; gap:.4rem; margin-top:.5rem; font-size:.85rem;">
                            <input type="checkbox" name="remove_images[<?= $e($key) ?>]" value="1">
                            <?= te('Remove this photo and use the original instead') ?>
                        </label>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif (($field['type'] ?? 'text') === SiteContentService::TYPE_RICHTEXT): ?>
                <div class="rte" data-rte-for="field_<?= $e($key) ?>">
                    <div class="rte__toolbar" role="group" aria-label="<?= te('Formatting') ?>">
                        <button type="button" data-cmd="bold" title="<?= te('Bold') ?>"><strong>B</strong></button>
                        <button type="button" data-cmd="italic" title="<?= te('Italic') ?>"><em>I</em></button>
                        <button type="button" data-cmd="underline" title="<?= te('Underline') ?>"><u>U</u></button>
                        <button type="button" data-cmd="insertUnorderedList" title="<?= te('Bulleted list') ?>">&#8226;&#8226;&#8226;</button>
                        <button type="button" data-cmd="insertOrderedList" title="<?= te('Numbered list') ?>">1.2.3.</button>
                        <button type="button" data-cmd="createLink" title="<?= te('Link') ?>">&#128279;</button>
                        <button type="button" data-cmd="unlink" title="<?= te('Remove link') ?>">&#9587;</button>
                    </div>
                    <div class="rte__editor form-control" contenteditable="true"
                         data-placeholder="<?= $e($field['default'] !== '' ? $field['default'] : t('Not shown unless filled in')) ?>"
                    ><?= $values[$key] === $field['default'] && $field['default'] !== '' ? '' : ($values[$key] ?? '') ?></div>
                    <textarea id="field_<?= $e($key) ?>" name="fields[<?= $e($key) ?>]" style="display:none;"></textarea>
                </div>
                <?php if ($field['default'] !== ''): ?>
                    <div class="form-hint"><?= te('Default') ?>: <?= $e(strip_tags($field['default'])) ?></div>
                <?php endif; ?>

            <?php else: ?>
                <textarea id="field_<?= $e($key) ?>" name="fields[<?= $e($key) ?>]" class="form-control" rows="3"
                    placeholder="<?= $e($field['default'] !== '' ? $field['default'] : t('Not shown unless filled in')) ?>"><?= $e($values[$key] === $field['default'] && $field['default'] !== '' ? '' : ($values[$key] ?? '')) ?></textarea>
                <?php if ($field['default'] !== ''): ?>
                    <div class="form-hint"><?= te('Default') ?>: <?= $e($field['default']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
</form>

<style>
.rte__toolbar { display:flex; gap:.25rem; margin-bottom:.35rem; flex-wrap:wrap; }
.rte__toolbar button { border:1px solid var(--border-color,#ddd); background:#fff; border-radius:.35rem; padding:.3rem .6rem; font-size:.85rem; cursor:pointer; line-height:1; }
.rte__toolbar button:hover { background:#f2f2f2; }
.rte__editor { min-height:5.5rem; padding:.6rem .75rem; }
.rte__editor:empty:before { content: attr(data-placeholder); color:#9aa0a6; }
.rte__editor p { margin:0 0 .6rem; }
.rte__editor p:last-child { margin-bottom:0; }
</style>
<script>
(function () {
    function syncToTextarea(root) {
        var editor = root.querySelector('.rte__editor');
        var textarea = document.getElementById(root.getAttribute('data-rte-for'));
        if (editor && textarea) { textarea.value = editor.innerHTML; }
    }
    document.querySelectorAll('.rte').forEach(function (root) {
        syncToTextarea(root);
        root.querySelectorAll('.rte__toolbar button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var editor = root.querySelector('.rte__editor');
                editor.focus();
                var cmd = btn.getAttribute('data-cmd');
                if (cmd === 'createLink') {
                    var url = window.prompt(<?= json_encode(t('Link URL (https://...)'), JSON_UNESCAPED_UNICODE) ?>, 'https://');
                    if (!url) { return; }
                    document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
                syncToTextarea(root);
            });
        });
        root.querySelector('.rte__editor').addEventListener('input', function () { syncToTextarea(root); });
    });
    var form = document.querySelector('form[action$="/settings/site-content"]');
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('.rte').forEach(syncToTextarea);
        });
    }
})();
</script>
