<?php
/**
 * The student's and the parent's calendar.
 *
 * One view for both, because the thing being answered is the same --
 * when is the class, when is the test, when is the quiz due. Only the
 * heading and the lock markers differ, and both come from the data the
 * controller passed rather than from a role check in here.
 *
 * Nothing is hidden because of an unpaid fee. The timetable is not the
 * paid content; the padlock says the material behind a session is shut,
 * and the family can see exactly what they are missing and why.
 */

$byDate    = $byDate ?? [];
$upcoming  = $upcoming ?? [];
$openMap   = $openMap ?? [];
$month     = $month ?? date('Y-m');
$childName = $childName ?? null;

$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$base = $childName !== null ? '/family/calendar' : '/my/calendar';
$anyLocked = in_array(false, $openMap, true);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            <?= $childName !== null
                ? te("{name}'s calendar", ['name' => $childName])
                : te('My Calendar') ?>
        </h1>
        <p class="text-sm text-muted">
            <?= te('Classes, quizzes, tests, homework and events') ?> &middot; <?= htmlspecialchars(lang_date('F Y', $month . '-01'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div class="page-header__actions" style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <a href="<?= BASE_URL . $base ?>?month=<?= $prev ?>" class="btn btn--ghost">&larr;</a>
        <a href="<?= BASE_URL . $base ?>?month=<?= date('Y-m') ?>" class="btn btn--ghost"><?= te('Today') ?></a>
        <a href="<?= BASE_URL . $base ?>?month=<?= $next ?>" class="btn btn--ghost">&rarr;</a>
    </div>
</div>

<?php if ($anyLocked): ?>
    <div class="alert alert--warning" style="margin-bottom:1.25rem;">
        <strong><?= te('Some material is locked.') ?></strong>
        <?= te("The classes marked with a padlock are still on, and the times here are right — but this month's fee for them is not settled yet, so the recordings, notes and quizzes stay shut until it is.") ?>
    </div>
<?php endif; ?>

<div class="cal-layout">
    <div>
        <?php require __DIR__ . '/_grid.php'; ?>
    </div>

    <div class="card" style="padding:1.15rem;">
        <h3 style="margin:0 0 .25rem; font-size:1rem;"><?= te('Next up') ?></h3>
        <p class="text-sm text-muted" style="margin:0 0 .75rem;"><?= te('The next 30 days.') ?></p>

        <?php if ($upcoming === []): ?>
            <p class="text-sm text-muted"><?= te('Nothing scheduled.') ?></p>
        <?php else: ?>
            <?php foreach ($upcoming as $item):
                $locked = $item['class_id'] !== null
                    && $openMap !== []
                    && ($openMap[$item['class_id']] ?? false) === false;
                ?>
                <div style="padding:.55rem 0; border-top:1px solid var(--border);">
                    <div class="text-sm" style="font-weight:600;">
                        <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($locked): ?><span title="<?= te('Material locked until the fee is settled') ?>">&#128274;</span><?php endif; ?>
                    </div>
                    <div class="text-sm text-muted">
                        <?= te((string) $item['detail']) ?>
                        &middot; <?= htmlspecialchars(lang_date('D j M', (string) $item['date']), ENT_QUOTES, 'UTF-8') ?>
                        <?= $item['start_time'] !== null
                            ? ' ' . te('at {time}', ['time' => $item['start_time']])
                            : '' ?>
                    </div>
                    <?php if (!empty($item['syllabus'])): ?>
                        <div class="text-sm text-muted"><?= te('Covers: {what}', ['what' => $item['syllabus']]) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
