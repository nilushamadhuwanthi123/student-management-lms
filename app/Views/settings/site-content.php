<?php
/**
 * Settings - Website Content
 *
 * The fixed set of editable wording and photos on the public website,
 * grouped by the part of the site each one belongs to, and with an
 * English and a Sinhala box for every piece of wording.
 *
 * Everything factual on those pages -- branch names, class counts, days
 * and times, the teacher's name -- still comes only from real records.
 * This page can only change the fixed list of fields below, nothing more.
 *
 * Variables: $fields (SiteContentService::FIELDS), $groups, $langs,
 *            $baseLang, $values (key => lang => stored value)
 */

use App\Services\SiteContentService;

$fields   = $fields ?? [];
$groups   = $groups ?? ['' => ''];
$langs    = $langs ?? ['en'];
$baseLang = $baseLang ?? 'en';
$values   = $values ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$langNames = ['en' => t('English'), 'si' => t('Sinhala')];

// Group the fields, keeping GROUPS' order and tolerating an ungrouped key.
$grouped = [];
foreach ($fields as $key => $field) {
    $grouped[$field['group'] ?? ''][$key] = $field;
}

/** The stored value for a field in a language, or '' when the owner has not written one. */
$stored = static function (array $values, string $key, string $lang): string {
    return (string) ($values[$key][$lang] ?? '');
};
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Website Content') ?></h1>
        <p class="text-sm text-muted"><?= te('The wording and photos on the public home, about and contact pages. Leave a box blank to use the original wording, which is shown as faint text inside it.') ?></p>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/site-content" enctype="multipart/form-data">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <?php foreach ($groups as $groupKey => $groupLabel): ?>
        <?php $groupFields = $grouped[$groupKey] ?? []; ?>
        <?php if ($groupFields === []) { continue; } ?>

        <section class="card sc-group">
            <h2 class="sc-group__title"><?= te($groupLabel) ?></h2>

            <?php if ($groupKey === 'classes'): ?>
                <div class="sc-addbranch">
                    <span><?= te('This section only edits the heading text above the branch list. Each box on the Classes page is one branch.') ?></span>
                    <a href="<?= BASE_URL ?>/institutes/create" class="btn btn--sm btn--primary">+ <?= te('Add a new branch') ?></a>
                    <a href="<?= BASE_URL ?>/institutes" class="btn btn--sm btn--ghost"><?= te('Manage branches (photo, video, description)') ?></a>
                </div>
            <?php endif; ?>

            <?php foreach ($groupFields as $key => $field): ?>
                <?php $type = $field['type'] ?? SiteContentService::TYPE_TEXT; ?>
                <div class="form-group sc-field">
                    <label class="form-label" for="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"><?= te($field['label']) ?></label>

                    <?php if ($type === SiteContentService::TYPE_IMAGE): ?>
                        <?php $current = $stored($values, $key, $baseLang); ?>
                        <div class="sc-image">
                            <?php if ($current !== ''): ?>
                                <img src="<?= BASE_URL ?><?= $e($current) ?>" alt="">
                            <?php else: ?>
                                <div class="sc-image__none"><?= te('Using the original photo') ?></div>
                            <?php endif; ?>
                            <div class="sc-image__input">
                                <input type="file" id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>" name="images[<?= $e($key) ?>]" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-hint"><?= te('JPG, PNG or WEBP, up to 3MB. The same photo is used in both languages.') ?></div>
                                <?php if ($current !== ''): ?>
                                    <label class="sc-image__remove">
                                        <input type="checkbox" name="remove_images[<?= $e($key) ?>]" value="1">
                                        <?= te('Remove this photo and use the original instead') ?>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="sc-langs">
                            <?php foreach ($langs as $lang): ?>
                                <?php
                                $id    = 'field_' . $key . '_' . $lang;
                                $val   = $stored($values, $key, $lang);
                                // The default is the English wording that used to be
                                // hard-coded; for Sinhala it is whatever the dictionary
                                // makes of it, so the placeholder shows what the page
                                // actually says today when this box is left blank.
                                $hint  = (string) ($field['default'] ?? '');
                                if ($hint !== '' && $lang !== $baseLang && function_exists('lang_translate_to')) {
                                    $hint = (string) lang_translate_to($hint, $lang);
                                }
                                $place = $hint !== '' ? $hint : t('Not shown unless filled in');
                                ?>
                                <div class="sc-lang">
                                    <span class="sc-lang__tag"><?= $e($langNames[$lang] ?? strtoupper($lang)) ?></span>

                                    <?php if ($type === SiteContentService::TYPE_RICHTEXT): ?>
                                        <div class="rte" data-rte-for="<?= $e($id) ?>">
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
                                                 data-placeholder="<?= $e(strip_tags($place)) ?>"><?= $val ?></div>
                                            <textarea id="<?= $e($id) ?>" name="fields[<?= $e($lang) ?>][<?= $e($key) ?>]" style="display:none;"></textarea>
                                        </div>
                                    <?php else: ?>
                                        <textarea id="<?= $e($id) ?>" name="fields[<?= $e($lang) ?>][<?= $e($key) ?>]"
                                                  class="form-control" rows="2"
                                                  placeholder="<?= $e($place) ?>"><?= $e($val) ?></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <div class="sc-save">
        <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
    </div>
</form>

<style>
.sc-group { margin-bottom: 1.25rem; }
.sc-group__title { font-size: 1.05rem; margin: 0 0 1rem; padding-bottom: .5rem; border-bottom: 1px solid var(--border-color, #e5e7eb); }
.sc-field { margin-bottom: 1.5rem; }
.sc-field:last-child { margin-bottom: 0; }
.sc-langs { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: .75rem; }
.sc-lang { min-width: 0; }
.sc-lang__tag { display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted, #6b7280); margin-bottom: .25rem; }
.sc-image { display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.sc-image img { width: 140px; height: 100px; object-fit: cover; border-radius: .5rem; border: 1px solid var(--border-color, #ddd); }
.sc-image__none { width: 140px; height: 100px; border-radius: .5rem; border: 1px dashed var(--border-color, #ccc); display: flex; align-items: center; justify-content: center; font-size: .75rem; color: #888; text-align: center; padding: .5rem; }
.sc-image__input { flex: 1; min-width: 220px; }
.sc-image__remove { display: flex; align-items: center; gap: .4rem; margin-top: .5rem; font-size: .85rem; }
.sc-save { position: sticky; bottom: 0; padding: .9rem 0; background: var(--surface, #fff); border-top: 1px solid var(--border-color, #e5e7eb); }
.sc-addbranch { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: .6rem; padding: .7rem .9rem; margin: -.25rem 0 1.25rem; }
.sc-addbranch span { font-size: .85rem; color: var(--text-muted, #475569); flex: 1 1 260px; }
.sc-addbranch .btn--sm { padding: .35rem .75rem; font-size: .82rem; }
.rte__toolbar { display: flex; gap: .25rem; margin-bottom: .35rem; flex-wrap: wrap; }
.rte__toolbar button { border: 1px solid var(--border-color, #ddd); background: #fff; border-radius: .35rem; padding: .3rem .6rem; font-size: .85rem; cursor: pointer; line-height: 1; }
.rte__toolbar button:hover { background: #f2f2f2; }
.rte__editor { min-height: 5.5rem; padding: .6rem .75rem; }
.rte__editor:empty:before { content: attr(data-placeholder); color: #9aa0a6; }
.rte__editor p { margin: 0 0 .6rem; }
.rte__editor p:last-child { margin-bottom: 0; }
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
