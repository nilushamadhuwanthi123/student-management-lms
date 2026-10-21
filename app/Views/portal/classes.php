<?php
/**
 * The student's own classes.
 *
 * A locked class is shown, not hidden. Hiding it would leave a student
 * who has not paid wondering whether they were removed from the class
 * altogether; showing it locked, with the reason and the amount, tells
 * them exactly what to do about it.
 */

$classes = $classes ?? [];
$months  = $months ?? [];
$month   = $month ?? date('Y-m-01');

$openCount = count(array_filter($classes, static fn (array $c): bool => $c['is_open']));
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">My classes</h1>
        <p class="text-sm text-muted">
            <?= $openCount ?> of <?= count($classes) ?> open for <?= date('F Y', strtotime($month)) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <form method="GET" action="<?= BASE_URL ?>/my/classes" style="display:flex; gap:.5rem; align-items:center;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--ghost btn--sm">Go</button></noscript>
        </form>
        <a href="<?= BASE_URL ?>/my/progress" class="btn btn--ghost">My progress</a>
    </div>
</div>

<?php if ($classes === []): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127979;</div>
        <h4 style="margin:.75rem 0 .35rem;">You are not enrolled in any class yet</h4>
        <p class="text-sm text-muted" style="margin:0;">Speak to the office to be added to a class.</p>
    </div>
<?php else: ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1.25rem;">
        <?php foreach ($classes as $c):
            $open = (bool) $c['is_open'];
            $gate = $c['gate'];
            $days = !empty($c['schedule_days']) ? (json_decode($c['schedule_days'], true) ?: []) : [];
        ?>
            <div class="card" style="display:flex; flex-direction:column; <?= $open ? '' : 'border-color:var(--warning);' ?>">
                <div class="card__body" style="flex:1;">
                    <div style="display:flex; justify-content:space-between; gap:.75rem; align-items:flex-start;">
                        <div>
                            <h3 style="margin:0 0 .25rem; font-size:1.05rem;">
                                <?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <div class="text-sm text-muted">
                                <?= htmlspecialchars($c['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($c['institute_name'])): ?>
                                    &middot; <?= htmlspecialchars($c['institute_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge" style="white-space:nowrap; background:<?= $open ? 'var(--success-light); color:var(--success)' : 'var(--warning-light); color:var(--warning)' ?>;">
                            <?= $open ? 'Open' : 'Locked' ?>
                        </span>
                    </div>

                    <?php if ($days !== [] || !empty($c['start_time'])): ?>
                        <div class="text-sm text-muted" style="margin-top:.75rem;">
                            <?= htmlspecialchars(implode(', ', $days), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($c['start_time'])): ?>
                                &middot; <?= date('g:i a', strtotime($c['start_time'])) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($c['teacher_name'])): ?>
                        <div class="text-sm text-muted">
                            with <?= htmlspecialchars($c['teacher_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$open): ?>
                        <div style="margin-top:1rem; padding:.75rem .875rem; background:var(--warning-light); border-radius:var(--radius-sm); border-left:3px solid var(--warning);">
                            <div class="text-sm" style="color:var(--warning); font-weight:600;">
                                <?= htmlspecialchars($gate['message'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if ($gate['amount_due'] !== null && $gate['amount_due'] > 0): ?>
                                <div class="text-sm text-muted" style="margin-top:.25rem;">
                                    Rs. <?= number_format((float) $gate['amount_due'], 2) ?> due
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($gate['reason'] === 'free_card'): ?>
                        <div class="text-sm" style="margin-top:1rem; color:var(--success);">
                            Open on a free card
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card__footer">
                    <a href="<?= BASE_URL ?>/my/classes/<?= (int) $c['id'] ?>?month=<?= urlencode($month) ?>"
                       class="btn <?= $open ? 'btn--primary' : 'btn--ghost' ?>" style="width:100%;">
                        <?= $open ? 'Open class' : 'See what is locked' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
