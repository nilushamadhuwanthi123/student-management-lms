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
        <h1 class="page-header__title"><?= te('Teaching') ?></h1>
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
            <noscript><button type="submit" class="btn btn--ghost btn--sm"><?= te('Go') ?></button></noscript>
        </form>
    </div>
</div>

<!-- The two things that need attention come first -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-bottom:1.75rem;">
    <div class="card" style="padding:1.1rem; <?= (int) ($totals['marking'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted"><?= te('Papers to mark') ?></div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:<?= (int) ($totals['marking'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text)' ?>;">
            <?= (int) ($totals['marking'] ?? 0) ?>
        </div>
    </div>
    <div class="card" style="padding:1.1rem; <?= (int) ($totals['unpaid'] ?? 0) > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted"><?= te('Unpaid this month') ?></div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:<?= (int) ($totals['unpaid'] ?? 0) > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
            <?= (int) ($totals['unpaid'] ?? 0) ?>
        </div>
        <div class="text-sm text-muted" style="margin-top:.15rem;"><?= te('locked out of content') ?></div>
    </div>
    <div class="card" style="padding:1.1rem;">
        <div class="text-sm text-muted"><?= te('Material posted') ?></div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:var(--primary);">
            <?= (int) ($totals['materials'] ?? 0) ?>
        </div>
    </div>
    <div class="card" style="padding:1.1rem;">
        <div class="text-sm text-muted"><?= te('Quizzes live') ?></div>
        <div style="font-size:1.7rem; font-weight:700; margin-top:.2rem; color:var(--primary);">
            <?= (int) ($totals['quizzes'] ?? 0) ?>
        </div>
    </div>
</div>

<?php
$participation = $participation ?? [];
$marksByGrade  = $marksByGrade ?? [];
$ce = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<?php if ($participation !== [] || $marksByGrade !== []): ?>
<div class="tc-wrap">

    <?php if ($participation !== []): ?>
    <section class="card tc-card">
        <div class="tc-head">
            <h3 class="tc-title"><?= te('Who actually sat each quiz') ?></h3>
            <p class="tc-sub"><?= te('A quiz nobody sat is not a hard quiz -- it is one they never saw, and its average means nothing.') ?></p>
        </div>

        <div class="tc-bars">
            <?php foreach ($participation as $q): ?>
                <?php
                $rate = (float) $q['rate'];
                $tone = $rate >= 80 ? 'good' : ($rate >= 50 ? 'mid' : 'bad');
                $missing = max(0, (int) $q['enrolled'] - (int) $q['sat']);
                ?>
                <div class="tc-bar">
                    <div class="tc-bar__top">
                        <span class="tc-bar__name" title="<?= $ce($q['quiz']) ?>"><?= $ce($q['quiz']) ?></span>
                        <span class="tc-bar__num"><?= (int) $q['sat'] ?> / <?= (int) $q['enrolled'] ?></span>
                    </div>
                    <div class="tc-track" role="img"
                         aria-label="<?= $ce($q['quiz']) ?>: <?= (int) $q['sat'] ?> of <?= (int) $q['enrolled'] ?>">
                        <span class="tc-fill tc-fill--<?= $tone ?>" style="width: <?= max(1, min(100, $rate)) ?>%;"></span>
                    </div>
                    <div class="tc-bar__foot">
                        <span><?= $ce($q['class']) ?><?php /* Most class names already begin with the grade ("Grade 10
                                 Science - Galle"), so repeating it reads as a mistake. */ ?>
                        <?php if ($q['grade'] !== '' && stripos($q['class'], $q['grade']) === false): ?> &middot; <?= $ce($q['grade']) ?><?php endif; ?></span>
                        <span class="tc-bar__pct tc-pct--<?= $tone ?>"><?= $ce($rate) ?>%</span>
                    </div>
                    <?php if ($missing > 0): ?>
                        <div class="tc-missing"><?= $missing === 1
                            ? te('1 student did not sit it')
                            : te('{n} did not sit it', ['n' => $missing]) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($marksByGrade !== []): ?>
    <section class="card tc-card">
        <div class="tc-head">
            <h3 class="tc-title"><?= te('Marks by grade -- the spread, not the average') ?></h3>
            <p class="tc-sub"><?= te('An average of 60 can be everyone at 60, or half at 95 and half at 25. Those are opposite problems, and the average hides both.') ?></p>
        </div>

        <div class="tc-grades">
            <?php foreach ($marksByGrade as $g): ?>
                <?php
                $low = (float) $g['lowest']; $avg = (float) $g['average']; $high = (float) $g['highest'];
                $span = $high - $low;
                $wide = $span >= 50;
                ?>
                <div class="tc-grade">
                    <div class="tc-grade__head">
                        <span class="tc-grade__name">
                            <?= $g['grade'] !== '' ? $ce($g['grade']) : te('No grade set') ?>
                        </span>
                        <span class="tc-grade__sat"><?= (int) $g['sat'] === 1
                            ? te('1 paper marked')
                            : te('{n} papers marked', ['n' => (int) $g['sat']]) ?></span>
                    </div>

                    <?php /* Lowest to highest as a band, with the average marked
                             on it. The width of the band is the point. */ ?>
                    <div class="tc-range" role="img"
                         aria-label="<?= te('Lowest') ?> <?= $ce($low) ?>%, <?= te('average') ?> <?= $ce($avg) ?>%, <?= te('highest') ?> <?= $ce($high) ?>%">
                        <span class="tc-range__band" style="left: <?= max(0, min(100, $low)) ?>%; width: <?= max(1, min(100, $span)) ?>%;"></span>
                        <span class="tc-range__avg" style="left: <?= max(0, min(100, $avg)) ?>%;"></span>
                    </div>
                    <div class="tc-range__labels">
                        <span><?= te('Lowest') ?> <b><?= $ce($low) ?>%</b></span>
                        <span><?= te('Average') ?> <b><?= $ce($avg) ?>%</b></span>
                        <span><?= te('Highest') ?> <b><?= $ce($high) ?>%</b></span>
                    </div>

                    <div class="tc-bands">
                        <?php
                        $bands = [
                            ['top',    t('75% and over'),  (int) $g['bands']['top']],
                            ['mid',    t('50 to 74%'),     (int) $g['bands']['mid']],
                            ['low',    t('35 to 49%'),     (int) $g['bands']['low']],
                            ['bottom', t('Under 35%'),     (int) $g['bands']['bottom']],
                        ];
                        $bandTotal = max(1, array_sum(array_column($bands, 2)));
                        ?>
                        <div class="tc-stack">
                            <?php foreach ($bands as [$key, $label, $n]): ?>
                                <?php if ($n > 0): ?>
                                    <span class="tc-seg tc-seg--<?= $key ?>" style="width: <?= $n / $bandTotal * 100 ?>%;"
                                          title="<?= $ce($label) ?>: <?= $n ?>"></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="tc-legend">
                            <?php foreach ($bands as [$key, $label, $n]): ?>
                                <span class="tc-key"><i class="tc-dot tc-dot--<?= $key ?>"></i><?= $ce($label) ?> <b><?= $n ?></b></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($wide): ?>
                        <div class="tc-flag"><?= te('This grade is split: the gap between the weakest and strongest paper is {n} marks.', ['n' => round($span)]) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>
<?php endif; ?>

<?php if ($byBranch === []): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127979;</div>
        <h4 style="margin:.75rem 0 .35rem;"><?= te('No classes assigned yet') ?></h4>
        <p class="text-sm text-muted" style="margin:0;"><?= te('Classes you teach will appear here.') ?></p>
    </div>
<?php else: ?>
    <?php foreach ($byBranch as $branch => $classes): ?>
        <h3 style="margin:1.75rem 0 .875rem; font-size:1.05rem; color:var(--text-secondary);">
            <?= htmlspecialchars($branch, ENT_QUOTES, 'UTF-8') ?>
            <span class="text-sm text-muted">&middot; <?= count($classes) ?> <?= te('class') ?><?= count($classes) === 1 ? '' : 'es' ?></span>
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
                                    <?= (int) $c['students'] ?> <?= te('students') ?>
                                    <?php if ($days !== []): ?>
                                        &middot; <?= htmlspecialchars(implode(', ', $days), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ((int) $c['awaiting_marking'] > 0): ?>
                                <span class="badge" style="background:var(--warning-light); color:var(--warning); white-space:nowrap;">
                                    <?= (int) $c['awaiting_marking'] ?> <?= te('to mark') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; gap:1.25rem; margin-top:.9rem;">
                            <div>
                                <div style="font-size:1.2rem; font-weight:700; color:var(--primary);">
                                    <?= (int) $c['materials_this_month'] ?>
                                </div>
                                <div class="text-sm text-muted"><?= te('material') ?></div>
                            </div>
                            <div>
                                <div style="font-size:1.2rem; font-weight:700; color:var(--primary);">
                                    <?= (int) $c['quizzes_this_month'] ?>
                                </div>
                                <div class="text-sm text-muted"><?= te('quizzes') ?></div>
                            </div>
                            <?php if ((int) $c['unpaid_this_month'] > 0): ?>
                                <div>
                                    <div style="font-size:1.2rem; font-weight:700; color:var(--warning);">
                                        <?= (int) $c['unpaid_this_month'] ?>
                                    </div>
                                    <div class="text-sm text-muted"><?= te('unpaid') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php /* Six buttons of equal weight made every class look
                             like a menu with no first choice. They are grouped
                             now: the two things done in the lesson itself, then
                             the three that are posted to the class, then the
                             two that are about the students rather than the
                             material. Same links, in the order the day runs. */ ?>
                    <div class="card__footer tt-actions">
                        <div class="tt-row">
                            <a href="<?= BASE_URL ?>/attendance/register?class_id=<?= (int) $c['id'] ?>"
                               class="btn btn--primary btn--sm tt-btn"><?= te('Mark the register') ?></a>
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/progress"
                               class="btn btn--secondary btn--sm tt-btn"><?= te('Class progress') ?></a>
                        </div>

                        <div class="tt-label"><?= te('Post to this class') ?></div>
                        <div class="tt-row">
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/materials?month=<?= urlencode($month) ?>"
                               class="btn btn--ghost btn--sm tt-btn"><?= te('Material & videos') ?></a>
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/quizzes"
                               class="btn btn--ghost btn--sm tt-btn"><?= te('Quizzes') ?></a>
                            <?php if (can('assignments.view')): ?>
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/assignments"
                               class="btn btn--ghost btn--sm tt-btn"><?= te('Homework') ?></a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/recordings"
                               class="btn btn--ghost btn--sm tt-btn"><?= te('Recordings') ?></a>
                        </div>

                        <div class="tt-row tt-row--quiet">
                            <a href="<?= BASE_URL ?>/classes/<?= (int) $c['id'] ?>/learning-gaps"
                               class="tt-link"><?= te('Learning gaps') ?> &rarr;</a>
                            <a href="<?= BASE_URL ?>/notices/create?class_id=<?= (int) $c['id'] ?>"
                               class="tt-link"><?= te('Send a notice') ?> &rarr;</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
    .tt-actions { display:grid; gap:.5rem; }
    .tt-row { display:flex; gap:.5rem; flex-wrap:wrap; }
    .tt-btn { flex:1 1 44%; min-width:120px; }
    .tt-label { font-size:.68rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
                color:#94A3B8; margin-top:.25rem; }
    .tt-row--quiet { gap:.9rem; margin-top:.15rem; }
    .tt-link { font-size:.78rem; font-weight:600; color:var(--primary,#1D4ED8); text-decoration:none; }
    .tt-link:hover { text-decoration:underline; }
</style>

<style>
    .tc-wrap { display:grid; gap:1rem; margin-bottom:1.75rem; }
    .tc-card { padding:1.1rem 1.2rem 1.2rem; }
    .tc-head { margin-bottom:.9rem; }
    .tc-title { margin:0 0 .2rem; font-size:1rem; }
    .tc-sub { margin:0; font-size:.8rem; color:#64748B; line-height:1.5; max-width:70ch; }

    .tc-bars { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:.9rem 1.2rem; }
    .tc-bar__top { display:flex; justify-content:space-between; gap:.6rem; align-items:baseline; margin-bottom:.3rem; }
    .tc-bar__name { font-size:.84rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tc-bar__num { font-size:.78rem; font-weight:700; color:#475569; white-space:nowrap; }
    .tc-track { height:9px; border-radius:999px; background:#EEF2F7; overflow:hidden; }
    .tc-fill { display:block; height:100%; border-radius:999px; }
    .tc-fill--good { background:#15803D; } .tc-fill--mid { background:#B45309; } .tc-fill--bad { background:#B3261E; }
    .tc-bar__foot { display:flex; justify-content:space-between; gap:.6rem; margin-top:.25rem; font-size:.72rem; color:#94A3B8; }
    .tc-pct--good { color:#15803D; font-weight:700; } .tc-pct--mid { color:#B45309; font-weight:700; } .tc-pct--bad { color:#B3261E; font-weight:700; }
    .tc-missing { margin-top:.2rem; font-size:.72rem; font-weight:600; color:#9A3412; }

    .tc-grades { display:grid; gap:1.1rem; }
    .tc-grade { border:1px solid var(--border-color,#E2E8F0); border-radius:11px; padding:.85rem .95rem; }
    .tc-grade__head { display:flex; justify-content:space-between; gap:.6rem; align-items:baseline; margin-bottom:.55rem; }
    .tc-grade__name { font-weight:800; font-size:.92rem; }
    .tc-grade__sat { font-size:.74rem; color:#94A3B8; }

    .tc-range { position:relative; height:12px; border-radius:999px; background:#F1F5F9; }
    .tc-range__band { position:absolute; top:0; bottom:0; border-radius:999px;
                      background:linear-gradient(90deg,#FCA5A5,#FDE047,#86EFAC); }
    .tc-range__avg { position:absolute; top:-3px; bottom:-3px; width:3px; border-radius:2px;
                     background:#15307E; transform:translateX(-1.5px); }
    .tc-range__labels { display:flex; justify-content:space-between; margin-top:.3rem; font-size:.72rem; color:#64748B; }
    .tc-range__labels b { color:#1C2538; }

    .tc-bands { margin-top:.7rem; }
    .tc-stack { display:flex; height:8px; border-radius:999px; overflow:hidden; background:#F1F5F9; }
    .tc-seg { display:block; height:100%; }
    .tc-seg--top { background:#15803D; } .tc-seg--mid { background:#2563EB; }
    .tc-seg--low { background:#B45309; } .tc-seg--bottom { background:#B3261E; }
    .tc-legend { display:flex; flex-wrap:wrap; gap:.5rem .9rem; margin-top:.4rem; font-size:.72rem; color:#64748B; }
    .tc-key { display:inline-flex; align-items:center; gap:.3rem; }
    .tc-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .tc-dot--top { background:#15803D; } .tc-dot--mid { background:#2563EB; }
    .tc-dot--low { background:#B45309; } .tc-dot--bottom { background:#B3261E; }

    .tc-flag { margin-top:.6rem; font-size:.75rem; font-weight:600; color:#9A3412;
               background:#FFF7ED; border:1px solid #FED7AA; border-radius:8px; padding:.35rem .6rem; }
</style>
