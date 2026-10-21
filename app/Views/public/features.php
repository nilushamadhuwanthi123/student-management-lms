<?php
/**
 * Features Page — Glassy Premium Design
 * Layout: layouts/public
 */

$breadcrumbs = [['Home', '/'], ['Features', '#']];
?>

<!-- Hero Header -->
<section class="py-20 relative overflow-hidden" style="background: linear-gradient(135deg, rgba(14,111,88,0.12) 0%, rgba(17,124,131,0.06) 100%);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="mb-4">
            <?php include __DIR__ . '/../components/breadcrumb.php'; ?>
        </div>
        <div class="max-w-3xl reveal">
            <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-4"
                  style="background: var(--primary-light); color: var(--primary);">
                Platform Capabilities
            </span>
            <h1 class="font-display font-bold mb-6" style="font-size: clamp(2.25rem, 4.5vw, 3.5rem); color: var(--text); letter-spacing: -0.02em;">
                Everything You Need to Run a<br>
                <span style="color: var(--primary);">World-Class Academy</span>
            </h1>
            <p class="text-base sm:text-lg" style="color: var(--text-secondary); line-height: 1.8;">
                From high-speed hardware QR attendance scanning to role-based access control, revenue share calculations, and exportable audit reports &mdash; discover how EduManager powers daily institutional success.
            </p>
        </div>
    </div>
</section>

<!-- Features Deep-Dive Grid -->
<section class="py-20" style="background: var(--bg);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-16">

        <!-- 1. User & Role Management -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center reveal">
            <div class="lg:col-span-6 space-y-4">
                <span class="badge badge--primary text-xs uppercase tracking-wider font-semibold">Security &amp; Permissions</span>
                <h2 class="font-display font-bold text-2xl sm:text-3xl" style="color: var(--text);">
                    Multi-Role User Directory &amp; Auto ID Generator
                </h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Manage Super Admins, Branch Coordinators, Administrative Staff, and Teachers with role-colored cards, quick filters, and one-click security controls.
                </p>
                <ul class="space-y-2 text-xs sm:text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Auto-generated branded IDs (<code>ADM20260001</code>, <code>COD...</code>, <code>STF...</code>, <code>TCH...</code>)
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Role-based KPI summary cards with active/inactive status filters
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Printable role-branded ID card generator with barcoded profile badges
                    </li>
                </ul>
            </div>
            <div class="lg:col-span-6">
                <div class="p-6 rounded-3xl border shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl border text-center" style="border-top: 4px solid #ef4444; background: var(--bg);">
                            <span class="text-xs uppercase font-bold text-red-500">Super Admin</span>
                            <div class="font-bold text-lg mt-1" style="color: var(--text);">Full Control</div>
                        </div>
                        <div class="p-4 rounded-xl border text-center" style="border-top: 4px solid #3b82f6; background: var(--bg);">
                            <span class="text-xs uppercase font-bold text-blue-500">Coordinator</span>
                            <div class="font-bold text-lg mt-1" style="color: var(--text);">Branch Hub</div>
                        </div>
                        <div class="p-4 rounded-xl border text-center" style="border-top: 4px solid #2563EB; background: var(--bg);">
                            <span class="text-xs uppercase font-bold text-emerald-500">Staff</span>
                            <div class="font-bold text-lg mt-1" style="color: var(--text);">Operations</div>
                        </div>
                        <div class="p-4 rounded-xl border text-center" style="border-top: 4px solid #2563EB; background: var(--bg);">
                            <span class="text-xs uppercase font-bold text-purple-500">Teacher</span>
                            <div class="font-bold text-lg mt-1" style="color: var(--text);">Class &amp; Attendance</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr style="border-color: var(--border);">

        <!-- 2. Institute & Class Management -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center reveal">
            <div class="lg:col-span-6 lg:order-2 space-y-4">
                <span class="badge badge--info text-xs uppercase tracking-wider font-semibold">Academics &amp; Facilities</span>
                <h2 class="font-display font-bold text-2xl sm:text-3xl" style="color: var(--text);">
                    Institute Network &amp; 13-Column Class Master
                </h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Full centralized control over campuses and comprehensive course management with automated revenue share calculations between teachers and institutes.
                </p>
                <ul class="space-y-2 text-xs sm:text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        13-column class grid tracking Subject, Medium, Grade, Teacher, Institute, Room, Schedule, Fee, and Split %
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Flexible schedule day checkboxes (Mon-Sun) and start/end time slots
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Institute directory with dedicated branch coordinator assignments
                    </li>
                </ul>
            </div>
            <div class="lg:col-span-6 lg:order-1">
                <div class="p-6 rounded-3xl border shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-xl" style="background: var(--bg);">
                            <div>
                                <div class="font-semibold text-xs" style="color: var(--text);">Combined Mathematics 2026 A/L</div>
                                <div class="text-[10px] text-muted">Grade 12 &bull; English Medium &bull; Main Hall A</div>
                            </div>
                            <span class="badge badge--success text-[10px]">70% / 30% Split</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl" style="background: var(--bg);">
                            <div>
                                <div class="font-semibold text-xs" style="color: var(--text);">Physics Theory &amp; Revision</div>
                                <div class="text-[10px] text-muted">Grade 13 &bull; Sinhala Medium &bull; Lab 2</div>
                            </div>
                            <span class="badge badge--success text-[10px]">75% / 25% Split</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr style="border-color: var(--border);">

        <!-- 3. QR Attendance & Loud Alarm Siren -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center reveal">
            <div class="lg:col-span-6 space-y-4">
                <span class="badge badge--warning text-xs uppercase tracking-wider font-semibold">Instant Check-In</span>
                <h2 class="font-display font-bold text-2xl sm:text-3xl" style="color: var(--text);">
                    Live QR Scan Terminal &amp; 5-Second Security Siren
                </h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    High-speed attendance terminal compatible with webcams, phone cameras, and physical barcode scanners. Incorporates automated safety compliance for deactivated accounts.
                </p>
                <ul class="space-y-2 text-xs sm:text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Web Audio API 5-second acoustic siren alarm triggering on unauthorized/deactivated scans
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Immediate red-flag attendance logging with flagged reason recording
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Manual check-in modal for quick staff override and daily summary counter
                    </li>
                </ul>
            </div>
            <div class="lg:col-span-6">
                <div class="p-6 rounded-3xl border shadow-sm text-center" style="background: #0f172a; border-color: #334155;">
                    <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center text-3xl" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                        &#128227;
                    </div>
                    <div class="text-white font-bold text-lg mb-1">Acoustic Deactivation Alarm</div>
                    <p class="text-xs text-slate-400 mb-4">Oscillator-based siren triggers automatically to alert gate staff when a deactivated student attempts admission.</p>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30">
                        Zero-Delay Web Audio Synthesis
                    </span>
                </div>
            </div>
        </div>

        <hr style="border-color: var(--border);">

        <!-- 4. Reports & Dashboards -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center reveal">
            <div class="lg:col-span-6 lg:order-2 space-y-4">
                <span class="badge badge--success text-xs uppercase tracking-wider font-semibold">Analytics &amp; Compliance</span>
                <h2 class="font-display font-bold text-2xl sm:text-3xl" style="color: var(--text);">
                    5 Exportable Management Reports &amp; 16 Dynamic Dashboard Widgets
                </h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Complete operational transparency with one-click CSV downloads, print-optimized layouts, and configurable dashboard visibility per user role.
                </p>
                <ul class="space-y-2 text-xs sm:text-sm" style="color: var(--text-secondary);">
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Attendance, Classes, Users, Payment Summary, and Student Status Reports
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        Revenue split breakdowns (Teacher net commission vs Institute facility share)
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-primary font-bold">&check;</span>
                        16 configurable dashboard widgets with role-based visibility toggles
                    </li>
                </ul>
            </div>
            <div class="lg:col-span-6 lg:order-1">
                <div class="p-6 rounded-3xl border shadow-sm" style="background: var(--surface); border-color: var(--border);">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-3 rounded-xl border" style="background: var(--bg);">
                            <div class="text-xs text-muted">Attendance</div>
                            <div class="font-bold text-sm text-primary mt-1">Export CSV</div>
                        </div>
                        <div class="p-3 rounded-xl border" style="background: var(--bg);">
                            <div class="text-xs text-muted">Revenue</div>
                            <div class="font-bold text-sm text-emerald-500 mt-1">Split Audit</div>
                        </div>
                        <div class="p-3 rounded-xl border" style="background: var(--bg);">
                            <div class="text-xs text-muted">Students</div>
                            <div class="font-bold text-sm text-blue-500 mt-1">Status Report</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Bottom CTA -->
<section class="py-16 text-center" style="background: linear-gradient(135deg, rgba(14,111,88,0.12) 0%, rgba(17,124,131,0.08) 100%);">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 reveal">
        <h2 class="font-display font-bold text-3xl mb-4" style="color: var(--text);">Start Exploring EduManager</h2>
        <p class="text-sm sm:text-base mb-8" style="color: var(--text-secondary);">Sign in to access your administrative dashboard, live terminal, and reports hub.</p>
        <a href="<?= BASE_URL ?>/login" class="btn btn--primary btn--lg rounded-full font-semibold btn-glow">Sign In Now &rarr;</a>
    </div>
</section>
