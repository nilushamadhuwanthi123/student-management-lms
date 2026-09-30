<?php
/**
 * Home Page
 * Layout: layouts/public
 */
?>

<!-- Hero Section -->
<section class="hero" style="background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 50%, var(--primary-light) 100%);">
    <div class="hero__overlay"></div>
    <div class="hero__content animate-slide-up">
        <h1 class="hero__title">Transform Your Educational Management</h1>
        <p class="hero__subtitle">
            Streamline institute operations with our all-in-one platform. From student enrollment
            and payment tracking to automated workflows and real-time analytics.
        </p>
        <div class="d-flex gap-2 justify-center flex-wrap">
            <a href="/login" class="btn btn--primary btn--lg">Get Started</a>
            <a href="/features" class="btn btn--secondary btn--lg">Learn More</a>
        </div>
    </div>
</section>

<!-- Stats Strip -->
<section style="background: var(--surface); border-bottom: 1px solid var(--border);">
    <div class="container">
        <div class="stats-strip">
            <div class="stats-strip__item animate-slide-up delay-1">
                <span style="font-size: 1.5rem; margin-bottom: 0.25rem; display: block;">&#127891;</span>
                <span class="stats-strip__number">500+</span>
                <span class="stats-strip__label">Students Managed</span>
            </div>
            <div class="stats-strip__item animate-slide-up delay-2">
                <span style="font-size: 1.5rem; margin-bottom: 0.25rem; display: block;">&#127979;</span>
                <span class="stats-strip__number">50+</span>
                <span class="stats-strip__label">Active Classes</span>
            </div>
            <div class="stats-strip__item animate-slide-up delay-3">
                <span style="font-size: 1.5rem; margin-bottom: 0.25rem; display: block;">&#128100;</span>
                <span class="stats-strip__number">20+</span>
                <span class="stats-strip__label">Dedicated Teachers</span>
            </div>
            <div class="stats-strip__item animate-slide-up delay-4">
                <span style="font-size: 1.5rem; margin-bottom: 0.25rem; display: block;">&#9889;</span>
                <span class="stats-strip__number">99.9%</span>
                <span class="stats-strip__label">System Uptime</span>
            </div>
        </div>
    </div>
</section>

<!-- Module Cards Section -->
<section style="padding: 4rem 0;">
    <div class="container">
        <div class="text-center mb-4">
            <h2>Everything You Need to Run Your Institute</h2>
            <p style="max-width: 600px; margin: 0 auto;">
                Powerful modules designed to handle every aspect of educational institution management.
            </p>
        </div>

        <div class="card-grid">
            <!-- Student Management -->
            <div class="card card--clickable animate-slide-up delay-1">
                <div class="card__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3 class="card__title">Student Management</h3>
                <p class="card__text">
                    Complete student registration, enrollment tracking, and multi-class management
                    with automated ID generation and lifecycle workflows.
                </p>
            </div>

            <!-- Payment Tracking -->
            <div class="card card--clickable animate-slide-up delay-2">
                <div class="card__icon" style="background: var(--secondary-light); color: var(--secondary);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <h3 class="card__title">Payment Tracking</h3>
                <p class="card__text">
                    Monthly fee management with scholarship support, overdue alerts, and
                    comprehensive payment history for every student.
                </p>
            </div>

            <!-- Attendance System -->
            <div class="card card--clickable animate-slide-up delay-3">
                <div class="card__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <path d="M9 16l2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="card__title">Attendance System</h3>
                <p class="card__text">
                    QR-based attendance marking with real-time tracking, detailed reports,
                    and automated notifications for absences.
                </p>
            </div>

            <!-- Role-Based Access -->
            <div class="card card--clickable animate-slide-up delay-1">
                <div class="card__icon" style="background: var(--secondary-light); color: var(--secondary);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h3 class="card__title">Role-Based Access</h3>
                <p class="card__text">
                    Seven distinct user roles with granular permission levels ensuring
                    secure access control across your entire organization.
                </p>
            </div>

            <!-- Automated Workflows -->
            <div class="card card--clickable animate-slide-up delay-2">
                <div class="card__icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 3 21 3 21 8"/>
                        <line x1="4" y1="20" x2="21" y2="3"/>
                        <polyline points="21 16 21 21 16 21"/>
                        <line x1="15" y1="15" x2="21" y2="21"/>
                        <line x1="4" y1="4" x2="9" y2="9"/>
                    </svg>
                </div>
                <h3 class="card__title">Automated Workflows</h3>
                <p class="card__text">
                    Smart deactivation, scheduled deletion, and multi-level approval
                    workflows to keep your data clean and compliant.
                </p>
            </div>

            <!-- Analytics & Reports -->
            <div class="card card--clickable animate-slide-up delay-3">
                <div class="card__icon" style="background: var(--secondary-light); color: var(--secondary);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <h3 class="card__title">Analytics &amp; Reports</h3>
                <p class="card__text">
                    Real-time dashboard with key metrics, comprehensive audit logs,
                    and exportable reports for informed decision-making.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Bottom CTA Section -->
<section style="padding: 4rem 0; background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 100%);">
    <div class="container text-center">
        <h2 style="margin-bottom: 0.75rem;">Ready to Transform Your Institute?</h2>
        <p style="max-width: 500px; margin: 0 auto 2rem;">
            Join hundreds of educational institutions already using EduManager to streamline their operations.
        </p>
        <a href="/login" class="btn btn--primary btn--lg">Login to Get Started</a>
    </div>
</section>
