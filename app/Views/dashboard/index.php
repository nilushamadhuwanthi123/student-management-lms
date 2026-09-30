<?php
/**
 * Dashboard - Main Index
 *
 * Variables: $user, $role, $stats, $recentActivity, $quickActions
 */

$user           = $user ?? [];
$role           = $role ?? 'student';
$stats          = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$quickActions   = $quickActions ?? [];

// Greeting based on Asia/Colombo time
$hour = (int) (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('G');
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

$displayName = htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>

<div class="mb-3">
    <h1 style="margin-bottom:0.25rem;"><?= $greeting ?>, <?= $displayName ?></h1>
    <p class="text-muted" style="margin-bottom:0;">Welcome to your dashboard. Here is an overview of the system.</p>
</div>

<!-- KPI Cards -->
<?php if (in_array($role, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
<div class="kpi-grid mb-3">
    <div class="kpi-card animate-slide-up delay-1">
        <span class="kpi-card__label">Total Students</span>
        <span class="kpi-card__value"><?= number_format((int) ($stats['total_students'] ?? 0)) ?></span>
        <?php if (isset($stats['active_students'])): ?>
            <span class="kpi-card__change kpi-card__change--up"><?= (int) $stats['active_students'] ?> active</span>
        <?php endif; ?>
    </div>
    <div class="kpi-card animate-slide-up delay-2">
        <span class="kpi-card__label">Active Classes</span>
        <span class="kpi-card__value"><?= number_format((int) ($stats['active_classes'] ?? 0)) ?></span>
    </div>
    <div class="kpi-card animate-slide-up delay-3">
        <span class="kpi-card__label">Revenue This Month</span>
        <span class="kpi-card__value">LKR <?= number_format((float) ($stats['revenue_collected'] ?? 0), 2) ?></span>
        <?php if (isset($stats['revenue_pending']) && $stats['revenue_pending'] > 0): ?>
            <span class="kpi-card__change kpi-card__change--down">LKR <?= number_format((float) $stats['revenue_pending'], 2) ?> pending</span>
        <?php endif; ?>
    </div>
    <div class="kpi-card animate-slide-up delay-4">
        <span class="kpi-card__label">Pending Approvals</span>
        <span class="kpi-card__value"><?= number_format((int) ($stats['pending_workflows'] ?? $stats['my_pending_approvals'] ?? 0)) ?></span>
    </div>
</div>
<?php endif; ?>

<?php if ($role === 'teacher'): ?>
<div class="kpi-grid mb-3">
    <div class="kpi-card animate-slide-up delay-1">
        <span class="kpi-card__label">Assigned Classes</span>
        <span class="kpi-card__value"><?= number_format((int) ($stats['assigned_classes'] ?? 0)) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<?php if (in_array($role, ['super_admin', 'admin', 'coordinator', 'staff'])): ?>
<div class="mb-3">
    <h3>Quick Actions</h3>
    <div class="d-flex flex-wrap gap-1" style="margin-top:0.75rem;">
        <a href="/students/create" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Register Student
        </a>
        <a href="/payments" class="btn btn--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Mark Payments
        </a>
        <a href="/workflows" class="btn btn--secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            View Workflows
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="card">
    <h3 style="margin-bottom:1rem;">Recent Activity</h3>
    <?php if (!empty($recentActivity)): ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid var(--divider);">
                    <div class="avatar avatar--sm">
                        <?= strtoupper(mb_substr(htmlspecialchars($activity['user_name'] ?? 'S', ENT_QUOTES, 'UTF-8'), 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="text-sm" style="color:var(--text);">
                            <strong><?= htmlspecialchars($activity['user_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></strong>
                            <?= htmlspecialchars($activity['action'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            <span class="text-muted"><?= htmlspecialchars($activity['entity_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="text-xs text-muted" style="margin-top:0.15rem;">
                            <?= htmlspecialchars($activity['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state__icon">&#128196;</div>
            <div class="empty-state__title">No recent activity</div>
            <div class="empty-state__text">Activity from the system will appear here.</div>
        </div>
    <?php endif; ?>
</div>
