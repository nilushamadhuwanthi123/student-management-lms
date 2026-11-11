<?php
/** Staff preview of one seminar. */
$seminar = $seminar ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$where = match ($seminar['target_scope']) {
    'all'    => t('All branches'),
    'branch' => (string) ($seminar['institute_name'] ?? ''),
    'grade'  => $seminar['grade'] . ($seminar['institute_name'] !== null ? ' · ' . $seminar['institute_name'] : ' · ' . t('every branch')),
    default  => (string) ($seminar['class_name'] ?? ''),
};
?>
<div class="page-header">
    <div><h1 class="page-header__title"><?= $e($seminar['title']) ?></h1></div>
    <div class="page-header__actions" style="display:flex; gap:.5rem;">
        <?php if (!empty($canManage)): ?>
            <?php if ((int) $seminar['registration_required'] === 1): ?><a href="<?= BASE_URL ?>/seminars/<?= (int) $seminar['id'] ?>/registrants" class="btn btn--ghost"><?= te('Registrants') ?></a><?php endif; ?>
            <a href="<?= BASE_URL ?>/seminars/<?= (int) $seminar['id'] ?>/edit" class="btn btn--ghost"><?= te('Edit') ?></a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/seminars" class="btn btn--ghost">&larr; <?= te('Seminars & Events') ?></a>
    </div>
</div>
<div class="card" style="max-width:760px; padding:1.25rem;">
    <?php $coverSrc = \App\Services\SeminarService::coverSrc($seminar['cover_image_url'] ?? null); ?>
    <?php if ($coverSrc !== ''): ?>
    <img src="<?= $e($coverSrc) ?>" alt="" style="width:100%; border-radius:8px; margin-bottom:1rem;" onerror="this.style.display='none';">
    <?php endif; ?>
    <div class="text-sm text-muted" style="margin-bottom:.75rem;">
        <?= $e(lang_date('j F Y', (string) $seminar['event_date'])) ?>
        <?php if ($seminar['start_time']): ?>, <?= $e(substr((string) $seminar['start_time'], 0, 5)) ?><?php if ($seminar['end_time']): ?> &ndash; <?= $e(substr((string) $seminar['end_time'], 0, 5)) ?><?php endif; ?><?php endif; ?>
        &middot; <?= $e($where) ?>
    </div>
    <?php if (!empty($canManage) && isset($audience)): ?>
    <div style="margin-bottom:.9rem; padding:.65rem .85rem; border-radius:10px; background:var(--primary-light); font-size:.9rem;">
        <strong>&#128101; <?= te('Who receives this') ?>:</strong> <?= $e($where) ?> &middot;
        <?= te('{n} students', ['n' => (int) $audience['student']]) ?>, <?= te('{n} parents', ['n' => (int) $audience['parent']]) ?>, <?= te('{n} staff', ['n' => (int) $audience['user']]) ?>
        <?php if ($audience['student'] + $audience['parent'] + $audience['user'] === 0): ?>
            <div style="color:var(--danger); margin-top:.3rem;"><?= te('Nobody is in this group yet, so nobody will see it. Check the class or branch you chose.') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($seminar['show_on_website'])): ?><div style="margin-bottom:.75rem;"><span class="badge badge--warning">&#128227; <?= te('On the website') ?></span> <span class="text-xs text-muted"><?= $seminar['status'] === 'published' ? te('Shown on the home page until the day of the event.') : te('Will appear on the home page once published.') ?></span></div><?php endif; ?>
    <?php if (!empty($seminar['venue'])): ?><div style="margin-bottom:.5rem;"><strong><?= te('Venue') ?>:</strong> <?= $e($seminar['venue']) ?></div><?php endif; ?>
    <?php if (!empty($seminar['online_url'])): ?><div style="margin-bottom:.5rem;"><strong><?= te('Online link') ?>:</strong> <a href="<?= $e($seminar['online_url']) ?>" target="_blank" rel="noopener"><?= $e($seminar['online_url']) ?></a></div><?php endif; ?>
    <?php if (!empty($seminar['description'])): ?><div style="margin-top:.75rem; white-space:pre-wrap;"><?= $e($seminar['description']) ?></div><?php endif; ?>
    <?php if ((int) $seminar['registration_required'] === 1): ?>
    <div class="text-sm text-muted" style="margin-top:1rem;">
        <?= te('{n} registered', ['n' => (int) $registeredCount]) ?><?php if (($seminar['max_capacity'] ?? null) !== null): ?>/<?= (int) $seminar['max_capacity'] ?><?php endif; ?>
        <?php if (!empty($canManage)): ?>
            <?php if (!empty($waitlistedCount)): ?> &middot; <?= te('{n} waitlisted', ['n' => (int) $waitlistedCount]) ?><?php endif; ?>
            &middot; <?= te('{n} attended', ['n' => (int) $attendedCount]) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:1rem;">
        <span class="badge" style="background:var(--surface-sunken);"><?= te(ucfirst((string) $seminar['status'])) ?></span>
    </div>
</div>
