<?php
/** Staff help desk: questions and support requests in scope. */
$threads = $threads ?? [];
$status = $status ?? null;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$open = count(array_filter($threads, static fn (array $t): bool => in_array($t['status'], ['open', 'in_progress'], true)));
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Help desk') ?></h1>
        <p class="text-sm text-muted"><?= te('{n} waiting for a reply', ['n' => $open]) ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/help" class="btn btn--<?= $status === null ? 'primary' : 'ghost' ?> btn--sm"><?= te('All') ?></a>
        <?php foreach (['open', 'in_progress', 'answered', 'resolved'] as $s): ?>
            <a href="<?= BASE_URL ?>/help?status=<?= $s ?>" class="btn btn--<?= $status === $s ? 'primary' : 'ghost' ?> btn--sm"><?= te(ucfirst(str_replace('_', ' ', $s))) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div class="card">
    <?php if ($threads === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No conversations here.') ?></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th><?= te('Subject') ?></th><th><?= te('Type') ?></th><th><?= te('Student') ?></th><th><?= te('Class / branch') ?></th><th><?= te('Last activity') ?></th><th><?= te('Status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($threads as $t): ?>
            <tr>
                <td style="font-weight:600;"><a href="<?= BASE_URL ?>/help/<?= (int) $t['id'] ?>"><?= $e($t['subject']) ?></a></td>
                <td class="text-sm"><?= $t['kind'] === 'question' ? te('Question') : te('Support') ?><?= !empty($t['parent_id']) ? ' &middot; ' . te('Parent') : '' ?></td>
                <td class="text-sm"><?= $e($t['student_name']) ?><br><span class="text-muted"><?= $e($t['student_id_display']) ?></span></td>
                <td class="text-sm"><?= $e($t['class_name'] ?? $t['institute_name'] ?? '') ?></td>
                <td class="text-sm"><?= $e(lang_date('j M, g:i a', (string) ($t['last_reply_at'] ?? $t['created_at']))) ?></td>
                <td><span class="badge" style="background:var(--surface-sunken);"><?= te(ucfirst(str_replace('_', ' ', (string) $t['status']))) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
