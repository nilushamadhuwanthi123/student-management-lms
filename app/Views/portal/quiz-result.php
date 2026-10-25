<?php
/**
 * One sat paper.
 *
 * Correct answers only appear once the window has shut. Before that the
 * student sees their score but not the key, which is the difference
 * between a result page and a cheat sheet for whoever sits it later.
 */

$attempt = $attempt ?? [];
$answers = $answers ?? [];
$reveal  = $reveal ?? false;

$pct = $attempt['percentage'] !== null ? (float) $attempt['percentage'] : null;
$pass = $attempt['pass_mark'] !== null && $pct !== null ? $pct >= (float) $attempt['pass_mark'] : null;
$letters = ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($attempt['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted">
            <?= te('Handed in {when}', ['when' => $attempt['submitted_at'] ? lang_date('j M Y, g:i a', (string) $attempt['submitted_at']) : '—']) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/my/marks" class="btn btn--ghost">&larr; <?= te('My Marks') ?></a>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card__body" style="text-align:center; padding:2rem 1.5rem;">
        <div style="font-size:3rem; font-weight:800; line-height:1; color:<?= $pass === true ? 'var(--success)' : ($pass === false ? 'var(--danger)' : 'var(--primary)') ?>;">
            <?= $pct !== null ? rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%' : '&mdash;' ?>
        </div>
        <div style="margin-top:.5rem; color:var(--text-secondary);">
            <?= $attempt['score'] !== null
                ? te('{a} out of {b} marks', ['a' => rtrim(rtrim(number_format((float) $attempt['score'], 2), '0'), '.'), 'b' => rtrim(rtrim(number_format((float) $attempt['total_marks'], 2), '0'), '.')])
                : te('Not marked yet') ?>
        </div>

        <?php if ($attempt['attempt_status'] === 'auto_submitted'): ?>
            <div class="text-sm" style="margin-top:.75rem; color:var(--warning);">
                <?= te('The time ran out and this was handed in automatically.') ?>
            </div>
        <?php endif; ?>

        <?php if ((int) $attempt['needs_marking'] === 1): ?>
            <div class="text-sm" style="margin-top:.75rem; color:var(--warning);">
                <?= te('Some answers are still being marked. This score may go up.') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$reveal): ?>
    <div class="card">
        <div class="card__body" style="text-align:center; padding:2rem 1.5rem;">
            <div style="font-size:2rem;">&#128274;</div>
            <p style="margin:.75rem 0 0; color:var(--text-secondary);">
                <?= te('The answers appear here once the quiz closes on {when}.', ['when' => lang_date('j M Y, g:i a', (string) $attempt['closes_at'])]) ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card__header"><h3 style="margin:0;"><?= te('Your answers') ?></h3></div>
        <div class="card__body" style="display:grid; gap:1rem;">
            <?php foreach ($answers as $a):
                $correct = (int) ($a['is_correct'] ?? 0) === 1;
                $unanswered = $a['selected_option'] === null && ($a['answer_text'] ?? '') === '';
            ?>
                <div style="border:1px solid <?= $correct ? 'var(--success)' : 'var(--border)' ?>;
                            border-left:4px solid <?= $correct ? 'var(--success)' : ($unanswered ? 'var(--text-muted)' : 'var(--danger)') ?>;
                            border-radius:var(--radius); padding:1rem;">
                    <div style="display:flex; justify-content:space-between; gap:1rem;">
                        <div style="font-weight:600;">
                            <?= (int) $a['question_number'] ?>. <?= htmlspecialchars($a['question_text'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-sm" style="white-space:nowrap; color:<?= $correct ? 'var(--success)' : 'var(--text-muted)' ?>;">
                            <?= rtrim(rtrim(number_format((float) ($a['marks_awarded'] ?? 0), 2), '0'), '.') ?>
                            / <?= rtrim(rtrim(number_format((float) $a['marks'], 2), '0'), '.') ?>
                        </div>
                    </div>

                    <?php if ($a['question_type'] === 'short_answer'): ?>
                        <div class="text-sm" style="margin-top:.75rem;">
                            <div><?= te('You wrote:') ?> <strong><?= htmlspecialchars((string) ($a['answer_text'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <?php if (!empty($a['correct_text'])): ?>
                                <div style="color:var(--success);"><?= te('Expected:') ?> <?= htmlspecialchars($a['correct_text'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <ul style="list-style:none; padding:0; margin:.75rem 0 0; display:grid; gap:.35rem;">
                            <?php foreach ($letters as $key => $label): ?>
                                <?php if (!empty($a['option_' . $key])):
                                    $isRight = strtolower((string) $a['correct_option']) === $key;
                                    $isMine  = strtolower((string) $a['selected_option']) === $key;
                                ?>
                                    <li class="text-sm" style="<?= $isRight ? 'color:var(--success); font-weight:600;' : ($isMine ? 'color:var(--danger);' : 'color:var(--text-secondary);') ?>">
                                        <?= $label ?>. <?= htmlspecialchars($a['option_' . $key], ENT_QUOTES, 'UTF-8') ?>
                                        <?= $isRight ? ' &#10003;' : '' ?>
                                        <?= $isMine && !$isRight ? ' &larr; ' . te('your answer') : '' ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($unanswered): ?>
                        <div class="text-sm text-muted" style="margin-top:.5rem;"><?= te('You left this blank.') ?></div>
                    <?php endif; ?>

                    <?php if (!empty($a['explanation'])): ?>
                        <div class="text-sm" style="margin-top:.75rem; padding:.6rem .75rem; background:var(--surface-sunken); border-radius:var(--radius-sm); color:var(--text-secondary);">
                            <?= htmlspecialchars($a['explanation'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
