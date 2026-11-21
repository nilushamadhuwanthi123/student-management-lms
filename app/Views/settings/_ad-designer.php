<?php
/**
 * The "design it here" panel for one ad form.
 *
 * Included twice on the Website Ads screen -- once in the new-ad form,
 * once in each edit form -- so it takes everything it needs as
 * variables and shares no ids between copies.
 *
 * Expects:
 *   $ad   array|[]  the ad being edited, or [] for a new one
 *   $uid  string    a suffix unique to this copy, for ids
 *
 * The preview is drawn by SiteAdDesign::render(), the same method the
 * public page uses, so what the office sees here is what gets
 * published. Typing and colour changes update the drawn markup in
 * place; they never rebuild it from a second copy of the layout, which
 * is how a preview starts quietly lying.
 */

use App\Services\SiteAdDesign;

$ad  = is_array($ad ?? null) ? $ad : [];
$uid = (string) ($uid ?? 'new');
$e   = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$curTemplate = (string) ($ad['design_template'] ?? '');
$isDesigned  = SiteAdDesign::isTemplate($curTemplate);
[$bgD, $textD, $accentD] = SiteAdDesign::DEFAULT_COLORS;

$curBg     = SiteAdDesign::color($ad['bg_color'] ?? null, $bgD);
$curText   = SiteAdDesign::color($ad['text_color'] ?? null, $textD);
$curAccent = SiteAdDesign::color($ad['accent_color'] ?? null, $accentD);

// What the preview shows before anything is typed: the ad as it stands,
// or an example, so the panel is never an empty grey box.
$previewAd = [
    'design_template' => $isDesigned ? $curTemplate : SiteAdDesign::DEFAULT_TEMPLATE,
    'title'           => (string) ($ad['title'] ?? ''),
    'headline'        => (string) ($ad['headline'] ?? ''),
    'subtext'         => (string) ($ad['subtext'] ?? ''),
    'button_text'     => (string) ($ad['button_text'] ?? ''),
    'bg_color'        => $curBg,
    'text_color'      => $curText,
    'accent_color'    => $curAccent,
];
if (trim($previewAd['headline']) === '' && trim($previewAd['title']) === '') {
    $previewAd['headline'] = t('Enrolment for the new term is now open');
}
if (trim($previewAd['subtext']) === '') {
    $previewAd['subtext'] = t('Grades 6 to 11, at every branch. Ask at the office for a form.');
}
if (trim($previewAd['button_text']) === '') {
    $previewAd['button_text'] = t('Find out more');
}
?>

<div class="adx" data-adx="<?= $e($uid) ?>">
    <label class="adx-switch">
        <input type="checkbox" class="adx-on" <?= $isDesigned ? 'checked' : '' ?>>
        <strong><?= te('Design the ad here') ?></strong>
        <span class="text-xs text-muted"><?= te('No picture needed -- pick a look, type the words, choose the colours.') ?></span>
    </label>

    <div class="adx-body" <?= $isDesigned ? '' : 'hidden' ?>>
        <?php /* Empty when the panel is switched off, so saving an ad as a
                 picture clears the design rather than leaving it half set. */ ?>
        <input type="hidden" name="design_template" class="adx-template"
               value="<?= $e($isDesigned ? $curTemplate : '') ?>">

        <div class="adx-row">
            <span class="form-label"><?= te('The look') ?></span>
            <div class="adx-tiles">
                <?php foreach (SiteAdDesign::TEMPLATES as $tKey => $tpl): ?>
                    <button type="button"
                            class="adx-tile<?= ($isDesigned ? $curTemplate : SiteAdDesign::DEFAULT_TEMPLATE) === $tKey ? ' is-on' : '' ?>"
                            data-template="<?= $e($tKey) ?>"
                            title="<?= $e(t($tpl[1])) ?>">
                        <span class="adx-tile__name"><?= $e(t($tpl[0])) ?></span>
                        <span class="adx-tile__hint"><?= $e(t($tpl[1])) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adx-row">
            <span class="form-label"><?= te('Colours') ?></span>
            <div class="adx-ways">
                <?php foreach (SiteAdDesign::COLORWAYS as $cKey => $way): ?>
                    <button type="button" class="adx-way" data-bg="<?= $e($way[1]) ?>"
                            data-text="<?= $e($way[2]) ?>" data-accent="<?= $e($way[3]) ?>"
                            title="<?= $e(t($way[0])) ?>"
                            style="background:<?= $e($way[1]) ?>; color:<?= $e($way[2]) ?>;">
                        <span style="background:<?= $e($way[3]) ?>"></span><?= $e(t($way[0])) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="adx-hexes">
                <label><?= te('Background') ?>
                    <input type="color" class="adx-pick" data-for="bg" value="<?= $e($curBg) ?>">
                    <input type="text" name="bg_color" class="form-control adx-hex" data-for="bg"
                           value="<?= $e($curBg) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                </label>
                <label><?= te('Lettering') ?>
                    <input type="color" class="adx-pick" data-for="text" value="<?= $e($curText) ?>">
                    <input type="text" name="text_color" class="form-control adx-hex" data-for="text"
                           value="<?= $e($curText) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                </label>
                <label><?= te('Button') ?>
                    <input type="color" class="adx-pick" data-for="accent" value="<?= $e($curAccent) ?>">
                    <input type="text" name="accent_color" class="form-control adx-hex" data-for="accent"
                           value="<?= $e($curAccent) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
                </label>
            </div>
        </div>

        <div class="adx-grid">
            <div class="form-group">
                <label class="form-label"><?= te('Heading on the ad') ?></label>
                <input type="text" name="headline" class="form-control adx-text" data-part="headline"
                       maxlength="160" value="<?= $e($ad['headline'] ?? '') ?>"
                       placeholder="<?= te('Left blank, the title above is used.') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Second line') ?></label>
                <input type="text" name="subtext" class="form-control adx-text" data-part="sub"
                       maxlength="400" value="<?= $e($ad['subtext'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Button wording') ?></label>
                <input type="text" name="button_text" class="form-control adx-text" data-part="btn"
                       maxlength="60" value="<?= $e($ad['button_text'] ?? '') ?>"
                       placeholder="<?= te('e.g. Find out more') ?>">
                <div class="form-hint text-xs"><?= te('The button uses the link above. With no link it shows as a plain label.') ?></div>
            </div>
        </div>

        <div class="adx-preview">
            <div class="adx-preview__label"><?= te('This is exactly how it will look on the site') ?></div>
            <div class="adx-preview__stage">
                <?= SiteAdDesign::render($previewAd, '#') ?>
            </div>
        </div>
    </div>
</div>
