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
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/seminars" class="btn btn--ghost">&larr; <?= te('Seminars & Events') ?></a></div>
</div>
<div class="card" style="max-width:760px; padding:1.25rem;">
    <?php if (!empty($seminar['cover_image_url'])): ?>
    <img src="<?= $e($seminar['cover_image_url']) ?>" alt="" style="width:100%; border-radius:8px; margin-bottom:1rem;" onerror="this.style.display='none';">
    <?php endif; ?>
    <div class="text-sm text-muted" style="margin-bottom:.75rem;">
        <?= $e(lang_date('j F Y', (string) $seminar['event_date'])) ?>
        <?php if ($seminar['start_time']): ?>, <?= $e(substr((string) $seminar['start_time'], 0, 5)) ?><?php if ($seminar['end_time']): ?> &ndash; <?= $e(substr((string) $seminar['end_time'], 0, 5)) ?><?php endif; ?><?php endif; ?>
        &middot; <?= $e($where) ?>
    </div>
    <?php if (!empty($seminar['venue'])): ?><div style="margin-bottom:.5rem;"><strong><?= te('Venue') ?>:</strong> <?= $e($seminar['venue']) ?></div><?php endif; ?>
    <?php if (!empty($seminar['online_url'])): ?><div style="margin-bottom:.5rem;"><strong><?= te('Online link') ?>:</strong> <a href="<?= $e($seminar['online_url']) ?>" target="_blank" rel="noopener"><?= $e($seminar['online_url']) ?></a></div><?php endif; ?>
    <?php if (!empty($seminar['description'])): ?><div style="margin-top:.75rem; white-space:pre-wrap;"><?= $e($seminar['description']) ?></div><?php endif; ?>
    <?php if ((int) $seminar['registration_required'] === 1): ?>
    <div class="text-sm text-muted" style="margin-top:1rem;"><?= te('{n} registered', ['n' => (int) $registeredCount]) ?></div>
    <?php endif; ?>
    <div style="margin-top:1rem;">
        <span class="badge" style="background:var(--surface-sunken);"><?= te(ucfirst((string) $seminar['status'])) ?></span>
    </div>
</div>
