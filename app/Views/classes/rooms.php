<?php
/**
 * Hall / Room arrangement board.
 * Groups every active class in the selected branch by the room it uses,
 * so an admin can see which halls are free and which are already
 * booked, and is warned immediately if two classes in the same branch
 * share a room at an overlapping time.
 */
$institutes  = $institutes ?? [];
$instituteId = $instituteId ?? null;
$rooms       = $rooms ?? [];
$conflicts   = $conflicts ?? [];
$unassigned  = $unassigned ?? [];

$dayOrder = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
$dayShort = static function (array $days) use ($dayOrder): string {
    $sorted = $days;
    usort($sorted, static fn ($a, $b) => ($dayOrder[$a] ?? 9) <=> ($dayOrder[$b] ?? 9));
    return implode(', ', array_map(static fn ($d) => lang_names(substr((string) $d, 0, 3)), $sorted));
};
$timeFmt = static function (?string $t): string {
    if (!$t) {
        return '';
    }
    return lang_names(date('g:i A', strtotime($t)));
};
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Hall Arrangement') ?></h1>
        <p class="text-sm text-muted"><?= te('See which halls are booked and when, for one branch at a time') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes" class="btn btn--secondary"><?= te('Back to Classes') ?></a>
        <a href="<?= BASE_URL ?>/classes/create" class="btn btn--primary">+ <?= te('Create Class') ?></a>
    </div>
</div>

<?php if (count($institutes) > 1): ?>
<div class="card mb-3">
    <div class="table-toolbar">
        <form method="GET" action="<?= BASE_URL ?>/classes/rooms" style="display:flex; gap:0.75rem; align-items:center;">
            <label class="form-label" style="margin:0;"><?= te('Branch') ?>:</label>
            <select name="institute_id" class="form-control form-control--sm" style="width:auto; min-width:220px;" onchange="this.form.submit()">
                <?php foreach ($institutes as $inst): ?>
                    <option value="<?= (int) $inst['id'] ?>" <?= (int) $instituteId === (int) $inst['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($institutes === []): ?>
    <div class="card"><div class="card__body"><p class="text-muted"><?= te('No branch is available to you.') ?></p></div></div>
<?php elseif (!empty($conflicts)): ?>
    <div class="card mb-3" style="border-left:4px solid #DC2626; background:#FEF2F2;">
        <div class="card__body">
            <strong style="color:#991B1B;">&#9888; <?= te('Hall double-booking found') ?></strong>
            <ul style="margin:0.5rem 0 0 1.25rem; color:#991B1B;">
                <?php foreach ($conflicts as $c): ?>
                    <li><?= te('{room}: "{a}" and "{b}" overlap -- please move one of them.', [
                        'room' => htmlspecialchars($c['room'], ENT_QUOTES, 'UTF-8'),
                        'a'    => htmlspecialchars($c['a'], ENT_QUOTES, 'UTF-8'),
                        'b'    => htmlspecialchars($c['b'], ENT_QUOTES, 'UTF-8'),
                    ]) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3" style="border-left:4px solid #16A34A; background:#F0FDF4;">
        <div class="card__body" style="color:#166534;">
            &#10003; <?= te('No hall clashes in this branch. New bookings that would clash are blocked automatically when a class is saved.') ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($rooms !== []): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%,340px), 1fr)); gap:1rem;">
        <?php foreach ($rooms as $roomName => $bookings): ?>
            <?php
            $roomHasConflict = false;
            foreach ($conflicts as $c) {
                if ($c['room'] === $roomName) {
                    $roomHasConflict = true;
                    break;
                }
            }
            ?>
            <div class="card" style="<?= $roomHasConflict ? 'border:2px solid #DC2626;' : '' ?>">
                <div class="card__header" style="display:flex; align-items:center; justify-content:space-between;">
                    <strong><?= htmlspecialchars($roomName, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="badge badge--neutral"><?= count($bookings) ?> <?= te('class(es)') ?></span>
                </div>
                <div class="card__body" style="padding:0;">
                    <?php usort($bookings, static fn ($a, $b) => strcmp((string) $a['start'], (string) $b['start'])); ?>
                    <?php foreach ($bookings as $b): ?>
                        <div style="padding:0.65rem 1rem; border-top:1px solid var(--border-color, #eee);">
                            <div style="font-weight:600;"><?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-sm text-muted">
                                <?= $dayShort($b['days']) ?>
                                <?php if ($b['start'] && $b['end']): ?>
                                    &middot; <?= $timeFmt($b['start']) ?> - <?= $timeFmt($b['end']) ?>
                                <?php endif; ?>
                                <?php if (!empty($b['teacher'])): ?>
                                    &middot; <?= htmlspecialchars($b['teacher'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($b['id'])): ?>
                            <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $b['id'] ?>/update-room" style="display:flex; gap:0.35rem; align-items:center; margin-top:0.4rem;">
                                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                <input type="text" name="room" class="form-control form-control--sm" style="width:120px;" value="<?= htmlspecialchars($roomName, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= te('Hall') ?>">
                                <button type="submit" class="btn btn--secondary btn--sm"><?= te('Move') ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card__footer" style="padding:0.5rem 1rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <a href="<?= BASE_URL ?>/classes?institute_id=<?= (int) $instituteId ?>&search=<?= urlencode($roomName) ?>" class="text-sm">
                        <?= te('View these classes') ?> &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif ($institutes !== []): ?>
    <div class="card"><div class="card__body"><p class="text-muted"><?= te('No class in this branch has a room assigned yet.') ?></p></div></div>
<?php endif; ?>

<?php if (!empty($unassigned)): ?>
    <div class="card mt-3">
        <div class="card__header"><strong><?= te('Classes with no hall assigned yet') ?></strong></div>
        <div class="card__body" style="padding:0;">
            <?php foreach ($unassigned as $b): ?>
                <div style="padding:0.65rem 1rem; border-top:1px solid var(--border-color, #eee); display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-sm text-muted">
                            <?= $dayShort($b['days']) ?>
                            <?php if ($b['start'] && $b['end']): ?>
                                &middot; <?= $timeFmt($b['start']) ?> - <?= $timeFmt($b['end']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($b['id'])): ?>
                    <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $b['id'] ?>/update-room" style="display:flex; gap:0.4rem; align-items:center; margin:0;">
                        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                        <input type="text" name="room" class="form-control form-control--sm" style="width:140px;" placeholder="<?= te('Assign hall') ?>" required>
                        <button type="submit" class="btn btn--secondary btn--sm"><?= te('Assign') ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
