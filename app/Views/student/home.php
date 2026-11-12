<?php
/**
 * A student's home. Everything here is about the student logged in --
 * no institute totals, no other students.
 */

$student       = $student ?? [];
$nextClass     = $nextClass ?? null;
$comingUp      = $comingUp ?? [];
$classes       = $classes ?? [];
$quizzes       = $quizzes ?? [];
$materials     = $materials ?? [];
$homework      = $homework ?? [];
$homeworkLocked = $homeworkLocked ?? 0;
$marks         = $marks ?? [];
$progress      = $progress ?? ['has_data' => false];
$notifications = $notifications ?? [];
$suggestions   = $suggestions ?? [];

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$hour = (int) $now->format('G');
$greeting = $hour < 12 ? t('Good morning') : ($hour < 17 ? t('Good afternoon') : t('Good evening'));
$first = explode(' ', trim((string) ($student['full_name'] ?? 'there')))[0];

$owed = array_sum(array_column($classes, 'owed'));
$avgMark = $marks !== [] ? round(array_sum(array_column($marks, 'percentage')) / count($marks)) : null;
$attendanceRate = ($progress['attendance']['sessions_recorded'] ?? 0) > 0 ? round((float) $progress['attendance']['rate']) : null;

$score = !empty($progress['has_data']) ? (float) $progress['score'] : 0.0;
$ringLength = 2 * M_PI * 52;
$ringFill = $ringLength * min(100, max(0, $score)) / 100;

$kind = [
    'recording' => ['Recording', '&#9654;'],
    'reading'   => ['Notes', '&#128196;'],
    'video'     => ['Video', '&#127916;'],
    'link'      => ['Link', '&#128279;'],
];

$whenLabel = static function (array $item) use ($now): string {
    $date = new DateTimeImmutable($item['date']);
    $diff = (int) $now->setTime(0, 0)->diff($date)->format('%r%a');
    $day = $diff === 0 ? t('Today') : ($diff === 1 ? t('Tomorrow') : lang_date('D j M', $date->getTimestamp()));
    return $item['start_time'] !== null ? "{$day}, {$item['start_time']}" : $day;
};
?>

<style>
.sh-hero { position:relative; overflow:hidden; border-radius:18px; padding:1.75rem 1.75rem 1.5rem;
  background:linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%); color:#fff; margin-bottom:1.5rem; }
.sh-hero::after { content:""; position:absolute; right:-60px; top:-60px; width:220px; height:220px;
  border-radius:50%; background:var(--accent); opacity:.18; }
.sh-hero h1 { margin:0; font-size:1.7rem; color:#fff; }
.sh-hero .sh-id { display:inline-block; margin-top:.4rem; padding:.15rem .6rem; border-radius:999px;
  background:rgba(255,255,255,.15); font-size:.8rem; letter-spacing:.03em; }
.sh-next { position:relative; z-index:1; margin-top:1.25rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:14px; padding:1rem 1.15rem; }
.sh-next__label { font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; opacity:.8; }
.sh-next__title { font-size:1.15rem; font-weight:700; }
.sh-next__count { margin-left:auto; text-align:right; }
.sh-next__count strong { display:block; font-size:1.5rem; color:var(--accent); line-height:1.1; }
.sh-lock { display:inline-block; margin-top:.35rem; font-size:.8rem; background:rgba(245,179,1,.22);
  padding:.15rem .55rem; border-radius:6px; }
.sh-join { display:inline-flex; align-items:center; gap:.45rem; margin-top:.6rem; padding:.45rem .9rem;
  border-radius:999px; font-size:.85rem; font-weight:600; text-decoration:none;
  background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35); color:#fff; }
.sh-join:hover { background:rgba(255,255,255,.28); }
.sh-join--live { background:#16A34A; border-color:#16A34A; }
.sh-join--live:hover { background:#15803D; }
.sh-join__dot { width:.5rem; height:.5rem; border-radius:50%; background:#fff;
  animation:shPulse 1.6s ease-in-out infinite; }
@keyframes shPulse { 0%,100% { opacity:1; } 50% { opacity:.3; } }
@media (prefers-reduced-motion: reduce) { .sh-join__dot { animation:none; } }

.sh-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.sh-stat { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1rem 1.1rem; }
.sh-stat__n { font-size:1.65rem; font-weight:700; line-height:1.1; color:var(--text); }
.sh-stat__l { font-size:.8rem; color:var(--text-muted); margin-top:.2rem; }

.sh-grid { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(0,1fr); gap:1.25rem; align-items:start; }
@media (max-width: 992px) { .sh-grid { grid-template-columns:minmax(0,1fr); } }
/* Let the columns shrink on a phone instead of widening the page. */
.sh-grid > * { min-width:0; }
.sh-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.15rem 1.25rem; margin-bottom:1.25rem; }
.sh-card h3 { margin:0 0 .85rem; font-size:1rem; display:flex; align-items:center; gap:.5rem; }
.sh-card h3 .sh-count { margin-left:auto; font-size:.75rem; font-weight:600; color:var(--text-muted); }
.sh-empty { color:var(--text-muted); font-size:.9rem; margin:0; }

.sh-row { display:flex; gap:.75rem; align-items:center; padding:.6rem 0; border-top:1px solid var(--border); }
.sh-row:first-of-type { border-top:none; padding-top:0; }
.sh-row__icon { flex:0 0 38px; height:38px; border-radius:10px; display:grid; place-items:center;
  background:var(--surface-sunken); font-size:1rem; }
.sh-row__main { min-width:0; flex:1; }
.sh-row__title { font-weight:600; font-size:.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sh-row__meta { font-size:.8rem; color:var(--text-muted); }
.sh-dot { width:8px; height:8px; border-radius:50%; background:var(--accent); flex:0 0 8px; }

.sh-pill { font-size:.72rem; font-weight:600; padding:.18rem .55rem; border-radius:999px; white-space:nowrap; }
.sh-pill--ok { background:rgba(21,128,61,.12); color:var(--success); }
.sh-pill--due { background:rgba(180,35,24,.10); color:var(--danger); }
.sh-pill--free { background:rgba(29,78,216,.10); color:var(--primary); }
.sh-pill--wait { background:var(--surface-sunken); color:var(--text-muted); }

.sh-classes { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.85rem; }
.sh-class { display:block; text-decoration:none; color:inherit; border:1px solid var(--border); border-radius:12px;
  padding:.9rem 1rem; border-top:4px solid var(--primary); transition:transform .15s, box-shadow .15s; }
.sh-class:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(15,23,42,.08); }
.sh-class--locked { border-top-color:var(--accent); }

.sh-ring { display:flex; gap:1.1rem; align-items:center; }
.sh-ring svg { flex:0 0 120px; }
.sh-bar { height:8px; border-radius:6px; background:var(--surface-sunken); overflow:hidden; margin:.25rem 0 .6rem; }
.sh-bar > span { display:block; height:100%; background:var(--primary); border-radius:6px; }

.sh-marks .sh-bar > span { background:var(--success); }
.sh-marks .sh-bar--low > span { background:var(--danger); }

.sh-ask { background:linear-gradient(135deg, rgba(29,78,216,.07), rgba(245,179,1,.10)); }
.sh-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
.sh-chip { border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:999px;
  padding:.35rem .75rem; font-size:.8rem; cursor:pointer; }
.sh-chip:hover { border-color:var(--primary); color:var(--primary); }

.sh-banner { display:flex; gap:.75rem; align-items:center; padding:.85rem 1rem; border-radius:12px; margin-bottom:1.25rem;
  background:rgba(245,179,1,.14); border:1px solid rgba(245,179,1,.4); color:var(--text); }
</style>

<!-- Hero: who, and when the next class is -->
<section class="sh-hero">
    <h1><?= $e($greeting) ?>, <?= $e($first) ?> &#128075;</h1>
    <span class="sh-id"><?= $e($student['student_id_display'] ?? '') ?></span>

    <?php if ($nextClass !== null): ?>
        <div class="sh-next">
            <div>
                <div class="sh-next__label"><?= te('Next class') ?></div>
                <div class="sh-next__title"><?= $e($nextClass['title']) ?></div>
                <div style="opacity:.9; font-size:.9rem;">
                    <?= $e($whenLabel($nextClass)) ?><?= $nextClass['end_time'] ? ' &ndash; ' . $e($nextClass['end_time']) : '' ?>
                    <?= $nextClass['room'] ? ' &middot; ' . te('Room {r}', ['r' => $nextClass['room']]) : '' ?>
                </div>
                <?php if (!$nextIsOpen): ?>
                    <span class="sh-lock">&#128274; <?= te("This month's notes and recordings for it are locked until the fee is settled") ?></span>
                <?php endif; ?>
                <?php
                // The joining link for an online class, which until now was
                // stored against the class but never shown to the student it
                // was meant for. Only an http(s) link is ever turned into a
                // button, and only for a class this student is scoped to.
                $joinUrl = null;
                if (($nextClass['delivery_mode'] ?? 'physical') !== 'physical') {
                    $candidate = trim((string) ($nextClass['meeting_url'] ?? ''));
                    if ($candidate !== '' && preg_match('#^https?://#i', $candidate) === 1) {
                        $joinUrl = $candidate;
                    }
                }
                $isLive = false;
                if ($joinUrl !== null) {
                    $startsAt = new DateTimeImmutable($nextClass['starts_at']);
                    $endsAt = $nextClass['end_time'] !== null
                        ? new DateTimeImmutable($nextClass['date'] . ' ' . $nextClass['end_time'])
                        : $startsAt->modify('+2 hours');
                    // Open the door a few minutes early, the way a hall does.
                    $isLive = $now >= $startsAt->modify('-15 minutes') && $now <= $endsAt;
                }
                ?>
                <?php if ($joinUrl !== null): ?>
                    <a class="sh-join<?= $isLive ? ' sh-join--live' : '' ?>"
                       href="<?= $e($joinUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <?php if ($isLive): ?>
                            <span class="sh-join__dot" aria-hidden="true"></span><?= te('Join the class now') ?>
                        <?php else: ?>
                            &#128250; <?= te('Online class link') ?>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="sh-next__count">
                <strong data-countdown="<?= $e(str_replace(' ', 'T', $nextClass['starts_at'])) ?>"
                        data-now="<?= $e($now->format('Y-m-d\TH:i:s')) ?>">&nbsp;</strong>
                <span style="font-size:.8rem; opacity:.85;"><?= te('to go') ?></span>
            </div>
        </div>
    <?php else: ?>
        <div class="sh-next"><div><?= te('No classes on the calendar for the next two weeks.') ?></div></div>
    <?php endif; ?>
</section>

<?php if ($owed > 0): ?>
    <div class="sh-banner">
        <span style="font-size:1.3rem;">&#128274;</span>
        <div>
            <strong><?= te('Rs. {n} is due for {month}.', ['n' => number_format($owed, 2), 'month' => lang_date('F', $now->getTimestamp())]) ?></strong>
            <?= te("The classes still run, but their notes, recordings and quizzes open once it's paid.") ?>
        </div>
    </div>
<?php endif; ?>

<!-- Level, a real motivational message, next steps, streaks, achievements
     and goals -- all from this student's own record. -->
<?php include __DIR__ . '/_motivation.php'; ?>

<!-- Four numbers, all about this student -->
<div class="sh-stats">
    <div class="sh-stat">
        <div class="sh-stat__n"><?= count($classes) ?></div>
        <div class="sh-stat__l"><?= te('My Classes') ?></div>
    </div>
    <div class="sh-stat">
        <div class="sh-stat__n" style="color:<?= count($quizzes) > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= count($quizzes) ?></div>
        <div class="sh-stat__l"><?= count($quizzes) === 1 ? te('Quiz waiting') : te('Quizzes waiting') ?></div>
    </div>
    <div class="sh-stat">
        <div class="sh-stat__n" style="color:<?= count($homework) > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= count($homework) ?></div>
        <div class="sh-stat__l"><?= te('Homework due') ?></div>
    </div>
    <div class="sh-stat">
        <div class="sh-stat__n"><?= $avgMark !== null ? $avgMark . '%' : '&ndash;' ?></div>
        <div class="sh-stat__l"><?= te('Average, last {n} quizzes', ['n' => count($marks)]) ?></div>
    </div>
    <div class="sh-stat">
        <div class="sh-stat__n"><?= $attendanceRate !== null ? $attendanceRate . '%' : '&ndash;' ?></div>
        <div class="sh-stat__l"><?= te('Attendance') ?></div>
    </div>
</div>

<div class="sh-grid">
    <div>
        <!-- Things to do -->
        <div class="sh-card">
            <h3>&#9997;&#65039; <?= te('Quizzes waiting for you') ?> <span class="sh-count"><?= count($quizzes) ?></span></h3>
            <?php if ($quizzes === []): ?>
                <p class="sh-empty"><?= te("You're all caught up.") ?> <?= $lockedQuizzes > 0 ? te("{n} more locked until this month's fee is settled.", ['n' => $lockedQuizzes]) : '' ?></p>
            <?php else: foreach ($quizzes as $q): ?>
                <div class="sh-row">
                    <div class="sh-row__icon">&#10067;</div>
                    <div class="sh-row__main">
                        <div class="sh-row__title"><?= $e($q['title']) ?></div>
                        <div class="sh-row__meta">
                            <?= $e($q['class_name']) ?> &middot; <?= te('closes {when}', ['when' => lang_date('D j M, g:ia', (string) $q['closes_at'])]) ?>
                            <?= $q['time_limit_minutes'] ? ' &middot; ' . te('{n} min', ['n' => (int) $q['time_limit_minutes']]) : '' ?>
                        </div>
                    </div>
                    <a class="btn btn--primary btn--sm" href="<?= BASE_URL ?>/my/quizzes/<?= (int) $q['id'] ?>/sit"><?= te('Start') ?></a>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Homework to hand in -->
        <div class="sh-card">
            <h3>&#128221; <?= te('Homework to hand in') ?> <span class="sh-count"><?= count($homework) ?></span></h3>
            <?php if ($homework === []): ?>
                <p class="sh-empty"><?= te("Nothing waiting to be handed in.") ?> <?= $homeworkLocked > 0 ? te("{n} more locked until this month's fee is settled.", ['n' => $homeworkLocked]) : '' ?></p>
            <?php else: foreach ($homework as $hw):
                $overdue = strtotime((string) $hw['due_at']) < $now->getTimestamp();
                ?>
                <div class="sh-row">
                    <div class="sh-row__icon" style="<?= $overdue ? 'background:rgba(180,35,24,.10);' : '' ?>">&#128221;</div>
                    <div class="sh-row__main">
                        <div class="sh-row__title"><?= $e($hw['title']) ?></div>
                        <div class="sh-row__meta">
                            <?= $e($hw['class_name']) ?> &middot;
                            <?php if ($overdue): ?>
                                <span style="color:var(--danger);"><?= te('was due {when}', ['when' => lang_date('D j M', (string) $hw['due_at'])]) ?></span>
                            <?php else: ?>
                                <?= te('due {when}', ['when' => lang_date('D j M', (string) $hw['due_at'])]) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a class="btn btn--primary btn--sm" href="<?= BASE_URL ?>/my/assignments/<?= (int) $hw['id'] ?>"><?= te('Hand in') ?></a>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- New material -->
        <div class="sh-card">
            <h3>&#10024; <?= te('New for you') ?></h3>
            <?php if ($materials === []): ?>
                <p class="sh-empty"><?= te("Nothing new yet. Recordings and notes appear here as soon as they're posted.") ?></p>
            <?php else: foreach ($materials as $m): [$label, $icon] = $kind[$m['material_type']] ?? ['Material', '&#128196;']; ?>
                <div class="sh-row">
                    <div class="sh-row__icon"><?= $icon ?></div>
                    <div class="sh-row__main">
                        <div class="sh-row__title"><?= $e($m['title']) ?></div>
                        <div class="sh-row__meta">
                            <?= te($label) ?> &middot; <?= $e($m['class_name']) ?> &middot; <?= $e(lang_date('j M', (string) $m['created_at'])) ?>
                            <?= $m['duration_minutes'] ? ' &middot; ' . te('{n} min', ['n' => (int) $m['duration_minutes']]) : '' ?>
                        </div>
                    </div>
                    <?php if ((int) $m['seen'] === 0): ?><span class="sh-dot" title="<?= te('Not opened yet') ?>"></span><?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/my/materials/<?= (int) $m['id'] ?>/open"><?= te('Open') ?></a>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Classes -->
        <div class="sh-card">
            <h3>&#128218; <?= te('My Classes') ?></h3>
            <?php if ($classes === []): ?>
                <p class="sh-empty"><?= te("You're not enrolled in any classes yet.") ?></p>
            <?php else: ?>
                <div class="sh-classes">
                    <?php foreach ($classes as $c): ?>
                        <a class="sh-class <?= $c['open'] ? '' : 'sh-class--locked' ?>" href="<?= BASE_URL ?>/my/classes/<?= (int) $c['id'] ?>">
                            <div style="font-weight:700;"><?= $e($c['class_name']) ?></div>
                            <div class="sh-row__meta" style="margin:.25rem 0 .6rem;">
                                <?= $e(lang_names((string) $c['days'])) ?><?= $c['start_time'] ? ' &middot; ' . $e(substr((string) $c['start_time'], 0, 5)) : '' ?>
                                <?= $c['institute_name'] ? ' &middot; ' . $e($c['institute_name']) : '' ?>
                            </div>
                            <?php if (($c['scholarship_type'] ?? '') === 'free_card'): ?>
                                <span class="sh-pill sh-pill--free"><?= te('Free card') ?></span>
                            <?php elseif (in_array($c['payment_status'], ['paid', 'waived'], true)): ?>
                                <span class="sh-pill sh-pill--ok">&#10003; <?= te('{month} paid', ['month' => lang_date('F', $now->getTimestamp())]) ?></span>
                            <?php elseif ($c['payment_status'] === null): ?>
                                <span class="sh-pill sh-pill--wait"><?= te('Fee not issued yet') ?></span>
                            <?php else: ?>
                                <span class="sh-pill sh-pill--due">&#128274; <?= te('Rs. {n} due', ['n' => number_format((float) $c['owed'], 0)]) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <!-- Progress -->
        <div class="sh-card">
            <h3>&#127919; <?= te('My progress') ?></h3>
            <?php if (empty($progress['has_data'])): ?>
                <p class="sh-empty"><?= te('Your level appears once you have quiz marks or attendance.') ?></p>
            <?php else: ?>
                <div class="sh-ring">
                    <svg width="120" height="120" viewBox="0 0 120 120" role="img"
                         aria-label="<?= te('Overall score {n} out of 100', ['n' => round($score)]) ?>">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--surface-sunken)" stroke-width="12"/>
                        <circle cx="60" cy="60" r="52" fill="none" stroke="var(--primary)" stroke-width="12"
                                stroke-linecap="round" transform="rotate(-90 60 60)"
                                stroke-dasharray="<?= round($ringFill, 1) ?> <?= round($ringLength, 1) ?>"/>
                        <text x="60" y="58" text-anchor="middle" font-size="26" font-weight="700" fill="var(--text)"><?= round($score) ?></text>
                        <text x="60" y="78" text-anchor="middle" font-size="11" fill="var(--text-muted)"><?= te('out of 100') ?></text>
                    </svg>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:1.15rem; font-weight:700; color:var(--primary);"><?= te((string) $progress['level']) ?></div>
                        <?php foreach ([
                            ['Quiz marks', $progress['marks']['quizzes_taken'] > 0 ? $progress['marks']['average_percentage'] : null],
                            ['Attendance', $progress['attendance']['sessions_recorded'] > 0 ? $progress['attendance']['rate'] : null],
                            ['Material opened', $progress['engagement']['materials_total'] > 0 ? $progress['engagement']['rate'] : null],
                        ] as [$label, $value]): ?>
                            <div class="sh-row__meta" style="display:flex; justify-content:space-between;">
                                <span><?= te($label) ?></span><span><?= $value !== null ? round((float) $value) . '%' : '&ndash;' ?></span>
                            </div>
                            <div class="sh-bar"><span style="width:<?= $value !== null ? min(100, max(0, (float) $value)) : 0 ?>%;"></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/my/progress" class="text-sm"><?= te("See how it's worked out") ?> &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Coming up -->
        <div class="sh-card">
            <h3>&#128197; <?= te('Coming up') ?></h3>
            <?php if ($comingUp === []): ?>
                <p class="sh-empty"><?= te('No tests, deadlines or extra classes in the next two weeks.') ?></p>
            <?php else: foreach ($comingUp as $item):
                $isExam = in_array($item['event_type'] ?? '', ['exam', 'test'], true);
                ?>
                <div class="sh-row">
                    <div class="sh-row__icon" style="<?= $isExam || $item['type'] === 'quiz_closes' ? 'background:rgba(180,35,24,.10);' : '' ?>">
                        <?= $isExam ? '&#128221;' : ($item['type'] === 'quiz_closes' ? '&#9200;' : '&#128204;') ?>
                    </div>
                    <div class="sh-row__main">
                        <div class="sh-row__title"><?= $e($item['title']) ?></div>
                        <div class="sh-row__meta"><?= te((string) $item['detail']) ?> &middot; <?= $e($whenLabel($item)) ?></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
            <a href="<?= BASE_URL ?>/my/calendar" class="text-sm"><?= te('Open my calendar') ?> &rarr;</a>
        </div>

        <!-- Recent marks -->
        <div class="sh-card sh-marks">
            <h3>&#128202; <?= te('Recent marks') ?></h3>
            <?php if ($marks === []): ?>
                <p class="sh-empty"><?= te('No marked quizzes yet.') ?></p>
            <?php else: foreach ($marks as $m): $pct = (float) $m['percentage']; ?>
                <div class="sh-row__meta" style="display:flex; justify-content:space-between; gap:.5rem;">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= $e($m['title']) ?></span>
                    <strong style="color:var(--text);"><?= round($pct) ?>%</strong>
                </div>
                <div class="sh-bar <?= $pct < 50 ? 'sh-bar--low' : '' ?>"><span style="width:<?= min(100, max(0, $pct)) ?>%;"></span></div>
            <?php endforeach; ?>
                <a href="<?= BASE_URL ?>/my/marks" class="text-sm"><?= te('All my marks') ?> &rarr;</a>
            <?php endif; ?>
        </div>

        <!-- Ask the assistant -->
        <div class="sh-card sh-ask">
            <h3>&#129302; <?= te('Ask me anything about your classes') ?></h3>
            <p class="sh-row__meta" style="margin:-.4rem 0 .75rem;"><?= te('English, Sinhala or Singlish — "ilanga panthiya kawadada?" works too.') ?></p>
            <div class="sh-chips">
                <?php foreach (array_slice($suggestions, 0, 4) as $s): ?>
                    <button type="button" class="sh-chip" data-ask="<?= $e($s) ?>"><?= te($s) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($notifications !== []): ?>
            <div class="sh-card">
                <h3>&#128276; <?= te('Latest notifications') ?></h3>
                <?php foreach ($notifications as $n): ?>
                    <div class="sh-row">
                        <?php if (!(int) $n['is_read']): ?><span class="sh-dot"></span><?php endif; ?>
                        <div class="sh-row__main">
                            <div class="sh-row__title" style="font-weight:<?= (int) $n['is_read'] ? 500 : 700 ?>;"><?= $e($n['title']) ?></div>
                            <div class="sh-row__meta"><?= $e(lang_date('j M, g:ia', (string) $n['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Countdown to the next class. Measured from the server's clock at render
// time plus elapsed browser time, so a laptop with a wrong clock still
// counts down correctly.
(function () {
    var el = document.querySelector('[data-countdown]');
    if (!el) return;
    var target = new Date(el.getAttribute('data-countdown')).getTime();
    var serverNow = new Date(el.getAttribute('data-now')).getTime();
    var started = Date.now();

    function render() {
        var ms = target - (serverNow + (Date.now() - started));
        if (ms <= 0) { el.textContent = <?= json_encode(t('Now')) ?>; return; }
        var m = Math.floor(ms / 60000), d = Math.floor(m / 1440), h = Math.floor((m % 1440) / 60), mm = m % 60;
        el.textContent = d > 0 ? d + 'd ' + h + 'h' : (h > 0 ? h + 'h ' + mm + 'm' : mm + ' min');
    }
    render();
    setInterval(render, 30000);
})();

// The suggestion chips on this page open the assistant with that question.
document.querySelectorAll('[data-ask]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (window.StudyAssistant) window.StudyAssistant.ask(btn.getAttribute('data-ask'), btn.textContent.trim());
    });
});
</script>
