<?php
/** Homework set for one class (teacher / staff view). */
$class = $class ?? [];
$assignments = $assignments ?? [];
$enrolled = (int) ($enrolled ?? 0);
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Assignments') ?></h1>
        <p class="text-sm text-muted"><?= $e($class['class_name'] ?? '') ?> &middot; <?= te('{n} students enrolled', ['n' => $enrolled]) ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials" class="btn btn--ghost"><?= te('Material') ?></a>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes" class="btn btn--ghost"><?= te('Quizzes') ?></a>
        <?php /* A coordinator may read the homework but not set it, and
                 the route says so -- so the button is not drawn for one. */ ?>
        <?php if (can('assignments.set')): ?>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments/create" class="btn btn--primary">+ <?= te('Set an assignment') ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if ($assignments === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#128218;</div>
            <h4 style="margin:.75rem 0 .35rem;"><?= te('No assignments yet') ?></h4>
            <p class="text-sm text-muted" style="margin:0 0 1.25rem;"><?= can('assignments.set')
                ? te('Set homework with a due date. Students hand it in here and you mark it with feedback.')
                : te('No homework has been set for this class yet. The class teacher sets it.') ?></p>
            <?php if (can('assignments.set')): ?>
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments/create" class="btn btn--primary"><?= te('Set the first assignment') ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><?= te('Assignment') ?></th><th><?= te('Due') ?></th><th class="text-right"><?= te('Marks') ?></th>
                    <th class="text-right"><?= te('Handed in') ?></th><th class="text-right"><?= te('To mark') ?></th><th><?= te('Status') ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td style="font-weight:600;"><a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments/<?= (int) $a['id'] ?>"><?= $e($a['title']) ?></a></td>
                        <td class="text-sm"><?= $e(lang_date('j M Y, g:i a', (string) $a['due_at'])) ?></td>
                        <td class="text-right"><?= $num($a['total_marks']) ?></td>
                        <td class="text-right"><?= (int) $a['handed_in'] ?> / <?= $enrolled ?></td>
                        <td class="text-right" style="<?= (int) $a['to_mark'] > 0 ? 'color:var(--warning); font-weight:700;' : '' ?>"><?= (int) $a['to_mark'] ?></td>
                        <td><span class="badge" style="background:var(--surface-sunken); color:<?= $a['status'] === 'published' ? 'var(--success)' : 'var(--text-muted)' ?>;"><?= te(ucfirst((string) $a['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
