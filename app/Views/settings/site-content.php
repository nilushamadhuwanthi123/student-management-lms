<?php
/**
 * Settings - Website Content
 *
 * The small, fixed set of editable text blocks on the public website.
 * Everything else on those pages (branch names, class counts, the
 * teacher's name) still comes only from real records -- this page
 * can only change the fixed list of wording below, nothing more.
 *
 * Variables: $fields (SiteContentService::FIELDS), $values
 */

$fields = $fields ?? [];
$values = $values ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Website Content</h1>
        <p class="text-sm text-muted">The wording on the public home, about and contact pages. Leave a field blank to use the original wording.</p>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/settings/site-content" class="card">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <?php foreach ($fields as $key => $field): ?>
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="field_<?= $e($key) ?>"><?= $e($field['label']) ?></label>
            <textarea id="field_<?= $e($key) ?>" name="fields[<?= $e($key) ?>]" class="form-control" rows="3"
                placeholder="<?= $e($field['default'] !== '' ? $field['default'] : 'Not shown unless filled in') ?>"><?= $e($values[$key] === $field['default'] && $field['default'] !== '' ? '' : ($values[$key] ?? '')) ?></textarea>
            <?php if ($field['default'] !== ''): ?>
                <div class="form-hint">Default: <?= $e($field['default']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn--primary">Save changes</button>
</form>
