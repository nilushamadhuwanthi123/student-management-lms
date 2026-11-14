<?php
/**
 * "Edit any text on the site" -- super admin only. One row per English
 * source string that t()/te() somewhere on the site is called with; the
 * default Sinhala (from lang/si.php) is shown for reference, and either
 * language can be overridden here. Leaving a box blank (or typing back
 * the plain English source into the English box) removes that override
 * and falls back to the shipped default, same as before this screen
 * existed -- see TranslationController::upsertOrClear().
 *
 * Variables: $rows, $q, $page, $pages, $total
 */
$rows = $rows ?? [];
$q = $q ?? '';
$page = $page ?? 1;
$pages = $pages ?? 1;
$total = $total ?? 0;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qs = static fn (int $p): string => BASE_URL . '/settings/translations?' . http_build_query(['q' => $q, 'page' => $p]);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Translations') ?></h1>
        <p class="text-sm text-muted">
            <?= te('Change the exact wording shown anywhere on the site -- navigation labels, buttons, headings, any fixed text -- in English and/or Sinhala, without a code change.') ?>
            <?= te('Leave a box blank to fall back to the default shown underneath it.') ?>
        </p>
    </div>
</div>

<form method="GET" action="<?= BASE_URL ?>/settings/translations" class="mb-3" style="display:flex; gap:.5rem; max-width:480px;">
    <input type="text" name="q" class="form-control" value="<?= $e($q) ?>" placeholder="<?= te('Search text...') ?>">
    <button type="submit" class="btn btn--secondary"><?= te('Search') ?></button>
    <?php if ($q !== ''): ?><a href="<?= BASE_URL ?>/settings/translations" class="btn btn--ghost"><?= te('Clear') ?></a><?php endif; ?>
</form>

<p class="text-xs text-muted mb-2"><?= te('{n} matching results', ['n' => $total]) ?></p>

<?php if ($rows === []): ?>
<div class="card"><p class="text-muted" style="margin:0;"><?= te('No text matched that search.') ?></p></div>
<?php else: ?>
<div class="card" style="padding:0;">
    <?php foreach ($rows as $r): ?>
        <form method="POST" action="<?= BASE_URL ?>/settings/translations/update" style="padding:1rem 1.1rem; border-top:1px solid var(--border-color,#eee); display:grid; gap:.6rem;">
            <?= csrfField() ?>
            <input type="hidden" name="source" value="<?= $e($r['source']) ?>">
            <input type="hidden" name="back_qs" value="<?= $e(http_build_query(['q' => $q, 'page' => $page])) ?>">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:.75rem;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label"><?= te('English') ?></label>
                    <input type="text" name="override_en" class="form-control" value="<?= $e($r['override_en']) ?>" placeholder="<?= $e($r['source']) ?>">
                    <?php if ($r['override_en'] !== ''): ?><div class="text-xs text-muted" style="margin-top:.2rem;"><?= te('Default:') ?> <?= $e($r['source']) ?></div><?php endif; ?>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label"><?= te('Sinhala') ?></label>
                    <input type="text" name="override_si" class="form-control" value="<?= $e($r['override_si']) ?>" placeholder="<?= $e($r['default_si']) ?>">
                    <?php if ($r['override_si'] !== ''): ?><div class="text-xs text-muted" style="margin-top:.2rem;"><?= te('Default:') ?> <?= $e($r['default_si']) ?></div><?php endif; ?>
                </div>
            </div>
            <div><button type="submit" class="btn btn--primary btn--sm"><?= te('Save') ?></button></div>
        </form>
    <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex; gap:.4rem; justify-content:center; margin-top:1rem; flex-wrap:wrap;">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="<?= $e($qs($p)) ?>" class="btn <?= $p === $page ? 'btn--primary' : 'btn--ghost' ?> btn--sm"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
