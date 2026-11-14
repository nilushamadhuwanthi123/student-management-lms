<?php
/**
 * Quizzes set for one class.
 *
 * The status column answers the question a teacher actually opens this
 * page with — "is anything waiting on me?" — so papers needing marking
 * are called out rather than hidden behind a count.
 */

$class   = $class ?? [];
$quizzes = $quizzes ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Quizzes') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials" class="btn btn--ghost"><?= te('Material') ?></a>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments" class="btn btn--ghost"><?= te('Assignments') ?></a>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/create" class="btn btn--primary">
            + <?= te('Set a quiz') ?>
        </a>
    </div>
</div>

<div class="card">
    <?php if ($quizzes === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#128221;</div>
            <h4 style="margin:.75rem 0 .35rem;"><?= te('No quizzes yet') ?></h4>
            <p class="text-sm text-muted" style="margin:0 0 1.25rem;">
                <?= te('Set a paper, add the questions, then publish it. Students sit it inside the window you choose.') ?>
            </p>
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/create" class="btn btn--primary">
                <?= te('Set the first quiz') ?>
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= te('Quiz') ?></th>
                        <th><?= te('Window') ?></th>
                        <th class="text-right"><?= te('Questions') ?></th>
                        <th class="text-right"><?= te('Marks') ?></th>
                        <th class="text-right"><?= te('Sat') ?></th>
                        <th><?= te('Status') ?></th>
                        <th class="text-right"><?= te('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $q):
                    $w = Quiz::window($q);
                    $toneVar = match ($w['tone']) {
                        'success' => 'var(--success)',
                        'info'    => 'var(--primary)',
                        default   => 'var(--text-muted)',
                    };
                ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;">
                                <?= htmlspecialchars($q['title'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="text-sm text-muted">
                                <?= date('F Y', strtotime($q['month_year'])) ?>
                                <?php if (!empty($q['time_limit_minutes'])): ?>
                                    &middot; <?= (int) $q['time_limit_minutes'] ?> <?= te('min limit') ?>
                                <?php endif; ?>
                            </div>
                            <?php if ((int) $q['awaiting_marking'] > 0): ?>
                                <div class="text-sm" style="color:var(--warning); font-weight:600; margin-top:.25rem;">
                                    <?= (int) $q['awaiting_marking'] ?> <?= te('paper') ?><?= (int) $q['awaiting_marking'] === 1 ? '' : 's' ?>
                                    <?= te('waiting on you') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= date('j M, g:i a', strtotime($q['opens_at'])) ?><br>
                            <span class="text-muted"><?= te('to') ?> <?= date('j M, g:i a', strtotime($q['closes_at'])) ?></span>
                        </td>
                        <td class="text-right"><?= (int) $q['question_count'] ?></td>
                        <td class="text-right"><?= rtrim(rtrim(number_format((float) $q['total_marks'], 2), '0'), '.') ?></td>
                        <td class="text-right"><?= (int) $q['sat_count'] ?></td>
                        <td>
                            <span class="badge" style="background:var(--surface-sunken); color:<?= $toneVar ?>;">
                                <?= htmlspecialchars($w['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/<?= (int) $q['id'] ?>"
                               class="btn btn--ghost btn--sm"><?= te('Questions') ?></a>
                            <?php if ((int) $q['sat_count'] > 0): ?>
                                <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/<?= (int) $q['id'] ?>/results"
                                   class="btn btn--ghost btn--sm"><?= te('Results') ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
