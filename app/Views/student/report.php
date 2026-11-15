<?php
/**
 * The student's own report, for the months and sections they chose.
 * A page of its own, laid out for A4 and for "Save as PDF".
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s = $summary;
$sections = $opts['sections'];
$className = null;
foreach ($classes as $c) { if ((int) $c['id'] === $opts['class_id']) { $className = $c['class_name']; } }
$period = lang_date('F Y', $opts['from']) . ' – ' . lang_date('F Y', $opts['to']);
$back = session_get('user_type') === 'parent' ? BASE_URL . '/family' : BASE_URL . '/my/profile';
$query = http_build_query(['from' => substr($opts['from'], 0, 7), 'to' => substr($opts['to'], 0, 7), 'class' => $opts['class_id'] ?? '']);
$statusText = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'red_flag' => 'Flagged',
               'paid' => 'Paid', 'waived' => 'Waived', 'pending' => 'Pending', 'overdue' => 'Overdue'];
?>
<!DOCTYPE html>
<html lang="<?= lang_current() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= te('Student report') ?> – <?= $e($student['full_name'] ?? '') ?></title>
<?php if (is_file(__DIR__ . '/../components/favicon.php')) { include __DIR__ . '/../components/favicon.php'; } ?>
<style>
  :root { --blue:#1D4ED8; --deep:#0F2464; --yellow:#F5B301; --ink:#0F172A; --muted:#64748B; --line:#D8E2F2; --green:#15803D; --red:#B42318; }
  * { box-sizing:border-box; }
  body { margin:0; background:#EEF3FB; color:var(--ink);
    font-family:-apple-system, "Segoe UI", Roboto, Arial, "Noto Sans Sinhala", "Iskoola Pota", "Nirmala UI", sans-serif; }
  .bar { display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap; padding:14px 16px; }
  .bar a, .bar button { font:inherit; font-weight:700; font-size:.88rem; padding:.55rem 1rem; border-radius:10px; border:1px solid var(--line);
    background:#fff; color:var(--ink); cursor:pointer; text-decoration:none; }
  .bar .primary { background:var(--blue); color:#fff; border-color:var(--blue); }
  .page { width:min(210mm, calc(100% - 24px)); margin:0 auto 32px; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 32px rgba(15,23,42,.12); }
  .head { background:linear-gradient(135deg, #17348A, var(--deep)); color:#fff; padding:22px 28px; position:relative; }
  .head::before { content:""; position:absolute; left:0; right:0; top:0; height:6px; background:var(--yellow); }
  .head h1 { margin:0; font-size:1.45rem; }
  .head .sub { opacity:.9; margin-top:6px; font-size:.9rem; }
  .who { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px 24px; padding:18px 28px; border-bottom:1px solid var(--line); font-size:.9rem; }
  .who span { display:block; color:var(--muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; }
  section { padding:18px 28px; border-bottom:1px solid var(--line); break-inside:avoid; }
  h2 { margin:0 0 12px; font-size:1.05rem; color:var(--deep); }
  .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; }
  .kpi { border:1px solid var(--line); border-radius:10px; padding:10px 12px; }
  .kpi b { display:block; font-size:1.35rem; }
  .kpi span { color:var(--muted); font-size:.78rem; }
  table { width:100%; border-collapse:collapse; font-size:.86rem; }
  th { text-align:left; background:#EAF0FA; color:#475569; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; padding:8px 10px; }
  td { padding:8px 10px; border-bottom:1px solid var(--line); }
  .r { text-align:right; }
  .ok { color:var(--green); font-weight:700; } .bad { color:var(--red); font-weight:700; } .muted { color:var(--muted); }
  .empty { color:var(--muted); margin:0; }
  .foot { padding:14px 28px 22px; color:var(--muted); font-size:.76rem; }
  @media (max-width: 600px) { .head, .who, section, .foot { padding-left:16px; padding-right:16px; } table { font-size:.8rem; } }
  @media print {
    @page { size:A4; margin:12mm; }
    body { background:#fff; }
    .bar { display:none; }
    .page { width:auto; margin:0; box-shadow:none; border-radius:0; }
    .head, th { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    tr { break-inside:avoid; }
  }
</style>
</head>
<body>
  <div class="bar">
    <button type="button" class="primary" onclick="window.print()">&#11015; <?= te('Download PDF / Print') ?></button>
    <a href="<?= BASE_URL ?>/my/report.csv?<?= $e($query) ?>&amp;type=marks">&#11015; <?= te('Marks (spreadsheet)') ?></a>
    <a href="<?= $e($back) ?>">&larr; <?= te('Back') ?></a>
  </div>

  <main class="page">
    <header class="head">
      <h1><?= te('Student report') ?></h1>
      <div class="sub"><?= $e($period) ?> &middot; <?= $className !== null ? $e($className) : te('All classes') ?></div>
    </header>

    <div class="who">
      <div><span><?= te('Student') ?></span><?= $e($student['full_name'] ?? '') ?></div>
      <div><span><?= te('Student ID') ?></span><?= $e($student['student_id_display'] ?? '') ?></div>
      <div><span><?= te('Classes') ?></span><?= $e(implode(', ', array_column($classes, 'class_name'))) ?></div>
      <div><span><?= te('Generated') ?></span><?= $e(lang_date('j F Y, g:i a', $generated)) ?></div>
    </div>

    <?php if (in_array('summary', $sections, true)): ?>
    <section>
      <h2><?= te('Summary') ?></h2>
      <div class="kpis">
        <div class="kpi"><b><?= $s['average'] !== null ? round((float) $s['average']) . '%' : '–' ?></b><span><?= te('Quiz average') ?></span></div>
        <div class="kpi"><b><?= $s['best'] !== null ? round((float) $s['best']) . '%' : '–' ?></b><span><?= te('Personal best') ?></span></div>
        <div class="kpi"><b><?= (int) $s['passed'] ?>/<?= (int) $s['quizzes'] ?></b><span><?= te('Quizzes passed') ?></span></div>
        <div class="kpi"><b><?= $s['attendance'] !== null ? round((float) $s['attendance']) . '%' : '–' ?></b><span><?= te('Attendance ({p} of {n})', ['p' => $s['present'], 'n' => $s['sessions']]) ?></span></div>
        <div class="kpi"><b>Rs. <?= number_format((float) $s['fees_paid'], 0) ?></b><span><?= te('Fees paid') ?></span></div>
        <div class="kpi"><b class="<?= $s['fees_owing'] > 0 ? 'bad' : 'ok' ?>">Rs. <?= number_format((float) $s['fees_owing'], 0) ?></b><span><?= te('Still to pay') ?></span></div>
      </div>
    </section>
    <?php endif; ?>

    <?php if (in_array('marks', $sections, true)): ?>
    <section>
      <h2><?= te('Quiz results') ?></h2>
      <?php if ($marks === []): ?><p class="empty"><?= te('No quizzes in this period.') ?></p><?php else: ?>
      <table>
        <thead><tr><th><?= te('Quiz') ?></th><th><?= te('Class') ?></th><th><?= te('Handed in') ?></th><th class="r"><?= te('Score') ?></th><th class="r"><?= te('Result') ?></th></tr></thead>
        <tbody>
        <?php foreach ($marks as $m): $g = $m['attempt_status'] === 'graded' && $m['percentage'] !== null; $ok = $g && (float) $m['percentage'] >= (float) $m['pass_mark']; ?>
          <tr>
            <td><?= $e($m['title']) ?></td>
            <td><?= $e($m['class_name']) ?></td>
            <td><?= $e(lang_date('j M Y', (string) $m['submitted_at'])) ?></td>
            <td class="r"><?= $g ? $e(rtrim(rtrim((string) $m['score'], '0'), '.')) . '/' . $e(rtrim(rtrim((string) $m['total_marks'], '0'), '.')) . ' (' . round((float) $m['percentage']) . '%)' : '–' ?></td>
            <td class="r <?= $g ? ($ok ? 'ok' : 'bad') : 'muted' ?>"><?= !$g ? te('Waiting to be marked') : ($ok ? te('Passed') : te('Not passed')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (in_array('attendance', $sections, true)): ?>
    <section>
      <h2><?= te('Attendance') ?></h2>
      <?php if ($attendance === []): ?><p class="empty"><?= te('No attendance recorded in this period.') ?></p><?php else: ?>
      <table>
        <thead><tr><th><?= te('Date') ?></th><th><?= te('Class') ?></th><th><?= te('Arrived at') ?></th><th class="r"><?= te('Status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($attendance as $a): ?>
          <tr>
            <td><?= $e(lang_date('D j M Y', (string) $a['date'])) ?></td>
            <td><?= $e($a['class_name']) ?></td>
            <td><?= $a['check_in_time'] ? $e(lang_date('g:i a', strtotime((string) $a['check_in_time']))) : '–' ?></td>
            <td class="r <?= $a['status'] === 'absent' ? 'bad' : 'ok' ?>"><?= te($statusText[$a['status']] ?? ucfirst((string) $a['status'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (in_array('payments', $sections, true)): ?>
    <section>
      <h2><?= te('Payments') ?></h2>
      <?php if ($payments === []): ?><p class="empty"><?= te('No fees in this period.') ?></p><?php else: ?>
      <table>
        <thead><tr><th><?= te('Month') ?></th><th><?= te('Class') ?></th><th class="r"><?= te('Amount due') ?></th><th class="r"><?= te('Amount paid') ?></th><th class="r"><?= te('Status') ?></th><th><?= te('Receipt number') ?></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): $settled = in_array($p['payment_status'], ['paid', 'waived'], true); ?>
          <tr>
            <td><?= $e(lang_date('F Y', (string) $p['month_year'])) ?></td>
            <td><?= $e($p['class_name']) ?></td>
            <td class="r">Rs. <?= number_format((float) $p['amount_due'], 2) ?></td>
            <td class="r">Rs. <?= number_format((float) $p['amount_paid'], 2) ?></td>
            <td class="r <?= $settled ? 'ok' : 'bad' ?>"><?= te($statusText[$p['payment_status']] ?? ucfirst((string) $p['payment_status'])) ?></td>
            <td class="muted"><?= $settled ? $e(\App\Services\StudentRecordService::receiptNumber((int) $p['id'], (string) $p['month_year'])) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="foot"><?= te('Produced by the class system from the records on the date above.') ?></div>
  </main>
</body>
</html>
