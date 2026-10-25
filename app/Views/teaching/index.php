<?php
/**
 * The teaching hub.
 *
 * Two numbers are deliberately pulled to the front: papers waiting to
 * be marked, and students who have not paid. Both are things that
 * quietly rot if nobody looks, and neither is visible from anywhere
 * else without opening each class in turn.
 */

$byBranch = $byBranch ?? [];
$totals   = $totals ?? [];
$months   = $months ?? [];
$month    = $month ?? date('Y-m-01');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Teaching</h1>
        <p class="text-sm text-muted">
            <?= (int) ($totals['classes'] ?? 0) ?> classes
            &middot; <?= (int) ($totals['students'] ?? 0) ?> students
            &middot; <?= date('F Y', strtotime($month)) ?>
        </p>
    </div>
    <div class="page-header__actions">
        <form method="GET" action="<?= BASE_URL ?>/teaching" style="display:flex; gap:.5rem;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--ghost btn--sm">Go</button></noscript>
        </form>
    </div>
</div>

<!-- The two things that need attention come first -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-bottom:1.75rem;">
    <div class="card" style="padding:1.1rem; <?= (int) ($totals['marking'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted">Papers to mark</div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:<?= (int) ($totals['marking'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text)' ?>;">
            <?= (int) ($totals['marking'] ?? 0) ?>
        </div>
    </div>
    <div class="card" style="padding:1.1rem; <?= (int) ($totals['unpaid'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted">Unpaid this month</div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:<?= (int) ($totals['unpaid'] ?? 0) > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
            <?= (int) ($totals['unpaid'] ?? 0) ?>
        </div>
        <div class="text-sm text-muted" style="margin-top:.15rem;">locked out of content</div>
    </div>
    <div class="card" style="padding:1.1rem;">
        <div class="text-sm text-muted">Material posted</div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:var(--primary);">
            <?= (int) ($totals['materials'] ?? 0) ?>
        </div>
    </div>
    <div class="card" style="padding:1.1rem;">
        <div class="text-sm text-muted">Quizzes live</div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:var(--primary);">
            <?= (int) ($totals['quizzes'] ?? 0) ?>
        </div>
    </div>
</div>

<?php if ($byBranch === []): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127979;</div>
        <h4 style="margin:.75rem 0 .35rem;">No classes assigned yet</h4>
        <p class="text-sm text-muted" style="margin:0;">Classes you teach will appear here.</p>
    </div>
<?php else: ?>
    <?php foreach ($byBranch as $branch => $classes): ?>
        <h3 style="margin:1.75rem 0 .875rem; font-size:1.05rem; color:var(--text-secondary);">
            <?= htmlspecialchars($branch, ENT_QUOTES, 'UTF-8') ?>
            <span class="text-sm text-muted">&middot; <?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?></span>
        </h3>

        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:1.1rem;">
            <?php foreach ($classes as $c):
                $days = !empty($c['schedule_days']) ? (json_decode($c['schedule_days'], true) ?: []) : [];
            ?>
                <div class="card" style="display:flex; flex-direction:column;">
                    <div class="card__body" style="flex:1;">
                        <div style="display:flex; justify-content:space-between; gap:.75rem; align-items:flex-start;">
                            <div>
                                <h4 style="margin:0 0 .2rem; font-size:1rem;">
                                    <?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h4>
                                <div class="text-sm text-muted">
                                    <?= (int) $c['students'] ?> students
                                    <?php if ($days !== []): ?>
                                        &middot; <?= htmlspecialchars(implode(', ', $days), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ((int) $c['awaiting_marking'] > 0): ?>
                                <span class="badge" style="background:var(--warning-light); color:var(--warning); white-space:nowrap;">
                                    <?= (int) $c['awaiting_marking'] ?> to mark
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; gap:1.25rem; margin-top:.9rem;">
                            <div>
                                <div style="font-size:1.2rem; font-weight:700; color:var(--primary);">
                                    <?= (int) $c['materials_this_month'] ?>
                                </div>
                                <div class="text-sm text-muted">material</div>
                            </div>
                            <div>
                                <div style="font-size:1.2rem; font-weight:700; color:var(--primary);">
                                    <?= (int) $c['quizzes_this_month'] ?>
                                </div>
                                <div class="text-sm text-muted">quizzes</div>
                            </div>
                            <?php if ((int) $c['unpaid_this_month'] > 0): ?>
                                <div>
                                    <div style="font-size:1.2rem; font-weight:700; color:var(--warning);">
                                        <?= (int) $c['unpaid_this_month'] ?>
                                    </div>
                                    <div class="text-sm text-muted">unpaid</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card__footer" style="display:flex; gap:.5rem; flex-wrap:wrap;">
                        <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/materials?month=<?= urlencode($month) ?>"
                           class="btn btn--ghost btn--sm" style="flex:1;">Material</a>
                        <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/quizzes"
                           class="btn btn--primary btn--sm" style="flex:1;">Quizzes</a>
                        <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/assignments"
                           class="btn btn--ghost btn--sm" style="flex:1;"><?= te('Assignments') ?></a>
                        <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/learning-gaps"
                           class="btn btn--ghost btn--sm" style="flex:1;"><?= te('Learning gaps') ?></a>
                        <a href="<?= BASE_URL ?>/attendance/register?class_id=<?= (int) $c['id'] ?>"
                           class="btn btn--ghost btn--sm" style="flex:1;"><?= te('Register') ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
