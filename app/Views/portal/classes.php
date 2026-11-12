<?php
/**
 * The student's own classes, each as a full panel.
 *
 * A locked class is shown, not hidden. Hiding it would leave a student
 * who has not paid wondering whether they were removed from the class
 * altogether; showing it locked, with the amount and with a count of
 * exactly what is waiting behind the lock, tells them what to do and
 * why it is worth doing.
 */

$classes  = $classes ?? [];
$months   = $months ?? [];
$month    = $month ?? date('Y-m-01');
$overview = $overview ?? [];
$monthLabel = lang_date('F Y', $month);
$isCurrentMonth = $month === date('Y-m-01');

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$openCount = count(array_filter($classes, static fn (array $c): bool => $c['is_open']));
$allNew = 0; $allQuizzes = 0;
foreach ($overview as $o) {
    $allNew += (int) $o['library']['unopened'];
    $allQuizzes += count($o['quizzes']['open']);
}

$kinds = [
    'recording' => ['Recordings', '&#127909;'],
    'video'     => ['Videos', '&#127916;'],
    'reading'   => ['Notes', '&#128196;'],
    'link'      => ['Links', '&#128279;'],
];

$dayLabel = static function (array $s): string {
    $d = new DateTimeImmutable($s['date']);
    $diff = (int) (new DateTimeImmutable('today'))->diff($d)->format('%r%a');
    return $diff === 0 ? t('Today') : ($diff === 1 ? t('Tomorrow') : lang_date('D j M', $d->getTimestamp()));
};

$barTone = static fn (?float $v): string => $v === null ? 'var(--border)' : ($v >= 70 ? 'var(--success)' : ($v >= 50 ? 'var(--accent)' : 'var(--danger)'));
?>

<style>
.mc-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.85rem; margin-bottom:1.5rem; }
.mc-sum { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:.9rem 1rem; display:flex; gap:.75rem; align-items:center; }
.mc-sum__icon { width:40px; height:40px; border-radius:11px; display:grid; place-items:center; font-size:1.15rem; background:var(--surface-sunken); }
.mc-sum__n { font-size:1.35rem; font-weight:700; line-height:1.1; }
.mc-sum__l { font-size:.78rem; color:var(--text-muted); }

.mc-panel { background:var(--surface); border:1px solid var(--border); border-radius:18px; overflow:hidden; margin-bottom:1.5rem; }
.mc-head { padding:1.25rem 1.4rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;
  background:linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%); color:#fff; position:relative; overflow:hidden; }
.mc-head::after { content:""; position:absolute; right:-40px; top:-70px; width:190px; height:190px; border-radius:50%; background:var(--accent); opacity:.16; }
.mc-panel--locked .mc-head { background:linear-gradient(135deg, #475569 0%, #1e293b 100%); }
.mc-head h2 { margin:0; font-size:1.3rem; color:#fff; }
.mc-head__meta { font-size:.85rem; opacity:.88; margin-top:.2rem; }
.mc-avatar { width:46px; height:46px; border-radius:50%; background:var(--accent); color:#1a1300; font-weight:800;
  display:grid; place-items:center; font-size:1.1rem; flex:0 0 46px; position:relative; z-index:1; }
.mc-status { margin-left:auto; position:relative; z-index:1; font-size:.8rem; font-weight:700; padding:.35rem .8rem; border-radius:999px; background:rgba(255,255,255,.16); }
.mc-status--ok { background:rgba(21,128,61,.9); }
.mc-status--due { background:var(--accent); color:#1a1300; }

.mc-body { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); }
@media (max-width: 1100px) { .mc-body { grid-template-columns:1fr 1fr; } }
@media (max-width: 700px)  { .mc-body { grid-template-columns:1fr; } }
.mc-col { padding:1.15rem 1.3rem; border-right:1px solid var(--border); border-top:1px solid var(--border); }
.mc-col:last-child { border-right:none; }
.mc-col h4 { margin:0 0 .75rem; font-size:.78rem; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); }

.mc-count { font-size:1.6rem; font-weight:800; color:var(--primary); line-height:1; }
.mc-chips { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.6rem; }
.mc-chip { font-size:.75rem; padding:.22rem .55rem; border-radius:7px; background:var(--surface-sunken); color:var(--text-secondary); }
.mc-chip--now { background:rgba(29,78,216,.10); color:var(--primary); font-weight:600; }
.mc-test { margin-top:.85rem; padding:.6rem .7rem; border-radius:10px; background:rgba(180,35,24,.07); border-left:3px solid var(--danger); font-size:.83rem; }

.mc-lib { display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
.mc-lib__item { display:flex; align-items:center; gap:.45rem; font-size:.83rem; padding:.45rem .55rem; border-radius:9px; background:var(--surface-sunken); }
.mc-lib__item strong { margin-left:auto; }
.mc-continue { display:flex; gap:.6rem; align-items:center; margin-top:.8rem; padding:.65rem .75rem; border-radius:11px;
  border:1px dashed var(--primary); text-decoration:none; color:inherit; }
.mc-continue:hover { background:rgba(29,78,216,.05); }
.mc-new { font-size:.7rem; font-weight:700; color:#1a1300; background:var(--accent); border-radius:999px; padding:.1rem .45rem; }

.mc-stand__row { display:flex; justify-content:space-between; font-size:.82rem; color:var(--text-secondary); }
.mc-bar { height:7px; border-radius:6px; background:var(--surface-sunken); overflow:hidden; margin:.25rem 0 .65rem; }
.mc-bar > span { display:block; height:100%; border-radius:6px; }
.mc-level { display:inline-block; font-size:.78rem; font-weight:700; padding:.2rem .6rem; border-radius:999px; background:rgba(29,78,216,.10); color:var(--primary); margin-bottom:.7rem; }

.mc-quiz { display:flex; gap:.55rem; align-items:center; padding:.5rem 0; border-top:1px solid var(--border); font-size:.84rem; }
.mc-quiz:first-of-type { border-top:none; }
.mc-quiz__t { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mc-score { font-weight:700; }

.mc-lockbox { margin:0 1.3rem 1.2rem; padding:.9rem 1rem; border-radius:12px; background:rgba(245,179,1,.14); border:1px solid rgba(245,179,1,.45); }
.mc-foot { display:flex; gap:.6rem; flex-wrap:wrap; padding:1rem 1.3rem; border-top:1px solid var(--border); background:var(--bg); }
.mc-foot .btn { flex:0 0 auto; }
.mc-muted { color:var(--text-muted); font-size:.85rem; margin:0; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('My Classes') ?></h1>
        <p class="text-sm text-muted"><?= te('{a} of {b} open for {month}', ['a' => $openCount, 'b' => count($classes), 'month' => $monthLabel]) ?></p>
    </div>
    <div class="page-header__actions">
        <form method="GET" action="<?= BASE_URL ?>/my/classes" style="display:flex; gap:.5rem; align-items:center;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()" aria-label="<?= te('Month') ?>">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= $e($value) ?>" <?= $value === $month ? 'selected' : '' ?>><?= $e(lang_date('F Y', (string) $value)) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--ghost btn--sm"><?= te('Go') ?></button></noscript>
        </form>
        <a href="<?= BASE_URL ?>/my/calendar" class="btn btn--ghost"><?= te('Calendar') ?></a>
        <a href="<?= BASE_URL ?>/my/progress" class="btn btn--ghost"><?= te('My progress') ?></a>
    </div>
</div>

<?php if ($classes === []): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127979;</div>
        <h4 style="margin:.75rem 0 .35rem;"><?= te('You are not enrolled in any class yet') ?></h4>
        <p class="text-sm text-muted" style="margin:0;"><?= te('Speak to the office to be added to a class.') ?></p>
    </div>
<?php else: ?>

<div class="mc-summary">
    <div class="mc-sum"><div class="mc-sum__icon">&#128218;</div>
        <div><div class="mc-sum__n"><?= $openCount ?>/<?= count($classes) ?></div><div class="mc-sum__l"><?= te('Classes open this month') ?></div></div></div>
    <div class="mc-sum"><div class="mc-sum__icon">&#10024;</div>
        <div><div class="mc-sum__n"><?= $allNew ?></div><div class="mc-sum__l"><?= te('Not opened yet') ?></div></div></div>
    <div class="mc-sum"><div class="mc-sum__icon">&#9997;&#65039;</div>
        <div><div class="mc-sum__n" style="color:<?= $allQuizzes ? 'var(--danger)' : 'var(--success)' ?>;"><?= $allQuizzes ?></div><div class="mc-sum__l"><?= te('Quizzes to sit now') ?></div></div></div>
</div>

<?php foreach ($classes as $c):
    $id = (int) $c['id'];
    $open = (bool) $c['is_open'];
    $gate = $c['gate'];
    $o = $overview[$id] ?? null;
    $lib = $o['library'] ?? ['counts' => [], 'total' => 0, 'unopened' => 0, 'next' => null];
    $qz = $o['quizzes'] ?? ['open' => [], 'upcoming' => 0, 'done' => [], 'missed' => 0];
    $st = $o['standing'] ?? ['has_data' => false];
    $days = !empty($c['schedule_days']) ? (json_decode($c['schedule_days'], true) ?: []) : [];
    $teacherInitial = mb_strtoupper(mb_substr(trim((string) ($c['teacher_name'] ?? 'T')), 0, 1));

    if ($gate['reason'] === 'free_card') { $statusClass = 'mc-status--ok'; $statusText = te('Free card'); }
    elseif ($open) { $statusClass = 'mc-status--ok'; $statusText = '&#10003; ' . te('{month} paid', ['month' => lang_date('F', $month)]); }
    elseif ($gate['reason'] === 'not_generated') { $statusClass = ''; $statusText = te('Fee not issued yet'); }
    else { $statusClass = 'mc-status--due'; $statusText = '&#128274; ' . te('Rs. {n} due', ['n' => number_format((float) $gate['amount_due'], 0)]); }

    // The fee service bakes the month into its English message, which no
    // dictionary could match, so the sentence is rebuilt from the reason.
    $gateMessage = match ($gate['reason']) {
        'overdue'       => t('Your {month} fee is overdue. Settle it to reopen this class.', ['month' => $monthLabel]),
        'unpaid'        => t('Your {month} fee is not settled yet.', ['month' => $monthLabel]),
        'not_generated' => t('The {month} fee has not been issued yet. This class opens once it is.', ['month' => $monthLabel]),
        'not_enrolled'  => t('You are not enrolled in this class.'),
        default         => (string) $gate['message'],
    };
?>
<section class="mc-panel <?= $open ? '' : 'mc-panel--locked' ?>" aria-label="<?= $e($c['class_name']) ?>">
    <header class="mc-head">
        <div class="mc-avatar" title="<?= $e($c['teacher_name'] ?? '') ?>"><?= $e($teacherInitial) ?></div>
        <div style="position:relative; z-index:1; min-width:0;">
            <h2><?= $e($c['class_name']) ?></h2>
            <div class="mc-head__meta">
                <?= $e($c['teacher_name'] ?? '') ?>
                <?= !empty($c['institute_name']) ? ' &middot; ' . $e($c['institute_name']) : '' ?>
                <?= !empty($c['room']) ? ' &middot; ' . $e($c['room']) : '' ?>
                <?= $days ? ' &middot; ' . $e(lang_names(implode(' & ', $days))) : '' ?>
                <?= !empty($c['start_time']) ? ' ' . $e(lang_date('g:i a', (string) $c['start_time'])) : '' ?>
                <?= !empty($c['end_time']) ? '&ndash;' . $e(lang_date('g:i a', (string) $c['end_time'])) : '' ?>
            </div>
        </div>
        <span class="mc-status <?= $statusClass ?>"><?= $statusText ?></span>
    </header>

    <?php if (!$open): ?>
        <div class="mc-lockbox" style="margin-top:1.1rem;">
            <strong><?= $e($gateMessage) ?></strong>
            <?php if ($lib['total'] > 0 || count($qz['open']) + $qz['upcoming'] > 0): ?>
                <div class="text-sm" style="margin-top:.3rem;">
                    <?= te('Waiting behind the lock for {month}:', ['month' => $monthLabel]) ?>
                    <?php
                    $waiting = [];
                    foreach ($kinds as $k => [$label]) {
                        if (!empty($lib['counts'][$k])) { $waiting[] = t('{n} ' . strtolower($label), ['n' => $lib['counts'][$k]]); }
                    }
                    $lockedQuizzes = count($qz['open']) + $qz['upcoming'];
                    if ($lockedQuizzes) { $waiting[] = t($lockedQuizzes === 1 ? '{n} quiz' : '{n} quizzes', ['n' => $lockedQuizzes]); }
                    echo $e(implode(', ', $waiting));
                    ?>.
                    <?= te('The class itself still runs — come along as usual.') ?>
                </div>
            <?php else: ?>
                <div class="text-sm" style="margin-top:.3rem;"><?= te('The class itself still runs — come along as usual.') ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mc-body">
        <!-- When -->
        <div class="mc-col">
            <h4><?= te('Next class') ?></h4>
            <?php if (!empty($o['next'])): $n = $o['next']; ?>
                <div class="mc-count" data-countdown="<?= $e(str_replace(' ', 'T', $n['starts_at'])) ?>"
                     data-now="<?= $e(date('Y-m-d\TH:i:s')) ?>">&nbsp;</div>
                <div class="text-sm" style="margin-top:.3rem;"><?= te('{day} at {time}', ['day' => $dayLabel($n), 'time' => $n['start_time']]) ?><?= ($n['event_type'] ?? '') === 'extra_class' ? ' &middot; ' . te('extra class') : '' ?></div>
                <?php if (count($o['week']) > 1): ?>
                    <div class="mc-chips" aria-label="<?= te('Sessions in the next 7 days') ?>">
                        <?php foreach ($o['week'] as $i => $s): ?>
                            <span class="mc-chip <?= $i === 0 ? 'mc-chip--now' : '' ?>"><?= $e(lang_date('D j', (string) $s['date'])) ?> &middot; <?= $e($s['start_time']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php
                // The joining link for an online class. Only an http(s) link
                // becomes a button, and only on a class this student is in.
                $joinUrl = null;
                if (($n['delivery_mode'] ?? 'physical') !== 'physical') {
                    $candidate = trim((string) ($n['meeting_url'] ?? ''));
                    if ($candidate !== '' && preg_match('#^https?://#i', $candidate) === 1) {
                        $joinUrl = $candidate;
                    }
                }
                ?>
                <?php if ($joinUrl !== null): ?>
                    <a class="mc-join" href="<?= $e($joinUrl) ?>" target="_blank" rel="noopener noreferrer">
                        &#128250; <?= te('Join the online class') ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <p class="mc-muted"><?= te('No sessions in the next 7 days.') ?></p>
            <?php endif; ?>

            <?php if (!empty($o['test'])): $t = $o['test']; ?>
                <div class="mc-test">
                    <strong>&#128221; <?= $e($t['title']) ?></strong><br>
                    <?= $t['start_time'] ? te('{day} at {time}', ['day' => $dayLabel($t), 'time' => $t['start_time']]) : $e($dayLabel($t)) ?>
                    <?php if (!empty($t['syllabus'])): ?><br><span class="text-muted"><?= te('Covers: {what}', ['what' => $t['syllabus']]) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- This month's folder -->
        <div class="mc-col">
            <h4><?= te('{month} folder', ['month' => lang_date('F', $month)]) ?>
                <?php if ($open && $lib['unopened'] > 0): ?><span class="mc-new" style="margin-left:.35rem;"><?= te('{n} new', ['n' => (int) $lib['unopened']]) ?></span><?php endif; ?>
            </h4>
            <?php if ($lib['total'] === 0): ?>
                <p class="mc-muted"><?= te('Nothing posted for {month} yet.', ['month' => lang_date('F', $month)]) ?></p>
            <?php else: ?>
                <div class="mc-lib">
                    <?php foreach ($kinds as $k => [$label, $icon]): if (empty($lib['counts'][$k])) { continue; } ?>
                        <div class="mc-lib__item"><span><?= $icon ?></span><?= te($label) ?><strong><?= (int) $lib['counts'][$k] ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($open && $lib['next'] !== null): $nx = $lib['next']; ?>
                    <a class="mc-continue" href="<?= BASE_URL ?>/my/materials/<?= (int) $nx['id'] ?>/open">
                        <span style="font-size:1.2rem;"><?= $kinds[$nx['material_type']][1] ?? '&#128196;' ?></span>
                        <span style="min-width:0;">
                            <span class="text-sm text-muted"><?= te('Up next for you') ?></span><br>
                            <strong style="font-size:.88rem;"><?= $e($nx['title']) ?></strong>
                        </span>
                        <span style="margin-left:auto; font-weight:700; color:var(--primary);">&rarr;</span>
                    </a>
                <?php elseif ($open): ?>
                    <p class="mc-muted" style="margin-top:.7rem;">&#10003; <?= te("You've opened everything in this folder.") ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- How I'm doing -->
        <div class="mc-col">
            <h4><?= te("How I'm doing here") ?></h4>
            <?php if (empty($st['has_data'])): ?>
                <p class="mc-muted"><?= te('Your standing appears once there are marks or attendance in this class.') ?></p>
            <?php else: ?>
                <span class="mc-level"><?= te((string) $st['level']) ?> &middot; <?= round((float) $st['score']) ?>/100</span>
                <?php foreach ([
                    ['Average mark', $st['marks']['quizzes_taken'] > 0 ? (float) $st['marks']['average_percentage'] : null, t($st['marks']['quizzes_taken'] == 1 ? '{n} quiz' : '{n} quizzes', ['n' => $st['marks']['quizzes_taken']])],
                    ['Attendance', $st['attendance']['sessions_recorded'] > 0 ? (float) $st['attendance']['rate'] : null, t('{n} sessions', ['n' => $st['attendance']['sessions_recorded']])],
                    ['Material opened', $st['engagement']['materials_total'] > 0 ? (float) $st['engagement']['rate'] : null, t('{n} items', ['n' => $st['engagement']['materials_total']])],
                ] as [$label, $value, $basis]): ?>
                    <div class="mc-stand__row"><span><?= te($label) ?> <span class="text-muted" style="font-size:.75rem;">(<?= $e($basis) ?>)</span></span>
                        <strong><?= $value !== null ? round($value) . '%' : '&ndash;' ?></strong></div>
                    <div class="mc-bar"><span style="width:<?= $value !== null ? min(100, max(0, $value)) : 0 ?>%; background:<?= $barTone($value) ?>;"></span></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h4 style="margin-top:.6rem;"><?= te('Quizzes') ?> &middot; <?= $e(lang_date('F', $month)) ?></h4>
            <?php if (!$qz['open'] && !$qz['done'] && !$qz['upcoming'] && !$qz['missed']): ?>
                <p class="mc-muted"><?= te('No quizzes this month.') ?></p>
            <?php else: ?>
                <?php foreach ($qz['open'] as $q): ?>
                    <div class="mc-quiz">
                        <span>&#10067;</span>
                        <span class="mc-quiz__t" title="<?= $e($q['title']) ?>"><?= $e($q['title']) ?></span>
                        <?php if ($open): ?>
                            <a class="btn btn--primary btn--sm" href="<?= BASE_URL ?>/my/quizzes/<?= (int) $q['id'] ?>/sit"><?= te('Start') ?></a>
                        <?php else: ?>
                            <span class="text-muted">&#128274;</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($qz['done'], 0, 3) as $q): $pct = $q['best'] !== null ? (float) $q['best'] : null; ?>
                    <div class="mc-quiz">
                        <span>&#10003;</span>
                        <span class="mc-quiz__t" title="<?= $e($q['title']) ?>"><?= $e($q['title']) ?></span>
                        <span class="mc-score" style="color:<?= $barTone($pct) ?>;"><?= $pct !== null ? round($pct) . '%' : te('Marking') ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($qz['upcoming'] || $qz['missed']): ?>
                    <div class="text-sm text-muted" style="margin-top:.4rem;">
                        <?= $qz['upcoming'] ? te('{n} coming later this month', ['n' => (int) $qz['upcoming']]) : '' ?>
                        <?= $qz['upcoming'] && $qz['missed'] ? ' &middot; ' : '' ?>
                        <?= $qz['missed'] ? '<span style="color:var(--danger);">' . te('{n} missed', ['n' => (int) $qz['missed']]) . '</span>' : '' ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="mc-foot">
        <a href="<?= BASE_URL ?>/my/classes/<?= $id ?>?month=<?= urlencode($month) ?>" class="btn <?= $open ? 'btn--primary' : 'btn--ghost' ?>">
            <?= $open ? te('Open class') : te('See what is locked') ?>
        </a>
        <a href="<?= BASE_URL ?>/my/calendar" class="btn btn--ghost"><?= te('Calendar') ?></a>
        <a href="<?= BASE_URL ?>/my/marks" class="btn btn--ghost"><?= te('All marks') ?></a>
        <button type="button" class="btn btn--ghost" data-ask="When is my next class?">&#129302; <?= te('Ask the assistant') ?></button>
    </footer>
</section>
<?php endforeach; ?>

<?php endif; ?>

<script>
// Countdown per class. Measured from the server's clock at render time
// plus elapsed browser time, so a laptop with a wrong clock still counts
// down correctly.
(function () {
    var started = Date.now();
    var els = document.querySelectorAll('[data-countdown]');
    var L = <?= json_encode(['now' => t('Now'), 'inTpl' => t('in {x}'), 'd' => t('d'), 'h' => t('h'), 'm' => t('m'), 'min' => t('min')], JSON_UNESCAPED_UNICODE) ?>;
    function render() {
        els.forEach(function (el) {
            var ms = new Date(el.getAttribute('data-countdown')).getTime()
                   - (new Date(el.getAttribute('data-now')).getTime() + (Date.now() - started));
            if (ms <= 0) { el.textContent = L.now; return; }
            var m = Math.floor(ms / 60000), d = Math.floor(m / 1440), h = Math.floor((m % 1440) / 60), mm = m % 60;
            var span = d > 0 ? d + L.d + ' ' + h + L.h : (h > 0 ? h + L.h + ' ' + mm + L.m : mm + ' ' + L.min);
            el.textContent = L.inTpl.replace('{x}', span);
        });
    }
    render();
    setInterval(render, 30000);
})();
document.querySelectorAll('[data-ask]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (window.StudyAssistant) window.StudyAssistant.ask(btn.getAttribute('data-ask'), btn.textContent.trim());
    });
});
</script>
