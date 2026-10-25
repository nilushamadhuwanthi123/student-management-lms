<?php
/** Fees, slip upload and slip history for a student or parent. */
$fees = $fees ?? [];
$slips = $slips ?? [];
$base = BASE_URL . ($base ?? '/my/payments');
$methods = $methods ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rs = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
$due = array_values(array_filter($fees, static fn (array $f): bool => in_array($f['payment_status'], ['pending', 'overdue'], true)));
$owed = array_sum(array_map(static fn (array $f): float => (float) $f['amount_due'] - (float) $f['amount_paid'], $due));
$methodLabel = static fn (string $m): string => t(match ($m) { 'bank_transfer' => 'Bank transfer', 'online_banking' => 'Online banking', 'other' => 'Other', default => 'Bank deposit' });
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Payments') ?></h1>
        <p class="text-sm text-muted"><?= $owed > 0 ? te('Rs. {n} still due', ['n' => number_format($owed, 2)]) : te('Nothing owing') ?></p>
    </div>
</div>

<?php if ($due !== []): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card__header"><h3 style="margin:0;"><?= te('Pay by bank and upload the slip') ?></h3></div>
    <div class="card__body">
        <p class="text-sm text-muted" style="margin:0 0 1rem;"><?= te('Deposit or transfer the fee to the account the office gave you, then upload a clear photo or PDF of the slip. The office checks it; your class opens once it is approved.') ?></p>
        <form method="POST" action="<?= $base ?>/slip" enctype="multipart/form-data" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr)); gap:.85rem; align-items:end;">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="payment_id"><?= te('Fee') ?></label>
                <select id="payment_id" name="payment_id" class="form-control" required>
                    <?php foreach ($due as $f): ?>
                        <option value="<?= (int) $f['id'] ?>" <?= (int) $f['pending_slips'] > 0 ? 'disabled' : '' ?>>
                            <?= $e(lang_date('F Y', (string) $f['month_year'])) ?> &middot; <?= $e($f['class_name']) ?> &middot; <?= $e($rs((float) $f['amount_due'] - (float) $f['amount_paid'])) ?><?= (int) $f['pending_slips'] > 0 ? ' (' . te('waiting for review') . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label" for="amount"><?= te('Amount on the slip (Rs.)') ?></label>
                <input type="number" id="amount" name="amount" class="form-control" min="1" step="0.01" required></div>
            <div class="form-group"><label class="form-label" for="paid_on"><?= te('Date paid') ?></label>
                <input type="date" id="paid_on" name="paid_on" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label class="form-label" for="method"><?= te('How') ?></label>
                <select id="method" name="method" class="form-control"><?php foreach ($methods as $m): ?><option value="<?= $e($m) ?>"><?= $e($methodLabel($m)) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label" for="bank_name"><?= te('Bank (optional)') ?></label>
                <input type="text" id="bank_name" name="bank_name" class="form-control" maxlength="100"></div>
            <div class="form-group"><label class="form-label" for="reference"><?= te('Reference number (optional)') ?></label>
                <input type="text" id="reference" name="reference" class="form-control" maxlength="100"></div>
            <div class="form-group"><label class="form-label" for="slip"><?= te('Slip photo or PDF (up to 5 MB)') ?></label>
                <input type="file" id="slip" name="slip" class="form-control" accept=".pdf,.png,.jpg,.jpeg" required></div>
            <div><button type="submit" class="btn btn--primary"><?= te('Send for review') ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($slips !== []): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card__header"><h3 style="margin:0;"><?= te('Slips you sent') ?></h3></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th><?= te('Sent') ?></th><th><?= te('Fee') ?></th><th class="text-right"><?= te('Amount') ?></th><th><?= te('Status') ?></th><th><?= te('Note') ?></th></tr></thead>
        <tbody>
        <?php foreach ($slips as $s):
            $tone = $s['status'] === 'approved' ? 'var(--success)' : ($s['status'] === 'rejected' ? 'var(--danger)' : 'var(--warning)'); ?>
            <tr>
                <td class="text-sm"><?= $e(lang_date('j M Y', (string) $s['created_at'])) ?></td>
                <td class="text-sm"><?= $e(lang_date('F Y', (string) $s['month_year'])) ?> &middot; <?= $e($s['class_name']) ?></td>
                <td class="text-right"><?= $e($rs($s['amount'])) ?></td>
                <td><strong style="color:<?= $tone ?>;"><?= te($s['status'] === 'pending' ? 'Waiting for review' : ucfirst((string) $s['status'])) ?></strong></td>
                <td class="text-sm"><?= $s['status'] === 'rejected' ? $e($s['review_note']) : ($s['status'] === 'approved' ? te('Rs. {n} recorded', ['n' => number_format((float) $s['approved_amount'], 2)]) : '') ?>
                    &middot; <a href="<?= BASE_URL ?>/payment-slips/<?= (int) $s['id'] ?>/file"><?= te('View slip') ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card__header"><h3 style="margin:0;"><?= te('Every month') ?></h3></div>
    <?php if ($fees === []): ?>
        <div class="card__body text-sm text-muted"><?= te('No fees raised yet.') ?></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th><?= te('Month') ?></th><th><?= te('Class') ?></th><th class="text-right"><?= te('Due') ?></th><th class="text-right"><?= te('Paid') ?></th><th><?= te('Status') ?></th><th><?= te('Receipt') ?></th></tr></thead>
        <tbody>
        <?php foreach ($fees as $f):
            $settled = in_array($f['payment_status'], ['paid', 'waived'], true); ?>
            <tr>
                <td style="font-weight:600;"><?= $e(lang_date('F Y', (string) $f['month_year'])) ?></td>
                <td class="text-sm text-muted"><?= $e($f['class_name']) ?></td>
                <td class="text-right"><?= $e($rs($f['amount_due'])) ?></td>
                <td class="text-right"><?= $e($rs($f['amount_paid'])) ?></td>
                <td><span class="badge" style="background:var(--surface-sunken); color:<?= $settled ? 'var(--success)' : ($f['payment_status'] === 'overdue' ? 'var(--danger)' : 'var(--warning)') ?>;"><?= te(ucfirst((string) $f['payment_status'])) ?></span></td>
                <td><?php if ($settled): ?><a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="<?= BASE_URL ?>/my/receipts/<?= (int) $f['id'] ?>">&#129534; <?= te('Receipt') ?></a><?php else: ?>&mdash;<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
