<?php
/**
 * Every quiz the student has handed in.
 *
 * A paper still waiting on the teacher is shown as waiting rather than
 * as a zero, so a slow marking week never looks like a failed test.
 */

$attempts = $attempts ?? [];
$progress = $progress ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">My marks</h1>
        <p class="text-sm text-muted"><?= count($attempts) ?> quiz<?= count($attempts) === 1 ? '' : 'zes' ?> handed in</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/my/progress" class="btn btn--ghost">My progress</a>
        <a href="<?= BASE_URL ?>/my/classes" class="btn btn--ghost">My classes</a>
    </div>
</div>

<?php if (!empty($progress['has_data'])): ?>
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card__body" style="display:flex; gap:2rem; flex-wrap:wrap; align-items:center;">
        <div>
            <div class="text-sm text-muted">Average</div>
            <div style="font-size:1.75rem; font-weight:700; color:var(--primary);">
                <?= rtrim(rtrim(number_format((float) $progress['marks']['average_percentage'], 1), '0'), '.') ?>%
            </div>
        </div>
        <div>
            <div class="text-sm text-muted">Best</div>
            <div style="font-size:1.75rem; font-weight:700; color:var(--success);">
                <?= $progress['marks']['best_percentage'] !== null
                    ? rtrim(rtrim(number_format((float) $progress['marks']['best_percentage'], 1), '0'), '.') . '%'
                    : '&mdash;' ?>
            </div>
        </div>
        <?php if ((int) $progress['marks']['awaiting_marking'] > 0): ?>
        <div>
            <div class="text-sm text-muted">Waiting on the teacher</div>
            <div style="font-size:1.75rem; font-weight:700; color:var(--warning);">
                <?= (int) $progress['marks']['awaiting_marking'] ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if ($attempts === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#128221;</div>
            <h4 style="margin:.75rem 0 .35rem;">No quizzes yet</h4>
            <p class="text-sm text-muted" style="margin:0;">
                Your marks appear here once you have sat a quiz.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Class</th>
                        <th class="text-right">Score</th>
                        <th class="text-right">Percentage</th>
                        <th>Handed in</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($attempts as $a):
                    $pending = (int) $a['needs_marking'] === 1;
                    $pct = $a['percentage'] !== null ? (float) $a['percentage'] : null;
                    $pass = $a['pass_mark'] !== null && $pct !== null ? $pct >= (float) $a['pass_mark'] : null;
                ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($a['class_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-right">
                            <?= $a['score'] !== null
                                ? rtrim(rtrim(number_format((float) $a['score'], 2), '0'), '.') . ' / ' . rtrim(rtrim(number_format((float) $a['total_marks'], 2), '0'), '.')
                                : '&mdash;' ?>
                        </td>
                        <td class="text-right" style="font-weight:700; <?= $pass === true ? 'color:var(--success);' : ($pass === false ? 'color:var(--danger);' : '') ?>">
                            <?php if ($pending): ?>
                                <span class="text-sm" style="color:var(--warning); font-weight:600;">Being marked</span>
                            <?php else: ?>
                                <?= $pct !== null ? rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%' : '&mdash;' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted">
                            <?= $a['submitted_at'] ? date('j M Y', strtotime($a['submitted_at'])) : '&mdash;' ?>
                        </td>
                        <td class="text-right">
                            <a href="<?= BASE_URL ?>/my/quizzes/<?= (int) $a['id'] ?>/result" class="btn btn--ghost btn--sm">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
