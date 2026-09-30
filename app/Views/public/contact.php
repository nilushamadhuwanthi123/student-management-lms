<?php
/**
 * Contact Page
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', '/'], ['Contact', '#']];
?>

<!-- Page Header -->
<section style="padding: 2rem 0 1rem;">
    <div class="container">
        <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        <div class="page-header">
            <h1 class="page-header__title">Contact Us</h1>
        </div>
        <p style="max-width: 600px; color: var(--text-secondary);">
            Have a question or need assistance? We would love to hear from you.
        </p>
    </div>
</section>

<!-- Contact Section -->
<section style="padding: 2rem 0 4rem;">
    <div class="container">
        <div class="contact-grid" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">

            <!-- Contact Form -->
            <div>
                <div class="card" style="padding: 2rem;">
                    <h3 style="margin-bottom: 1.5rem;">Send us a Message</h3>
                    <form action="/contact" method="POST">
                        <?= csrfField() ?>

                        <div class="form-row form-row--2">
                            <div class="form-group">
                                <label class="form-label" for="contact_name">
                                    Full Name <span class="required">*</span>
                                </label>
                                <input type="text" id="contact_name" name="name" class="form-control"
                                       placeholder="Your full name" required
                                       value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['name'])): ?>
                                    <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_email">
                                    Email Address <span class="required">*</span>
                                </label>
                                <input type="email" id="contact_email" name="email" class="form-control"
                                       placeholder="you@example.com" required
                                       value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['email'])): ?>
                                    <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_subject">
                                Subject <span class="required">*</span>
                            </label>
                            <input type="text" id="contact_subject" name="subject" class="form-control"
                                   placeholder="How can we help?" required
                                   value="<?= htmlspecialchars($old['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (!empty($errors['subject'])): ?>
                                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact_message">
                                Message <span class="required">*</span>
                            </label>
                            <textarea id="contact_message" name="message" class="form-control"
                                      placeholder="Tell us more about your inquiry..." rows="5" required><?= htmlspecialchars($old['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <?php if (!empty($errors['message'])): ?>
                                <div class="form-feedback form-feedback--invalid"><?= htmlspecialchars($errors['message'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg">Send Message</button>
                    </form>
                </div>
            </div>

            <!-- Contact Info Sidebar -->
            <div>
                <div class="card" style="padding: 2rem; margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 1.25rem;">Contact Information</h3>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 1.25rem;">
                        <li style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="color: var(--primary); font-size: 1.25rem; flex-shrink: 0; margin-top: 0.1rem;">&#9962;</span>
                            <div>
                                <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.2rem;">Address</strong>
                                <span class="text-secondary text-sm">123 Education Lane, Colombo 07, Sri Lanka</span>
                            </div>
                        </li>
                        <li style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="color: var(--primary); font-size: 1.25rem; flex-shrink: 0; margin-top: 0.1rem;">&#9742;</span>
                            <div>
                                <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.2rem;">Phone</strong>
                                <span class="text-secondary text-sm">+94 11 234 5678</span>
                            </div>
                        </li>
                        <li style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="color: var(--primary); font-size: 1.25rem; flex-shrink: 0; margin-top: 0.1rem;">&#9993;</span>
                            <div>
                                <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.2rem;">Email</strong>
                                <span class="text-secondary text-sm">info@edumanager.com</span>
                            </div>
                        </li>
                        <li style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="color: var(--primary); font-size: 1.25rem; flex-shrink: 0; margin-top: 0.1rem;">&#128340;</span>
                            <div>
                                <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.2rem;">Office Hours</strong>
                                <span class="text-secondary text-sm">Monday &ndash; Friday: 8:30 AM &ndash; 5:30 PM<br>Saturday: 9:00 AM &ndash; 1:00 PM</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Map Placeholder -->
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="background: var(--surface-sunken); height: 220px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.875rem;">
                        <div class="text-center">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.5rem; opacity: 0.4;">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <p class="mb-0">Map placeholder &mdash; Colombo, Sri Lanka</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (min-width: 768px) {
            .contact-grid {
                grid-template-columns: 3fr 2fr !important;
            }
        }
    </style>
</section>
