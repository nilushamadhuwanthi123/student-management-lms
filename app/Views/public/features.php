<?php
/**
 * Features Page
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', '/'], ['Features', '#']];
?>

<!-- Page Header -->
<section style="padding: 2rem 0 1rem;">
    <div class="container">
        <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        <div class="page-header">
            <h1 class="page-header__title">Features</h1>
        </div>
        <p style="max-width: 600px; color: var(--text-secondary);">
            Discover the comprehensive tools and modules that power EduManager.
        </p>
    </div>
</section>

<!-- Feature Sections (Alternating Layout) -->
<section style="padding: 2rem 0 4rem;">
    <div class="container">

        <?php
        $features = [
            [
                'title'       => 'Student 360&deg; Management',
                'description' => 'Get a complete view of every student from the moment they register. Track personal details, enrollment history, payment records, and attendance all in one place.',
                'bullets'     => [
                    'Auto-generated unique student IDs (STU20260001)',
                    'Multi-class enrollment with per-class fee tracking',
                    'Student lifecycle management (active, inactive, deactivated)',
                    'Parent and guardian information linking',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                'color'       => 'primary',
            ],
            [
                'title'       => 'Smart Payment Engine',
                'description' => 'Automate monthly fee generation, track payments in real time, and never lose sight of overdue balances. Support for scholarships and flexible fee structures.',
                'bullets'     => [
                    'Automatic monthly fee record generation per enrollment',
                    'Scholarship percentage support per student per class',
                    'Overdue payment alerts and risk assessment',
                    'Complete payment history with audit trail',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
                'color'       => 'secondary',
            ],
            [
                'title'       => 'Automated Lifecycle Management',
                'description' => 'Let the system handle routine administrative tasks. Configurable rules automatically deactivate inactive students and schedule data cleanup with approval workflows.',
                'bullets'     => [
                    'Configurable inactivity periods for auto-deactivation',
                    'Scheduled deletion with grace periods',
                    'Multi-level approval workflows for sensitive actions',
                    'Complete workflow history and audit logging',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>',
                'color'       => 'primary',
            ],
            [
                'title'       => 'Role-Based Security',
                'description' => 'Seven distinct user roles ensure that every team member has exactly the access they need. From super admins to data entry operators, permissions are precise and auditable.',
                'bullets'     => [
                    'Super Admin, Admin, Manager, Teacher, Accountant, Data Entry, Parent',
                    'Granular permission matrix per role',
                    'First-login password change enforcement',
                    'Session management and activity logging',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>',
                'color'       => 'secondary',
            ],
            [
                'title'       => 'Real-Time Analytics',
                'description' => 'Make data-driven decisions with a live dashboard showing key metrics. Monitor enrollment trends, payment collection rates, and system activity at a glance.',
                'bullets'     => [
                    'KPI cards for students, payments, classes, and revenue',
                    'Overdue fee monitoring with risk indicators',
                    'Comprehensive audit log with filtering',
                    'Exportable reports for stakeholders',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
                'color'       => 'primary',
            ],
            [
                'title'       => 'Multi-Institute Support',
                'description' => 'Manage multiple institutes from a single installation. Each institute maintains its own classes, students, and configurations while sharing a unified admin experience.',
                'bullets'     => [
                    'Separate institute profiles with unique settings',
                    'Institute-specific class and fee management',
                    'Cross-institute reporting and analytics',
                    'Centralized user management across institutes',
                ],
                'icon'        => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
                'color'       => 'secondary',
            ],
        ];
        ?>

        <?php foreach ($features as $i => $feature): ?>
            <?php $isReversed = ($i % 2 !== 0); ?>
            <div style="display: flex; flex-wrap: wrap; gap: 2.5rem; align-items: center; padding: 2.5rem 0; <?= $i > 0 ? 'border-top: 1px solid var(--divider);' : '' ?>" class="animate-slide-up">

                <!-- Icon / Visual Side -->
                <div style="flex: 1; min-width: 260px; <?= $isReversed ? 'order: 2;' : 'order: 1;' ?> display: flex; justify-content: center;">
                    <div style="width: 120px; height: 120px; border-radius: var(--radius-xl); background: var(--<?= $feature['color'] ?>-light); display: flex; align-items: center; justify-content: center; color: var(--<?= $feature['color'] ?>);">
                        <?= $feature['icon'] ?>
                    </div>
                </div>

                <!-- Text Side -->
                <div style="flex: 2; min-width: 300px; <?= $isReversed ? 'order: 1;' : 'order: 2;' ?>">
                    <h3 style="font-size: 1.35rem; margin-bottom: 0.75rem;"><?= $feature['title'] ?></h3>
                    <p style="line-height: 1.7; margin-bottom: 1rem;"><?= htmlspecialchars($feature['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($feature['bullets'] as $bullet): ?>
                            <li style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.9375rem; color: var(--text-secondary);">
                                <span style="color: var(--primary); font-weight: 700; flex-shrink: 0;">&#10003;</span>
                                <?= htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- Bottom CTA -->
<section style="padding: 4rem 0; background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 100%);">
    <div class="container text-center">
        <h2 style="margin-bottom: 0.75rem;">Experience All Features Today</h2>
        <p style="max-width: 480px; margin: 0 auto 2rem;">
            Log in to explore the full power of EduManager for your educational institute.
        </p>
        <a href="/login" class="btn btn--primary btn--lg">Login to Get Started</a>
    </div>
</section>
