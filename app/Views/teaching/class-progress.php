<?php
/**
 * One class, every student, worst first.
 *
 * Variables: $class, $rows, $needAttention, $noData, $classAverage, $total
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$toneClass = [
    'success' => 'cp-pill--good',
    'info'    => 'cp-pill--ok',
    'warning' => 'cp-pill--warn',
    'danger'  => 'cp-pill--bad',
    'muted'   => 'cp-pill--none',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class progress') ?></h1>
        <p class="text-sm text-muted">
            <?= $e($class['class_name']) ?>
            <?php if (!empty($class['institute_name']) && stripos((string) $class['class_name'], (string) $class['institute_name']) === false): ?>
                &middot; <?= $e($class['institute_name']) ?>
            <?php endif; ?>
            <?php /* The class name usually already carries the grade, and
                     often the branch too. Printing them again reads as a
                     mistake rather than as detail. */ ?>
            <?php if (!empty($class['grade']) && stripos((string) $class['class_name'], (string) $class['grade']) === false): ?>
                &middot; <?= $e($class['grade']) ?>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn--secondary" href="<?= BASE_URL ?>/teaching">&larr; <?= te('Back to teaching') ?></a>
</div>

<div class="cp-stats">
    <div class="cp-stat">
        <div class="cp-stat__n"><?= (int) $total ?></div>
        <div class="cp-stat__l"><?= te('Students') ?></div>
    </div>
    <div class="cp-stat<?= $needAttention > 0 ? ' is-bad' : '' ?>">
        <div class="cp-stat__n"><?= (int) $needAttention ?></div>
        <div class="cp-stat__l"><?= te('Need attention') ?></div>
    </div>
    <div class="cp-stat<?= $noData > 0 ? ' is-warn' : '' ?>">
        <div class="cp-stat__n"><?= (int) $noData ?></div>
        <div class="cp-stat__l"><?= te('Nothing on record yet') ?></div>
    </div>
    <div class="cp-stat">
        <div class="cp-stat__n"><?= $classAverage === null ? '&mdash;' : $e($classAverage) ?></div>
        <div class="cp-stat__l"><?= te('Class average') ?></div>
    </div>
</div>

<?php if ($rows === []): ?>
    <div class="card"><p class="text-muted" style="margin:0;"><?= te('No students are enrolled in this class yet.') ?></p></div>
<?php else: ?>
<div class="card" style="padding:0; overflow:hidden;">
    <table class="cp-table">
        <thead>
            <tr>
                <th><?= te('Student') ?></th>
                <th><?= te('How they are doing') ?></th>
                <th><?= te('Quizzes') ?></th>
                <th><?= te('Attendance') ?></th>
                <th><?= te('Material opened') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php $p = $r['progress']; $s = $r['student']; ?>
            <tr>
                <td>
                    <div class="cp-who">
                        <span class="cp-avatar"><?= $e(mb_strtoupper(mb_substr((string) $s['full_name'], 0, 1))) ?></span>
                        <span>
                            <span class="cp-name"><?= $e($s['full_name']) ?></span>
                            <span class="cp-id"><?= $e($s['student_id_display']) ?></span>
                        </span>
                    </div>
                </td>
                <td>
                    <span class="cp-pill <?= $toneClass[$p['tone']] ?? 'cp-pill--none' ?>">
                        <?= te($p['level']) ?><?php if ($p['has_data']): ?> &middot; <?= $e($p['score']) ?><?php endif; ?>
                    </span>
                    <div class="cp-summary"><?= te($p['summary']) ?></div>
                </td>
                <td>
                    <?php if ($p['marks']['quizzes_taken'] > 0): ?>
                        <b><?= $e(round((float) $p['marks']['average_percentage'])) ?>%</b>
                        <span class="cp-sub"><?= te('{n} sat', ['n' => (int) $p['marks']['quizzes_taken']]) ?></span>
                    <?php else: ?>
                        <span class="cp-sub"><?= te('None yet') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['attendance']['sessions_recorded'] > 0): ?>
                        <b><?= $e(round((float) $p['attendance']['rate'])) ?>%</b>
                        <span class="cp-sub"><?= te('of {n}', ['n' => (int) $p['attendance']['sessions_recorded']]) ?></span>
                    <?php else: ?>
                        <span class="cp-sub"><?= te('None yet') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['engagement']['materials_total'] > 0): ?>
                        <b><?= $e(round((float) $p['engagement']['rate'])) ?>%</b>
                        <span class="cp-sub"><?= te('of {n}', ['n' => (int) $p['engagement']['materials_total']]) ?></span>
                    <?php else: ?>
                        <span class="cp-sub"><?= te('None posted') ?></span>
                    <?php endif; ?>
                </td>
                <td class="cp-go">
                    <a class="btn btn--ghost btn--xs" href="<?= BASE_URL ?>/students/<?= (int) $s['id'] ?>"><?= te('Open') ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="cp-note">
    <?= te('Worst first, so the students who need you are at the top. "Nothing on record yet" is listed first of all -- a child with no quiz, no attendance and nothing opened is not doing fine, they are invisible.') ?>
</p>
<?php endif; ?>

<style>
.cp-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.75rem; margin-bottom:1.1rem; }
.cp-stat { background:var(--card-bg,#fff); border:1px solid var(--border-color,#E2E8F0); border-radius:12px; padding:.85rem 1rem; }
.cp-stat__n { font-size:1.6rem; font-weight:800; color:var(--text,#1C2538); line-height:1.1; }
.cp-stat__l { font-size:.78rem; color:#64748B; margin-top:.15rem; }
.cp-stat.is-bad { border-color:#FCA5A5; background:#FEF2F2; } .cp-stat.is-bad .cp-stat__n { color:#B3261E; }
.cp-stat.is-warn { border-color:#FED7AA; background:#FFF7ED; } .cp-stat.is-warn .cp-stat__n { color:#9A3412; }

.cp-table { width:100%; border-collapse:collapse; font-size:.86rem; }
.cp-table th { text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em;
               color:#64748B; padding:.6rem .9rem; border-bottom:1px solid var(--border-color,#E2E8F0); white-space:nowrap; }
.cp-table td { padding:.7rem .9rem; border-bottom:1px solid #F1F5F9; vertical-align:top; }
.cp-table tr:last-child td { border-bottom:0; }

.cp-who { display:flex; gap:.55rem; align-items:center; }
.cp-avatar { flex:none; width:30px; height:30px; border-radius:50%; background:#EEF2FF; color:#15307E;
             display:grid; place-items:center; font-weight:800; font-size:.8rem; }
.cp-name { display:block; font-weight:600; }
.cp-id { display:block; font-size:.72rem; color:#94A3B8; }

.cp-pill { display:inline-block; font-size:.72rem; font-weight:800; border-radius:999px; padding:.15rem .6rem; }
.cp-pill--good { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; }
.cp-pill--ok   { background:#EFF6FF; color:#15307E; border:1px solid #BFDBFE; }
.cp-pill--warn { background:#FFF7ED; color:#9A3412; border:1px solid #FED7AA; }
.cp-pill--bad  { background:#FEF2F2; color:#B3261E; border:1px solid #FCA5A5; }
.cp-pill--none { background:#F1F5F9; color:#475569; border:1px solid #E2E8F0; }
.cp-summary { font-size:.74rem; color:#64748B; margin-top:.25rem; max-width:34ch; line-height:1.4; }
.cp-sub { display:block; font-size:.72rem; color:#94A3B8; }
.cp-go { text-align:right; white-space:nowrap; }
.cp-note { font-size:.78rem; color:#64748B; margin:.7rem .2rem 0; line-height:1.55; }
</style>
