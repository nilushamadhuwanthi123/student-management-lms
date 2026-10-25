<?php
/** One assignment, from the student's side: instructions, hand-in, mark. */
$a = $assignment ?? [];
$sub = $submission ?? null;
$now = $now ?? date('Y-m-d H:i:s');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$marked = $sub !== null && $sub['marks'] !== null;
$overdue = $a['due_at'] < $now;
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($a['title']) ?></h1>
        <p class="text-sm text-muted"><?= $e($a['class_name']) ?> &middot; <?= te('Due {when}', ['when' => lang_date('l j M, g:i a', (string) $a['due_at'])]) ?> &middot; <?= te('Out of {n}', ['n' => $num($a['total_marks'])]) ?></p>
    </div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/my/assignments" class="btn btn--ghost">&larr; <?= te('My assignments') ?></a></div>
</div>

<?php if ($marked): ?>
<div class="card" style="margin-bottom:1.25rem; border-color:var(--success);">
    <div class="card__body" style="display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap;">
        <div style="font-size:2.2rem; font-weight:800; color:var(--success);"><?= $num($sub['marks']) ?><span style="font-size:1rem; color:var(--text-muted);"> / <?= $num($a['total_marks']) ?></span></div>
        <div style="flex:1; min-width:200px;">
            <div class="text-sm text-muted"><?= te("Teacher's feedback") ?></div>
            <div><?= $sub['feedback'] ? nl2br($e($sub['feedback'])) : '<span class="text-muted">&mdash;</span>' ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($a['instructions'])): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card__header"><h3 style="margin:0;"><?= te('Instructions') ?></h3></div>
    <div class="card__body"><?= nl2br($e($a['instructions'])) ?></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card__header"><h3 style="margin:0;"><?= $sub ? te('Your hand-in') : te('Hand it in') ?></h3></div>
    <div class="card__body">
        <?php if ($sub): ?>
            <p class="text-sm" style="margin:0 0 .75rem;">
                &#10003; <?= te('Handed in {when}', ['when' => lang_date('j M, g:i a', (string) $sub['submitted_at'])]) ?>
                <?php if ((int) $sub['is_late'] === 1): ?><span class="badge" style="background:var(--warning-light); color:var(--warning);"><?= te('Late') ?></span><?php endif; ?>
                <?php if (!empty($sub['file_path'])): ?>
                    &middot; <a href="<?= BASE_URL ?>/assignments/submissions/<?= (int) $sub['id'] ?>/file">&#128206; <?= $e($sub['original_name'] ?: t('Attached file')) ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($marked): ?>
            <?php if (!empty($sub['answer_text'])): ?><div style="white-space:pre-wrap; padding:.75rem; background:var(--surface-sunken); border-radius:var(--radius-sm);"><?= $e($sub['answer_text']) ?></div><?php endif; ?>
        <?php else: ?>
            <?php if ($overdue): ?><div class="alert alert--warning" style="margin-bottom:.75rem;"><?= te('The due date has passed. You can still hand in, but it will be marked late.') ?></div><?php endif; ?>
            <form method="POST" action="<?= BASE_URL ?>/my/assignments/<?= (int) $a['id'] ?>/submit" enctype="multipart/form-data" style="display:grid; gap:.85rem;">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="answer_text"><?= te('Your answer') ?></label>
                    <textarea id="answer_text" name="answer_text" class="form-control" rows="7"><?= $e($sub['answer_text'] ?? '') ?></textarea>
                </div>
                <?php if ((int) $a['allow_file'] === 1): ?>
                <div class="form-group">
                    <label class="form-label" for="attachment"><?= te('Attach a photo or PDF (up to 5 MB)') ?></label>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.png,.jpg,.jpeg,image/png,image/jpeg,application/pdf">
                </div>
                <?php endif; ?>
                <div><button type="submit" class="btn btn--primary"><?= $sub ? te('Update my hand-in') : te('Hand it in') ?></button></div>
            </form>
        <?php endif; ?>
    </div>
</div>
