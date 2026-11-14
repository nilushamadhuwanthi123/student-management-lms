<?php
/**
 * Results for one quiz.
 *
 * Everyone enrolled is listed, not just everyone who sat it. The
 * students who did not turn up are the ones a teacher most needs to
 * see, and they are invisible on a list built from attempts alone.
 */

$class = $class ?? [];
$quiz  = $quiz ?? [];
$rows  = $rows ?? [];
$stats = $stats ?? [];

$classId = (int) ($class['id'] ?? 0);
$passMark = $quiz['pass_mark'] !== null ? (float) $quiz['pass_mark'] : null;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Results') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>
            &middot; <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= $classId ?>/quizzes/<?= (int) $quiz['id'] ?>" class="btn btn--ghost">
            &larr; <?= te('Questions') ?>
        </a>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <?php
    $cards = [
        [te('Sat it'), $stats['sat'] . ' / ' . $stats['enrolled'], 'var(--primary)'],
        [te('Average'), $stats['average'] !== null ? $stats['average'] . '%' : '&mdash;', 'var(--text)'],
        [te('Highest'), $stats['highest'] !== null ? $stats['highest'] . '%' : '&mdash;', 'var(--success)'],
        [te('Lowest'), $stats['lowest'] !== null ? $stats['lowest'] . '%' : '&mdash;', 'var(--danger)'],
    ];

    if ($stats['passed'] !== null) {
        $cards[] = [te('Passed'), $stats['passed'] . ' / ' . $stats['sat'], 'var(--success)'];
    }

    foreach ($cards as [$label, $value, $colour]): ?>
        <div class="card" style="padding:1rem;">
            <div class="text-sm text-muted"><?= $label ?></div>
            <div style="font-size:1.6rem; font-weight:700; color:<?= $colour ?>; margin-top:.25rem;">
                <?= $value ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card__header"><h3 style="margin:0;"><?= te('Every student in the class') ?></h3></div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= te('Student') ?></th>
                    <th><?= te('Reg. no.') ?></th>
                    <th class="text-right"><?= te('Score') ?></th>
                    <th class="text-right"><?= te('Percentage') ?></th>
                    <th><?= te('Submitted') ?></th>
                    <th><?= te('Status') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $sat = $r['attempt_id'] !== null;
                $pct = $sat ? (float) $r['percentage'] : null;
                $passed = $passMark !== null && $pct !== null ? $pct >= $passMark : null;
            ?>
                <tr<?= $sat ? '' : ' style="background:var(--warning-light);"' ?>>
                    <td style="font-weight:600;">
                        <?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= htmlspecialchars($r['student_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="text-right">
                        <?php if ($sat): ?>
                            <?= rtrim(rtrim(number_format((float) $r['score'], 2), '0'), '.') ?>
                            / <?= rtrim(rtrim(number_format((float) $r['total_marks'], 2), '0'), '.') ?>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td class="text-right" style="font-weight:600; <?= $passed === false ? 'color:var(--danger);' : ($passed === true ? 'color:var(--success);' : '') ?>">
                        <?= $pct !== null ? $pct . '%' : '&mdash;' ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= $sat && $r['submitted_at'] ? date('j M, g:i a', strtotime($r['submitted_at'])) : '&mdash;' ?>
                    </td>
                    <td>
                        <?php if (!$sat): ?>
                            <span class="badge" style="background:var(--warning-light); color:var(--warning);">
                                <?= te('Did not sit') ?>
                            </span>
                        <?php elseif ((int) $r['needs_marking'] === 1): ?>
                            <span class="badge" style="background:var(--warning-light); color:var(--warning);">
                                <?= te('Waiting on you') ?>
                            </span>
                        <?php elseif ($r['attempt_status'] === 'auto_submitted'): ?>
                            <span class="badge" style="background:var(--surface-sunken); color:var(--text-secondary);">
                                <?= te('Time ran out') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge" style="background:var(--success-light); color:var(--success);">
                                <?= te('Marked') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
