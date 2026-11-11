<?php
/**
 * Super Admin & Role Dashboard with all 16 Widgets (Document 1 Page 1)
 * Widgets are shown/hidden based on $widgetVisibility settings
 */

$user             = $user ?? [];
$role             = $role ?? 'student';
$widgets          = $widgets ?? [];
$widgetVisibility = $widgetVisibility ?? [];

$canSee = function(string $key) use ($widgetVisibility): bool {
    return $widgetVisibility[$key] ?? true;
};

// Greeting
$hour = (int) (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('G');
$greeting = ($hour < 12) ? 'Good morning' : (($hour < 17) ? 'Good afternoon' : 'Good evening');
$displayName = htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>

<div class="mb-3 d-flex justify-between items-center flex-wrap gap-1">
    <div>
        <h1 style="margin-bottom:0.25rem;"><?= te($greeting) ?>, <?= $displayName ?></h1>
        <p class="text-muted" style="margin-bottom:0;"><?= htmlspecialchars(lang_date('l, j F Y', (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d')), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars(\App\Services\PublicSite::brandName(), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php if ($role === 'super_admin'): ?>
    <div>
        <a href="<?= BASE_URL ?>/settings/dashboard-items" class="btn btn--ghost btn--sm">
            &#9881; <?= te('Configure Dashboard Items') ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($widgets['work_queue'])): ?>
<!-- Today's work: real counts, each linked to where it is handled -->
<section class="card mb-3" aria-labelledby="work-queue-title">
    <div class="card__body">
        <h2 id="work-queue-title" style="font-size:1.05rem; margin:0 0 .75rem;"><?= te("Today's work") ?></h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:.75rem;">
            <?php foreach ($widgets['work_queue'] as $item): ?>
                <a href="<?= BASE_URL . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                   class="kpi-card" style="text-decoration:none; border-left:4px solid var(--<?= $item['count'] > 0 ? htmlspecialchars($item['tone'], ENT_QUOTES, 'UTF-8') : 'border' ?>); min-width:0;">
                    <span class="kpi-card__label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="kpi-card__value"><?= number_format((int) $item['count']) ?></span>
                    <span class="text-xs text-muted"><?= (int) $item['count'] > 0 ? te('Open') . ' &rarr;' : te('Nothing waiting') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================================== -->
<!-- ROW 1: PRIMARY FINANCIAL & ATTENDANCE KPIS -->
<!-- ========================================== -->
<div class="kpi-grid mb-3">
    <!-- 1. Active Students -->
    <?php if ($canSee('active_students')): ?>
    <div class="kpi-card animate-slide-up delay-1" style="border-left: 4px solid var(--primary);">
        <span class="kpi-card__label"><?= te('Active Students') ?></span>
        <span class="kpi-card__value"><?= number_format((int) ($widgets['active_students'] ?? 0)) ?></span>
        <span class="text-xs text-muted"><?= te('Currently Enrolled') ?></span>
    </div>
    <?php endif; ?>

    <!-- 2. Today's Attendance -->
    <?php if ($canSee('today_attendance')): ?>
    <div class="kpi-card animate-slide-up delay-2" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te("Today's Attendance") ?></span>
        <span class="kpi-card__value" style="color:#1D4ED8;"><?= number_format((int) ($widgets['today_attendance'] ?? 0)) ?></span>
        <span class="text-xs text-muted"><?= lang_date('l, d M Y') ?></span>
    </div>
    <?php endif; ?>

    <!-- 3. Active Classes -->
    <?php if ($canSee('active_classes')): ?>
    <div class="kpi-card animate-slide-up delay-3" style="border-left: 4px solid #2563EB;">
        <span class="kpi-card__label"><?= te('Active Classes') ?></span>
        <span class="kpi-card__value"><?= number_format((int) ($widgets['active_classes'] ?? 0)) ?></span>
        <span class="text-xs text-muted"><?= !empty($widgets['is_global']) ? te('Across all branches') : te('In your branch') ?></span>
    </div>
    <?php endif; ?>

    <!-- 8. Active Teachers -->
    <?php if ($canSee('active_teachers')): ?>
    <div class="kpi-card animate-slide-up delay-4" style="border-left: 4px solid #2563eb;">
        <span class="kpi-card__label"><?= te('Active Teachers') ?></span>
        <span class="kpi-card__value"><?= number_format((int) ($widgets['active_teachers'] ?? 0)) ?></span>
        <span class="text-xs text-muted"><?= te('Instructional Staff') ?></span>
    </div>
    <?php endif; ?>
</div>

<div class="kpi-grid mb-3">
    <!-- 4. Today's Revenue -->
    <?php if ($canSee('today_revenue')): ?>
    <div class="kpi-card animate-slide-up" style="border-left: 4px solid #1D4ED8;">
        <span class="kpi-card__label"><?= te("Today's Revenue") ?></span>
        <span class="kpi-card__value">Rs. <?= number_format((float) ($widgets['today_revenue'] ?? 0), 2) ?></span>
        <span class="text-xs text-muted"><?= te('Collected today') ?></span>
    </div>
    <?php endif; ?>

    <!-- 5. Monthly Revenue -->
    <?php if ($canSee('monthly_revenue')): ?>
    <div class="kpi-card animate-slide-up" style="border-left: 4px solid #1D4ED8;">
        <span class="kpi-card__label"><?= te('Monthly Revenue') ?></span>
        <span class="kpi-card__value">Rs. <?= number_format((float) ($widgets['monthly_revenue'] ?? 0), 2) ?></span>
        <span class="text-xs text-muted"><?= lang_date('F Y') ?></span>
    </div>
    <?php endif; ?>

    <!-- 6. Pending Payments -->
    <?php if ($canSee('pending_payments')): ?>
    <div class="kpi-card animate-slide-up" style="border-left: 4px solid var(--danger);">
        <span class="kpi-card__label"><?= te('Pending Payments') ?></span>
        <span class="kpi-card__value" style="color:var(--danger);">Rs. <?= number_format((float) ($widgets['pending_payments'] ?? 0), 2) ?></span>
        <span class="text-xs text-muted"><?= te('Overdue fees') ?></span>
    </div>
    <?php endif; ?>

    <!-- 13 & 14. New Students (Today & This Month) -->
    <?php if ($canSee('today_new_students') || $canSee('this_month_new_students')): ?>
    <div class="kpi-card animate-slide-up" style="border-left: 4px solid #F5B301;">
        <span class="kpi-card__label"><?= te('New Admissions') ?></span>
        <div class="d-flex items-center gap-2">
            <?php if ($canSee('today_new_students')): ?>
                <div>
                    <span style="font-size:1.25rem; font-weight:800; color:var(--text);"><?= (int) ($widgets['today_new_students'] ?? 0) ?></span>
                    <span class="text-xs text-muted" style="display:block;"><?= te('Today') ?></span>
                </div>
            <?php endif; ?>
            <?php if ($canSee('this_month_new_students')): ?>
                <div style="padding-left:1rem; border-left:1px solid var(--border);">
                    <span style="font-size:1.25rem; font-weight:800; color:var(--primary);"><?= (int) ($widgets['this_month_new_students'] ?? 0) ?></span>
                    <span class="text-xs text-muted" style="display:block;"><?= te('This Month') ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- ROW 2: ATTENDANCE TREND & OVERVIEW CHARTS -->
<!-- ========================================== -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.25rem;" class="mb-3">
    <!-- 9. Attendance Trend (7 Days) [Daily present vs total] -->
    <?php if ($canSee('attendance_trend_7d')): ?>
    <div class="card">
        <h3 style="margin-bottom:0.75rem; font-size:1rem;"><?= te('Attendance Trend (7 Days)') ?></h3>
        <p class="text-xs text-muted mb-3"><?= te('Daily present student count vs enrolled') ?></p>
        <div style="display:flex; justify-content:space-between; align-items:flex-end; height:140px; padding:0 0.5rem; border-bottom:1px solid var(--divider);">
            <?php 
            $trend = $widgets['attendance_trend_7d'] ?? [];
            $maxPresent = max(1, ...array_map(fn($t) => $t['present'], $trend ?: [['present' => 1]]));
            foreach ($trend as $t): 
                $barHeight = round(($t['present'] / $maxPresent) * 100);
                if ($barHeight < 15) $barHeight = 15;
            ?>
                <div style="display:flex; flex-direction:column; align-items:center; flex:1; gap:0.25rem;">
                    <span class="text-xs font-bold" style="color:var(--primary);"><?= $t['present'] ?></span>
                    <div style="width:24px; height:<?= $barHeight ?>px; background:linear-gradient(180deg, var(--primary) 0%, #B4C5E0 100%); border-radius:4px 4px 0 0;"></div>
                    <span class="text-xs text-muted" style="margin-top:0.25rem;"><?= $t['day'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 7. Today's Attendance Overview & 17. Income Split -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
        <?php if ($canSee('today_attendance_overview')): 
            $attOverview = $widgets['today_attendance_overview'] ?? ['present' => 0, 'total' => 0, 'rate' => 0];
        ?>
        <div class="card">
            <h3 style="margin-bottom:0.5rem; font-size:1rem;"><?= te("Today's Attendance Overview") ?></h3>
            <div class="d-flex items-center justify-between mb-2">
                <span class="text-sm text-muted"><?= te('Attendance Rate') ?>:</span>
                <span class="badge badge--success font-bold" style="font-size:0.9rem;"><?= $attOverview['rate'] ?>%</span>
            </div>
            <div style="width:100%; height:8px; background:var(--surface-sunken); border-radius:4px; overflow:hidden;">
                <div style="width:<?= min(100, $attOverview['rate']) ?>%; height:100%; background:#2563EB; border-radius:4px;"></div>
            </div>
            <div class="d-flex justify-between text-xs text-muted mt-1">
                <span><?= te('Present') ?>: <strong><?= $attOverview['present'] ?></strong></span>
                <span><?= te('Active Total') ?>: <strong><?= $attOverview['total'] ?></strong></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- 17. This Month Income (Institutes & Teachers) -->
        <?php if ($canSee('this_month_income')): 
            $inc = $widgets['this_month_income'] ?? ['total' => 0, 'teacher_share' => 0, 'institute_share' => 0];
        ?>
        <div class="card">
            <h3 style="margin-bottom:0.5rem; font-size:1rem;"><?= te('This Month Income (Institutes & Teachers)') ?></h3>
            <div class="d-flex justify-between items-center mb-1">
                <span class="text-xs text-muted"><?= te('Total Collections') ?>:</span>
                <strong style="color:var(--text);">Rs. <?= number_format($inc['total'], 2) ?></strong>
            </div>
            <div class="d-flex gap-2 mt-2">
                <div style="flex:1; padding:0.5rem; background:#eff6ff; border-radius:var(--radius-sm); border-left:3px solid #2563eb;">
                    <div class="text-xs text-muted"><?= te('Teachers Share') ?></div>
                    <div style="font-weight:700; color:#1d4ed8; font-size:0.875rem;">Rs. <?= number_format($inc['teacher_share'], 2) ?></div>
                </div>
                <div style="flex:1; padding:0.5rem; background:#f0fdf4; border-radius:var(--radius-sm); border-left:3px solid #16a34a;">
                    <div class="text-xs text-muted"><?= te('Institute Share') ?></div>
                    <div style="font-weight:700; color:#15803d; font-size:0.875rem;">Rs. <?= number_format($inc['institute_share'], 2) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- ROW 3: CLASSES DISTRIBUTION & SCHEDULES -->
<!-- ========================================== -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.25rem;" class="mb-3">
    <!-- 10. Students by Class [Active student distribution] -->
    <?php if ($canSee('students_by_class')): ?>
    <div class="card">
        <h3 style="margin-bottom:0.75rem; font-size:1rem;"><?= te('Students by Class (Top Distribution)') ?></h3>
        <?php if (!empty($widgets['students_by_class'])): ?>
            <div style="display:flex; flex-direction:column; gap:0.6rem;">
                <?php foreach ($widgets['students_by_class'] as $sc): ?>
                    <div>
                        <div class="d-flex justify-between text-sm mb-1">
                            <span style="font-weight:600;"><?= htmlspecialchars($sc['class_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="badge badge--info font-bold"><?= (int) $sc['student_count'] ?> <?= te('students') ?></span>
                        </div>
                        <div class="text-xs text-muted"><?= htmlspecialchars($sc['subject'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($sc['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-sm text-muted"><?= te('No student enrollments yet.') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 12. Today Classes & 15. Upcoming Classes -->
    <?php if ($canSee('today_classes') || $canSee('upcoming_classes')): ?>
    <div class="card">
        <h3 style="margin-bottom:0.75rem; font-size:1rem;"><?= te('Classes Scheduled for Today') ?> (<?= lang_date('l') ?>)</h3>
        <?php if (!empty($widgets['today_classes'])): ?>
            <div style="display:flex; flex-direction:column; gap:0.6rem;">
                <?php foreach (array_slice($widgets['today_classes'], 0, 5) as $tc): ?>
                    <div style="padding:0.5rem 0.75rem; background:var(--surface-sunken); border-radius:var(--radius-sm); border-left:3px solid var(--primary); display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:600; font-size:0.875rem;"><?= htmlspecialchars($tc['class_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-xs text-muted">
                                <?= htmlspecialchars($tc['teacher_name'] ?? te('TBA'), ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($tc['institute_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge--teal" style="font-size:0.7rem;">
                                <?= !empty($tc['start_time']) ? lang_date('g:i A', strtotime($tc['start_time'])) : te('TBD') ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-sm text-muted"><?= te('No classes scheduled for today.') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- ROW 4: ALL ACTIVE CLASSES & RECENT ACTIVITY-->
<!-- ========================================== -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap:1.25rem;" class="mb-3">
    <!-- 11. All Active Classes -->
    <?php if ($canSee('all_active_classes')): ?>
    <div class="card">
        <div class="d-flex justify-between items-center mb-2">
            <h3 style="font-size:1rem; margin:0;"><?= te('Active Classes Directory') ?></h3>
            <a href="<?= BASE_URL ?>/classes" class="text-xs text-primary font-bold"><?= te('View All') ?> &rarr;</a>
        </div>
        <div class="table-wrapper">
            <table class="data-table" style="font-size:0.8125rem;">
                <thead>
                    <tr>
                        <th><?= te('Class') ?></th>
                        <th><?= te('Teacher') ?></th>
                        <th><?= te('Students') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($widgets['all_active_classes'])): ?>
                        <?php foreach ($widgets['all_active_classes'] as $ac): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ac['class_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($ac['institute_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars($ac['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge badge--neutral"><?= (int) ($ac['student_count'] ?? 0) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-muted text-center"><?= te('No active classes.') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- 16. Recent Messages & Workflow Approvals -->
    <?php if ($canSee('recent_messages_approvals')): ?>
    <div class="card">
        <h3 style="margin-bottom:0.75rem; font-size:1rem;"><?= te('Recent Messages & Workflow Approvals') ?></h3>
        
        <?php if (!empty($widgets['recent_workflows'])): ?>
            <div class="mb-2">
                <span class="text-xs font-bold text-muted uppercase"><?= te('Pending Workflows') ?></span>
                <div style="display:flex; flex-direction:column; gap:0.4rem; margin-top:0.25rem;">
                    <?php foreach ($widgets['recent_workflows'] as $wf): ?>
                        <div class="d-flex justify-between items-center" style="padding:0.4rem 0.6rem; background:#fff1f2; border-radius:var(--radius-sm); border-left:3px solid var(--danger);">
                            <span class="text-xs">
                                <strong><?= ucfirst(str_replace('_', ' ', $wf['type'])) ?>:</strong> 
                                <?= htmlspecialchars($wf['student_name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <a href="<?= BASE_URL ?>/workflows" class="badge badge--danger" style="font-size:0.65rem;"><?= te('Review') ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($widgets['recent_messages'])): ?>
            <div>
                <span class="text-xs font-bold text-muted uppercase"><?= te('Recent System Messages') ?></span>
                <div style="display:flex; flex-direction:column; gap:0.4rem; margin-top:0.25rem;">
                    <?php foreach (array_slice($widgets['recent_messages'], 0, 4) as $msg): ?>
                        <div style="padding:0.4rem 0.6rem; background:var(--surface-sunken); border-radius:var(--radius-sm);">
                            <div class="text-xs font-bold"><?= htmlspecialchars($msg['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars(mb_strimwidth($msg['body'] ?? '', 0, 60, '...'), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-sm text-muted"><?= te('No recent notifications.') ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
