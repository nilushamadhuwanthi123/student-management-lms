<?php
/** Staff notice board. */
$notices = $notices ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reach = static function (array $n): string {
    $where = match ($n['audience']) { 'all' => t('All branches'), 'branch' => (string) $n['institute_name'], default => (string) $n['class_name'] };
    $who = array_filter([(int) $n['for_students'] ? t('Students') : null, (int) $n['for_parents'] ? t('Parents') : null, (int) $n['for_staff'] ? t('Staff') : null]);
    return $where . ' · ' . implode(', ', $who);
};
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Notice board') ?></h1>
        <p class="text-sm text-muted"><?= te('Announcements for a class, a branch, or every branch.') ?></p>
    </div>
    <?php if (!empty($canPost)): ?>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/notices/create" class="btn btn--primary">+ <?= te('New notice') ?></a></div>
    <?php endif; ?>
</div>
<?php if ($notices === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No notices yet.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.85rem;">
<?php foreach ($notices as $n):
    $scheduled = $n['status'] === 'published' && $n['publish_at'] !== null && strtotime((string) $n['publish_at']) > time(); ?>
    <div class="card" style="padding:1rem 1.15rem;">
        <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
            <div style="min-width:0;">
                <div style="font-weight:700; font-size:1.02rem;"><?= $e($n['title']) ?></div>
                <div class="text-xs text-muted"><?= $e($reach($n)) ?> &middot; <?= $e($n['author'] ?? '') ?> &middot; <?= $e(lang_date('j M Y, g:i a', (string) ($n['publish_at'] ?? $n['created_at']))) ?></div>
            </div>
            <div style="display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;">
                <span class="badge" style="background:var(--surface-sunken); color:<?= $n['status'] === 'published' ? 'var(--success)' : 'var(--text-muted)' ?>;">
                    <?= $scheduled ? te('Scheduled') : te(ucfirst((string) $n['status'])) ?></span>
                <?php if (!empty($n['can_manage'])): ?>
                    <?php if ($n['status'] === 'draft'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/notices/<?= (int) $n['id'] ?>/publish" style="margin:0;"><?= csrfField() ?><button class="btn btn--primary btn--sm" type="submit"><?= te('Publish') ?></button></form>
                    <?php endif; ?>
                    <form method="POST" action="<?= BASE_URL ?>/notices/<?= (int) $n['id'] ?>/archive" style="margin:0;"><?= csrfField() ?><button class="btn btn--ghost btn--sm" type="submit"><?= te('Archive') ?></button></form>
                <?php endif; ?>
            </div>
        </div>
        <div style="margin-top:.6rem; white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e($n['body']) ?></div>
    </div>
<?php endforeach; ?>
</div>
