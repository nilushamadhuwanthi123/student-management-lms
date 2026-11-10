<?php
/** Organizer view: who registered/waitlisted for a seminar, and attendance check-in. */
$seminar = $seminar ?? [];
$registrants = $registrants ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$registered = array_values(array_filter($registrants, static fn ($r) => $r['status'] === 'registered'));
$waitlisted = array_values(array_filter($registrants, static fn ($r) => $r['status'] === 'waitlisted'));
$typeLabel = static fn (string $t) => match ($t) { 'student' => te('Student'), 'parent' => te('Parent'), default => te('Staff') };
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $e($seminar['title']) ?></h1>
        <p class="text-sm text-muted">
            <?= te('{n} registered', ['n' => count($registered)]) ?>
            <?php if ($waitlisted !== []): ?> &middot; <?= te('{n} waitlisted', ['n' => count($waitlisted)]) ?><?php endif; ?>
            &middot; <?= te('{n} attended', ['n' => count(array_filter($registrants, static fn ($r) => $r['attended']))]) ?>
        </p>
    </div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/seminars/<?= (int) $seminar['id'] ?>" class="btn btn--ghost">&larr; <?= te('Back to seminar') ?></a></div>
</div>

<?php if ($registrants === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No one has registered yet.') ?></div></div>
<?php endif; ?>

<?php foreach (['registered' => $registered, 'waitlisted' => $waitlisted] as $group => $rows): if ($rows === []) { continue; } ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card__header"><strong><?= $group === 'registered' ? te('Registered') : te('Waiting list') ?></strong></div>
    <div style="overflow-x:auto;">
    <table class="table" style="width:100%;">
        <thead><tr>
            <th><?= te('Name') ?></th>
            <th><?= te('Type') ?></th>
            <th><?= te('Registered at') ?></th>
            <?php if ($group === 'registered'): ?><th><?= te('Attended') ?></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $e($r['name']) ?><?php if ($r['detail'] !== ''): ?> <span class="text-xs text-muted">(<?= $e($r['detail']) ?>)</span><?php endif; ?></td>
                <td><?= $typeLabel($r['user_type']) ?></td>
                <td class="text-sm text-muted"><?= $e(lang_date('j M Y, g:i A', $r['registered_at'])) ?></td>
                <?php if ($group === 'registered'): ?>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/seminars/<?= (int) $seminar['id'] ?>/attendance" style="margin:0;">
                        <?= csrfField() ?>
                        <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="attended" value="<?= $r['attended'] ? '0' : '1' ?>">
                        <button type="submit" class="btn btn--sm <?= $r['attended'] ? 'btn--primary' : 'btn--ghost' ?>">
                            <?= $r['attended'] ? '✓ ' . te('Attended') : te('Mark attended') ?>
                        </button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endforeach; ?>
