<?php
/**
 * The unpaid-fees bar, shown on the student's profile and the parent's.
 *
 * The office asked for this to be impossible to miss: a family that has
 * quietly stopped paying still attends every week, so the only moment
 * anyone notices is a report nobody runs. The same figure is announced
 * at the QR terminal when the student scans in; this is that figure
 * sitting where the family will see it themselves.
 *
 * It names the months rather than only a total, because "you owe
 * Rs. 6,000" invites an argument and "September, October and November"
 * does not.
 *
 * Nothing here is a punishment: it states what is owed and who to pay,
 * and says plainly that classes are not affected. A child should not be
 * made to feel they are being shut out.
 *
 * Expects: $feeDue (PaymentDueService::summary() shape). Renders
 * nothing at all when there is nothing owed.
 */
$feeDue = $feeDue ?? null;

if (!is_array($feeDue) || empty($feeDue['overdue'])) {
    return;
}

$fdE = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fdMonths = array_values(array_filter((array) ($feeDue['months'] ?? [])));
$fdCount = (int) ($feeDue['count'] ?? count($fdMonths));
$fdTotal = (float) ($feeDue['total'] ?? 0);

// "September, October and November"
if (count($fdMonths) > 1) {
    $fdLast = array_pop($fdMonths);
    $fdSentence = implode(', ', $fdMonths) . ' ' . t('and') . ' ' . $fdLast;
} else {
    $fdSentence = (string) ($fdMonths[0] ?? '');
}
?>
<div class="fee-bar" role="alert">
    <div class="fee-bar__mark" aria-hidden="true">!</div>

    <div class="fee-bar__body">
        <div class="fee-bar__title">
            <?= $fdCount === 1
                ? te('One month\'s class fee has not been paid yet')
                : te('{n} months of class fees have not been paid yet', ['n' => $fdCount]) ?>
        </div>

        <?php if ($fdSentence !== ''): ?>
            <div class="fee-bar__months"><?= $fdE($fdSentence) ?></div>
        <?php endif; ?>

        <?php if ($fdTotal > 0): ?>
            <div class="fee-bar__total">
                <?= te('Total outstanding') ?>:
                <strong><?= $fdE('Rs. ' . number_format($fdTotal, 2)) ?></strong>
            </div>
        <?php endif; ?>

        <p class="fee-bar__note">
            <?= te('Please settle this at the branch office. Classes, attendance and the portal are not affected -- this is a reminder, not a block.') ?>
        </p>
    </div>
</div>

<style>
    .fee-bar {
        display: flex; gap: .9rem; align-items: flex-start;
        background: #FEF2F2;
        border: 1px solid #FCA5A5;
        border-left: 6px solid #B3261E;
        border-radius: 12px;
        padding: 1rem 1.1rem;
        margin: 0 0 1.25rem;
    }
    .fee-bar__mark {
        flex: none; width: 30px; height: 30px; border-radius: 50%;
        background: #B3261E; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.05rem; line-height: 1;
    }
    .fee-bar__body { display: grid; gap: .2rem; }
    .fee-bar__title { font-weight: 700; color: #7F1D1D; font-size: 1rem; }
    .fee-bar__months { font-weight: 600; color: #B3261E; font-size: .92rem; }
    .fee-bar__total { font-size: .92rem; color: #7F1D1D; }
    .fee-bar__note { margin: .35rem 0 0; font-size: .83rem; color: #8A4B47; line-height: 1.5; }

    @media print { .fee-bar { break-inside: avoid; } }
</style>
