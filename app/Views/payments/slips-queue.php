<?php
/** Office review queue for payment slips. */
$slips = $slips ?? [];
$status = $status ?? 'pending';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rs = static fn ($v): string => 'Rs. ' . number_format((float) $v, 2);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Payment slips') ?></h1>
        <p class="text-sm text-muted"><?= te('A slip is not a payment until you approve it. Check the amount, date and reference against the bank.') ?></p>
    </div>
    <div class="page-header__actions">
        <?php foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label): ?>
            <a href="<?= BASE_URL ?>/payments/slips?status=<?= $k ?>" class="btn btn--<?= $status === $k ? 'primary' : 'ghost' ?> btn--sm"><?= te($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($slips === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('Nothing here.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.85rem;">
<?php foreach ($slips as $s):
    $outstanding = (float) $s['amount_due'] - (float) $s['amount_paid'];
    $mismatch = abs((float) $s['amount'] - $outstanding) > 0.009; ?>
    <div class="card" style="padding:1rem 1.15rem;">
        <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
            <div style="min-width:0;">
                <div style="font-weight:700;"><?= $e($s['student_name']) ?> <span class="text-sm text-muted"><?= $e($s['student_id_display']) ?></span></div>
                <div class="text-sm text-muted"><?= $e($s['class_name']) ?> &middot; <?= $e($s['institute_name'] ?? '') ?> &middot; <?= $e(lang_date('F Y', (string) $s['month_year'])) ?></div>
                <div class="text-sm" style="margin-top:.35rem;">
                    <?= te('Slip') ?>: <strong><?= $e($rs($s['amount'])) ?></strong> &middot; <?= te('paid {d}', ['d' => lang_date('j M Y', (string) $s['paid_on'])]) ?>
                    <?= $s['bank_name'] ? ' &middot; ' . $e($s['bank_name']) : '' ?><?= $s['bank_reference'] ? ' &middot; ' . te('Ref') . ' ' . $e($s['bank_reference']) : '' ?>
                    &middot; <?= te('Still due') ?>: <strong style="<?= $mismatch ? 'color:var(--warning);' : '' ?>"><?= $e($rs($outstanding)) ?></strong>
                </div>
                <div class="text-sm" style="margin-top:.35rem;"><a href="<?= BASE_URL ?>/payment-slips/<?= (int) $s['id'] ?>/file" target="_blank" rel="noopener">&#128206; <?= te('Open the slip') ?></a>
                    &middot; <span class="text-muted"><?= te('Sent {d}', ['d' => lang_date('j M, g:i a', (string) $s['created_at'])]) ?> (<?= $s['submitted_by_type'] === 'parent' ? te('Parent') : te('Student') ?>)</span></div>
                <?php if ($s['status'] !== 'pending'): ?>
                    <div class="text-sm" style="margin-top:.35rem;"><strong><?= te(ucfirst((string) $s['status'])) ?></strong> &middot; <?= $e($s['reviewer_name'] ?? '') ?> &middot; <?= $e(lang_date('j M Y', (string) $s['reviewed_at'])) ?>
                        <?= $s['status'] === 'rejected' ? ' &middot; ' . $e($s['review_note']) : ' &middot; ' . $e($rs($s['approved_amount'])) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($s['status'] === 'pending'): ?>
            <div style="display:grid; gap:.5rem; min-width:min(100%,280px);">
                <form method="POST" action="<?= BASE_URL ?>/payments/slips/<?= (int) $s['id'] ?>/approve" style="display:flex; gap:.4rem;">
                    <?= csrfField() ?>
                    <input type="number" name="approved_amount" class="form-control form-control--sm" step="0.01" min="0.01" max="<?= $e(number_format($outstanding, 2, '.', '')) ?>" value="<?= $e(number_format(min((float) $s['amount'], $outstanding), 2, '.', '')) ?>" required>
                    <button type="submit" class="btn btn--primary btn--sm"><?= te('Approve') ?></button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/payments/slips/<?= (int) $s['id'] ?>/reject" style="display:flex; gap:.4rem;">
                    <?= csrfField() ?>
                    <input type="text" name="reason" class="form-control form-control--sm" maxlength="500" placeholder="<?= te('Reason (shown to the family)') ?>" required>
                    <button type="submit" class="btn btn--ghost btn--sm"><?= te('Reject') ?></button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
