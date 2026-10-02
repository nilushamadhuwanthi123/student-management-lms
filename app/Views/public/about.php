<?php
/**
 * About Page — Glassy Premium Design
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', '/'], ['About', '#']];
?>

<!-- Hero Section -->
<section class="py-20 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(14,111,88,0.12) 0%, rgba(17,124,131,0.06) 100%);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="mb-4">
            <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        </div>
        <div class="max-w-3xl reveal">
            <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-4"
                  style="background: var(--primary-light); color: var(--primary);">
                Our Story &amp; Vision
            </span>
            <h1 class="font-display font-bold mb-6" style="font-size: clamp(2.25rem, 4.5vw, 3.5rem); color: var(--text); letter-spacing: -0.02em;">
                Empowering the Future of<br>
                <span style="color: var(--primary);">Educational Leadership</span>
            </h1>
            <p class="text-base sm:text-lg" style="color: var(--text-secondary); line-height: 1.8;">
                EduManager was engineered to resolve the real-world operational bottlenecks faced by modern educational institutes, tuition networks, and multi-branch academies across Sri Lanka and beyond.
            </p>
        </div>
    </div>
</section>

<!-- Impact Numbers Strip -->
<section class="py-12 border-y" style="background: var(--surface); border-color: var(--border);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center reveal-stagger">
            <div class="reveal">
                <div class="font-display text-3xl sm:text-4xl font-bold mb-1" style="color: var(--primary);" data-count="50+">50+</div>
                <div class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-muted);">Partner Institutes</div>
            </div>
            <div class="reveal">
                <div class="font-display text-3xl sm:text-4xl font-bold mb-1" style="color: var(--primary);" data-count="25,000+">25,000+</div>
                <div class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-muted);">Students Managed</div>
            </div>
            <div class="reveal">
                <div class="font-display text-3xl sm:text-4xl font-bold mb-1" style="color: var(--primary);" data-count="99.9%">99.9%</div>
                <div class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-muted);">Platform Availability</div>
            </div>
            <div class="reveal">
                <div class="font-display text-3xl sm:text-4xl font-bold mb-1" style="color: var(--primary);" data-count="100%">100%</div>
                <div class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-muted);">Financial Audit Accuracy</div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Pillars Section -->
<section class="py-20" style="background: var(--bg);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center mb-20">
            <div class="lg:col-span-6 reveal">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-4"
                      style="background: var(--secondary-light); color: var(--secondary);">
                    Core Purpose
                </span>
                <h2 class="font-display font-bold text-3xl mb-6" style="color: var(--text);">
                    Transforming Administrative Complexity into Effortless Speed
                </h2>
                <p class="text-sm sm:text-base leading-relaxed mb-4" style="color: var(--text-secondary);">
                    Managing an educational institution involves juggling admissions, timetable scheduling, faculty commission splits, daily physical attendance, delayed tuition fees, and stringent audit trails.
                </p>
                <p class="text-sm sm:text-base leading-relaxed" style="color: var(--text-secondary);">
                    EduManager unifies every stakeholder &mdash; from Super Administrators and Branch Coordinators to Lecturers, Cashiers, and Reception Staff &mdash; into a single synchronized digital ecosystem with clear role permissions and zero data silos.
                </p>
            </div>

            <div class="lg:col-span-6 reveal">
                <div class="p-8 rounded-3xl border shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <div class="w-14 h-14 rounded-2xl mb-6 flex items-center justify-center text-2xl"
                         style="background: var(--primary-light); color: var(--primary);">
                        &#127891;
                    </div>
                    <blockquote class="font-display italic text-lg sm:text-xl mb-4 leading-relaxed" style="color: var(--text);">
                        "Our goal was never just to build software, but to give institute directors total peace of mind that their finances, student security, and attendance are rock solid 24/7."
                    </blockquote>
                    <div class="flex items-center gap-3">
                        <div class="avatar avatar--md font-bold" style="background: var(--primary); color: #fff;">
                            K
                        </div>
                        <div>
                            <div class="font-semibold text-sm" style="color: var(--text);">Kavindu &amp; Engineering Team</div>
                            <div class="text-xs" style="color: var(--text-muted);">EduManager Core Architecture</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3 Core Pillars -->
        <div class="text-center max-w-2xl mx-auto mb-12 reveal">
            <h2 class="font-display font-bold text-3xl mb-3" style="color: var(--text);">Built on Four Pillars of Excellence</h2>
            <p class="text-sm" style="color: var(--text-secondary);">Every module in EduManager adheres to strict architectural standards.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal-stagger">
            <div class="card p-6 rounded-2xl border" style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 text-xl" style="background: var(--primary-light); color: var(--primary);">
                    &#128274;
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Role Integrity</h3>
                <p class="text-xs leading-relaxed" style="color: var(--text-secondary);">
                    Strict RBAC policies for Admins, Coordinators, Staff, and Teachers ensuring zero unauthorized data access or privilege elevation.
                </p>
            </div>

            <div class="card p-6 rounded-2xl border" style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 text-xl" style="background: var(--secondary-light); color: var(--secondary);">
                    &#9889;
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Instant QR Scanning</h3>
                <p class="text-xs leading-relaxed" style="color: var(--text-secondary);">
                    High-speed check-ins with automated 5-second acoustic security alerts for deactivated or non-compliant student accounts.
                </p>
            </div>

            <div class="card p-6 rounded-2xl border" style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 text-xl" style="background: var(--primary-light); color: var(--primary);">
                    &#128176;
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Revenue Splitting</h3>
                <p class="text-xs leading-relaxed" style="color: var(--text-secondary);">
                    Real-time computation of teacher commission percentages vs institute facility revenue, eliminating manual accounting errors.
                </p>
            </div>

            <div class="card p-6 rounded-2xl border" style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 text-xl" style="background: var(--secondary-light); color: var(--secondary);">
                    &#128200;
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Audit &amp; Reporting</h3>
                <p class="text-xs leading-relaxed" style="color: var(--text-secondary);">
                    Complete audit trail with 5 instant exportable CSV/Print reports covering attendance, users, classes, payments, and statuses.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-16 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(14,111,88,0.15) 0%, rgba(17,124,131,0.1) 100%);">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center reveal">
        <h2 class="font-display font-bold text-3xl mb-4" style="color: var(--text);">Ready to Modernize Your Institute?</h2>
        <p class="text-base mb-8 max-w-xl mx-auto" style="color: var(--text-secondary);">
            Join institutions that have simplified their daily operations with EduManager's unified LMS &amp; student platform.
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="/login" class="btn btn--primary btn--lg rounded-full font-semibold btn-glow">Access Portal &rarr;</a>
            <a href="/contact" class="btn btn--secondary btn--lg rounded-full font-semibold">Speak with Us</a>
        </div>
    </div>
</section>
