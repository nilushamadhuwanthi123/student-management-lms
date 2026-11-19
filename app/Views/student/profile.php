<?php
/**
 * A student's profile: who they are, their classes, their fees with a
 * receipt for each paid month, recent marks, and a report they can
 * build and download themselves.
 */

$student    = $student ?? [];
$guardians  = $guardians ?? [];
$classes    = $classes ?? [];
$payments   = $payments ?? [];
$marks      = $marks ?? [];
$summary    = $summary ?? [];
$progress   = $progress ?? ['has_data' => false];
$motivation = $motivation ?? [];
$report     = $report ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$name = (string) ($student['full_name'] ?? '');
$initials = implode('', array_map(static fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2)));
$firstClass = $classes[0] ?? null;
$earned = count(array_filter($motivation['badges'] ?? [], static fn ($b) => $b['earned']));
$statusLabel = ['paid' => 'Paid', 'waived' => 'Waived', 'pending' => 'Pending', 'overdue' => 'Overdue'];
$statusClass = ['paid' => 'pf-pill--ok', 'waived' => 'pf-pill--ok', 'pending' => 'pf-pill--wait', 'overdue' => 'pf-pill--due'];
?>
<?php include __DIR__ . '/../components/fee-due-bar.php'; ?>

<style>
.pf-hero { display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap; padding:1.5rem 1.6rem; border-radius:20px; color:#fff;
  background:linear-gradient(135deg, var(--primary) 0%, var(--primary-deep) 100%); margin-bottom:1.25rem; position:relative; overflow:hidden; }
.pf-hero::after { content:""; position:absolute; right:-70px; top:-90px; width:260px; height:260px; border-radius:50%; background:var(--accent); opacity:.15; }
.pf-avatar { width:84px; height:84px; border-radius:50%; background:var(--accent); color:#1a1300; font-size:1.9rem; font-weight:800;
  display:grid; place-items:center; flex:0 0 84px; box-shadow:0 0 0 5px rgba(255,255,255,.18); position:relative; z-index:1; }
.pf-hero h1 { margin:0; color:#fff; font-size:1.6rem; }
.pf-hero__meta { opacity:.9; margin-top:.25rem; font-size:.92rem; }
.pf-chip { display:inline-block; padding:.18rem .6rem; border-radius:999px; background:rgba(255,255,255,.16); font-size:.78rem; margin:.45rem .3rem 0 0; }

.pf-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.9rem; margin-bottom:1.25rem; }
.pf-stat { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:.95rem 1.05rem; }
.pf-stat__n { font-size:1.55rem; font-weight:800; line-height:1.1; }
.pf-stat__l { font-size:.8rem; color:var(--text-muted); }

.pf-grid { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr); gap:1.25rem; align-items:start; }
@media (max-width: 1100px) { .pf-grid { grid-template-columns:minmax(0,1fr); } }
/* Grid children must be allowed to shrink, or a wide table pushes the
   whole page sideways on a phone instead of scrolling inside its card. */
.pf-grid > * { min-width:0; }
.pf-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.15rem 1.25rem; margin-bottom:1.25rem; }
.pf-card h3 { margin:0 0 .9rem; font-size:1rem; display:flex; align-items:center; gap:.45rem; }

.pf-dl { display:grid; grid-template-columns:auto 1fr; gap:.45rem 1rem; margin:0; font-size:.9rem; }
.pf-dl dt { color:var(--text-muted); }
.pf-dl dd { margin:0; font-weight:600; word-break:break-word; }

.pf-pill { font-size:.72rem; font-weight:700; padding:.18rem .55rem; border-radius:999px; white-space:nowrap; }
.pf-pill--ok { background:var(--success-light); color:var(--success); }
.pf-pill--due { background:var(--danger-light); color:var(--danger); }
.pf-pill--wait { background:var(--warning-light); color:var(--warning); }

/* The digital ID card */
.pf-id { position:relative; overflow:hidden; border-radius:16px; padding:1.1rem 1.2rem; color:#fff; aspect-ratio:1.586;
  max-width:420px; background:linear-gradient(135deg, #17348A 0%, #0F2464 100%); box-shadow:0 10px 30px rgba(15,36,100,.3); }
.pf-id::before { content:""; position:absolute; left:0; right:0; top:0; height:7px; background:var(--accent); }
.pf-id::after { content:""; position:absolute; right:-50px; bottom:-60px; width:180px; height:180px; border-radius:50%; background:rgba(245,179,1,.14); }
.pf-id__brand { display:flex; align-items:center; gap:.5rem; font-weight:800; letter-spacing:.02em; font-size:.85rem; margin-top:.2rem; }
.pf-id__body { display:flex; gap:.9rem; align-items:center; margin-top:.9rem; position:relative; z-index:1; }
.pf-id__face { width:64px; height:64px; border-radius:12px; background:var(--accent); color:#1a1300; display:grid; place-items:center; font-weight:800; font-size:1.4rem; flex:0 0 64px; }
.pf-id__name { font-weight:800; font-size:1.1rem; line-height:1.2; }
.pf-id__no { font-family:ui-monospace, Consolas, monospace; font-size:1.05rem; letter-spacing:.12em; margin-top:.35rem; color:var(--accent); }
.pf-id__foot { position:absolute; left:1.2rem; right:1.2rem; bottom:.85rem; display:flex; justify-content:space-between; font-size:.72rem; opacity:.85; z-index:1; }

.pf-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.75rem; }
.pf-checks { display:flex; flex-wrap:wrap; gap:.5rem 1rem; margin:.85rem 0 1rem; }
.pf-checks label { display:flex; align-items:center; gap:.4rem; font-size:.88rem; cursor:pointer; }
.pf-actions { display:flex; flex-wrap:wrap; gap:.5rem; }

@media print {
  body.pf-printing-card * { visibility:hidden !important; }
  body.pf-printing-card .pf-id, body.pf-printing-card .pf-id * { visibility:visible !important; }
  body.pf-printing-card .pf-id { position:fixed; left:12mm; top:12mm; width:86mm; box-shadow:none;
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>

<section class="pf-hero">
    <div class="pf-avatar" aria-hidden="true"><?= $e($initials) ?></div>
    <div style="position:relative; z-index:1; min-width:0;">
        <h1><?= $e($name) ?></h1>
        <div class="pf-hero__meta">
            <?= te('Student ID') ?>: <strong><?= $e($student['student_id_display'] ?? '') ?></strong>
            <?php if (!empty($student['created_at'])): ?> &middot; <?= te('With us since {d}', ['d' => lang_date('F Y', (string) $student['created_at'])]) ?><?php endif; ?>
        </div>
        <div>
            <?php foreach ($classes as $c): ?><span class="pf-chip">&#128218; <?= $e($c['class_name']) ?></span><?php endforeach; ?>
            <?php if (!empty($progress['has_data'])): ?><span class="pf-chip">&#127919; <?= te((string) $progress['level']) ?></span><?php endif; ?>
        </div>
    </div>
</section>

<div class="pf-stats">
    <div class="pf-stat"><div class="pf-stat__n" style="color:var(--primary);"><?= $summary['average'] !== null ? round((float) $summary['average']) . '%' : '&ndash;' ?></div><div class="pf-stat__l"><?= te('Quiz average') ?></div></div>
    <div class="pf-stat"><div class="pf-stat__n"><?= (int) $summary['quizzes'] ?></div><div class="pf-stat__l"><?= te('Quizzes marked') ?></div></div>
    <div class="pf-stat"><div class="pf-stat__n" style="color:var(--success);"><?= $summary['attendance'] !== null ? round((float) $summary['attendance']) . '%' : '&ndash;' ?></div><div class="pf-stat__l"><?= te('Attendance') ?></div></div>
    <div class="pf-stat"><div class="pf-stat__n" style="color:var(--accent-text);">&#127942; <?= $earned ?></div><div class="pf-stat__l"><?= te('Achievements') ?></div></div>
</div>

<div class="pf-grid">
    <div>
        <!-- Fees and receipts -->
        <div class="pf-card">
            <h3>&#129534; <?= te('Fees and receipts') ?></h3>
            <?php if ($payments === []): ?>
                <p class="text-muted" style="margin:0;"><?= te('No fees have been issued yet.') ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr>
                            <th><?= te('Month') ?></th><th><?= te('Class') ?></th>
                            <th class="text-right"><?= te('Amount') ?></th><th><?= te('Status') ?></th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): $settled = in_array($p['payment_status'], ['paid', 'waived'], true); ?>
                            <tr>
                                <td><?= $e(lang_date('F Y', (string) $p['month_year'])) ?></td>
                                <td><?= $e($p['class_name']) ?></td>
                                <td class="text-right">Rs. <?= number_format((float) $p['amount_due'], 2) ?></td>
                                <td><span class="pf-pill <?= $statusClass[$p['payment_status']] ?? 'pf-pill--wait' ?>"><?= te($statusLabel[$p['payment_status']] ?? ucfirst((string) $p['payment_status'])) ?></span></td>
                                <td class="text-right">
                                    <?php if ($settled): ?>
                                        <a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/my/receipts/<?= (int) $p['id'] ?>" target="_blank" rel="noopener">&#129534; <?= te('Receipt') ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($summary['fees_owing'] > 0): ?>
                    <p class="text-sm" style="margin:.8rem 0 0; color:var(--danger);">
                        <?= te('Outstanding: Rs. {n}', ['n' => number_format((float) $summary['fees_owing'], 2)]) ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Report builder -->
        <div class="pf-card" style="background:linear-gradient(160deg, rgba(29,78,216,.05), rgba(245,179,1,.08));">
            <h3>&#128196; <?= te('Make a report') ?></h3>
            <p class="text-sm text-muted" style="margin:-.4rem 0 1rem;"><?= te('Choose the months and what to include. Download it as a PDF to print or share, or as a spreadsheet.') ?></p>
            <form method="GET" action="<?= BASE_URL ?>/my/report" target="_blank" id="pf-report">
                <div class="pf-form">
                    <label class="form-label"><?= te('From') ?>
                        <input type="month" name="from" class="form-control" value="<?= $e(substr((string) $report['from'], 0, 7)) ?>" required>
                    </label>
                    <label class="form-label"><?= te('To') ?>
                        <input type="month" name="to" class="form-control" value="<?= $e(substr((string) $report['to'], 0, 7)) ?>" required>
                    </label>
                    <label class="form-label"><?= te('Class') ?>
                        <select name="class" class="form-control">
                            <option value=""><?= te('All classes') ?></option>
                            <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $e($c['class_name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="pf-checks">
                    <?php foreach (['summary' => 'Summary', 'marks' => 'Quiz results', 'attendance' => 'Attendance', 'payments' => 'Payments'] as $key => $label): ?>
                        <label><input type="checkbox" name="sections[]" value="<?= $key ?>" checked> <?= te($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="pf-actions">
                    <button type="submit" class="btn btn--primary">&#128196; <?= te('View and download PDF') ?></button>
                    <button type="submit" class="btn btn--ghost" formaction="<?= BASE_URL ?>/my/report.csv" formtarget="_self" name="type" value="marks">&#11015; <?= te('Marks (spreadsheet)') ?></button>
                    <button type="submit" class="btn btn--ghost" formaction="<?= BASE_URL ?>/my/report.csv" formtarget="_self" name="type" value="attendance">&#11015; <?= te('Attendance (spreadsheet)') ?></button>
                    <button type="submit" class="btn btn--ghost" formaction="<?= BASE_URL ?>/my/report.csv" formtarget="_self" name="type" value="payments">&#11015; <?= te('Payments (spreadsheet)') ?></button>
                </div>
            </form>
        </div>

        <!-- Recent marks -->
        <div class="pf-card">
            <h3>&#128221; <?= te('Recent quiz results') ?></h3>
            <?php if ($marks === []): ?>
                <p class="text-muted" style="margin:0;"><?= te('No quizzes yet') ?></p>
            <?php else: foreach ($marks as $m): $graded = $m['attempt_status'] === 'graded' && $m['percentage'] !== null; $pct = (float) $m['percentage']; ?>
                <div style="display:flex; gap:.75rem; align-items:center; padding:.55rem 0; border-top:1px solid var(--divider);">
                    <div style="min-width:0; flex:1;">
                        <div style="font-weight:600; font-size:.9rem;"><?= $e($m['title']) ?></div>
                        <div class="text-sm text-muted"><?= $e($m['class_name']) ?> &middot; <?= $e(lang_date('j M Y', (string) $m['submitted_at'])) ?></div>
                    </div>
                    <strong style="color:<?= !$graded ? 'var(--text-muted)' : ($pct >= (float) $m['pass_mark'] ? 'var(--success)' : 'var(--danger)') ?>;">
                        <?= $graded ? round($pct) . '%' : te('Waiting to be marked') ?>
                    </strong>
                </div>
            <?php endforeach; ?>
                <a href="<?= BASE_URL ?>/my/marks" class="text-sm"><?= te('All my marks') ?> &rarr;</a>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <!-- Digital ID -->
        <div class="pf-card">
            <h3>&#129706; <?= te('My student ID card') ?></h3>
            <div class="pf-id" id="pf-id-card">
                <div class="pf-id__brand">
                    <svg width="22" height="22" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#F5B301"/><path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="#0F2464" stroke-width="1.6" fill="none"/></svg>
                    <?= te('SCIENCE CLASSES') ?> &middot; <?= $e($firstClass['teacher_name'] ?? '') ?>
                </div>
                <div class="pf-id__body">
                    <div class="pf-id__face"><?= $e($initials) ?></div>
                    <div style="min-width:0;">
                        <div class="pf-id__name"><?= $e($name) ?></div>
                        <div style="font-size:.8rem; opacity:.9;"><?= $e($firstClass['class_name'] ?? '') ?></div>
                        <div class="pf-id__no"><?= $e($student['student_id_display'] ?? '') ?></div>
                    </div>
                </div>
                <div class="pf-id__foot">
                    <span><?= $e($firstClass['institute_name'] ?? '') ?></span>
                    <span><?= te('Valid {y}', ['y' => date('Y')]) ?></span>
                </div>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" style="margin-top:.85rem;" id="pf-print-card">&#128424; <?= te('Print my ID card') ?></button>
        </div>

        <!-- Details -->
        <div class="pf-card">
            <h3>&#128100; <?= te('My details') ?></h3>
            <dl class="pf-dl">
                <?php foreach ([
                    'Phone'         => $student['phone1'] ?? null,
                    'WhatsApp'      => $student['whatsapp'] ?? null,
                    'Email'         => $student['email'] ?? null,
                    'Date of birth' => !empty($student['date_of_birth']) ? lang_date('j F Y', (string) $student['date_of_birth']) : null,
                    'Address'       => $student['address'] ?? null,
                    'District'      => $student['district'] ?? null,
                ] as $label => $value): if ($value === null || $value === '') { continue; } ?>
                    <dt><?= te($label) ?></dt><dd><?= $e($value) ?></dd>
                <?php endforeach; ?>
            </dl>
            <p class="text-sm text-muted" style="margin:.9rem 0 0;"><?= te('Something wrong here? Tell the office and they will fix it.') ?></p>
        </div>

        <?php if ($guardians): ?>
            <div class="pf-card">
                <h3>&#128106; <?= te('Parent or guardian') ?></h3>
                <?php foreach ($guardians as $g): ?>
                    <div style="padding:.35rem 0;"><strong><?= $e($g['parent_name']) ?></strong>
                        <?php if (!empty($g['phone1'])): ?><span class="text-muted"> &middot; <?= $e($g['phone1']) ?></span><?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Classes -->
        <div class="pf-card">
            <h3>&#128218; <?= te('My Classes') ?></h3>
            <?php foreach ($classes as $c): $days = json_decode((string) $c['schedule_days'], true) ?: []; ?>
                <div style="padding:.5rem 0; border-top:1px solid var(--divider);">
                    <div style="font-weight:700;"><?= $e($c['class_name']) ?></div>
                    <div class="text-sm text-muted">
                        <?= $e(implode(' & ', array_map(static fn ($d) => lang_date('l', strtotime('next ' . $d)), $days))) ?>
                        <?= !empty($c['start_time']) ? ' &middot; ' . $e(lang_date('g:i a', strtotime((string) $c['start_time']))) : '' ?>
                        <?= !empty($c['institute_name']) ? ' &middot; ' . $e($c['institute_name']) : '' ?>
                    </div>
                    <?php if (($c['scholarship_type'] ?? '') === 'free_card'): ?><span class="pf-pill pf-pill--ok"><?= te('Free card') ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Account -->
        <div class="pf-card">
            <h3>&#128274; <?= te('Account') ?></h3>
            <a class="btn btn--ghost btn--sm" href="<?= BASE_URL ?>/change-password"><?= te('Change my password') ?></a>
            <p class="text-sm text-muted" style="margin:.75rem 0 0;"><?= te('Language') ?>: <strong><?= lang_current() === 'si' ? 'සිංහල' : 'English' ?></strong> &middot; <?= te('switch it at the top of any page') ?></p>
        </div>
    </div>
</div>

<script>
// Print just the ID card: everything else is hidden for the print only.
document.getElementById('pf-print-card').addEventListener('click', function () {
    document.body.classList.add('pf-printing-card');
    window.print();
    setTimeout(function () { document.body.classList.remove('pf-printing-card'); }, 500);
});
</script>
