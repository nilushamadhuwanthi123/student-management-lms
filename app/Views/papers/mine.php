<?php
/**
 * A student's past papers. Variables: $papers, $open (grade => paid this month)
 */
use App\Controllers\PastPaperController;
use App\Services\TopicService;
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Past papers') ?></h1>
        <p class="text-sm text-muted"><?= te('Papers your teacher has shared for your grade. Try them under exam time, then check the marking scheme when it is released.') ?></p>
    </div>
</div>

<?php if ($papers === []): ?>
    <div class="card"><div class="card__body"><p class="text-muted" style="margin:0;"><?= te('No papers for your grade yet.') ?></p></div></div>
<?php else: ?>
    <?php if (in_array(false, $open, true)): ?>
        <div class="alert alert--warning"><?= te('Some papers are locked until this month\'s fee is settled.') ?> <a href="<?= BASE_URL ?>/my/payments"><?= te('Pay a fee') ?></a></div>
    <?php endif; ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1rem;">
    <?php foreach ($papers as $p): $isOpen = !empty($open[(int) $p['grade']]); ?>
        <div class="card" style="min-width:0;"><div class="card__body">
            <div class="text-xs text-muted"><?= te('Grade {n}', ['n' => (int) $p['grade']]) ?> &middot; <?= (int) $p['year'] ?> &middot; <?= te(PastPaperController::MEDIA[$p['medium']] ?? '') ?></div>
            <div style="font-weight:600; margin:.25rem 0;"><?= $h($p['title']) ?></div>
            <div class="text-sm text-muted"><?= te(PastPaperController::TYPES[$p['paper_type']] ?? 'Other') ?><?php if ($p['topic_title']): ?> &middot; <?= $h(TopicService::label(['title' => $p['topic_title'], 'title_si' => $p['topic_title_si'], 'unit_no' => $p['topic_unit']])) ?><?php endif; ?></div>
            <div style="margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                <?php if ($isOpen): ?>
                    <a class="btn btn--primary btn--sm" href="<?= BASE_URL ?>/past-papers/<?= (int) $p['id'] ?>/file/paper"><?= te('Download paper') ?></a>
                    <?php if ((int) $p['has_answers'] === 1): ?><a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/past-papers/<?= (int) $p['id'] ?>/file/answers"><?= te('Marking scheme') ?></a><?php endif; ?>
                <?php else: ?>
                    <span class="badge badge--warning"><?= te('Locked') ?></span>
                <?php endif; ?>
            </div>
        </div></div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
