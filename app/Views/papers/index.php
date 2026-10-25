<?php
/**
 * Staff past-paper library. Variables: $papers, $grade
 */
use App\Controllers\PastPaperController;
use App\Services\TopicService;
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Past papers') ?></h1>
        <p class="text-sm text-muted"><?= te('Term tests, model papers and marking schemes by grade. Students see published papers of their own grade once the month is paid.') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/past-papers/create<?= $grade ? '?grade=' . (int) $grade : '' ?>" class="btn btn--primary"><?= te('Add a past paper') ?></a>
    </div>
</div>

<div class="mb-3" style="display:flex; flex-wrap:wrap; gap:.5rem;">
    <a class="btn btn--sm <?= $grade === 0 ? 'btn--primary' : 'btn--ghost' ?>" href="<?= BASE_URL ?>/past-papers"><?= te('All grades') ?></a>
    <?php for ($g = 6; $g <= 11; $g++): ?>
        <a class="btn btn--sm <?= $grade === $g ? 'btn--primary' : 'btn--ghost' ?>" href="<?= BASE_URL ?>/past-papers?grade=<?= $g ?>"><?= te('Grade {n}', ['n' => $g]) ?></a>
    <?php endfor; ?>
</div>

<div class="card"><div class="card__body" style="overflow-x:auto;">
<?php if ($papers === []): ?>
    <p class="text-muted" style="margin:0;"><?= te('No papers yet.') ?></p>
<?php else: ?>
    <table class="table">
        <thead><tr><th><?= te('Grade') ?></th><th><?= te('Paper') ?></th><th><?= te('Year') ?></th><th><?= te('Medium') ?></th><th><?= te('Topic (unit)') ?></th><th><?= te('Status') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($papers as $p): ?>
            <tr>
                <td><?= (int) $p['grade'] ?></td>
                <td><strong><?= $h($p['title']) ?></strong><br><span class="text-xs text-muted"><?= te(PastPaperController::TYPES[$p['paper_type']] ?? 'Other') ?></span></td>
                <td><?= (int) $p['year'] ?></td>
                <td><?= te(PastPaperController::MEDIA[$p['medium']] ?? '') ?></td>
                <td><?= $p['topic_title'] ? $h(TopicService::label(['title' => $p['topic_title'], 'title_si' => $p['topic_title_si'], 'unit_no' => $p['topic_unit']])) : '&ndash;' ?></td>
                <td>
                    <span class="badge <?= $p['status'] === 'published' ? 'badge--success' : 'badge--secondary' ?>"><?= $p['status'] === 'published' ? te('Published') : te('Draft') ?></span>
                    <?php if ($p['answers_path']): ?><br><span class="text-xs text-muted"><?= (int) $p['show_answers'] === 1 ? te('Marking scheme released') : te('Marking scheme hidden') ?></span><?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/past-papers/<?= (int) $p['id'] ?>/file/paper"><?= te('Paper') ?></a>
                    <?php if ($p['answers_path']): ?><a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/past-papers/<?= (int) $p['id'] ?>/file/answers"><?= te('Marking scheme') ?></a><?php endif; ?>
                    <?php
                    $actions = $p['status'] === 'published' ? ['draft' => te('Unpublish')] : ['publish' => te('Publish')];
                    if ($p['answers_path']) {
                        $actions += (int) $p['show_answers'] === 1 ? ['answers_off' => te('Hide marking scheme')] : ['answers_on' => te('Release marking scheme')];
                    }
                    $actions['archive'] = te('Remove');
                    foreach ($actions as $to => $label): ?>
                        <form method="POST" action="<?= BASE_URL ?>/past-papers/<?= (int) $p['id'] ?>/status" style="display:inline;">
                            <?= csrfField() ?><input type="hidden" name="to" value="<?= $h($to) ?>">
                            <button type="submit" class="btn btn--ghost btn--sm"><?= $label ?></button>
                        </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div></div>
