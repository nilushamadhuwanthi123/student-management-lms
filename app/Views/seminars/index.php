<?php
/** Staff seminar/event management list. */
$seminars = $seminars ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reach = static function (array $s): string {
    $where = match ($s['target_scope']) {
        'all'    => t('All branches'),
        'branch' => (string) $s['institute_name'],
        'grade'  => $s['grade'] . ($s['institute_name'] !== null ? ' · ' . $s['institute_name'] : ' · ' . t('every branch')),
        default  => (string) $s['class_name'],
    };
    $who = array_filter([(int) $s['for_students'] ? t('Students') : null, (int) $s['for_parents'] ? t('Parents') : null, (int) $s['for_staff'] ? t('Staff') : null]);
    return $where . ' · ' . implode(', ', $who);
};
$statusColor = static fn (string $status): string => match ($status) {
    'published' => 'var(--success)', 'cancelled' => 'var(--danger)', default => 'var(--text-muted)',
};
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Seminars & Events') ?></h1>
        <p class="text-sm text-muted"><?= te('Seminars, workshops and events for a class, a grade, a branch, or every branch.') ?></p>
    </div>
    <?php if (!empty($canPost)): ?>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/seminars/create" class="btn btn--primary">+ <?= te('New seminar') ?></a></div>
    <?php endif; ?>
</div>
<?php if ($seminars === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No seminars yet.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.85rem;">
<?php foreach ($seminars as $s): ?>
    <div class="card" style="padding:1rem 1.15rem;">
        <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
            <div style="min-width:0;">
                <a href="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>" style="font-weight:700; font-size:1.02rem; text-decoration:none; color:inherit;"><?= $e($s['title']) ?></a>
                <?php if (!empty($s['show_on_website'])): ?><span class="badge badge--warning" style="margin-left:.35rem; vertical-align:2px;">&#128227; <?= te('On the website') ?></span><?php endif; ?>
                <div class="text-xs text-muted">
                    <?= $e(lang_date('j M Y', (string) $s['event_date'])) ?><?= $s['start_time'] ? ', ' . $e(substr((string) $s['start_time'], 0, 5)) : '' ?>
                    &middot; <?= $e($reach($s)) ?> &middot; <?= $e($s['author'] ?? '') ?>
                    <?php if ((int) $s['registration_required'] === 1): ?> &middot; <?= te('{n} registered', ['n' => (int) $s['registered_count']]) ?><?php if (($s['max_capacity'] ?? null) !== null): ?>/<?= (int) $s['max_capacity'] ?><?php endif; ?><?php if (!empty($s['waitlisted_count'])): ?> &middot; <?= te('{n} waitlisted', ['n' => (int) $s['waitlisted_count']]) ?><?php endif; ?><?php endif; ?>
                </div>
            </div>
            <div style="display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;">
                <span class="badge" style="background:var(--surface-sunken); color:<?= $statusColor($s['status']) ?>;"><?= te(ucfirst((string) $s['status'])) ?></span>
                <?php if (!empty($s['can_manage'])): ?>
                    <?php if ((int) $s['registration_required'] === 1): ?>
                    <a href="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>/registrants" class="btn btn--ghost btn--sm"><?= te('Registrants') ?></a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>/edit" class="btn btn--ghost btn--sm"><?= te('Edit') ?></a>
                    <?php if ($s['status'] === 'draft'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>/publish" style="margin:0;"><?= csrfField() ?><button class="btn btn--primary btn--sm" type="submit"><?= te('Publish') ?></button></form>
                    <form method="POST" action="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>/delete" style="margin:0;" data-confirm="<?= te('Delete this draft?') ?>"><?= csrfField() ?><button class="btn btn--ghost btn--sm" type="submit"><?= te('Delete') ?></button></form>
                    <?php elseif ($s['status'] === 'published'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/seminars/<?= (int) $s['id'] ?>/cancel" style="margin:0;" data-confirm="<?= te('Cancel this seminar? Everyone who can see it will see it as cancelled.') ?>"><?= csrfField() ?><button class="btn btn--ghost btn--sm" type="submit"><?= te('Cancel') ?></button></form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($s['description'])): ?>
        <div style="margin-top:.6rem; white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e(mb_substr((string) $s['description'], 0, 200)) ?><?= mb_strlen((string) $s['description']) > 200 ? '…' : '' ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
