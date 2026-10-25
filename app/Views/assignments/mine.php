<?php
/** A student's homework across their classes. */
$assignments = $assignments ?? [];
$now = $now ?? date('Y-m-d H:i:s');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$todo = array_values(array_filter($assignments, static fn (array $a): bool => $a['submitted_at'] === null));
$done = array_values(array_filter($assignments, static fn (array $a): bool => $a['submitted_at'] !== null));
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('My assignments') ?></h1>
        <p class="text-sm text-muted"><?= te('{n} to do', ['n' => count($todo)]) ?> &middot; <?= te('{n} handed in', ['n' => count($done)]) ?></p>
    </div>
</div>

<?php if ($assignments === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127881;</div>
        <p class="text-muted" style="margin:.5rem 0 0;"><?= te('No homework right now. Enjoy the break!') ?></p>
    </div></div>
<?php endif; ?>

<?php foreach ([[t('To do'), $todo], [t('Handed in'), $done]] as [$heading, $list]): if ($list === []) { continue; } ?>
<h3 style="margin:1.25rem 0 .6rem; font-size:1rem;"><?= $e($heading) ?></h3>
<div style="display:grid; gap:.75rem;">
    <?php foreach ($list as $a):
        $overdue = $a['submitted_at'] === null && $a['due_at'] < $now; ?>
        <a href="<?= BASE_URL ?>/my/assignments/<?= (int) $a['id'] ?>" class="card" style="display:block; padding:1rem 1.15rem; text-decoration:none; color:inherit; <?= $overdue ? 'border-color:var(--danger);' : '' ?>">
            <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700;"><?= $e($a['title']) ?></div>
                    <div class="text-sm text-muted"><?= $e($a['class_name']) ?> &middot; <?= te('Due {when}', ['when' => lang_date('D j M, g:i a', (string) $a['due_at'])]) ?></div>
                </div>
                <div class="text-sm" style="text-align:right;">
                    <?php if ($a['locked']): ?>
                        <span style="color:var(--warning);">&#128274; <?= te('Locked until the fee is settled') ?></span>
                    <?php elseif ($a['marks'] !== null): ?>
                        <strong style="font-size:1.1rem; color:var(--success);"><?= $num($a['marks']) ?> / <?= $num($a['total_marks']) ?></strong>
                    <?php elseif ($a['submitted_at'] !== null): ?>
                        <span style="color:var(--primary);">&#10003; <?= te('Waiting for marks') ?></span>
                    <?php elseif ($overdue): ?>
                        <span style="color:var(--danger); font-weight:700;"><?= te('Overdue - hand it in now') ?></span>
                    <?php else: ?>
                        <span class="btn btn--primary btn--sm"><?= te('Start') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
