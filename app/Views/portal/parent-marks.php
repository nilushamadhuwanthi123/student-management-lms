<?php
$student  = $student ?? [];
$attempts = $attempts ?? [];
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Marks') ?></h1>
        <p class="text-sm text-muted"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/family" class="btn btn--ghost">&larr; <?= te('Overview') ?></a>
    </div>
</div>

<div class="card">
    <?php if ($attempts === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No quizzes sat yet.') ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th><?= te('Quiz') ?></th><th><?= te('Class') ?></th><th class="text-right"><?= te('Score') ?></th><th class="text-right"><?= te('Percentage') ?></th><th><?= te('Date') ?></th></tr>
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
                            <?= $pending ? '<span class="text-sm" style="color:var(--warning);">' . te('Being marked') . '</span>'
                                : ($pct !== null ? rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%' : '&mdash;') ?>
                        </td>
                        <td class="text-sm text-muted"><?= $a['submitted_at'] ? htmlspecialchars(lang_date('j M Y', (string) $a['submitted_at']), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
