<?php
/**
 * Payments - Generate Monthly Fee Records
 *
 * Variables: $currentMonth, $pageTitle
 */

$currentMonth = $currentMonth ?? date('Y-m');
?>

<div class="breadcrumb mb-2">
    <a href="<?= BASE_URL ?>/dashboard"><?= te('Dashboard') ?></a>
    <span class="breadcrumb__sep">/</span>
    <a href="<?= BASE_URL ?>/payments"><?= te('Payments') ?></a>
    <span class="breadcrumb__sep">/</span>
    <span><?= te('Generate Monthly') ?></span>
</div>

<div class="page-header mb-3">
    <h1 class="page-header__title"><?= htmlspecialchars($pageTitle ?? te('Generate Monthly Invoices'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-sm text-muted"><?= te('Create pending payment fee records for all active student enrollments for the specified billing cycle.') ?></p>
</div>

<div class="card" style="max-width:600px;">
    <form method="POST" action="<?= BASE_URL ?>/payments/generate">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <div class="form-group mb-3">
            <label class="form-label" for="month_year"><?= te('Target Billing Month') ?> <span class="required">*</span></label>
            <input type="month" id="month_year" name="month_year" class="form-control" value="<?= htmlspecialchars($currentMonth, ENT_QUOTES, 'UTF-8') ?>" required>
            <span class="text-xs text-muted"><?= te('Select the year and month to generate fee entries for. Existing records will not be duplicated.') ?></span>
        </div>

        <div class="alert alert--info mb-3" style="padding:0.75rem 1rem; border-radius:var(--radius-md); background:var(--surface-sunken); border:1px solid var(--border);">
            <div class="d-flex items-center gap-1 font-medium mb-1">
                <span>&#9432;</span>
                <span><?= te('Automatic Scholarship Logic Applied') ?></span>
            </div>
            <p class="text-xs text-muted mb-0">
                - <?= te('Full Payment students are invoiced at 100% of class fee.') ?><br>
                - <?= te('Half Payment (50%) students are invoiced at 50% discount.') ?><br>
                - <?= te('Free Card (100%) students are generated with LKR 0.00 amount due.') ?>
            </p>
        </div>

        <div class="d-flex gap-1">
            <button type="submit" class="btn btn--primary" data-confirm="<?= te('Generate monthly invoices for all active enrollments for this period?') ?>">
                &#9881; <?= te('Generate Invoices') ?>
            </button>
            <a href="<?= BASE_URL ?>/payments" class="btn btn--ghost"><?= te('Cancel') ?></a>
        </div>
    </form>
</div>
