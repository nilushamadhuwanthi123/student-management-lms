<?php
/** Seminars/events for a student or a parent, with register/unregister. */
$seminars = $seminars ?? [];
$isParent = !empty($isParent);
$base = $isParent ? '/family/seminars' : '/my/seminars';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header"><div><h1 class="page-header__title"><?= te('Seminars & Events') ?></h1>
    <p class="text-sm text-muted"><?= te('Seminars and events for your classes and branch.') ?></p></div></div>
<?php if ($seminars === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No seminars right now.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.85rem; max-width:860px;">
<?php foreach ($seminars as $s):
    $where = $s['class_name'] ?? $s['institute_name'] ?? ($s['grade'] ?? t('All branches'));
    $cancelled = $s['status'] === 'cancelled';
?>
    <article class="card" style="padding:1rem 1.15rem; border-left:4px solid <?= $cancelled ? 'var(--text-muted)' : 'var(--accent)' ?>;">
        <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
            <h3 style="margin:0; font-size:1.05rem;"><?= $e($s['title']) ?></h3>
            <?php if ($cancelled): ?><span class="badge" style="background:var(--surface-sunken);"><?= te('Cancelled') ?></span><?php endif; ?>
        </div>
        <div class="text-xs text-muted" style="margin:.2rem 0 .6rem;">
            <?= $e(lang_date('l j M Y', (string) $s['event_date'])) ?><?= $s['start_time'] ? ', ' . $e(substr((string) $s['start_time'], 0, 5)) : '' ?>
            &middot; <?= $e($where) ?>
            <?php if (!empty($s['venue'])): ?> &middot; <?= $e($s['venue']) ?><?php endif; ?>
        </div>
        <?php if (!empty($s['description'])): ?><div style="white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e($s['description']) ?></div><?php endif; ?>
        <?php if (!empty($s['online_url'])): ?><div style="margin-top:.5rem;"><a href="<?= $e($s['online_url']) ?>" target="_blank" rel="noopener"><?= te('Join online') ?></a></div><?php endif; ?>
        <?php if (!$cancelled && (int) $s['registration_required'] === 1): ?>
        <div style="margin-top:.75rem; display:flex; align-items:center; gap:.6rem; flex-wrap:wrap;">
            <?php if (!empty($s['is_registered'])): ?>
                <?php if (!empty($s['is_waitlisted'])): ?><span class="badge" style="background:var(--surface-sunken);"><?= te('Waitlisted') ?></span><?php else: ?><span class="badge" style="background:var(--surface-sunken); color:var(--success);"><?= te('Registered') ?></span><?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?><?= $base ?>/<?= (int) $s['id'] ?>/unregister" style="margin:0;"><?= csrfField() ?>
                    <button class="btn btn--ghost btn--sm" type="submit"><?= te('Cancel my registration') ?></button></form>
            <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?><?= $base ?>/<?= (int) $s['id'] ?>/register" style="margin:0;"><?= csrfField() ?>
                    <button class="btn btn--primary btn--sm" type="submit"><?= $s['spots_left'] === 0 ? te('Join waiting list') : te('Register') ?></button></form>
            <?php endif; ?>
            <?php if ($s['spots_left'] !== null && !$s['is_registered']): ?><span class="text-xs text-muted"><?= $s['spots_left'] > 0 ? te('{n} spots left', ['n' => $s['spots_left']]) : te('Full') ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
