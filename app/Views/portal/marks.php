<?php
/**
 * My marks -- every quiz, what it means, and what to aim for next.
 *
 * Each result is judged against its own quiz's pass mark (a percentage,
 * as the quiz form sets it), compared with the quiz before it, and the
 * personal best is marked. Only this student's attempts are here; there
 * is no class average or ranking to compare against.
 */

$attempts = $attempts ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$graded = array_values(array_filter($attempts, static fn ($a) => $a['attempt_status'] === 'graded' && $a['percentage'] !== null));
$pending = count($attempts) - count($graded);

// Oldest first, to work out each quiz's change from the one before.
$chrono = $graded;
usort($chrono, static fn ($a, $b) => [$a['submitted_at'], $a['id']] <=> [$b['submitted_at'], $b['id']]);
$delta = [];
$prev = null;
foreach ($chrono as $a) {
    $delta[(int) $a['id']] = $prev === null ? null : (float) $a['percentage'] - $prev;
    $prev = (float) $a['percentage'];
}

$pcts = array_map(static fn ($a) => (float) $a['percentage'], $graded);
$avg = $pcts ? array_sum($pcts) / count($pcts) : null;
$best = $pcts ? max($pcts) : null;
$passed = count(array_filter($graded, static fn ($a) => (float) $a['percentage'] >= (float) ($a['pass_mark'] ?? 50)));
$target = $best !== null ? min(100, (int) (ceil(($best + 5) / 5) * 5)) : null;

// Per class.
$byClass = [];
foreach ($graded as $a) {
    $byClass[$a['class_name']][] = (float) $a['percentage'];
}

$classes = array_values(array_unique(array_column($attempts, 'class_name')));
$months = [];
foreach ($attempts as $a) {
    $months[(string) $a['month_year']] = lang_date('F Y', (string) $a['month_year']);
}
krsort($months);

$tone = static fn (float $p, float $pass): string => $p >= 80 ? 'var(--success)' : ($p >= $pass ? 'var(--primary)' : 'var(--danger)');
?>

<style>
.mk-hero { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.9rem; margin-bottom:1.25rem; }
.mk-stat { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1rem 1.1rem; }
.mk-stat__n { font-size:1.75rem; font-weight:800; line-height:1.1; }
.mk-stat__l { font-size:.8rem; color:var(--text-muted); margin-top:.15rem; }
.mk-target { grid-column:1 / -1; display:flex; gap:1rem; align-items:center; flex-wrap:wrap; padding:1rem 1.2rem; border-radius:14px; color:#fff;
  background:linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%); }
.mk-target strong { color:var(--accent); font-size:1.4rem; }

.mk-layout { display:grid; grid-template-columns:minmax(0,1.7fr) minmax(0,1fr); gap:1.25rem; align-items:start; }
@media (max-width: 992px) { .mk-layout { grid-template-columns:1fr; } }
.mk-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.15rem 1.25rem; margin-bottom:1.25rem; }
.mk-card h3 { margin:0 0 .85rem; font-size:1rem; }

.mk-filters { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1rem; }
.mk-filters select { width:auto; min-width:160px; }

.mk-item { display:grid; grid-template-columns:64px minmax(0,1fr) auto; gap:.9rem; align-items:center; padding:.9rem 0; border-top:1px solid var(--divider); }
.mk-item:first-child { border-top:none; padding-top:0; }
.mk-pct { width:64px; height:64px; border-radius:16px; display:grid; place-items:center; font-weight:800; font-size:1.05rem; color:#fff; }
.mk-title { font-weight:700; font-size:.95rem; }
.mk-meta { font-size:.8rem; color:var(--text-muted); margin-top:.15rem; }
.mk-chips { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.4rem; }
.mk-chip { font-size:.72rem; font-weight:700; padding:.15rem .5rem; border-radius:999px; }
.mk-chip--pass { background:var(--success-light); color:var(--success); }
.mk-chip--fail { background:var(--danger-light); color:var(--danger); }
.mk-chip--up { background:var(--success-light); color:var(--success); }
.mk-chip--down { background:var(--danger-light); color:var(--danger); }
.mk-chip--best { background:var(--accent); color:#1a1300; }
.mk-chip--wait { background:var(--surface-sunken); color:var(--text-muted); }
@media (max-width: 520px) { .mk-item { grid-template-columns:52px minmax(0,1fr); } .mk-item > a { grid-column:2; justify-self:start; } .mk-pct { width:52px; height:52px; font-size:.95rem; } }

.mk-bars { display:flex; align-items:flex-end; gap:6px; height:130px; padding-top:.5rem; border-bottom:1px solid var(--border); position:relative; }
.mk-bars__pass { position:absolute; left:0; right:0; border-top:1px dashed var(--danger); opacity:.55; }
.mk-bar { flex:1; min-width:10px; border-radius:6px 6px 0 0; position:relative; }
.mk-bar span { position:absolute; top:-18px; left:50%; transform:translateX(-50%); font-size:.68rem; font-weight:700; color:var(--text-secondary); }

.mk-class { margin-bottom:.8rem; }
.mk-class__row { display:flex; justify-content:space-between; font-size:.88rem; }
.mk-class__bar { height:8px; border-radius:6px; background:var(--surface-sunken); overflow:hidden; margin-top:.3rem; }
.mk-class__bar > span { display:block; height:100%; border-radius:6px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('My marks') ?></h1>
        <p class="text-sm text-muted">
            <?= te('{n} quizzes marked', ['n' => count($graded)]) ?><?= $pending ? ' &middot; ' . te('{n} waiting to be marked', ['n' => $pending]) : '' ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/my/progress" class="btn btn--ghost"><?= te('My Progress') ?></a>
        <a href="<?= BASE_URL ?>/my/classes" class="btn btn--ghost"><?= te('My Classes') ?></a>
    </div>
</div>

<?php if ($attempts === []): ?>
    <div class="mk-card" style="text-align:center; padding:3rem 1.5rem;">
        <div style="font-size:2.6rem;">&#127891;</div>
        <h3 style="margin:.6rem 0 .3rem;"><?= te('No quizzes yet') ?></h3>
        <p class="text-muted" style="margin:0 0 1rem;"><?= te('Your marks show up here as soon as you hand in your first quiz.') ?></p>
        <a class="btn btn--primary" href="<?= BASE_URL ?>/my/classes"><?= te('Find a quiz to sit') ?></a>
    </div>
<?php else: ?>

<div class="mk-hero">
    <div class="mk-stat">
        <div class="mk-stat__n" style="color:var(--primary);"><?= $avg !== null ? round($avg) . '%' : '&ndash;' ?></div>
        <div class="mk-stat__l"><?= te('Average') ?></div>
    </div>
    <div class="mk-stat">
        <div class="mk-stat__n" style="color:var(--success);"><?= $best !== null ? round($best) . '%' : '&ndash;' ?></div>
        <div class="mk-stat__l"><?= te('Personal best') ?></div>
    </div>
    <div class="mk-stat">
        <div class="mk-stat__n"><?= $graded ? $passed . '/' . count($graded) : '&ndash;' ?></div>
        <div class="mk-stat__l"><?= te('Passed') ?></div>
    </div>
    <div class="mk-stat">
        <div class="mk-stat__n"><?= count($attempts) ?></div>
        <div class="mk-stat__l"><?= te('Quizzes handed in') ?></div>
    </div>
    <?php if ($target !== null): ?>
        <div class="mk-target">
            <span style="font-size:1.8rem;">&#127919;</span>
            <div>
                <div style="opacity:.85; font-size:.85rem;"><?= te('Your next target') ?></div>
                <div><?= $best >= 100
                    ? te('You have hit 100%. Can you do it again?')
                    : te('Beat your best: aim for {t}% in your next quiz', ['t' => $target]) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="mk-layout">
    <div class="mk-card">
        <h3>&#128221; <?= te('All results') ?></h3>

        <?php if (count($classes) > 1 || count($months) > 1): ?>
            <div class="mk-filters">
                <?php if (count($classes) > 1): ?>
                    <select class="form-control" id="mk-class" aria-label="<?= te('Class') ?>">
                        <option value=""><?= te('All classes') ?></option>
                        <?php foreach ($classes as $c): ?><option value="<?= $e($c) ?>"><?= $e($c) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if (count($months) > 1): ?>
                    <select class="form-control" id="mk-month" aria-label="<?= te('Month') ?>">
                        <option value=""><?= te('All months') ?></option>
                        <?php foreach ($months as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="mk-list">
        <?php foreach ($attempts as $a):
            $isGraded = $a['attempt_status'] === 'graded' && $a['percentage'] !== null;
            $pct = $isGraded ? (float) $a['percentage'] : null;
            $pass = (float) ($a['pass_mark'] ?? 50);
            $d = $delta[(int) $a['id']] ?? null;
            $isBest = $isGraded && $best !== null && $pct === $best;
        ?>
            <div class="mk-item" data-class="<?= $e($a['class_name']) ?>" data-month="<?= $e($a['month_year']) ?>">
                <div class="mk-pct" style="background:<?= $isGraded ? $tone($pct, $pass) : 'var(--border-strong)' ?>;">
                    <?= $isGraded ? round($pct) . '%' : '&hellip;' ?>
                </div>
                <div style="min-width:0;">
                    <div class="mk-title"><?= $e($a['title']) ?></div>
                    <div class="mk-meta">
                        <?= $e($a['class_name']) ?> &middot; <?= $e(lang_date('j M Y', (string) $a['submitted_at'])) ?>
                        <?php if ($isGraded && $a['score'] !== null): ?> &middot; <?= te('{s} out of {t}', ['s' => rtrim(rtrim((string) $a['score'], '0'), '.'), 't' => rtrim(rtrim((string) $a['total_marks'], '0'), '.')]) ?><?php endif; ?>
                    </div>
                    <div class="mk-chips">
                        <?php if (!$isGraded): ?>
                            <span class="mk-chip mk-chip--wait"><?= te('Waiting to be marked') ?></span>
                        <?php else: ?>
                            <span class="mk-chip <?= $pct >= $pass ? 'mk-chip--pass' : 'mk-chip--fail' ?>">
                                <?= $pct >= $pass ? te('Passed') : te('Below the pass mark ({p}%)', ['p' => round($pass)]) ?>
                            </span>
                            <?php if ($d !== null && abs($d) >= 1): ?>
                                <span class="mk-chip <?= $d > 0 ? 'mk-chip--up' : 'mk-chip--down' ?>">
                                    <?= $d > 0 ? '&#9650; ' . te('{n} up on last time', ['n' => round($d)]) : '&#9660; ' . te('{n} down on last time', ['n' => round(abs($d))]) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isBest): ?><span class="mk-chip mk-chip--best">&#11088; <?= te('Personal best') ?></span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/my/quizzes/<?= (int) $a['id'] ?>/result"><?= te('See answers') ?></a>
            </div>
        <?php endforeach; ?>
        </div>
        <p id="mk-none" class="text-muted" style="display:none; margin:.5rem 0 0;"><?= te('No results match these filters.') ?></p>
    </div>

    <div>
        <?php if (count($chrono) >= 2): ?>
            <div class="mk-card">
                <h3>&#128202; <?= te('Every quiz at a glance') ?></h3>
                <div class="mk-bars" role="img" aria-label="<?= te('Bar chart of your quiz marks, oldest first') ?>">
                    <div class="mk-bars__pass" style="bottom:50%;" title="<?= te('Pass mark') ?>"></div>
                    <?php foreach (array_slice($chrono, -14) as $a): $p = (float) $a['percentage']; ?>
                        <div class="mk-bar" style="height:<?= max(4, $p) ?>%; background:<?= $tone($p, (float) ($a['pass_mark'] ?? 50)) ?>;"
                             title="<?= $e($a['title']) ?>: <?= round($p) ?>%"><span><?= round($p) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <p class="text-sm text-muted" style="margin:.6rem 0 0;"><?= te('Oldest on the left, newest on the right. The dashed line is 50%.') ?></p>
            </div>
        <?php endif; ?>

        <?php if ($byClass): ?>
            <div class="mk-card">
                <h3>&#128218; <?= te('By class') ?></h3>
                <?php foreach ($byClass as $name => $list): $ca = array_sum($list) / count($list); ?>
                    <div class="mk-class">
                        <div class="mk-class__row"><span><?= $e($name) ?></span><strong><?= round($ca) ?>%</strong></div>
                        <div class="mk-class__bar"><span style="width:<?= min(100, $ca) ?>%; background:<?= $tone($ca, 50) ?>;"></span></div>
                        <div class="mk-meta"><?= te('{n} quizzes', ['n' => count($list)]) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mk-card" style="background:linear-gradient(160deg, rgba(245,179,1,.12), rgba(29,78,216,.05));">
            <h3>&#128161; <?= te('Getting more marks') ?></h3>
            <ul style="margin:0; padding-left:1.1rem; line-height:1.7;" class="text-sm">
                <li><?= te('Open "See answers" on a quiz and read the ones you got wrong.') ?></li>
                <li><?= te('Watch that lesson\'s recording again before the next quiz.') ?></li>
                <li><?= te('Sit quizzes early, while the lesson is fresh.') ?></li>
            </ul>
        </div>
    </div>
</div>

<script>
// Filters work on the page, with no reload.
(function () {
    var cls = document.getElementById('mk-class'), mon = document.getElementById('mk-month');
    var items = document.querySelectorAll('#mk-list .mk-item'), none = document.getElementById('mk-none');
    function apply() {
        var c = cls ? cls.value : '', m = mon ? mon.value : '', shown = 0;
        items.forEach(function (it) {
            var ok = (!c || it.dataset.class === c) && (!m || it.dataset.month === m);
            it.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        none.style.display = shown ? 'none' : '';
    }
    if (cls) cls.addEventListener('change', apply);
    if (mon) mon.addEventListener('change', apply);
})();
</script>

<?php endif; ?>
