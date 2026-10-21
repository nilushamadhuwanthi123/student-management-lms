<?php
$student     = $student ?? [];
$payments    = $payments ?? [];
$outstanding = $outstanding ?? 0.0;
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Fees</h1>
        <p class="text-sm text-muted"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/family" class="btn btn--ghost">&larr; Overview</a>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem; <?= $outstanding > 0 ? 'border-color:var(--warning);' : '' ?>">
    <div class="card__body" style="padding:1.5rem; text-align:center;">
        <div class="text-sm text-muted">Total outstanding</div>
        <div style="font-size:2rem; font-weight:800; margin-top:.25rem; color:<?= $outstanding > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
            <?= $outstanding > 0 ? 'Rs. ' . number_format($outstanding, 2) : 'Nothing owing' ?>
        </div>
        <?php if ($outstanding > 0): ?>
            <p class="text-sm text-muted" style="margin:.75rem 0 0;">
                Class material and quizzes stay locked for any month that is not settled.
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__header"><h3 style="margin:0;">Every month</h3></div>
    <?php if ($payments === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted">No fees raised yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Month</th><th>Class</th><th class="text-right">Due</th><th class="text-right">Paid</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p):
                    $settled = in_array($p['payment_status'], ['paid', 'waived'], true);
                    $colour = $settled ? 'var(--success)' : ($p['payment_status'] === 'overdue' ? 'var(--danger)' : 'var(--warning)');
                ?>
                    <tr>
                        <td style="font-weight:600;"><?= date('F Y', strtotime($p['month_year'])) ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($p['class_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-right">Rs. <?= number_format((float) $p['amount_due'], 2) ?></td>
                        <td class="text-right">Rs. <?= number_format((float) $p['amount_paid'], 2) ?></td>
                        <td>
                            <span class="badge" style="background:var(--surface-sunken); color:<?= $colour ?>; text-transform:capitalize;">
                                <?= htmlspecialchars($p['payment_status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
