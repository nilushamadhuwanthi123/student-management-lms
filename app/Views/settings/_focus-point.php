<?php
/**
 * Picking the focus point by looking at the photo, not by typing.
 *
 * The field used to be a text box wanting something like "70% 35%".
 * Nobody can read that off a photograph, so it stayed at the default
 * and faces got cropped out of the top of the home page -- which is
 * the one thing the setting exists to prevent.
 *
 * This shows the slide at the shape the hero actually crops it to and
 * lets the office click the part that must stay visible. The text box
 * is still here and still the field that submits, so a value typed by
 * hand keeps working and the two stay in step either way.
 *
 * Expects:
 *   $fpSrc   string  URL of the current photo or video, '' for a new slide
 *   $fpType  string  'image' or 'video'
 *   $fpValue string  the stored position, e.g. '50% 50%'
 *   $fpName  string  the input name (object_position)
 *   $fpUid   string  a suffix unique to this copy
 *   $fpFile  string  id of the file input to watch, '' for none
 */
$fpSrc   = (string) ($fpSrc ?? '');
$fpType  = ($fpType ?? 'image') === 'video' ? 'video' : 'image';
$fpValue = trim((string) ($fpValue ?? '')) ?: '50% 50%';
$fpName  = (string) ($fpName ?? 'object_position');
$fpUid   = (string) ($fpUid ?? 'new');
$fpFile  = (string) ($fpFile ?? '');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Parsed here as well as in the browser, so the crosshair is already in
// the right place on first paint rather than jumping once JS runs.
$fpX = 50;
$fpY = 50;
if (preg_match('/^\s*(\d{1,3})%\s+(\d{1,3})%\s*$/', $fpValue, $m) === 1) {
    $fpX = min(100, (int) $m[1]);
    $fpY = min(100, (int) $m[2]);
}
?>
<div class="form-group fp" data-fp="<?= $e($fpUid) ?>" data-file="<?= $e($fpFile) ?>">
    <label class="form-label" for="fp_<?= $e($fpUid) ?>"><?= te('Focus point') ?></label>
    <p class="form-hint text-xs" style="margin:0 0 .4rem;">
        <?= te('Click the part of the picture that must stay visible. Phones and wide screens crop it differently, so this is the point they all keep.') ?>
    </p>

    <div class="fp-stage" tabindex="0" role="application"
         aria-label="<?= te('Click the part of the picture that must stay visible') ?>">
        <?php if ($fpSrc === ''): ?>
            <div class="fp-empty"><?= te('Choose a photo or video above to set this by eye.') ?></div>
        <?php elseif ($fpType === 'video'): ?>
            <video class="fp-media" src="<?= $e($fpSrc) ?>" muted autoplay loop playsinline
                   style="object-position: <?= $fpX ?>% <?= $fpY ?>%;"></video>
        <?php else: ?>
            <img class="fp-media" src="<?= $e($fpSrc) ?>" alt=""
                 style="object-position: <?= $fpX ?>% <?= $fpY ?>%;">
        <?php endif; ?>
        <span class="fp-mark" style="left: <?= $fpX ?>%; top: <?= $fpY ?>%;" aria-hidden="true"></span>
    </div>

    <div class="fp-foot">
        <input type="text" id="fp_<?= $e($fpUid) ?>" name="<?= $e($fpName) ?>"
               class="form-control fp-value" value="<?= $e($fpValue) ?>" placeholder="50% 50%">
        <button type="button" class="btn btn--ghost btn--xs fp-reset"><?= te('Centre it') ?></button>
    </div>
</div>
