<?php
/**
 * Reports Hub Overview View
 * Kavindu Role Guide Specification:
 * Module: Reports (Attendance, Classes, Users, Payments, Student Status)
 */
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Management Reports Hub') ?></h1>
        <p class="text-sm text-muted"><?= te('Comprehensive administrative analytics, operational metrics, and export-ready logs') ?></p>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
    <!-- 1. Attendance Reports -->
    <div class="card" style="border-top:4px solid #2563EB; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="font-size:2rem; margin-bottom:0.5rem;">&#128197;</div>
            <h3 style="margin-bottom:0.4rem;"><?= te('Attendance Reports') ?></h3>
            <p class="text-sm text-muted"><?= te('Daily, weekly, and monthly attendance tracking, present rates, check-in timestamps, and red flag alert summaries.') ?></p>
        </div>
        <div class="mt-3 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/reports/attendance" class="btn btn--primary btn--sm" style="width:100%; text-align:center;">
                <?= te('View Attendance Reports') ?> &rarr;
            </a>
        </div>
    </div>

    <!-- 2. Class Reports -->
    <div class="card" style="border-top:4px solid #2563EB; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="font-size:2rem; margin-bottom:0.5rem;">&#128218;</div>
            <h3 style="margin-bottom:0.4rem;"><?= te('Class & Enrollment Reports') ?></h3>
            <p class="text-sm text-muted"><?= te('Tuition batch rosters, active student headcounts, venue capacity allocations, and teacher schedules.') ?></p>
        </div>
        <div class="mt-3 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/reports/classes" class="btn btn--primary btn--sm" style="width:100%; text-align:center;">
                <?= te('View Class Reports') ?> &rarr;
            </a>
        </div>
    </div>

    <!-- 3. User Reports: staff directory, so admins only -->
    <?php if (can('reports.users')): ?>
    <div class="card" style="border-top:4px solid #2563EB; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="font-size:2rem; margin-bottom:0.5rem;">&#128101;</div>
            <h3 style="margin-bottom:0.4rem;"><?= te('User & Staff Reports') ?></h3>
            <p class="text-sm text-muted"><?= te('Directory breakdown of Admins, Coordinators, Staff, and Teachers with active/inactive statuses and branch assignments.') ?></p>
        </div>
        <div class="mt-3 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/reports/users" class="btn btn--primary btn--sm" style="width:100%; text-align:center;">
                <?= te('View User Reports') ?> &rarr;
            </a>
        </div>
    </div>

    <?php endif; ?>

    <!-- 4. Payment Summary Reports: the takings, so admins only -->
    <?php if (can('reports.money')): ?>
    <div class="card" style="border-top:4px solid #1D4ED8; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="font-size:2rem; margin-bottom:0.5rem;">&#128176;</div>
            <h3 style="margin-bottom:0.4rem;"><?= te('Payment Summary Reports') ?></h3>
            <p class="text-sm text-muted"><?= te('Financial reconciliation, monthly collections vs arrears, teacher revenue splits, and institute earnings breakdown.') ?></p>
        </div>
        <div class="mt-3 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/reports/payments" class="btn btn--primary btn--sm" style="width:100%; text-align:center;">
                <?= te('View Payment Reports') ?> &rarr;
            </a>
        </div>
    </div>

    <?php endif; ?>

    <!-- 5. Student Status Reports -->
    <div class="card" style="border-top:4px solid var(--danger); display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="font-size:2rem; margin-bottom:0.5rem;">&#127891;</div>
            <h3 style="margin-bottom:0.4rem;"><?= te('Student Status Reports') ?></h3>
            <p class="text-sm text-muted"><?= te('Active, inactive, and auto-deactivated student tracking, grace period risks, and enrollment retention audits.') ?></p>
        </div>
        <div class="mt-3 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/reports/students" class="btn btn--primary btn--sm" style="width:100%; text-align:center;">
                <?= te('View Student Status Reports') ?> &rarr;
            </a>
        </div>
    </div>
</div>
