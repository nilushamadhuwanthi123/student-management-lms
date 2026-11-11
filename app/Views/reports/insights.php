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
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rs = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$isGlobal = \App\Services\BranchScope::current()->isGlobal();

$rateColour = static fn (?float $r): string => match (true) {
    $r === null => 'var(--text-muted)',
    $r >= 85    => 'var(--success)',
    $r >= 60    => 'var(--warning)',
    default     => 'var(--danger)',
};

$maxBilled = 0.0;
foreach ($trend as $t) { $maxBilled = max($maxBilled, (float) $t['billed'], (float) $t['collected']); }
?>
<style>
    .ins-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .ins-kpi { padding: 1.15rem; }
    .ins-kpi__value { font-size: 1.6rem; font-weight: 700; margin-top: .2rem; }
    /* Chart: the plot area has a fixed height and the bars are drawn inside
       it; the month and rate sit underneath in their own row, so nothing
       can grow upwards over the heading. */
    .ins-chart { display: grid; grid-template-columns: repeat(var(--n), minmax(0, 1fr)); gap: .75rem; }
    .ins-chart__plot { position: relative; height: 160px; display: flex; align-items: flex-end; justify-content: center; gap: 5px; border-bottom: 1px solid var(--border); }
    .ins-chart__bar { width: min(22px, 32%); border-radius: 4px 4px 0 0; min-height: 3px; transition: opacity .2s ease; }
    .ins-chart__bar--billed { background: var(--border-strong); }
    .ins-chart__bar--collected { background: var(--primary); }
    .ins-chart__col:hover .ins-chart__bar { opacity: .85; }
    .ins-chart__label { text-align: center; margin-top: .45rem; line-height: 1.3; }
    .ins-legend { display: flex; gap: 1.25rem; margin-top: 1rem; flex-wrap: wrap; }
    .ins-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: .35rem; vertical-align: -1px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Branch insights') ?></h1>
        <p class="text-sm text-muted"><?= $isGlobal ? te('Money and marks across every branch') : te('Money and marks for your branch') ?> &middot; <?= $e(lang_date('F Y', $month)) ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/reports" class="btn btn--ghost"><?= te('All reports') ?></a>
        <form method="GET" action="<?= BASE_URL ?>/reports/insights" style="display:inline-flex; gap:.5rem;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()" aria-label="<?= te('Month') ?>">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= $e($value) ?>" <?= $value === $month ? 'selected' : '' ?>><?= $e(lang_date('F Y', (string) $value)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- The month in four numbers -->
<div class="ins-kpis">
    <div class="card ins-kpi">
        <div class="text-sm text-muted"><?= te('Billed') ?></div>
        <div class="ins-kpi__value"><?= $rs($totals['billed'] ?? 0) ?></div>
        <div class="text-sm text-muted"><?= te('{n} invoices', ['n' => (int) ($totals['invoices'] ?? 0)]) ?></div>
    </div>
    <div class="card ins-kpi">
        <div class="text-sm text-muted"><?= te('Collected') ?></div>
        <div class="ins-kpi__value" style="color:var(--success);"><?= $rs($totals['collected'] ?? 0) ?></div>
    </div>
    <div class="card ins-kpi" style="<?= (float) ($totals['outstanding'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted"><?= te('Outstanding') ?></div>
        <div class="ins-kpi__value" style="color:var(--warning);"><?= $rs($totals['outstanding'] ?? 0) ?></div>
        <div class="text-sm text-muted"><?= te('{n} unpaid', ['n' => (int) ($totals['unpaid'] ?? 0)]) ?></div>
    </div>
    <div class="card ins-kpi">
        <div class="text-sm text-muted"><?= te('Collection rate') ?></div>
        <div class="ins-kpi__value" style="color:<?= $rateColour($totals['rate'] ?? null) ?>;">
            <?= ($totals['rate'] ?? null) !== null ? $e($totals['rate']) . '%' : '&mdash;' ?>
        </div>
    </div>
</div>

<!-- Billed against collected, six months -->
<?php if ($trend !== []): ?>
<div class="card" style="margin-bottom:1.75rem;">
    <div class="card__header">
        <h3 style="margin:0;"><?= te('Billed against collected') ?></h3>
        <p class="text-sm text-muted" style="margin:.25rem 0 0;"><?= te('The gap between the two bars is the money that was invoiced and never came in.') ?></p>
    </div>
    <div class="card__body">
        <div class="ins-chart" style="--n: <?= count($trend) ?>;" role="img" aria-label="<?= te('Billed against collected') ?>">
            <?php foreach ($trend as $t):
                $billed = (float) $t['billed'];
                $collected = (float) $t['collected'];
                $bh = $maxBilled > 0 ? ($billed / $maxBilled) * 100 : 0;
                $ch = $maxBilled > 0 ? ($collected / $maxBilled) * 100 : 0;
                $rate = $billed > 0 ? (int) round(($collected / $billed) * 100) : null;
                $tip = t('Billed') . ' ' . $rs($billed) . ' · ' . t('Collected') . ' ' . $rs($collected);
            ?>
                <div class="ins-chart__col" title="<?= $e($tip) ?>">
                    <div class="ins-chart__plot">
                        <div class="ins-chart__bar ins-chart__bar--billed" style="height:<?= round($bh, 1) ?>%;"></div>
                        <div class="ins-chart__bar ins-chart__bar--collected" style="height:<?= round($ch, 1) ?>%;"></div>
                    </div>
                    <div class="ins-chart__label">
                        <div class="text-sm text-muted"><?= $e(lang_date('M', (string) $t['month_year'])) ?></div>
                        <div class="text-sm" style="font-weight:600; color:<?= $rateColour($rate === null ? null : (float) $rate) ?>;"><?= $rate !== null ? $rate . '%' : '&mdash;' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ins-legend text-sm text-muted">
            <span><i style="background:var(--border-strong);"></i><?= te('Billed') ?></span>
            <span><i style="background:var(--primary);"></i><?= te('Collected') ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Branch by branch -->
<div class="card" style="margin-bottom:1.75rem;">
    <div class="card__header"><h3 style="margin:0;"><?= $isGlobal ? te('By branch') : te('Your branch') ?></h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= te('Branch') ?></th>
                    <th class="text-right"><?= te('Classes') ?></th>
                    <th class="text-right"><?= te('Students') ?></th>
                    <th class="text-right"><?= te('Billed') ?></th>
                    <th class="text-right"><?= te('Collected') ?></th>
                    <th class="text-right"><?= te('Outstanding') ?></th>
                    <th class="text-right"><?= te('Collection') ?></th>
                    <th><?= te('Unpaid') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td style="font-weight:600;"><?= $e($b['name']) ?></td>
                    <td class="text-right"><?= (int) $b['classes'] ?></td>
                    <td class="text-right"><?= (int) $b['students'] ?></td>
                    <td class="text-right"><?= $rs($b['billed']) ?></td>
                    <td class="text-right" style="color:var(--success);"><?= $rs($b['collected']) ?></td>
                    <td class="text-right" style="color:<?= (float) $b['outstanding'] > 0 ? 'var(--warning)' : 'inherit' ?>;"><?= $rs($b['outstanding']) ?></td>
                    <td class="text-right" style="font-weight:700; color:<?= $rateColour($b['collection_rate']) ?>;">
                        <?= $b['collection_rate'] !== null ? $e($b['collection_rate']) . '%' : '&mdash;' ?>
                    </td>
                    <td class="text-sm">
                        <?php if ((int) $b['overdue_count'] > 0): ?>
                            <span style="color:var(--danger); font-weight:600;"><?= te('{n} overdue', ['n' => (int) $b['overdue_count']]) ?></span>
                        <?php endif; ?>
                        <?php if ((int) $b['pending_count'] > 0): ?>
                            <span class="text-muted"><?= te('{n} pending', ['n' => (int) $b['pending_count']]) ?></span>
                        <?php endif; ?>
                        <?php if ((int) $b['overdue_count'] === 0 && (int) $b['pending_count'] === 0): ?>
                            <span style="color:var(--success);"><?= te('all settled') ?></span>
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
        <h3 style="margin:0;"><?= te('Class performance') ?></h3>
        <p class="text-sm text-muted" style="margin:.25rem 0 0;"><?= te('Weakest first. A class with nobody sitting is as worth seeing as one scoring badly.') ?></p>
        <?php
        // Nobody has sat anything in the chosen month. Say so, rather than
        // leaving a table of dashes that reads like a fault.
        $nobodySat = empty(array_filter($classes, static fn ($c) => $c['average'] !== null));
        ?>
        <?php if ($nobodySat): ?>
            <p class="text-sm" style="margin:.5rem 0 0; color:var(--text-secondary);">
                <?= te('No papers have been sat in {month} yet -- the fee figures above are still current.', ['month' => lang_date('F Y', $month)]) ?>
                <?php if (!empty($lastSatMonth) && $lastSatMonth !== $month): ?>
                    <a href="<?= BASE_URL ?>/reports/insights?month=<?= $e($lastSatMonth) ?>"><?= te('See {month} marks', ['month' => lang_date('F Y', (string) $lastSatMonth)]) ?></a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th><?= te('Class') ?></th>
                    <th><?= te('Branch') ?></th>
                    <th class="text-right"><?= te('Enrolled') ?></th>
                    <th class="text-right"><?= te('Sat') ?></th>
                    <th class="text-right"><?= te('Average') ?></th>
                    <th class="text-right"><?= te('Passed') ?></th>
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
                        <div style="font-weight:600;"><?= $e($c['class_name']) ?></div>
                        <div class="text-sm text-muted"><?= $e(t((string) $c['grade'])) ?></div>
                    </td>
                    <td class="text-sm text-muted"><?= $e($c['institute_name']) ?></td>
                    <td class="text-right"><?= $enrolled ?></td>
                    <td class="text-right">
                        <?= $sat ?>
                        <?php if ($sat === 0): ?>
                            <div class="text-sm" style="color:var(--warning);"><?= te('nobody sat') ?></div>
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

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%, 330px),1fr)); gap:1.5rem;">
    <div class="card">
        <div class="card__header">
            <h3 style="margin:0;"><?= te('Needing attention') ?></h3>
            <p class="text-sm text-muted" style="margin:.25rem 0 0;"><?= te('Averaging under 50% over two or more quizzes -- not one bad paper.') ?></p>
        </div>
        <?php if ($strugglers === []): ?>
            <div style="padding:2rem; text-align:center;" class="text-sm text-muted"><?= te('Nobody is below 50% on average.') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= te('Student') ?></th><th><?= te('Classes') ?></th><th class="text-right"><?= te('Sat') ?></th><th class="text-right"><?= te('Average') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($strugglers as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= $e($s['full_name']) ?></div>
                                <div class="text-sm text-muted"><?= $e($s['student_id_display']) ?></div>
                            </td>
                            <td class="text-sm text-muted"><?= $e($s['classes']) ?></td>
                            <td class="text-right"><?= (int) $s['quizzes_sat'] ?></td>
                            <td class="text-right" style="font-weight:700; color:var(--danger);"><?= round((float) $s['average'], 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card__header"><h3 style="margin:0;"><?= te('Doing best') ?></h3></div>
        <?php if ($topStudents === []): ?>
            <div style="padding:2rem; text-align:center;" class="text-sm text-muted"><?= te('Not enough quizzes sat yet.') ?></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= te('Student') ?></th><th class="text-right"><?= te('Sat') ?></th><th class="text-right"><?= te('Average') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($topStudents as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= $e($s['full_name']) ?></div>
                                <div class="text-sm text-muted"><?= $e($s['student_id_display']) ?></div>
                            </td>
                            <td class="text-right"><?= (int) $s['quizzes_sat'] ?></td>
                            <td class="text-right" style="font-weight:700; color:var(--success);"><?= round((float) $s['average'], 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
