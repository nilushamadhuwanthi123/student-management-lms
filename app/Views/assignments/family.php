<?php
/** A parent's read-only view of their child's homework. */
$assignments = $assignments ?? [];
$now = $now ?? date('Y-m-d H:i:s');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Assignments') ?></h1>
        <p class="text-sm text-muted"><?= $e($childName ?? '') ?></p>
    </div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/family" class="btn btn--ghost">&larr; <?= te('Overview') ?></a></div>
</div>
<div class="card">
    <?php if ($assignments === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No homework has been set yet.') ?></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th><?= te('Assignment') ?></th><th><?= te('Class') ?></th><th><?= te('Due') ?></th><th><?= te('Status') ?></th><th class="text-right"><?= te('Marks') ?></th><th><?= te('Feedback') ?></th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a):
            $status = $a['marks'] !== null ? t('Marked') : ($a['submitted_at'] !== null ? t('Handed in') : ($a['due_at'] < $now ? t('Not handed in') : t('To do')));
            $tone = $a['marks'] !== null ? 'var(--success)' : ($a['submitted_at'] !== null ? 'var(--primary)' : ($a['due_at'] < $now ? 'var(--danger)' : 'var(--text-muted)')); ?>
            <tr>
                <td style="font-weight:600;"><?= $e($a['title']) ?></td>
                <td class="text-sm text-muted"><?= $e($a['class_name']) ?></td>
                <td class="text-sm"><?= $e(lang_date('j M Y', (string) $a['due_at'])) ?></td>
                <td><span style="color:<?= $tone ?>; font-weight:600;"><?= $e($status) ?></span><?= (int) ($a['is_late'] ?? 0) === 1 ? ' <span class="text-xs text-muted">(' . te('Late') . ')</span>' : '' ?></td>
                <td class="text-right"><?= $a['marks'] !== null ? $num($a['marks']) . ' / ' . $num($a['total_marks']) : '&mdash;' ?></td>
                <td class="text-sm"><?= $e($a['feedback'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
