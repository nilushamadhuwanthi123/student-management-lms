<?php
/**
 * About Page
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', BASE_URL . '/'], ['About', '#']];
?>

<!-- Page Header -->
<section style="padding: 2rem 0 1rem;">
    <div class="container">
        <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        <div class="page-header">
            <h1 class="page-header__title">About EduManager</h1>
        </div>
    </div>
</section>

<!-- Mission Section -->
<section style="padding: 2rem 0 3rem;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;" class="animate-slide-up">
            <div style="width: 64px; height: 64px; border-radius: var(--radius-lg); background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: var(--primary); font-size: 1.5rem;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h2>Our Mission</h2>
            <p style="font-size: 1.0625rem; line-height: 1.8;">
                EduManager was built with a singular purpose: to simplify the complexities of
                educational institution management. We believe that administrators, teachers, and
                staff should spend less time on paperwork and more time on what truly matters
                &mdash; educating students. Our platform brings together every aspect of institute
                management into one seamless, intuitive system.
            </p>
        </div>
    </div>
</section>

<!-- Values Section -->
<section style="padding: 3rem 0; background: var(--surface-sunken);">
    <div class="container">
        <div class="text-center mb-4">
            <h2>Our Core Values</h2>
            <p>The principles that guide everything we build.</p>
        </div>

        <div class="card-grid">
            <div class="card text-center animate-slide-up delay-1">
                <div class="card__icon" style="margin: 0 auto 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <h3 class="card__title">Innovation</h3>
                <p class="card__text">
                    We constantly evolve our platform with modern technologies and user-driven
                    improvements. From QR-based attendance to automated lifecycle management,
                    we build features that matter.
                </p>
            </div>

            <div class="card text-center animate-slide-up delay-2">
                <div class="card__icon" style="margin: 0 auto 1rem; background: var(--secondary-light); color: var(--secondary);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <h3 class="card__title">Security</h3>
                <p class="card__text">
                    Student data is sensitive. Our role-based access control, audit logging,
                    and CSRF protection ensure that your information remains safe and
                    access is tightly controlled.
                </p>
            </div>

            <div class="card text-center animate-slide-up delay-3">
                <div class="card__icon" style="margin: 0 auto 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                </div>
                <h3 class="card__title">Simplicity</h3>
                <p class="card__text">
                    Complex operations, simple interface. We design every screen so that anyone
                    on your team can use it effectively, regardless of their technical background.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Technology Section -->
<section style="padding: 3rem 0;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;" class="animate-slide-up">
            <h2>Built with Modern Technology</h2>
            <p style="font-size: 1.0625rem; line-height: 1.8; margin-bottom: 2rem;">
                EduManager is crafted using proven, reliable technologies. Our backend runs on
                <strong>PHP</strong> with a clean MVC architecture, powered by <strong>MySQL</strong>
                for robust data management. The frontend features a modern glassmorphism design
                system with responsive layouts, dark mode support, and accessible components &mdash;
                ensuring a premium experience on every device.
            </p>
            <div class="d-flex gap-2 justify-center flex-wrap">
                <span class="badge badge--info" style="font-size: 0.875rem; padding: 0.4rem 1rem;">PHP 8.1+</span>
                <span class="badge badge--info" style="font-size: 0.875rem; padding: 0.4rem 1rem;">MySQL</span>
                <span class="badge badge--info" style="font-size: 0.875rem; padding: 0.4rem 1rem;">MVC Architecture</span>
                <span class="badge badge--info" style="font-size: 0.875rem; padding: 0.4rem 1rem;">Responsive Design</span>
                <span class="badge badge--info" style="font-size: 0.875rem; padding: 0.4rem 1rem;">Glassmorphism UI</span>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 3rem 0; background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 100%);">
    <div class="container text-center">
        <h2 style="margin-bottom: 0.75rem;">Want to Learn More?</h2>
        <p style="max-width: 480px; margin: 0 auto 2rem;">
            Discover the full range of features that make EduManager the ideal choice for your institute.
        </p>
        <div class="d-flex gap-2 justify-center flex-wrap">
            <a href="<?= BASE_URL ?>/features" class="btn btn--primary btn--lg">Explore Features</a>
            <a href="<?= BASE_URL ?>/contact" class="btn btn--secondary btn--lg">Contact Us</a>
        </div>
    </div>
</section>
