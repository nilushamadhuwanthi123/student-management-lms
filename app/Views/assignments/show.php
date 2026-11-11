<?php
/** One assignment: details, who handed in, and marking. */
$class = $class ?? [];
$a = $assignment ?? [];
$roster = $roster ?? [];
$canEdit = !empty($canEdit);
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$base = BASE_URL . '/classes/' . (int) $class['id'] . '/assignments/' . (int) $a['id'];
$handed = count(array_filter($roster, static fn (array $r): bool => $r['submission_id'] !== null));
$marked = count(array_filter($roster, static fn (array $r): bool => $r['marks'] !== null));
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($a['title']) ?></h1>
        <p class="text-sm text-muted">
            <?= $e($class['class_name'] ?? '') ?> &middot; <?= te('Due {when}', ['when' => lang_date('j M Y, g:i a', (string) $a['due_at'])]) ?>
            &middot; <?= te('Out of {n}', ['n' => $num($a['total_marks'])]) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments" class="btn btn--ghost">&larr; <?= te('Assignments') ?></a>
        <?php if ($canEdit && $a['status'] === 'draft'): ?>
            <form method="POST" action="<?= $base ?>/publish" style="margin:0;"><?= csrfField() ?>
                <button class="btn btn--primary" type="submit"><?= te('Publish and tell the class') ?></button></form>
        <?php endif; ?>
        <?php if ($canEdit && $a['status'] !== 'archived'): ?>
            <form method="POST" action="<?= $base ?>/archive" style="margin:0;" data-confirm="<?= te('Archive this assignment? Hand-ins and marks are kept.') ?>"><?= csrfField() ?>
                <button class="btn btn--ghost" type="submit"><?= te('Archive') ?></button></form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.25rem;">
    <div class="card" style="padding:1rem;"><div class="text-sm text-muted"><?= te('Status') ?></div><div style="font-size:1.3rem; font-weight:700;"><?= te(ucfirst((string) $a['status'])) ?></div></div>
    <div class="card" style="padding:1rem;"><div class="text-sm text-muted"><?= te('Handed in') ?></div><div style="font-size:1.3rem; font-weight:700;"><?= $handed ?> / <?= count($roster) ?></div></div>
    <div class="card" style="padding:1rem;"><div class="text-sm text-muted"><?= te('Marked') ?></div><div style="font-size:1.3rem; font-weight:700;"><?= $marked ?> / <?= $handed ?></div></div>
</div>

<?php if (!empty($a['instructions'])): ?>
<div class="card" style="margin-bottom:1.25rem;"><div class="card__body"><?= nl2br($e($a['instructions'])) ?></div></div>
<?php endif; ?>

<div class="card">
    <div class="card__header"><h3 style="margin:0;"><?= te('Students') ?></h3></div>
    <div class="card__body" style="display:grid; gap:.85rem;">
        <?php if ($roster === []): ?>
            <p class="text-sm text-muted" style="margin:0;"><?= te('No students are enrolled in this class.') ?></p>
        <?php endif; ?>
        <?php foreach ($roster as $r): ?>
            <div style="border:1px solid var(--border); border-radius:var(--radius); padding:.85rem 1rem;">
                <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                    <div>
                        <strong><?= $e($r['full_name']) ?></strong>
                        <span class="text-sm text-muted"><?= $e($r['student_id_display']) ?></span>
                    </div>
                    <div class="text-sm">
                        <?php if ($r['submission_id'] === null): ?>
                            <span style="color:var(--text-muted);"><?= te('Not handed in') ?></span>
                        <?php else: ?>
                            <?= te('Handed in {when}', ['when' => lang_date('j M, g:i a', (string) $r['submitted_at'])]) ?>
                            <?php if ((int) $r['is_late'] === 1): ?><span class="badge" style="background:var(--warning-light); color:var(--warning);"><?= te('Late') ?></span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($r['submission_id'] !== null): ?>
                    <?php if (!empty($r['answer_text'])): ?>
                        <div class="text-sm" style="margin-top:.6rem; padding:.6rem .75rem; background:var(--surface-sunken); border-radius:var(--radius-sm); white-space:pre-wrap;"><?= $e($r['answer_text']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($r['file_path'])): ?>
                        <div class="text-sm" style="margin-top:.5rem;"><a href="<?= BASE_URL ?>/assignments/submissions/<?= (int) $r['submission_id'] ?>/file">&#128206; <?= $e($r['original_name'] ?: t('Attached file')) ?></a></div>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <form method="POST" action="<?= $base ?>/mark" style="display:grid; grid-template-columns:120px 1fr auto; gap:.5rem; margin-top:.75rem; align-items:start;" class="asg-mark">
                        <?= csrfField() ?>
                        <input type="hidden" name="submission_id" value="<?= (int) $r['submission_id'] ?>">
                        <input type="number" name="marks" class="form-control form-control--sm" min="0" max="<?= $e($a['total_marks']) ?>" step="0.5" value="<?= $r['marks'] !== null ? $e($num($r['marks'])) : '' ?>" placeholder="<?= te('Marks') ?>">
                        <input type="text" name="feedback" class="form-control form-control--sm" value="<?= $e($r['feedback'] ?? '') ?>" placeholder="<?= te('Feedback for the student') ?>">
                        <button type="submit" class="btn btn--primary btn--sm"><?= $r['marks'] !== null ? te('Update') : te('Save mark') ?></button>
                    </form>
                    <?php elseif ($r['marks'] !== null): ?>
                        <div class="text-sm" style="margin-top:.5rem;"><strong><?= $num($r['marks']) ?> / <?= $num($a['total_marks']) ?></strong> <?= $e($r['feedback'] ?? '') ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<style>@media (max-width:640px){ .asg-mark { grid-template-columns:1fr !important; } }</style>
