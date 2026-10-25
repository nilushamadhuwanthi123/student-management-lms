<?php
/** One conversation, for the family or for staff. */
$t = $thread ?? [];
$messages = $messages ?? [];
$staff = !empty($staff);
$base = BASE_URL . ($base ?? '/my/help');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$closed = in_array($t['status'], ['closed'], true);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title" style="overflow-wrap:anywhere;"><?= $e($t['subject']) ?></h1>
        <p class="text-sm text-muted">
            <?= $t['kind'] === 'question' ? te('Question to the teacher') . ' &middot; ' . $e($t['class_name'] ?? '') : te('Support request') ?>
            <?php if ($staff): ?> &middot; <?= $e($t['student_name']) ?> (<?= $e($t['student_id_display']) ?>)<?= !empty($t['parent_name']) ? ' &middot; ' . te('from parent {name}', ['name' => $t['parent_name']]) : '' ?><?php endif; ?>
            &middot; <strong><?= te(ucfirst(str_replace('_', ' ', (string) $t['status']))) ?></strong>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= $base ?>" class="btn btn--ghost">&larr; <?= $staff ? te('Help desk') : te('Help & questions') ?></a>
        <?php if (!$staff && !in_array($t['status'], ['resolved', 'closed'], true)): ?>
            <form method="POST" action="<?= $base ?>/<?= (int) $t['id'] ?>/resolve" style="margin:0;"><?= csrfField() ?>
                <button class="btn btn--ghost" type="submit">&#10003; <?= te('My problem is solved') ?></button></form>
        <?php endif; ?>
        <?php if ($staff): ?>
            <form method="POST" action="<?= $base ?>/<?= (int) $t['id'] ?>/status" style="margin:0; display:flex; gap:.4rem;"><?= csrfField() ?>
                <select name="status" class="form-control form-control--sm">
                    <?php foreach ($statuses ?? [] as $s): ?><option value="<?= $e($s) ?>" <?= $s === $t['status'] ? 'selected' : '' ?>><?= te(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn--ghost btn--sm" type="submit"><?= te('Set') ?></button></form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; gap:.75rem; max-width:860px;">
    <?php foreach ($messages as $m):
        $mine = $staff ? $m['author_type'] === 'user' : $m['author_type'] !== 'user'; ?>
        <div class="card" style="padding:.85rem 1rem; <?= $mine ? 'border-left:4px solid var(--primary);' : 'border-left:4px solid var(--accent);' ?>">
            <div class="text-xs text-muted" style="margin-bottom:.35rem;">
                <strong><?= $e($m['author_name'] ?? '') ?></strong>
                (<?= $m['author_type'] === 'user' ? te('Staff') : ($m['author_type'] === 'parent' ? te('Parent') : te('Student')) ?>)
                &middot; <?= $e(lang_date('j M Y, g:i a', (string) $m['created_at'])) ?>
            </div>
            <div style="white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e($m['body']) ?></div>
            <?php if (!empty($m['file_path'])): ?>
                <div class="text-sm" style="margin-top:.4rem;"><a href="<?= BASE_URL ?>/help/messages/<?= (int) $m['id'] ?>/file">&#128206; <?= $e($m['original_name'] ?: t('Attached file')) ?></a></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$closed): ?>
    <form method="POST" action="<?= $base ?>/<?= (int) $t['id'] ?>/reply" enctype="multipart/form-data" class="card" style="padding:1rem; display:grid; gap:.6rem;">
        <?= csrfField() ?>
        <textarea name="body" class="form-control" rows="4" required placeholder="<?= te('Write a reply...') ?>"></textarea>
        <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
            <input type="file" name="attachment" class="form-control" accept=".pdf,.png,.jpg,.jpeg" style="max-width:320px;">
            <button type="submit" class="btn btn--primary"><?= te('Send reply') ?></button>
        </div>
    </form>
    <?php else: ?>
        <p class="text-sm text-muted"><?= te('This conversation is closed. Start a new one if you need more help.') ?></p>
    <?php endif; ?>
</div>
