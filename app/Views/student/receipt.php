<?php
/**
 * A payment receipt -- a page of its own, made to be printed or saved as
 * a PDF. Only reachable for the student's own settled payments.
 */
$r = $receipt;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$waived = $r['payment_status'] === 'waived';
$back = session_get('user_type') === 'parent' ? BASE_URL . '/family/fees' : BASE_URL . '/my/profile';
?>
<!DOCTYPE html>
<html lang="<?= lang_current() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= te('Receipt') ?> <?= $e($r['receipt_number']) ?></title>
<?php if (is_file(__DIR__ . '/../components/favicon.php')) { include __DIR__ . '/../components/favicon.php'; } ?>
<style>
  :root { --blue:#1D4ED8; --deep:#0F2464; --yellow:#F5B301; --ink:#0F172A; --muted:#64748B; --line:#D8E2F2; }
  * { box-sizing:border-box; }
  body { margin:0; background:#EEF3FB; color:var(--ink);
    font-family:-apple-system, "Segoe UI", Roboto, Arial, "Noto Sans Sinhala", "Iskoola Pota", "Nirmala UI", sans-serif; }
  .bar { display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap; padding:14px 16px; }
  .bar a, .bar button { font:inherit; font-weight:700; font-size:.9rem; padding:.6rem 1.1rem; border-radius:10px; border:1px solid var(--line);
    background:#fff; color:var(--ink); cursor:pointer; text-decoration:none; }
  .bar button.primary { background:var(--blue); color:#fff; border-color:var(--blue); }
  .sheet { width:min(148mm, calc(100% - 32px)); margin:0 auto 32px; background:#fff; border-radius:14px; overflow:hidden;
    box-shadow:0 10px 32px rgba(15,23,42,.12); }
  .head { background:linear-gradient(135deg, #17348A, var(--deep)); color:#fff; padding:20px 24px 18px; position:relative; }
  .head::before { content:""; position:absolute; left:0; right:0; top:0; height:6px; background:var(--yellow); }
  .brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:1.05rem; }
  .head small { display:block; opacity:.85; margin-top:4px; font-size:.8rem; }
  .title { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; padding:18px 24px 6px; flex-wrap:wrap; }
  .title h1 { margin:0; font-size:1.35rem; letter-spacing:.02em; }
  .no { font-family:ui-monospace, Consolas, monospace; color:var(--muted); font-size:.9rem; }
  .stamp { border:2.5px solid #15803D; color:#15803D; border-radius:8px; padding:4px 12px; font-weight:800; transform:rotate(-4deg);
    letter-spacing:.08em; font-size:.95rem; }
  table { width:100%; border-collapse:collapse; margin:10px 0 0; }
  td { padding:9px 24px; border-bottom:1px solid var(--line); font-size:.92rem; vertical-align:top; }
  td:first-child { color:var(--muted); width:42%; }
  td:last-child { font-weight:600; }
  .total { display:flex; justify-content:space-between; align-items:center; margin:16px 24px; padding:14px 16px; border-radius:12px;
    background:#FFF7DD; border:1px solid #F5D47A; }
  .total strong { font-size:1.4rem; }
  .foot { padding:4px 24px 22px; color:var(--muted); font-size:.78rem; line-height:1.5; }
  @media print {
    @page { size:A5; margin:10mm; }
    body { background:#fff; }
    .bar { display:none; }
    .sheet { width:auto; margin:0; box-shadow:none; border-radius:0; }
    .head, .total, .stamp { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
  <div class="bar">
    <button type="button" class="primary" onclick="window.print()">&#11015; <?= te('Download PDF / Print') ?></button>
    <a href="<?= $e($back) ?>">&larr; <?= te('Back') ?></a>
  </div>

  <main class="sheet">
    <header class="head">
      <div class="brand">
        <svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#F5B301"/><path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="#0F2464" stroke-width="1.6" fill="none"/></svg>
        <?= $e($r['institute_name'] ?? '') ?>
      </div>
      <small><?= $e(trim(($r['institute_location'] ?? '') . (!empty($r['institute_phone']) ? ' · ' . $r['institute_phone'] : ''))) ?></small>
    </header>

    <div class="title">
      <div>
        <h1><?= te('Payment receipt') ?></h1>
        <div class="no"><?= $e($r['receipt_number']) ?></div>
      </div>
      <div class="stamp"><?= $waived ? te('WAIVED') : te('PAID') ?></div>
    </div>

    <table>
      <tr><td><?= te('Student') ?></td><td><?= $e($r['student_name']) ?></td></tr>
      <tr><td><?= te('Student ID') ?></td><td><?= $e($r['student_id_display']) ?></td></tr>
      <tr><td><?= te('Class') ?></td><td><?= $e($r['class_name']) ?></td></tr>
      <tr><td><?= te('For the month of') ?></td><td><?= $e(lang_date('F Y', (string) $r['month_year'])) ?></td></tr>
      <?php // A payment recorded with a date only lands at 00:00; "12:00 am" would be a time nobody paid at.
            $paidHasTime = $r['paid_at'] && substr((string) $r['paid_at'], 11, 8) !== '00:00:00'; ?>
      <tr><td><?= te('Paid on') ?></td><td><?= $r['paid_at'] ? $e(lang_date($paidHasTime ? 'j F Y, g:i a' : 'j F Y', (string) $r['paid_at'])) : '&ndash;' ?></td></tr>
      <?php if (!empty($r['received_by'])): ?><tr><td><?= te('Received by') ?></td><td><?= $e($r['received_by']) ?></td></tr><?php endif; ?>
      <?php if (($r['scholarship_type'] ?? '') === 'half_payment'): ?><tr><td><?= te('Scholarship') ?></td><td><?= te('Half fee') ?></td></tr><?php endif; ?>
    </table>

    <div class="total">
      <span><?= $waived ? te('Fee waived') : te('Amount paid') ?></span>
      <strong>Rs. <?= number_format((float) ($waived ? $r['amount_due'] : $r['amount_paid']), 2) ?></strong>
    </div>

    <div class="foot">
      <?= te('This receipt was produced by the class system from the payment record. Keep it for your records.') ?><br>
      <?= te('Printed {d}', ['d' => lang_date('j F Y, g:i a')]) ?>
    </div>
  </main>
</body>
</html>
