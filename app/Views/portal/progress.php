<?php
/**
 * My progress -- written to make a student want to do the next thing.
 *
 * Built from the student's own record only. It leads with what is going
 * well, shows how far the next level is, turns the weakest area into a
 * concrete step, and shows achievements -- the locked ones too, with how
 * to earn them. Every sentence goes through te() so it reads in Sinhala
 * or English.
 */

$overall    = $overall ?? ['has_data' => false];
$classes    = $classes ?? [];
$motivation = $motivation ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$hasData = !empty($overall['has_data']);
$score = $hasData ? (float) $overall['score'] : 0.0;
$next = $motivation['next_level'] ?? null;
$trend = $motivation['trend'] ?? null;
$history = $motivation['history'] ?? [];
$streak = $motivation['streak'] ?? ['current' => 0, 'best' => 0, 'sessions' => 0];
$badges = $motivation['badges'] ?? [];
$goals = $motivation['goals'] ?? [];
$headline = $motivation['headline'] ?? ['key' => 'start', 'vars' => []];
$earnedCount = count(array_filter($badges, static fn ($b) => $b['earned']));

$levelColour = static fn (string $l): string => match ($l) {
    'Excellent', 'Strong' => 'var(--success)',
    'Steady'              => 'var(--primary)',
    'Developing'          => 'var(--accent-text)',
    default               => 'var(--danger)',
};

$ring = 2 * M_PI * 70;

$headlines = [
    'start'  => ['Every expert started somewhere.', 'Sit your first quiz and your level appears here.'],
    'rising' => ['You are improving!', 'Your quiz marks are {delta} points up on {month}. Keep going.'],
    'dip'    => ['A dip, not a fall.', 'Marks are {delta} points down on last month. One good quiz brings you right back.'],
    'top'    => ['You are at the top level.', 'Excellent work. Keep the streak going and help a friend who is stuck.'],
    'steady' => ['Solid and steady.', 'You are doing well. Pick one goal below and push for the next level.'],
];
[$h1, $h2] = $headlines[$headline['key']] ?? $headlines['steady'];
$hVars = $headline['vars'];
if (isset($hVars['month'])) {
    $hVars['month'] = lang_date('F', $hVars['month']);
}

$badgeText = [
    'first_quiz'   => ['First step',   'Sat your first quiz.',                     'Sit your first quiz.'],
    'high_flyer'   => ['High flyer',   'Scored 80% or more in a quiz.',            'Score 80% or more in any quiz.'],
    'on_a_roll'    => ['On a roll',    'Three quizzes in a row at 70% or more.',   'Score 70% or more in three quizzes in a row.'],
    'comeback'     => ['Comeback',     'Beat your last quiz by 15 points.',        'Beat your previous quiz mark by 15 points.'],
    'early_bird'   => ['Early bird',   'Handed in a quiz within a day of it opening.', 'Hand in a quiz within a day of it opening.'],
    'never_missed' => ['Never missed', 'Attended 8 classes in a row.',             'Come to 8 classes in a row.'],
    'bookworm'     => ['Bookworm',     'Opened every recording and note.',         'Open every recording and note in your classes.'],
    'perfect'      => ['Perfect score', 'Got full marks in a quiz.',               'Get full marks in a quiz.'],
];

$partName = ['marks' => 'quiz marks', 'attendance' => 'attendance', 'material' => 'opening the class material'];
?>

<style>
.pg-hero { display:grid; grid-template-columns:auto 1fr; gap:1.75rem; align-items:center; padding:1.6rem 1.75rem;
  border-radius:20px; color:#fff; margin-bottom:1.25rem; position:relative; overflow:hidden;
  background:linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%); }
.pg-hero::after { content:""; position:absolute; right:-80px; bottom:-120px; width:300px; height:300px; border-radius:50%;
  background:var(--accent); opacity:.14; }
.pg-hero h1 { color:#fff; margin:0 0 .35rem; font-size:1.55rem; }
.pg-hero p { margin:0; color:rgba(255,255,255,.92); max-width:56ch; } /* global p colour would otherwise win over the inherited white */
.pg-ring text { font-family:inherit; }
.pg-next { margin-top:1rem; position:relative; z-index:1; max-width:460px; }
.pg-next__bar { height:10px; border-radius:8px; background:rgba(255,255,255,.18); overflow:hidden; margin:.4rem 0 .3rem; }
.pg-next__bar > span { display:block; height:100%; border-radius:8px; background:var(--accent); }
.pg-next small { color:rgba(255,255,255,.85); }
@media (max-width: 640px) { .pg-hero { grid-template-columns:1fr; text-align:center; } .pg-hero p, .pg-next { margin-left:auto; margin-right:auto; } }

.pg-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.9rem; margin-bottom:1.25rem; }
.pg-tile { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1rem 1.1rem; }
.pg-tile__n { font-size:1.6rem; font-weight:800; line-height:1.1; }
.pg-tile__l { font-size:.8rem; color:var(--text-muted); margin-top:.15rem; }
.pg-tile__s { font-size:.78rem; margin-top:.35rem; }

.pg-grid { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); gap:1.25rem; align-items:start; }
@media (max-width: 992px) { .pg-grid { grid-template-columns:1fr; } }
.pg-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.2rem 1.3rem; margin-bottom:1.25rem; }
.pg-card h3 { margin:0 0 .9rem; font-size:1.02rem; }

.pg-goal { display:flex; gap:.75rem; align-items:center; padding:.75rem .85rem; border-radius:12px; background:var(--surface-sunken);
  margin-bottom:.55rem; text-decoration:none; color:inherit; }
.pg-goal:hover { background:var(--primary-light); }
.pg-goal__n { flex:0 0 30px; height:30px; border-radius:50%; background:var(--primary); color:#fff; font-weight:800;
  display:grid; place-items:center; font-size:.85rem; }

.pg-badges { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.7rem; }
.pg-badge { border:1px solid var(--border); border-radius:14px; padding:.9rem .8rem; text-align:center; position:relative; }
.pg-badge__icon { font-size:1.9rem; line-height:1; }
.pg-badge__t { font-weight:700; font-size:.88rem; margin-top:.45rem; }
.pg-badge__d { font-size:.75rem; color:var(--text-muted); margin-top:.2rem; }
.pg-badge--earned { background:linear-gradient(160deg, rgba(245,179,1,.16), rgba(29,78,216,.06)); border-color:rgba(245,179,1,.55); }
.pg-badge--locked .pg-badge__icon { filter:grayscale(1); opacity:.35; }
.pg-badge--locked .pg-badge__t { color:var(--text-secondary); }
.pg-badge__tick { position:absolute; top:8px; right:8px; width:20px; height:20px; border-radius:50%; background:var(--success);
  color:#fff; font-size:.7rem; display:grid; place-items:center; }

.pg-part__row { display:flex; justify-content:space-between; font-size:.85rem; }
.pg-part__bar { height:9px; border-radius:6px; background:var(--surface-sunken); overflow:hidden; margin:.3rem 0 .85rem; }
.pg-part__bar > span { display:block; height:100%; border-radius:6px; }

.pg-chart svg { width:100%; height:auto; display:block; }
.pg-chart text { font-size:10px; fill:var(--text-muted); font-family:inherit; }
</style>

<!-- The headline: one true sentence, then how far to the next level -->
<section class="pg-hero">
    <svg class="pg-ring" width="170" height="170" viewBox="0 0 170 170" role="img"
         aria-label="<?= te('Overall score {n} out of 100', ['n' => round($score)]) ?>" style="position:relative; z-index:1; margin:0 auto;">
        <circle cx="85" cy="85" r="70" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="14"/>
        <?php if ($hasData): ?>
        <circle cx="85" cy="85" r="70" fill="none" stroke="var(--accent)" stroke-width="14" stroke-linecap="round"
                transform="rotate(-90 85 85)" stroke-dasharray="<?= round($ring * $score / 100, 1) ?> <?= round($ring, 1) ?>"/>
        <?php endif; ?>
        <text x="85" y="82" text-anchor="middle" font-size="40" font-weight="800" fill="#fff"><?= $hasData ? round($score) : '&ndash;' ?></text>
        <text x="85" y="106" text-anchor="middle" font-size="13" fill="rgba(255,255,255,.85)"><?= $hasData ? te((string) $overall['level']) : te('Not started') ?></text>
    </svg>
    <div style="position:relative; z-index:1;">
        <h1><?= te($h1) ?></h1>
        <p><?= te($h2, $hVars) ?></p>
        <?php if ($next !== null): ?>
            <div class="pg-next">
                <strong><?= te('{points} more points to {level}', ['points' => $next['to_go'], 'level' => t($next['label'])]) ?></strong>
                <div class="pg-next__bar"><span style="width:<?= max(4, min(100, $next['percent'])) ?>%;"></span></div>
                <small><?= te('Your score is {score} out of 100. {level} starts at {at}.', ['score' => round($score), 'level' => t($next['label']), 'at' => $next['at']]) ?></small>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Four numbers, each about getting better -->
<div class="pg-tiles">
    <div class="pg-tile">
        <div class="pg-tile__n" style="color:var(--accent-text);">&#128293; <?= (int) $streak['current'] ?></div>
        <div class="pg-tile__l"><?= te('Classes in a row') ?></div>
        <div class="pg-tile__s text-muted"><?= te('Best ever: {n}', ['n' => (int) $streak['best']]) ?></div>
    </div>
    <div class="pg-tile">
        <div class="pg-tile__n" style="color:var(--success);"><?= $motivation['best'] !== null ? round((float) $motivation['best']) . '%' : '&ndash;' ?></div>
        <div class="pg-tile__l"><?= te('Personal best') ?></div>
        <div class="pg-tile__s text-muted"><?= te('Your highest quiz mark') ?></div>
    </div>
    <div class="pg-tile">
        <?php if ($trend !== null): $up = $trend['delta'] >= 0; ?>
            <div class="pg-tile__n" style="color:<?= $up ? 'var(--success)' : 'var(--danger)' ?>;"><?= $up ? '&#9650;' : '&#9660;' ?> <?= abs((int) $trend['delta']) ?></div>
            <div class="pg-tile__l"><?= te('Points vs {month}', ['month' => lang_date('F', $trend['previous_month'])]) ?></div>
            <div class="pg-tile__s text-muted"><?= te('{a}% then, {b}% now', ['a' => $trend['previous'], 'b' => $trend['recent']]) ?></div>
        <?php else: ?>
            <div class="pg-tile__n">&ndash;</div>
            <div class="pg-tile__l"><?= te('Month on month') ?></div>
            <div class="pg-tile__s text-muted"><?= te('Shows once you have marks in two months') ?></div>
        <?php endif; ?>
    </div>
    <div class="pg-tile">
        <div class="pg-tile__n" style="color:var(--primary);">&#127942; <?= $earnedCount ?>/<?= count($badges) ?></div>
        <div class="pg-tile__l"><?= te('Achievements') ?></div>
        <div class="pg-tile__s text-muted"><?= te('{n} still to unlock', ['n' => count($badges) - $earnedCount]) ?></div>
    </div>
</div>

<div class="pg-grid">
    <div>
        <!-- Next steps -->
        <div class="pg-card">
            <h3>&#127919; <?= te('Your next steps') ?></h3>
            <?php if ($goals === []): ?>
                <p class="text-muted" style="margin:0;"><?= te('Nothing waiting right now. Check back after your next class.') ?></p>
            <?php else: foreach ($goals as $i => $g):
                $text = match ($g['key']) {
                    'sit_quiz'      => te('Sit "{title}" before {when}', ['title' => $g['vars']['title'], 'when' => lang_date('D j M, g:ia', $g['vars']['when'])]),
                    'open_material' => te('Open the {n} new recordings and notes waiting for you', ['n' => $g['vars']['n']]),
                    'level_up'      => te('To reach {level}, your quickest win is {part}', ['level' => t($g['vars']['level']), 'part' => t($partName[$g['vars']['part']] ?? $g['vars']['part'])]),
                    'stay_top'      => te('Keep every part above 85 to stay at Excellent'),
                    default         => '',
                };
                $tag = $g['href'] ? 'a' : 'div';
            ?>
                <<?= $tag ?> class="pg-goal" <?= $g['href'] ? 'href="' . BASE_URL . $e($g['href']) . '"' : '' ?>>
                    <span class="pg-goal__n"><?= $i + 1 ?></span>
                    <span><?= $text ?></span>
                    <?php if ($g['href']): ?><span style="margin-left:auto; color:var(--primary); font-weight:700;">&rarr;</span><?php endif; ?>
                </<?= $tag ?>>
            <?php endforeach; endif; ?>
        </div>

        <!-- Marks over time -->
        <div class="pg-card pg-chart">
            <h3>&#128200; <?= te('Your quiz marks over time') ?></h3>
            <?php if (count($history) < 2): ?>
                <p class="text-muted" style="margin:0;"><?= te('Your chart appears after your second quiz.') ?></p>
            <?php else:
                $w = 600; $h = 200; $padL = 34; $padR = 12; $padT = 12; $padB = 26;
                $n = count($history);
                $x = static fn (int $i): float => $padL + ($n === 1 ? 0 : $i * ($w - $padL - $padR) / ($n - 1));
                $y = static fn (float $p): float => $padT + (100 - $p) * ($h - $padT - $padB) / 100;
                $points = [];
                foreach ($history as $i => $hp) { $points[] = round($x($i), 1) . ',' . round($y($hp['percentage']), 1); }
            ?>
                <svg viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="<?= te('Line chart of your last {n} quiz marks', ['n' => $n]) ?>">
                    <?php foreach ([0, 25, 50, 75, 100] as $g): ?>
                        <line x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" stroke="var(--divider)" stroke-width="1"/>
                        <text x="<?= $padL - 6 ?>" y="<?= $y($g) + 3 ?>" text-anchor="end"><?= $g ?></text>
                    <?php endforeach; ?>
                    <line x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= $y(50) ?>" y2="<?= $y(50) ?>" stroke="var(--danger)" stroke-width="1" stroke-dasharray="4 4" opacity=".6"/>
                    <polyline points="<?= implode(' ', $points) ?>" fill="none" stroke="var(--primary)" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                    <?php foreach ($history as $i => $hp): $best = $hp['percentage'] === (float) $motivation['best']; ?>
                        <circle cx="<?= round($x($i), 1) ?>" cy="<?= round($y($hp['percentage']), 1) ?>" r="<?= $best ? 6 : 4.5 ?>"
                                fill="<?= $best ? 'var(--accent)' : '#fff' ?>" stroke="var(--primary)" stroke-width="2.5">
                            <title><?= $e($hp['title']) ?>: <?= round($hp['percentage']) ?>%</title>
                        </circle>
                        <text x="<?= round($x($i), 1) ?>" y="<?= $h - 8 ?>" text-anchor="middle"><?= $e(lang_date('j M', (string) $hp['submitted_at'])) ?></text>
                    <?php endforeach; ?>
                </svg>
                <p class="text-sm text-muted" style="margin:.5rem 0 0;"><?= te('The dashed line is the pass mark. The yellow dot is your best.') ?></p>
            <?php endif; ?>
        </div>

        <!-- Achievements -->
        <div class="pg-card">
            <h3>&#127942; <?= te('Achievements') ?> <span class="text-muted" style="font-weight:500; font-size:.85rem;">&middot; <?= te('{a} of {b} unlocked', ['a' => $earnedCount, 'b' => count($badges)]) ?></span></h3>
            <div class="pg-badges">
                <?php foreach ($badges as $b):
                    // The original 8 badges' wording still comes from $badgeText
                    // (same text, kept so nothing changes for them); the newer
                    // achievements supply their own title/done/how, built the
                    // same way, from AchievementService.
                    [$title, $earnedText, $howText] = $badgeText[$b['key']] ?? [$b['title'], $b['done'], $b['how']];
                ?>
                    <div class="pg-badge <?= $b['earned'] ? 'pg-badge--earned' : 'pg-badge--locked' ?>"
                         title="<?= $b['earned'] ? te($earnedText) : te('How to unlock: {how}', ['how' => t($howText)]) ?>">
                        <?php if ($b['earned']): ?><span class="pg-badge__tick">&#10003;</span><?php endif; ?>
                        <div class="pg-badge__icon"><?= $b['icon'] ?></div>
                        <div class="pg-badge__t"><?= te($title) ?></div>
                        <div class="pg-badge__d"><?= $b['earned'] ? te($earnedText) : '&#128274; ' . te($howText) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div>
        <!-- What the score is made of -->
        <div class="pg-card">
            <h3>&#129513; <?= te('What makes up your score') ?></h3>
            <?php if (!$hasData): ?>
                <p class="text-muted" style="margin:0;"><?= te('This fills in once there are marks, attendance or material.') ?></p>
            <?php else: foreach ([
                ['Quiz marks', $overall['marks']['quizzes_taken'] > 0 ? (float) $overall['marks']['average_percentage'] : null, '60%', 'var(--primary)'],
                ['Attendance', $overall['attendance']['sessions_recorded'] > 0 ? (float) $overall['attendance']['rate'] : null, '25%', 'var(--success)'],
                ['Material opened', $overall['engagement']['materials_total'] > 0 ? (float) $overall['engagement']['rate'] : null, '15%', 'var(--accent)'],
            ] as [$label, $value, $weight, $colour]): ?>
                <div class="pg-part__row">
                    <span><?= te($label) ?> <span class="text-muted" style="font-size:.75rem;">&middot; <?= te('counts {w}', ['w' => $weight]) ?></span></span>
                    <strong><?= $value !== null ? round($value) . '%' : '&ndash;' ?></strong>
                </div>
                <div class="pg-part__bar"><span style="width:<?= $value !== null ? min(100, max(0, $value)) : 0 ?>%; background:<?= $colour ?>;"></span></div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Per class -->
        <?php foreach ($classes as $c): $p = $c['progress']; ?>
            <div class="pg-card">
                <h3 style="margin-bottom:.3rem;"><?= $e($c['class_name']) ?></h3>
                <?php if (empty($p['has_data'])): ?>
                    <p class="text-muted" style="margin:0;"><?= te('No marks or attendance in this class yet.') ?></p>
                <?php else: ?>
                    <div style="display:flex; align-items:baseline; gap:.5rem; margin-bottom:.6rem;">
                        <span style="font-size:1.7rem; font-weight:800; color:<?= $levelColour((string) $p['level']) ?>;"><?= round((float) $p['score']) ?></span>
                        <span style="font-weight:700; color:<?= $levelColour((string) $p['level']) ?>;"><?= te((string) $p['level']) ?></span>
                    </div>
                    <div class="text-sm text-muted">
                        <?= te('Quiz average') ?>: <strong><?= $p['marks']['quizzes_taken'] > 0 ? round((float) $p['marks']['average_percentage']) . '%' : '&ndash;' ?></strong>
                        &middot; <?= te('Attendance') ?>: <strong><?= $p['attendance']['sessions_recorded'] > 0 ? round((float) $p['attendance']['rate']) . '%' : '&ndash;' ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
