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

            <?php if ($groupKey === 'appearance'): ?>
                <div class="sc-themes">
                    <div class="sc-themes__lead">
                        <strong><?= te('Ready-made styles') ?></strong>
                        <span><?= te('Click one to see it on the boxes below, then press Save changes to put it live. Nothing changes on the public site until you save, so try as many as you like.') ?></span>
                    </div>
                    <div class="sc-themes__grid">
                        <?php foreach (SiteContentService::THEMES as $tKey => $t): ?>
                            <button type="button" class="sc-theme" data-theme="<?= $e($tKey) ?>"
                                    data-primary="<?= $e($t['primary']) ?>" data-secondary="<?= $e($t['secondary']) ?>"
                                    data-bg="<?= $e($t['bg']) ?>" data-nav="<?= $e($t['nav']) ?>"
                                    data-navtext="<?= $e($t['navText']) ?>" data-accent="<?= $e($t['accent']) ?>"
                                    data-radius="<?= $e((string) $t['radius']) ?>" data-tint="<?= $e((string) $t['tint']) ?>"
                                    data-font="<?= $e($t['font']) ?>">
                                <span class="sc-theme__preview" style="background: <?= $e($t['bg']) ?>;">
                                    <span class="sc-theme__bar" style="background: <?= $e($t['nav']) ?>;">
                                        <i style="background: <?= $e($t['navText']) ?>; opacity:.85;"></i>
                                        <i style="background: <?= $e($t['navText']) ?>; opacity:.55;"></i>
                                        <i style="background: <?= $e($t['accent']) ?>;"></i>
                                    </span>
                                    <span class="sc-theme__body">
                                        <span class="sc-theme__line" style="background: <?= $e($t['primary']) ?>; width:62%;"></span>
                                        <span class="sc-theme__line" style="background: <?= $e($t['secondary']) ?>; opacity:.5; width:88%;"></span>
                                        <span class="sc-theme__btn" style="background: <?= $e($t['primary']) ?>; border-radius: <?= (int) $t['radius'] ?>px;"></span>
                                        <span class="sc-theme__btn sc-theme__btn--alt" style="background: <?= $e($t['accent']) ?>; border-radius: <?= (int) $t['radius'] ?>px;"></span>
                                    </span>
                                </span>
                                <span class="sc-theme__meta">
                                    <span class="sc-theme__name"><?= te($t['name']) ?></span>
                                    <span class="sc-theme__note"><?= te($t['note']) ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="sc-themes__foot"><?= te('A style only fills in the boxes below -- you can still change any single colour afterwards.') ?></p>
                </div>
            <?php endif; ?>

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

                    <?php if ($type === SiteContentService::TYPE_IMAGE || $type === SiteContentService::TYPE_MEDIA): ?>
                        <?php
                        $current = $stored($values, $key, $baseLang);
                        $isMedia = $type === SiteContentService::TYPE_MEDIA;
                        $currentIsVideo = $current !== '' && SiteContentService::isVideoPath($current);
                        $accept = $isMedia
                            ? 'image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,video/ogg'
                            : 'image/jpeg,image/png,image/webp';
                        $hint = $isMedia
                            ? te('JPG/PNG/WEBP photo (up to 3MB) or MP4/WEBM/MOV/OGG video (up to 35MB). The same one is used in both languages.')
                            : te('JPG, PNG or WEBP, up to 3MB. The same photo is used in both languages.');
                        ?>
                        <div class="sc-image">
                            <?php if ($current !== '' && $currentIsVideo): ?>
                                <video src="<?= BASE_URL ?><?= $e($current) ?>" style="width:140px; height:100px; object-fit:cover; border-radius:.5rem; border:1px solid var(--border-color, #ddd);" muted autoplay loop playsinline></video>
                            <?php elseif ($current !== ''): ?>
                                <img src="<?= BASE_URL ?><?= $e($current) ?>" alt="">
                            <?php else: ?>
                                <div class="sc-image__none"><?= te('Using the original photo') ?></div>
                            <?php endif; ?>
                            <div class="sc-image__input">
                                <input type="file" id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>" name="images[<?= $e($key) ?>]" class="form-control" accept="<?= $e($accept) ?>">
                                <div class="form-hint"><?= $hint ?></div>
                                <?php if ($current !== ''): ?>
                                    <label class="sc-image__remove">
                                        <input type="checkbox" name="remove_images[<?= $e($key) ?>]" value="1">
                                        <?= te('Remove this and use the original instead') ?>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ($type === SiteContentService::TYPE_COLOR): ?>
                        <?php $cur = $stored($values, $key, $baseLang); ?>
                        <?php $cur = preg_match('/^#[0-9A-Fa-f]{6}$/', $cur) === 1 ? $cur : (string) ($field['default'] ?? '#000000'); ?>
                        <div class="sc-color">
                            <input type="color" class="sc-color__pick" value="<?= $e($cur) ?>"
                                   data-color-for="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"
                                   aria-label="<?= $e(t($field['label'])) ?>">
                            <input type="text" id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"
                                   name="fields[<?= $e($baseLang) ?>][<?= $e($key) ?>]"
                                   class="form-control sc-color__hex" value="<?= $e($cur) ?>"
                                   data-color-hex="1" spellcheck="false" maxlength="7">
                            <button type="button" class="btn btn--sm btn--ghost sc-color__reset"
                                    data-reset-to="<?= $e((string) ($field['default'] ?? '')) ?>"><?= te('Reset') ?></button>
                        </div>

                    <?php elseif ($type === SiteContentService::TYPE_TOGGLE): ?>
                        <?php
                        $raw = $stored($values, $key, $baseLang);
                        $on  = ($raw === '' ? (string) ($field['default'] ?? '1') : $raw) === '1';
                        ?>
                        <div class="sc-toggle">
                            <!-- An unticked box sends nothing, so this carries the "off". -->
                            <input type="hidden" name="fields[<?= $e($baseLang) ?>][<?= $e($key) ?>]" value="0">
                            <label class="sc-switch">
                                <input type="checkbox" id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"
                                       name="fields[<?= $e($baseLang) ?>][<?= $e($key) ?>]" value="1"
                                       <?= $on ? 'checked' : '' ?>>
                                <span class="sc-switch__track"><span class="sc-switch__thumb"></span></span>
                                <span class="sc-switch__text"><?= te('Shown') ?></span>
                            </label>
                        </div>

                    <?php elseif ($type === SiteContentService::TYPE_ICONSET): ?>
                        <?php
                        $raw = $stored($values, $key, $baseLang);
                        $raw = $raw === '' ? (string) ($field['default'] ?? '') : $raw;
                        $picked = array_filter(array_map('trim', explode(',', $raw)));
                        ?>
                        <div class="sc-icons">
                            <!-- Untick everything and the field would post nothing at all. -->
                            <input type="hidden" name="icons_present[<?= $e($key) ?>]" value="1">
                            <?php foreach (\App\Services\HeroIconLibrary::ICONS as $iKey => $icon): ?>
                                <label class="sc-icon<?= in_array($iKey, $picked, true) ? ' is-on' : '' ?>">
                                    <input type="checkbox" name="icons[<?= $e($key) ?>][]" value="<?= $e($iKey) ?>"
                                           <?= in_array($iKey, $picked, true) ? 'checked' : '' ?>>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon[1] ?></svg>
                                    <span><?= te($icon[0]) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($type === SiteContentService::TYPE_CHOICE): ?>
                        <?php
                        $listName = (string) ($field['choices'] ?? '');
                        $choices = match ($listName) {
                            'FONTS'      => SiteContentService::FONTS,
                            'NAV_STYLES' => SiteContentService::NAV_STYLES,
                            default      => [],
                        };
                        $raw = $stored($values, $key, $baseLang);
                        $cur = $raw === '' ? (string) ($field['default'] ?? '') : $raw;
                        ?>
                        <select id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"
                                name="fields[<?= $e($baseLang) ?>][<?= $e($key) ?>]" class="form-control" style="max-width:340px;">
                            <?php foreach ($choices as $cKey => $c): ?>
                                <option value="<?= $e($cKey) ?>" <?= $cur === $cKey ? 'selected' : '' ?>><?= te($c[0]) ?></option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($type === SiteContentService::TYPE_NUMBER): ?>
                        <?php
                        $raw = $stored($values, $key, $baseLang);
                        $cur = $raw === '' ? (string) ($field['default'] ?? '0') : $raw;
                        ?>
                        <div class="sc-number">
                            <input type="number" id="field_<?= $e($key) ?>_<?= $e($baseLang) ?>"
                                   name="fields[<?= $e($baseLang) ?>][<?= $e($key) ?>]"
                                   class="form-control" value="<?= $e($cur) ?>"
                                   min="<?= $e((string) ($field['min'] ?? 0)) ?>"
                                   max="<?= $e((string) ($field['max'] ?? 999999)) ?>"
                                   step="<?= $e((string) ($field['step'] ?? 1)) ?>">
                            <?php if (!empty($field['suffix'])): ?>
                                <span class="sc-number__suffix"><?= te($field['suffix']) ?></span>
                            <?php endif; ?>
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

                    <?php if (!empty($field['help'])): ?>
                        <p class="sc-help"><?= te($field['help']) ?></p>
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

<style>
    .sc-help { margin: .35rem 0 0; font-size: .82rem; color: #64748B; }

    .sc-themes { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 1.2rem; }
    .sc-themes__lead { display: grid; gap: .15rem; margin-bottom: .85rem; }
    .sc-themes__lead span { font-size: .84rem; color: #64748B; }
    .sc-themes__foot { margin: .8rem 0 0; font-size: .8rem; color: #64748B; }
    .sc-themes__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .7rem; }
    .sc-theme { cursor: pointer; text-align: left; padding: 0; background: #fff;
                border: 1.5px solid #E2E8F0; border-radius: 12px; overflow: hidden; }
    .sc-theme:hover { border-color: #94A3B8; }
    .sc-theme.is-picked { border-color: #1D4ED8; box-shadow: 0 0 0 3px rgba(29,78,216,.14); }
    .sc-theme__preview { display: block; height: 82px; }
    .sc-theme__bar { display: flex; align-items: center; gap: 4px; height: 20px; padding: 0 8px; }
    .sc-theme__bar i { width: 13px; height: 4px; border-radius: 2px; display: block; }
    .sc-theme__body { display: block; padding: 9px 10px; }
    .sc-theme__line { display: block; height: 5px; border-radius: 3px; margin-bottom: 5px; }
    .sc-theme__btn { display: inline-block; width: 34px; height: 12px; margin-top: 3px; }
    .sc-theme__btn--alt { width: 22px; margin-left: 4px; }
    .sc-theme__meta { display: block; padding: .5rem .6rem .6rem; border-top: 1px solid #F1F5F9; }
    .sc-theme__name { display: block; font-size: .84rem; font-weight: 700; color: #1C2538; }
    .sc-theme__note { display: block; font-size: .74rem; color: #64748B; line-height: 1.35; }

    .sc-palette { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: .9rem 1rem; margin-bottom: 1.1rem; }
    .sc-palette__lead { display: grid; gap: .15rem; margin-bottom: .7rem; }
    .sc-palette__lead span { font-size: .84rem; color: #64748B; }
    .sc-palette__row { display: flex; flex-wrap: wrap; gap: .5rem; }
    .sc-swatch { display: inline-flex; align-items: center; gap: .5rem; cursor: pointer;
                 background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; padding: .4rem .7rem; }
    .sc-swatch:hover { border-color: #94A3B8; }
    .sc-swatch.is-picked { border-color: #1D4ED8; box-shadow: 0 0 0 2px rgba(29,78,216,.15); }
    .sc-swatch__dots { display: inline-flex; }
    .sc-swatch__dots i { width: 14px; height: 14px; border-radius: 50%; display: inline-block;
                         border: 1.5px solid #fff; margin-left: -5px; }
    .sc-swatch__dots i:first-child { margin-left: 0; }
    .sc-swatch__name { font-size: .82rem; font-weight: 600; color: #1C2538; }

    .sc-icons { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: .5rem; }
    .sc-icon { display: flex; flex-direction: column; align-items: center; gap: .3rem; cursor: pointer;
               border: 1.5px solid #E2E8F0; border-radius: 10px; padding: .6rem .4rem; background: #fff;
               color: #64748B; transition: border-color .15s ease, color .15s ease; }
    .sc-icon:hover { border-color: #94A3B8; }
    .sc-icon.is-on { border-color: #1D4ED8; color: #1D4ED8; background: #F5F8FF; }
    .sc-icon input { position: absolute; opacity: 0; width: 0; height: 0; }
    .sc-icon svg { width: 26px; height: 26px; }
    .sc-icon span { font-size: .72rem; font-weight: 600; text-align: center; line-height: 1.2; }

    .sc-color { display: flex; align-items: center; gap: .5rem; }
    .sc-color__pick { width: 46px; height: 38px; padding: 2px; border: 1px solid #E2E8F0;
                      border-radius: 8px; background: #fff; cursor: pointer; }
    .sc-color__hex { max-width: 140px; font-family: ui-monospace, monospace; text-transform: uppercase; }

    .sc-number { display: flex; align-items: center; gap: .6rem; }
    .sc-number input { max-width: 160px; }
    .sc-number__suffix { font-size: .84rem; color: #64748B; }

    .sc-toggle { padding-top: .15rem; }
    .sc-switch { display: inline-flex; align-items: center; gap: .6rem; cursor: pointer; }
    .sc-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .sc-switch__track { width: 46px; height: 26px; border-radius: 999px; background: #CBD5E1;
                        position: relative; transition: background .18s ease; flex: none; }
    .sc-switch__thumb { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px;
                        border-radius: 50%; background: #fff; transition: transform .18s ease;
                        box-shadow: 0 1px 3px rgba(0,0,0,.25); }
    .sc-switch input:checked + .sc-switch__track { background: #16A34A; }
    .sc-switch input:checked + .sc-switch__track .sc-switch__thumb { transform: translateX(20px); }
    .sc-switch input:focus-visible + .sc-switch__track { outline: 2px solid #1D4ED8; outline-offset: 2px; }
    .sc-switch__text { font-size: .86rem; color: #475569; }
</style>

<script>
(function () {
    // Keep the picker and the hex box showing the same thing, whichever
    // one is used. The hex box is the field that actually submits.
    document.querySelectorAll('.sc-icon input').forEach(function (box) {
        box.addEventListener('change', function () {
            box.closest('.sc-icon').classList.toggle('is-on', box.checked);
        });
    });

    document.querySelectorAll('.sc-color__pick').forEach(function (pick) {
        var hex = document.getElementById(pick.getAttribute('data-color-for'));
        if (!hex) { return; }

        pick.addEventListener('input', function () { hex.value = pick.value.toUpperCase(); });
        hex.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(hex.value.trim())) { pick.value = hex.value.trim(); }
        });
    });

    document.querySelectorAll('.sc-color__reset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.sc-color');
            var to = btn.getAttribute('data-reset-to') || '';
            if (!wrap || !to) { return; }
            var hex = wrap.querySelector('[data-color-hex]');
            var pick = wrap.querySelector('.sc-color__pick');
            if (hex) { hex.value = to.toUpperCase(); }
            if (pick) { pick.value = to; }
        });
    });

    // A palette fills every colour box at once; nothing is saved until
    // the form is submitted, so a mis-click costs nothing.
    function setColour(key, value) {
        var hex = document.getElementById('field_' + key + '_<?= $e($baseLang) ?>');
        if (!hex) { return; }
        hex.value = value.toUpperCase();
        var pick = document.querySelector('[data-color-for="field_' + key + '_<?= $e($baseLang) ?>"]');
        if (pick) { pick.value = value; }
    }

    function setField(key, value) {
        var el = document.getElementById('field_' + key + '_<?= $e($baseLang) ?>');
        if (el) { el.value = value; }
    }

    // A style only fills the boxes in; the public site changes when the
    // form is saved, so clicking through them costs nothing.
    document.querySelectorAll('.sc-theme').forEach(function (card) {
        card.addEventListener('click', function () {
            setColour('theme_primary', card.getAttribute('data-primary'));
            setColour('theme_secondary', card.getAttribute('data-secondary'));
            setColour('theme_page_bg', card.getAttribute('data-bg'));
            setColour('theme_nav_bg', card.getAttribute('data-nav'));
            setColour('theme_nav_text', card.getAttribute('data-navtext'));
            setColour('theme_accent', card.getAttribute('data-accent'));
            setField('theme_radius', card.getAttribute('data-radius'));
            setField('theme_font', card.getAttribute('data-font'));
            setField('hero_tint_percent', card.getAttribute('data-tint'));

            document.querySelectorAll('.sc-theme').forEach(function (o) { o.classList.remove('is-picked'); });
            card.classList.add('is-picked');
            card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    });

    document.querySelectorAll('.sc-swatch').forEach(function (sw) {
        sw.addEventListener('click', function () {
            setColour('theme_primary', sw.getAttribute('data-primary'));
            setColour('theme_secondary', sw.getAttribute('data-secondary'));
            setColour('theme_page_bg', sw.getAttribute('data-bg'));
            setColour('theme_nav_bg', sw.getAttribute('data-nav'));

            document.querySelectorAll('.sc-swatch').forEach(function (o) { o.classList.remove('is-picked'); });
            sw.classList.add('is-picked');
        });
    });
})();
</script>
