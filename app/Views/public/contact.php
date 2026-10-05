<?php
/**
 * Contact Page — Glassy Premium Design
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', '/'], ['Contact', '#']];
?>

<!-- Page Header Hero -->
<section class="py-16 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(14,111,88,0.08) 0%, rgba(17,124,131,0.05) 100%);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="mb-4">
            <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        </div>
        <div class="max-w-2xl reveal">
            <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-4"
                  style="background: var(--primary-light); color: var(--primary);">
                Get In Touch
            </span>
            <h1 class="font-display font-bold mb-4" style="font-size: clamp(2rem, 4vw, 3rem); color: var(--text); letter-spacing: -0.02em;">
                We're Here to Help Your<br>
                <span style="color: var(--primary);">Institution Succeed</span>
            </h1>
            <p class="text-base sm:text-lg" style="color: var(--text-secondary); line-height: 1.7;">
                Have questions about onboarding your institute, requesting a customized demo, or technical assistance? Send us a message and our team will get back to you promptly.
            </p>
        </div>
    </div>
</section>

<!-- Contact Content Grid -->
<section class="py-16" style="background: var(--bg);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Contact Form Card -->
            <div class="lg:col-span-7 reveal">
                <div class="card p-6 sm:p-8 rounded-2xl shadow-sm border" style="background: var(--surface); border-color: var(--border);">
                    <div class="mb-6">
                        <h2 class="font-semibold text-xl mb-1" style="color: var(--text);">Send Us a Direct Inquiry</h2>
                        <p class="text-sm" style="color: var(--text-muted);">Fill in the form below and we'll reply to your email within 24 business hours.</p>
                    </div>

                    <form action="<?= BASE_URL ?>/contact" method="POST" class="space-y-5">
                        <?= csrfField() ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label text-xs font-semibold" for="contact_name">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="contact_name" name="name" class="form-control"
                                       placeholder="e.g. Dr. Kasun Perera" required
                                       value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div>
                                <label class="form-label text-xs font-semibold" for="contact_email">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="contact_email" name="email" class="form-control"
                                       placeholder="kasun@institute.edu" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label text-xs font-semibold" for="contact_phone">
                                    Contact Phone Number
                                </label>
                                <input type="tel" id="contact_phone" name="phone" class="form-control"
                                       placeholder="+94 77 123 4567"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div>
                                <label class="form-label text-xs font-semibold" for="contact_subject">
                                    Subject <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="contact_subject" name="subject" class="form-control"
                                       placeholder="Institute Onboarding / Demo" required
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs font-semibold" for="contact_message">
                                Message Details <span class="text-danger">*</span>
                            </label>
                            <textarea id="contact_message" name="message" class="form-control" rows="5"
                                      placeholder="Tell us about your educational institution, student count, and key requirements..." required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn--primary btn--lg w-full sm:w-auto font-semibold shadow-md btn-glow" style="border-radius: 9999px; padding-left: 2rem; padding-right: 2rem;">
                                Send Message &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact Information & Quick Actions -->
            <div class="lg:col-span-5 space-y-6 reveal">

                <!-- Fast WhatsApp Card -->
                <div class="p-6 rounded-2xl border" style="background: linear-gradient(135deg, rgba(37,211,102,0.1) 0%, rgba(18,140,126,0.08) 100%); border-color: rgba(37,211,102,0.25);">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white" style="background: #25D366;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-base" style="color: var(--text);">Instant WhatsApp Desk</h3>
                            <p class="text-xs" style="color: var(--text-muted);">Quick queries &amp; immediate support</p>
                        </div>
                    </div>
                    <p class="text-xs mb-4" style="color: var(--text-secondary);">Connect directly with our admissions and technical advisory team via WhatsApp messenger.</p>
                    <a href="https://wa.me/94771234567?text=Hello%20EduManager%2C%20I%20would%20like%20to%20learn%20more%20about%20the%20LMS%20platform" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm transition-all"
                       style="background: #25D366;">
                        Chat on WhatsApp &rarr;
                    </a>
                </div>

                <!-- Contact Details Card -->
                <div class="card p-6 rounded-2xl border" style="background: var(--surface); border-color: var(--border);">
                    <h3 class="font-semibold text-base mb-4" style="color: var(--text);">Headquarters &amp; Direct Lines</h3>
                    <ul class="space-y-4 text-sm">
                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--primary-light); color: var(--primary);">
                                &#9962;
                            </div>
                            <div>
                                <span class="font-semibold block text-xs" style="color: var(--text);">Campus Address</span>
                                <span class="text-xs" style="color: var(--text-secondary);">123 Education Lane, Colombo 07, Sri Lanka</span>
                            </div>
                        </li>

                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--primary-light); color: var(--primary);">
                                &#9742;
                            </div>
                            <div>
                                <span class="font-semibold block text-xs" style="color: var(--text);">Direct Phone</span>
                                <a href="tel:+94112345678" class="text-xs hover:underline" style="color: var(--primary);">+94 11 234 5678 / +94 77 123 4567</a>
                            </div>
                        </li>

                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--primary-light); color: var(--primary);">
                                &#9993;
                            </div>
                            <div>
                                <span class="font-semibold block text-xs" style="color: var(--text);">Official Email</span>
                                <a href="mailto:info@edumanager.com" class="text-xs hover:underline" style="color: var(--primary);">info@edumanager.com</a>
                            </div>
                        </li>

                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: var(--primary-light); color: var(--primary);">
                                &#128340;
                            </div>
                            <div>
                                <span class="font-semibold block text-xs" style="color: var(--text);">Operating Hours</span>
                                <span class="text-xs" style="color: var(--text-secondary);">Mon &ndash; Fri: 8:30 AM &ndash; 5:30 PM (IST)<br>Sat: 9:00 AM &ndash; 1:00 PM</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Interactive Location Map Card -->
                <div class="card rounded-2xl border overflow-hidden" style="background: var(--surface); border-color: var(--border);">
                    <div class="p-3 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between text-xs">
                        <span class="font-semibold" style="color: var(--text);">Colombo Central Campus</span>
                        <span class="badge badge--success text-[10px]">Open for Visitors</span>
                    </div>
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.798511757686!2d79.85966457499645!3d6.914677493084803!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae2596cd0bd99fb%3A0xd680789febe8b776!2sColombo%2007%2C%20Colombo!5e0!3m2!1sen!2slk!4v1700000000000!5m2!1sen!2slk"
                        width="100%"
                        height="200"
                        style="border:0; display:block;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>

            </div>
        </div>
    </div>
</section>
