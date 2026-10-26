<?php
/**
 * Student Home: level, a true motivational line, streaks, achievements and
 * goals. Included from student/home.php.
 *
 * Expects $motivation (MotivationService::forStudent) and $goalOptions
 * (GoalService::formOptions). Everything is the student's own record:
 * no leaderboard, no comparison with classmates, and the level is a
 * friendly name for the official progress score -- never a school grade.
 */

$mv = $motivation ?? [];
$lvl = $mv['level'] ?? ['has_data' => false];
$ls = $mv['learning_streak'] ?? ['current' => 0, 'longest' => 0, 'alive' => false, 'next_milestone' => 3, 'to_next' => 3];
$cs = $mv['streak'] ?? ['current' => 0, 'best' => 0];
$badges = $mv['badges'] ?? [];
$pGoals = $mv['personal_goals'] ?? [];
$opts = $goalOptions ?? ['classes' => [], 'topics' => []];
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$si = function_exists('lang_current') && lang_current() === 'si';

// One honest sentence, chosen from the student's own numbers.
$headline = $mv['headline']['key'] ?? 'start';
$hv = $mv['headline']['vars'] ?? [];
$message = match (true) {
    empty($lvl['has_data']) => t('Every Science champion starts with one quiz. Your first one sets your level.'),
    $headline === 'rising'  => t('Your marks are up {n} points on last month. That is real progress.', ['n' => $hv['delta'] ?? 0]),
    $headline === 'dip'     => t('A small dip, not a fall. One good quiz this week brings you right back.'),
    ($lvl['next_level'] ?? null) === null => t('You have reached the top level. Keep it steady and help a friend who is stuck.'),
    ($lvl['points_to_next'] ?? 99) <= 5 => t('Only {n} points to {level}. You are very close.', ['n' => $lvl['points_to_next'], 'level' => t($lvl['next_name'])]),
    ($ls['current'] ?? 0) >= 3 => t('{n} learning days in a row. Keep the streak alive today.', ['n' => $ls['current']]),
    default => t('Small steps every day add up. Pick one goal below and start today.'),
};

$earned = array_values(array_filter($badges, static fn ($b) => $b['earned']));
$locked = array_values(array_filter($badges, static fn ($b) => !$b['earned']));
$showEarned = array_slice($earned, 0, 4);
$showLocked = array_slice($locked, 0, max(2, 6 - count($showEarned)));

$goalLabel = static function (array $g) use ($si): string {
    $target = rtrim(rtrim(number_format((float) $g['target_value'], 1), '0'), '.');
    return match ($g['goal_type']) {
        'quiz_average' => t('Quiz average of {n}%', ['n' => $target]),
        'attendance'   => t('Attend {n}% of classes', ['n' => $target]),
        'homework'     => t('Hand in {n}% of homework', ['n' => $target]),
        'topic'        => t('{n}% in {unit}', ['n' => $target, 'unit' => ($si && !empty($g['topic_title_si'])) ? $g['topic_title_si'] : (string) $g['topic_title']]),
        default        => '',
    };
};
$ownActive = count(array_filter($pGoals, static fn ($g) => $g['status'] === 'active' && $g['set_by_type'] === 'student'));
$ringR = 34; $ringC = 2 * M_PI * $ringR;
?>
<style>
.mv-wrap { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(0,1fr); gap:1.25rem; margin-bottom:1.25rem; align-items:start; }
@media (max-width: 992px) { .mv-wrap { grid-template-columns:1fr; } }
.mv-level { position:relative; overflow:hidden; border-radius:16px; padding:1.15rem 1.25rem; color:#0f1d3d;
  background:linear-gradient(135deg, #fff 0%, #eef3ff 60%, #fff6d6 100%); border:1px solid rgba(29,78,216,.18); }
.mv-level__top { display:flex; gap:1rem; align-items:center; }
.mv-level__badge { flex:0 0 84px; }
.mv-level__k { font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; color:var(--primary); font-weight:700; }
.mv-level__name { font-size:1.35rem; font-weight:800; color:#0f2a6b; line-height:1.15; }
.mv-level__next { font-size:.85rem; color:var(--text-muted); margin-top:.15rem; }
.mv-band { height:8px; border-radius:6px; background:rgba(29,78,216,.12); overflow:hidden; margin-top:.5rem; }
.mv-band > span { display:block; height:100%; border-radius:6px; background:linear-gradient(90deg, var(--primary), #f5b301); }
.mv-msg { margin:.95rem 0 0; padding:.65rem .8rem; border-radius:10px; background:rgba(245,179,1,.16);
  border-left:4px solid #f5b301; font-size:.92rem; font-weight:600; color:#3d2f00; }
.mv-steps { margin-top:1rem; }
.mv-steps h4 { margin:0 0 .5rem; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:#0f2a6b; }
.mv-step { display:flex; gap:.6rem; align-items:center; padding:.55rem .7rem; border-radius:10px; background:rgba(255,255,255,.75);
  border:1px solid rgba(29,78,216,.12); margin-bottom:.45rem; text-decoration:none; color:var(--text); font-size:.88rem; }
a.mv-step:hover { border-color:var(--primary); }
.mv-step__n { flex:0 0 24px; height:24px; border-radius:50%; display:grid; place-items:center; font-size:.75rem; font-weight:800;
  background:var(--primary); color:#fff; }
.mv-note { font-size:.72rem; color:var(--text-muted); margin-top:.5rem; }
.mv-streaks { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
.mv-tile { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:.9rem 1rem; }
.mv-tile__n { font-size:1.6rem; font-weight:800; line-height:1.1; color:var(--text); }
.mv-tile__n small { font-size:.8rem; font-weight:600; color:var(--text-muted); }
.mv-tile__l { font-size:.8rem; color:var(--text-muted); margin-top:.15rem; }
.mv-tile--fire .mv-tile__n { color:#c2410c; }
.mv-ach { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.6rem; }
.mv-a { position:relative; border:1px solid var(--border); border-radius:12px; padding:.7rem .75rem; background:var(--surface); }
.mv-a__i { font-size:1.35rem; line-height:1; }
.mv-a__t { font-weight:700; font-size:.85rem; margin-top:.35rem; }
.mv-a__d { font-size:.74rem; color:var(--text-muted); margin-top:.15rem; }
.mv-a--on { border-color:rgba(245,179,1,.6); background:linear-gradient(180deg, #fffbea, var(--surface)); }
.mv-a--off .mv-a__i { filter:grayscale(1); opacity:.45; }
.mv-a__bar { height:5px; border-radius:4px; background:var(--surface-sunken); margin-top:.45rem; overflow:hidden; }
.mv-a__bar > span { display:block; height:100%; background:var(--primary); }
.mv-goal { padding:.7rem 0; border-top:1px solid var(--border); }
.mv-goal:first-of-type { border-top:none; padding-top:0; }
.mv-goal__row { display:flex; gap:.5rem; align-items:center; }
.mv-goal__t { font-weight:700; font-size:.9rem; flex:1; min-width:0; }
.mv-goal__m { font-size:.76rem; color:var(--text-muted); }
.mv-pill { font-size:.7rem; font-weight:700; padding:.15rem .5rem; border-radius:999px; white-space:nowrap; }
.mv-pill--active { background:rgba(29,78,216,.10); color:var(--primary); }
.mv-pill--achieved { background:rgba(21,128,61,.12); color:var(--success); }
.mv-pill--missed { background:var(--surface-sunken); color:var(--text-muted); }
.mv-pill--teacher { background:rgba(245,179,1,.2); color:#7a5600; }
.mv-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.6rem; margin-top:.75rem; }
.mv-form label { font-size:.75rem; color:var(--text-muted); display:block; margin-bottom:.2rem; }
.mv-form select, .mv-form input { width:100%; }
.mv-x { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:.8rem; padding:.1rem .3rem; }
.mv-x:hover { color:var(--danger); }
</style>

<div class="mv-wrap">
    <!-- Level: a friendly name for the official progress score -->
    <section class="mv-level" aria-label="<?= te('My level') ?>">
        <div class="mv-level__top">
            <svg class="mv-level__badge" width="84" height="84" viewBox="0 0 84 84" role="img"
                 aria-label="<?= $h(t('Progress score {n} out of 100', ['n' => (int) ($lvl['score'] ?? 0)])) ?>">
                <circle cx="42" cy="42" r="<?= $ringR ?>" fill="#fff" stroke="rgba(29,78,216,.14)" stroke-width="9"/>
                <circle cx="42" cy="42" r="<?= $ringR ?>" fill="none" stroke="url(#mvg)" stroke-width="9" stroke-linecap="round"
                        transform="rotate(-90 42 42)" stroke-dasharray="<?= round($ringC * min(100, (int) ($lvl['score'] ?? 0)) / 100, 1) ?> <?= round($ringC, 1) ?>"/>
                <defs><linearGradient id="mvg" x1="0" x2="1"><stop offset="0" stop-color="#1d4ed8"/><stop offset="1" stop-color="#f5b301"/></linearGradient></defs>
                <text x="42" y="40" text-anchor="middle" font-size="11" font-weight="700" fill="#1d4ed8"><?= te('Level') ?></text>
                <text x="42" y="58" text-anchor="middle" font-size="22" font-weight="800" fill="#0f2a6b"><?= (int) ($lvl['level'] ?? 1) ?></text>
            </svg>
            <div style="min-width:0; flex:1;">
                <div class="mv-level__k"><?= te('My level') ?></div>
                <div class="mv-level__name"><?= te($lvl['name'] ?? 'Starter') ?></div>
                <?php if (!empty($lvl['has_data']) && ($lvl['next_level'] ?? null) !== null): ?>
                    <div class="mv-level__next"><?= te('{n} points to Level {l} · {name}', ['n' => (int) $lvl['points_to_next'], 'l' => (int) $lvl['next_level'], 'name' => t($lvl['next_name'])]) ?></div>
                    <div class="mv-band"><span style="width:<?= (int) $lvl['band_progress'] ?>%;"></span></div>
                <?php elseif (!empty($lvl['has_data'])): ?>
                    <div class="mv-level__next"><?= te('Top level reached') ?> &#127942;</div>
                <?php else: ?>
                    <div class="mv-level__next"><?= te('Your level appears once you have quiz marks or attendance.') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <p class="mv-msg"><?= $h($message) ?></p>

        <?php $steps = $mv['goals'] ?? []; if ($steps !== []): ?>
            <div class="mv-steps">
                <h4><?= te('Your next steps') ?></h4>
                <?php foreach ($steps as $i => $st):
                    $text = match ($st['key']) {
                        'sit_quiz'      => te('Sit "{title}" before {when}', ['title' => $st['vars']['title'], 'when' => lang_date('D j M, g:ia', strtotime((string) $st['vars']['when']))]),
                        'open_material' => te('Open the {n} new recordings and notes waiting for you', ['n' => $st['vars']['n']]),
                        'level_up'      => te('To reach {level}, your quickest win is {part}', ['level' => t($st['vars']['level']), 'part' => t(['marks' => 'quiz marks', 'attendance' => 'attendance', 'material' => 'opening the class material'][$st['vars']['part']] ?? $st['vars']['part'])]),
                        'stay_top'      => te('Keep every part strong to stay at {level}', ['level' => t('Science Champion')]),
                        default         => '',
                    };
                    if ($text === '') { continue; }
                    $tag = !empty($st['href']) ? 'a' : 'div';
                ?>
                    <<?= $tag ?> class="mv-step" <?= $tag === 'a' ? 'href="' . BASE_URL . $h($st['href']) . '"' : '' ?>>
                        <span class="mv-step__n"><?= $i + 1 ?></span><span style="flex:1;"><?= $text ?></span>
                        <?php if ($tag === 'a'): ?><span style="color:var(--primary); font-weight:700;">&rarr;</span><?php endif; ?>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="mv-note"><?= te('Levels are for encouragement in this class only. They are not school grades.') ?></div>
    </section>

    <!-- Streaks -->
    <section>
        <div class="mv-streaks">
            <div class="mv-tile mv-tile--fire">
                <div class="mv-tile__n">&#128293; <?= (int) $ls['current'] ?> <small><?= te('days') ?></small></div>
                <div class="mv-tile__l"><?= te('Learning streak') ?></div>
                <div class="mv-tile__l">
                    <?php if ((int) $ls['current'] === 0): ?>
                        <?= te('Do a quiz, homework or open a lesson today to start one.') ?>
                    <?php elseif (!empty($ls['next_milestone'])): ?>
                        <?= te('{n} more to reach {m} days', ['n' => (int) $ls['to_next'], 'm' => (int) $ls['next_milestone']]) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mv-tile">
                <div class="mv-tile__n">&#128197; <?= (int) $cs['current'] ?></div>
                <div class="mv-tile__l"><?= te('Classes in a row') ?></div>
                <div class="mv-tile__l"><?= te('Best ever: {n}', ['n' => (int) $cs['best']]) ?></div>
            </div>
        </div>
        <p class="mv-note" style="margin:.5rem .1rem 0;"><?= te('One rest day never breaks your learning streak.') ?></p>

        <div class="sh-card" style="margin-top:.75rem; margin-bottom:0;">
            <h3>&#127942; <?= te('Achievements') ?>
                <span class="sh-count"><?= te('{a} of {b}', ['a' => count($earned), 'b' => count($badges)]) ?></span></h3>
            <div class="mv-ach">
                <?php foreach ($showEarned as $b): ?>
                    <div class="mv-a mv-a--on" title="<?= te($b['done']) ?>">
                        <div class="mv-a__i"><?= $b['icon'] ?></div>
                        <div class="mv-a__t"><?= te($b['title']) ?></div>
                        <div class="mv-a__d"><?= $b['earned_at'] ? te('Earned {d}', ['d' => lang_date('j M Y', strtotime((string) $b['earned_at']))]) : te('Earned') ?></div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($showLocked as $b): $pct = $b['target'] > 0 ? (int) round($b['progress'] / $b['target'] * 100) : 0; ?>
                    <div class="mv-a mv-a--off" title="<?= te('How to unlock: {how}', ['how' => t($b['how'])]) ?>">
                        <div class="mv-a__i"><?= $b['icon'] ?></div>
                        <div class="mv-a__t"><?= te($b['title']) ?></div>
                        <div class="mv-a__d">&#128274; <?= $h($b['hint'] !== '' ? $b['hint'] : t($b['how'])) ?></div>
                        <div class="mv-a__bar"><span style="width:<?= min(100, $pct) ?>%;"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= BASE_URL ?>/my/progress#achievements" class="text-sm" style="display:inline-block; margin-top:.6rem;"><?= te('See all achievements') ?> &rarr;</a>
        </div>
    </section>
</div>

<!-- Goals: the student's own, and any their teacher set -->
<div class="sh-card" id="goals">
    <h3>&#127919; <?= te('My goals') ?> <span class="sh-count"><?= te('{n} of {m} of your own', ['n' => $ownActive, 'm' => 3]) ?></span></h3>
    <?php if ($pGoals === []): ?>
        <p class="sh-empty"><?= te('No goals yet. Set one below. Your progress updates by itself from your marks, attendance and homework.') ?></p>
    <?php else: foreach ($pGoals as $g): ?>
        <div class="mv-goal">
            <div class="mv-goal__row">
                <div class="mv-goal__t"><?= $h($goalLabel($g)) ?></div>
                <?php if ($g['set_by_type'] === 'user'): ?>
                    <span class="mv-pill mv-pill--teacher"><?= te('Set by {name}', ['name' => $g['set_by_name'] ?? t('your teacher')]) ?></span>
                <?php endif; ?>
                <span class="mv-pill mv-pill--<?= $h($g['status']) ?>"><?= te(match ($g['status']) { 'achieved' => 'Achieved', 'missed' => 'Not reached', default => 'In progress' }) ?></span>
                <?php if ($g['status'] === 'active' && $g['set_by_type'] === 'student'): ?>
                    <form method="post" action="<?= BASE_URL ?>/my/goals/<?= (int) $g['id'] ?>/remove" style="margin:0;">
                        <?= csrfField() ?>
                        <button class="mv-x" type="submit" title="<?= te('Remove goal') ?>" aria-label="<?= te('Remove goal') ?>">&#10005;</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="sh-bar" style="margin:.35rem 0 .25rem;"><span style="width:<?= $g['status'] === 'achieved' ? 100 : (int) $g['pct_of_target'] ?>%; <?= $g['status'] === 'achieved' ? 'background:var(--success);' : '' ?>"></span></div>
            <div class="mv-goal__m">
                <?php if ($g['status'] === 'achieved' && !empty($g['achieved_at'])): ?>
                    <?= te('Reached on {d}', ['d' => lang_date('j M', strtotime((string) $g['achieved_at']))]) ?>
                <?php else: ?>
                    <?= $g['value'] !== null ? te('Now {n}%', ['n' => rtrim(rtrim(number_format((float) $g['value'], 1), '0'), '.')]) : te('Nothing to measure yet') ?>
                    &middot; <?= te('until {d}', ['d' => lang_date('j M', strtotime((string) $g['period_end']))]) ?>
                <?php endif; ?>
                <?= !empty($g['class_name']) ? ' &middot; ' . $h($g['class_name']) : '' ?>
                <?= !empty($g['note']) ? ' &middot; &ldquo;' . $h($g['note']) . '&rdquo;' : '' ?>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <?php if ($ownActive < 3): ?>
        <details style="margin-top:.85rem;">
            <summary class="btn btn--ghost btn--sm" style="display:inline-block; cursor:pointer;">+ <?= te('Set a goal') ?></summary>
            <form method="post" action="<?= BASE_URL ?>/my/goals" class="mv-form">
                <?= csrfField() ?>
                <div>
                    <label for="mv-type"><?= te('Goal') ?></label>
                    <select id="mv-type" name="goal_type" class="form-control" required>
                        <option value="quiz_average"><?= te('Quiz average') ?></option>
                        <option value="attendance"><?= te('Attendance') ?></option>
                        <option value="homework"><?= te('Homework handed in') ?></option>
                        <?php if ($opts['topics'] !== []): ?><option value="topic"><?= te('A Science unit') ?></option><?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="mv-target"><?= te('Target %') ?></label>
                    <input id="mv-target" type="number" name="target_value" min="1" max="100" value="75" class="form-control" required>
                </div>
                <div>
                    <label for="mv-end"><?= te('By') ?></label>
                    <input id="mv-end" type="date" name="period_end" class="form-control" required
                           min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+6 months')) ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <?php if (count($opts['classes']) > 1): ?>
                    <div>
                        <label for="mv-class"><?= te('Class') ?></label>
                        <select id="mv-class" name="class_id" class="form-control">
                            <option value=""><?= te('All my classes') ?></option>
                            <?php foreach ($opts['classes'] as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $h($c['class_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($opts['topics'] !== []): ?>
                    <div id="mv-topic-wrap" hidden>
                        <label for="mv-topic"><?= te('Unit') ?></label>
                        <select id="mv-topic" name="topic_id" class="form-control">
                            <?php foreach ($opts['topics'] as $tp): ?>
                                <option value="<?= (int) $tp['id'] ?>"><?= $tp['unit_no'] ? (int) $tp['unit_no'] . '. ' : '' ?><?= $h($si && !empty($tp['title_si']) ? $tp['title_si'] : $tp['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div style="align-self:end;"><button type="submit" class="btn btn--primary btn--sm"><?= te('Save goal') ?></button></div>
            </form>
        </details>
    <?php endif; ?>
</div>
<script>
(function () {
    var type = document.getElementById('mv-type'), wrap = document.getElementById('mv-topic-wrap');
    if (!type || !wrap) return;
    var sync = function () { wrap.hidden = type.value !== 'topic'; document.getElementById('mv-topic').required = !wrap.hidden; };
    type.addEventListener('change', sync); sync();
})();
</script>
