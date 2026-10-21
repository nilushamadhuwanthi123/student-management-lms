<?php
/**
 * Branch insights for the owner.
 *
 * Collection rate leads rather than revenue. A branch that bills well
 * and collects badly reads as healthy on a revenue line, and it is the
 * gap that costs money.
 */

$branches    = $branches ?? [];
$totals      = $totals ?? [];
$trend       = $trend ?? [];
$classes     = $classes ?? [];
$strugglers  = $strugglers ?? [];
$topStudents = $topStudents ?? [];
$months      = $months ?? [];
$month       = $month ?? date('Y-m-01');

$rateColour = static fn (?float $r): string => match (true) {
    $r === null => 'var(--text-muted)',
    $r >= 85    => 'var(--success)',
    $r >= 60    => 'var(--warning)',
    default     => 'var(--danger)',
};

$maxBilled = 0.0;
foreach ($trend as $t) { $maxBilled = max($maxBilled, (float) $t['billed']); }
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Branch insights</h1>
        <p class="text-sm text-muted">Money and marks across every branch &middot; <?= date('F Y', strtotime($month)) ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost">All reports</a>
        <form method="GET" action="<?= BASE_URL ?>/reports/insights" style="display:inline-flex; gap:.5rem;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- The month in four numbers -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.75rem;">
    <div class="card" style="padding:1.15rem;">
        <div class="text-sm text-muted">Billed</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.2rem;">
            Rs. <?= number_format((float) ($totals['billed'] ?? 0), 2) ?>
        </div>
        <div class="text-sm text-muted"><?= (int) ($totals['invoices'] ?? 0) ?> invoices</div>
    </div>
    <div class="card" style="padding:1.15rem;">
        <div class="text-sm text-muted">Collected</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.2rem; color:var(--success);">
            Rs. <?= number_format((float) ($totals['collected'] ?? 0), 2) ?>
        </div>
    </div>
    <div class="card" style="padding:1.15rem; <?= (float) ($totals['outstanding'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted">Outstanding</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.2rem; color:var(--warning);">
            Rs. <?= number_format((float) ($totals['outstanding'] ?? 0), 2) ?>
        </div>
        <div class="text-sm text-muted"><?= (int) ($totals['unpaid'] ?? 0) ?> unpaid</div>
    </div>
    <div class="card" style="padding:1.15rem;">
        <div class="text-sm text-muted">Collection rate</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.2rem; color:<?= $rateColour($totals['rate'] ?? null) ?>;">
            <?= $totals['rate'] !== null ? $totals['rate'] . '%' : '&mdash;' ?>
        </div>
    </div>
</div>

<!-- Billed against collected, six months -->
<?php if ($trend !== []): ?>
<div class="card" style="margin-bottom:1.75rem;">
    <div class="card__header">
        <h3 style="margin:0;">Billed against collected</h3>
        <p class="text-sm text-muted" style="margin:.25rem 0 0;">
            The gap between the two bars is the money that was invoiced and never came in.
        </p>
    </div>
    <div class="card__body">
        <div style="display:flex; align-items:flex-end; gap:1.25rem; height:190px; padding-top:.5rem;">
            <?php foreach ($trend as $t):
                $billed = (float) $t['billed'];
                $collected = (float) $t['collected'];
                $bh = $maxBilled > 0 ? max(4, ($billed / $maxBilled) * 150) : 4;
                $ch = $maxBilled > 0 ? max(4, ($collected / $maxBilled) * 150) : 4;
                $rate = $billed > 0 ? round(($collected / $billed) * 100) : 0;
            ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:.4rem;">
                    <div style="display:flex; gap:4px; align-items:flex-end; height:155px;"
                         title="Billed Rs. <?= number_format($billed, 2) ?>, collected Rs. <?= number_format($collected, 2) ?>">
                        <div style="width:18px; height:<?= $bh ?>px; background:var(--border-strong); border-radius:3px 3px 0 0;"></div>
                        <div style="width:18px; height:<?= $ch ?>px; background:var(--primary); border-radius:3px 3px 0 0;"></div>
                    </div>
                    <div class="text-sm text-muted" style="white-space:nowrap;">
                        <?= date('M', strtotime($t['month_year'])) ?>
                    </div>
                    <div class="text-sm" style="font-weight:600; color:<?= $rateColour((float) $rate) ?>;"><?= $rate ?>%</div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex; gap:1.25rem; margin-top:1rem;" class="text-sm text-muted">
            <span><span style="display:inline-block; width:10px; height:10px; background:var(--border-strong); border-radius:2px;"></span> Billed</span>
            <span><span style="display:inline-block; width:10px; height:10px; background:var(--primary); border-radius:2px;"></span> Collected</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Branch by branch -->
<div class="card" style="margin-bottom:1.75rem;">
    <div class="card__header"><h3 style="margin:0;">By branch</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th class="text-right">Classes</th>
                    <th class="text-right">Students</th>
                    <th class="text-right">Billed</th>
                    <th class="text-right">Collected</th>
                    <th class="text-right">Outstanding</th>
                    <th class="text-right">Collection</th>
                    <th>Unpaid</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-right"><?= (int) $b['classes'] ?></td>
                    <td class="text-right"><?= (int) $b['students'] ?></td>
                    <td class="text-right">Rs. <?= number_format((float) $b['billed'], 2) ?></td>
                    <td class="text-right" style="color:var(--success);">Rs. <?= number_format((float) $b['collected'], 2) ?></td>
                    <td class="text-right" style="color:<?= (float) $b['outstanding'] > 0 ? 'var(--warning)' : 'inherit' ?>;">
                        Rs. <?= number_format((float) $b['outstanding'], 2) ?>
                    </td>
                    <td class="text-right" style="font-weight:700; color:<?= $rateColour($b['collection_rate']) ?>;">
                        <?= $b['collection_rate'] !== null ? $b['collection_rate'] . '%' : '&mdash;' ?>
                    </td>
                    <td class="text-sm">
                        <?php if ((int) $b['overdue_count'] > 0): ?>
                            <span style="color:var(--danger); font-weight:600;"><?= (int) $b['overdue_count'] ?> overdue</span>
                        <?php endif; ?>
                        <?php if ((int) $b['pending_count'] > 0): ?>
                            <span class="text-muted"><?= (int) $b['pending_count'] ?> pending</span>
                        <?php endif; ?>
                        <?php if ((int) $b['overdue_count'] === 0 && (int) $b['pending_count'] === 0): ?>
                            <span style="color:var(--success);">all settled</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Where the marks are -->
<div class="card" style="margin-bottom:1.75rem;">
    <div class="card__header">
        <h3 style="margin:0;">Class performance</h3>
        <p class="text-sm text-muted" style="margin:.25rem 0 0;">
            Weakest first. A class with nobody sitting is as worth seeing as one scoring badly.
        </p>
        <?php
        // Nobody has sat anything in the chosen month. Say so, rather than
        // leaving a table of dashes that reads like a fault.
        $nobodySat = empty(array_filter($classes, static fn ($c) => $c['average'] !== null));
        ?>
        <?php if ($nobodySat): ?>
            <p class="text-sm" style="margin:.5rem 0 0; color:var(--text-secondary);">
                No papers have been sat in <?= htmlspecialchars(date('F Y', strtotime($month)), ENT_QUOTES) ?> yet &mdash; the fee figures above are still current.
                <?php if (!empty($lastSatMonth) && $lastSatMonth !== $month): ?>
                    <a href="<?= BASE_URL ?>/reports/insights?month=<?= htmlspecialchars($lastSatMonth, ENT_QUOTES) ?>">
                        See <?= htmlspecialchars(date('F Y', strtotime($lastSatMonth)), ENT_QUOTES) ?> marks</a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Branch</th>
                    <th class="text-right">Enrolled</th>
                    <th class="text-right">Sat</th>
                    <th class="text-right">Average</th>
                    <th class="text-right">Passed</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($classes as $c):
                $avg = $c['average'] !== null ? (float) $c['average'] : null;
                $sat = (int) $c['sat'];
                $enrolled = (int) $c['enrolled'];
            ?>
                <tr<?= $sat === 0 ? ' style="background:var(--warning-light);"' : '' ?>>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-sm text-muted"><?= htmlspecialchars((string) $c['grade'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td class="text-sm text-muted"><?= htmlspecialchars((string) $c['institute_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-right"><?= $enrolled ?></td>
                    <td class="text-right">
                        <?= $sat ?>
                        <?php if ($sat === 0): ?>
                            <div class="text-sm" style="color:var(--warning);">nobody sat</div>
                        <?php elseif ($enrolled > 0): ?>
                            <div class="text-sm text-muted"><?= round(($sat / $enrolled) * 100) ?>%</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right" style="font-weight:700; color:<?= $rateColour($avg) ?>;">
                        <?= $avg !== null ? round($avg, 1) . '%' : '&mdash;' ?>
                    </td>
                    <td class="text-right"><?= $sat > 0 ? (int) $c['passed'] . ' / ' . (int) $c['attempts'] : '&mdash;' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(330px,1fr)); gap:1.5rem;">
    <div class="card">
        <div class="card__header">
            <h3 style="margin:0;">Needing attention</h3>
            <p class="text-sm text-muted" style="margin:.25rem 0 0;">
                Averaging under 50% over two or more quizzes &mdash; not one bad paper.
            </p>
        </div>
        <?php if ($strugglers === []): ?>
            <div style="padding:2rem; text-align:center;" class="text-sm text-muted">Nobody is below 50% on average.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Classes</th><th class="text-right">Sat</th><th class="text-right">Average</th></tr></thead>
                    <tbody>
                    <?php foreach ($strugglers as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-sm text-muted"><?= htmlspecialchars((string) $s['student_id_display'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="text-sm text-muted"><?= htmlspecialchars((string) $s['classes'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= (int) $s['quizzes_sat'] ?></td>
                            <td class="text-right" style="font-weight:700; color:var(--danger);">
                                <?= round((float) $s['average'], 1) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card__header"><h3 style="margin:0;">Doing best</h3></div>
        <?php if ($topStudents === []): ?>
            <div style="padding:2rem; text-align:center;" class="text-sm text-muted">Not enough quizzes sat yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th class="text-right">Sat</th><th class="text-right">Average</th></tr></thead>
                    <tbody>
                    <?php foreach ($topStudents as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-sm text-muted"><?= htmlspecialchars((string) $s['student_id_display'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="text-right"><?= (int) $s['quizzes_sat'] ?></td>
                            <td class="text-right" style="font-weight:700; color:var(--success);">
                                <?= round((float) $s['average'], 1) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
