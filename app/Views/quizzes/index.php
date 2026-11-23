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
        <?php if (can('assignments.view')): ?>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/assignments" class="btn btn--ghost"><?= te('Assignments') ?></a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/create" class="btn btn--primary">
            + <?= te('Set a quiz') ?>
        </a>
    </div>
</div>

<?php /*
 * Setting a quiz is three steps, not one, and nothing on this page said
 * so. A quiz created and left alone is a draft with no questions: the
 * class sees nothing, and the teacher believes it is set. The strip
 * names the steps, and each row below says which one it is waiting on.
 */ ?>
<div class="qz-steps">
    <span class="qz-step"><i>1</i><?= te('Set the paper') ?></span>
    <span class="qz-arrow">&rarr;</span>
    <span class="qz-step"><i>2</i><?= te('Add the questions') ?></span>
    <span class="qz-arrow">&rarr;</span>
    <span class="qz-step"><i>3</i><?= te('Publish it') ?></span>
    <span class="qz-note"><?= te('Until it is published the class cannot see it.') ?></span>
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
                            <?php if ($w['state'] === 'draft' && (int) $q['question_count'] === 0): ?>
                                <div class="text-sm" style="color:var(--warning); font-weight:600; margin-top:.25rem;">
                                    <?= te('No questions yet -- the class cannot sit this.') ?>
                                </div>
                            <?php endif; ?>
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
                            <?php
                            // What this quiz is waiting on. Only a draft
                            // is waiting on the teacher: published and
                            // closed ones need nothing, so they keep the
                            // plain Questions link.
                            $isDraft = $w['state'] === 'draft';
                            if ($isDraft && (int) $q['question_count'] === 0) {
                                $next = t('Add questions');
                            } elseif ($isDraft) {
                                $next = t('Publish it');
                            } else {
                                $next = null;
                            }
                            ?>
                            <?php if ($next !== null): ?>
                                <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/<?= (int) $q['id'] ?>"
                                   class="btn btn--primary btn--sm"><?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/quizzes/<?= (int) $q['id'] ?>"
                               class="btn btn--ghost btn--sm"><?= te('Questions') ?></a>
                            <?php endif; ?>
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

<style>
    .qz-steps { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap;
                background:var(--card-bg,#fff); border:1px solid var(--border-color,#E2E8F0);
                border-radius:12px; padding:.7rem .9rem; margin-bottom:1rem; font-size:.84rem; }
    .qz-step { display:inline-flex; align-items:center; gap:.4rem; font-weight:600; }
    .qz-step i { width:20px; height:20px; border-radius:50%; background:var(--primary,#1D4ED8); color:#fff;
                 font-style:normal; font-size:.72rem; font-weight:800;
                 display:inline-flex; align-items:center; justify-content:center; }
    .qz-arrow { color:#94A3B8; }
    .qz-note { margin-left:auto; font-size:.76rem; color:#64748B; }
</style>
