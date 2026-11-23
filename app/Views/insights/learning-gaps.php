<?php
/**
 * Learning gaps for one class.
 * Variables: $class, $topicRows, $studentRows, $missed, $quizGaps, $homeworkGaps, $hasTopics, $weakBelow
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pct = static fn (?float $p): string => $p === null ? '&ndash;' : number_format($p, 0) . '%';
$tone = static fn (?float $p): string => $p === null ? 'var(--text-muted)' : ($p < 50 ? 'var(--danger)' : ($p < 70 ? 'var(--warning)' : 'var(--success)'));
$cid = (int) $class['id'];
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Learning gaps') ?> &middot; <?= $h($class['class_name']) ?></h1>
        <p class="text-sm text-muted"><?= te('From marked quizzes (latest attempt) and marked homework. Unanswered questions count as zero; answers waiting to be marked are left out.') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes/<?= $cid ?>/quizzes" class="btn btn--ghost"><?= te('Quizzes') ?></a>
        <?php if (can('assignments.view')): ?>
        <a href="<?= BASE_URL ?>/classes/<?= $cid ?>/assignments" class="btn btn--ghost"><?= te('Homework') ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$hasTopics): ?>
    <div class="alert alert--info"><?= te('No topics for this grade yet, so everything shows as "not tagged".') ?>
        <?php if (can('topics.manage')): ?><a href="<?= BASE_URL ?>/topics"><?= te('Add the units') ?></a><?php endif; ?></div>
<?php endif; ?>

<section class="card mb-3" aria-labelledby="by-topic"><div class="card__body" style="overflow-x:auto;">
    <h2 id="by-topic" style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('By topic (hardest first)') ?></h2>
    <?php if ($topicRows === []): ?>
        <p class="text-muted" style="margin:0;"><?= te('No marked work yet for this class.') ?></p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th><?= te('Topic (unit)') ?></th><th><?= te('Class average') ?></th><th><?= te('Students assessed') ?></th><th><?= te('Below {n}%', ['n' => (int) $weakBelow]) ?></th></tr></thead>
        <tbody>
        <?php foreach ($topicRows as $r): ?>
            <tr>
                <td><?= $r['untagged'] ? '<em>' . $h($r['label']) . '</em>' : $h($r['label']) ?></td>
                <td><strong style="color:<?= $tone($r['pct']) ?>;"><?= $pct($r['pct']) ?></strong>
                    <div style="height:6px; background:var(--border); border-radius:3px; margin-top:4px; max-width:160px;"><div style="height:6px; width:<?= max(0, min(100, (int) round($r['pct']))) ?>%; background:<?= $tone($r['pct']) ?>; border-radius:3px;"></div></div></td>
                <td><?= (int) $r['assessed'] ?></td>
                <td><?php if ($r['weak'] === []): ?>&ndash;<?php else: ?>
                    <?= implode(', ', array_map(static fn ($w) => $h($w['name']) . ' (' . number_format($w['pct'], 0) . '%)', array_slice($r['weak'], 0, 8))) ?><?= count($r['weak']) > 8 ? ' ' . te('and {n} more', ['n' => count($r['weak']) - 8]) : '' ?>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div></section>

<section class="card mb-3" aria-labelledby="by-student"><div class="card__body" style="overflow-x:auto;">
    <h2 id="by-student" style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('By student (lowest first)') ?></h2>
    <table class="table">
        <thead><tr><th><?= te('Student') ?></th><th><?= te('Overall') ?></th><th><?= te('Weakest topic') ?></th></tr></thead>
        <tbody>
        <?php foreach ($studentRows as $s): ?>
            <tr>
                <td><a href="<?= BASE_URL ?>/students/<?= (int) $s['id'] ?>"><?= $h($s['name']) ?></a> <span class="text-xs text-muted"><?= $h($s['code']) ?></span></td>
                <td style="color:<?= $tone($s['pct']) ?>;"><strong><?= $pct($s['pct']) ?></strong></td>
                <td><?= $s['weakest'] ? $h($s['weakest']['label']) . ' (' . number_format($s['weakest']['pct'], 0) . '%)' : '&ndash;' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></section>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1rem;">
<section class="card" aria-labelledby="missed" style="min-width:0;"><div class="card__body">
    <h2 id="missed" style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('Questions most often wrong') ?></h2>
    <?php if ($missed === []): ?><p class="text-muted" style="margin:0;"><?= te('Needs at least three marked answers per question.') ?></p><?php else: ?>
    <ol style="margin:0; padding-left:1.25rem;">
        <?php foreach ($missed as $m): $rate = (int) $m['answered'] > 0 ? (int) $m['full_marks'] / (int) $m['answered'] * 100 : 0; ?>
            <li style="margin-bottom:.5rem;"><a href="<?= BASE_URL ?>/classes/<?= $cid ?>/quizzes/<?= (int) $m['quiz_id'] ?>"><?= $h($m['quiz_title']) ?>, Q<?= (int) $m['question_number'] ?></a>:
                <?= $h(mb_strimwidth((string) $m['question_text'], 0, 90, '…')) ?><br>
                <span class="text-xs" style="color:<?= $tone($rate) ?>;"><?= te('{a} of {b} got full marks', ['a' => (int) $m['full_marks'], 'b' => (int) $m['answered']]) ?></span></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</div></section>

<section class="card" aria-labelledby="not-done" style="min-width:0;"><div class="card__body">
    <h2 id="not-done" style="font-size:1.05rem; margin:0 0 .75rem;"><?= te('Work not done') ?></h2>
    <?php if ($quizGaps === [] && $homeworkGaps === []): ?><p class="text-muted" style="margin:0;"><?= te('Nothing past its deadline yet.') ?></p><?php endif; ?>
    <ul style="margin:0; padding-left:1.25rem;">
        <?php foreach ($quizGaps as $q): ?><li><?= te('Quiz') ?>: <a href="<?= BASE_URL ?>/classes/<?= $cid ?>/quizzes/<?= (int) $q['id'] ?>/results"><?= $h($q['title']) ?></a> &mdash; <?= te('{n} did not sit it', ['n' => (int) $q['missing']]) ?></li><?php endforeach; ?>
        <?php foreach ($homeworkGaps as $a): ?><li><?= te('Homework') ?>: <a href="<?= BASE_URL ?>/classes/<?= $cid ?>/assignments/<?= (int) $a['id'] ?>"><?= $h($a['title']) ?></a> &mdash; <?= te('{n} did not hand it in', ['n' => (int) $a['missing']]) ?></li><?php endforeach; ?>
    </ul>
</div></section>
</div>
