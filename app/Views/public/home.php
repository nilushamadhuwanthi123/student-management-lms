<?php
/**
 * Home Page — Cinematic Premium Design
 * Layout: layouts/public
 */
?>

<!-- ═══════ CINEMATIC HERO ═══════ -->
<section class="hero--cinematic">
    <!-- Background media (gradient fallback — replace with real video/image for production) -->
    <div class="hero__media">
        <!--
            To add a real hero video, uncomment the <video> tag below
            and provide the video source file.
            <video autoplay muted loop playsinline poster="/assets/img/hero-poster.jpg">
                <source src="<?= BASE_URL ?>/assets/video/hero.mp4" type="video/mp4">
            </video>
        -->
        <!-- Animated gradient fallback -->
        <div class="absolute inset-0" style="background: linear-gradient(135deg, #15307E 0%, #1D4ED8 35%, #2563EB 65%, #15307E 100%); background-size: 400% 400%; animation: gradientShift 12s ease infinite;"></div>
        <!-- Subtle pattern overlay -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.15) 1px, transparent 1px); background-size: 40px 40px;"></div>
    </div>

    <!-- Gradient overlay -->
    <div class="hero__gradient-overlay" style="background: linear-gradient(180deg, rgba(10,15,12,0.3) 0%, rgba(10,15,12,0.5) 50%, rgba(10,15,12,0.85) 100%);"></div>

    <!-- Hero content -->
    <div class="relative z-10 max-w-3xl mx-auto text-center px-4 sm:px-6 py-32 sm:py-40">
        <div class="reveal">
            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-medium tracking-wider uppercase mb-6
                bg-white/10 text-white/90 backdrop-blur-sm border border-white/10">
                Trusted by 50+ Institutes
            </span>
        </div>

        <h1 class="reveal font-display text-white font-bold leading-tight mb-6"
            style="font-size: clamp(2.25rem, 5vw, 3.75rem); letter-spacing: -0.02em;">
            Transform Your<br>
            <span class="relative inline-block">
                <span style="background: linear-gradient(135deg, #7AA8FF, #93B8FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Educational</span>
            </span>
            Management
        </h1>

        <p class="reveal text-base sm:text-lg leading-relaxed mb-10 max-w-xl mx-auto" style="color: rgba(255,255,255,0.7); font-weight: 300;">
            Streamline institute operations with our all-in-one platform. From student enrollment
            and payment tracking to automated workflows and real-time analytics.
        </p>

        <div class="reveal flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="<?= BASE_URL ?>/login" class="inline-flex items-center px-8 py-3.5 rounded-full text-sm font-semibold
                bg-[#1D4ED8] text-white hover:bg-[#1A3FAF] transition-all duration-300
                shadow-lg shadow-emerald-600/25 hover:shadow-emerald-600/40 btn-glow">
                Get Started
                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
            <a href="<?= BASE_URL ?>/features" class="inline-flex items-center px-8 py-3.5 rounded-full text-sm font-semibold
                text-white border border-white/20 hover:bg-white/10 backdrop-blur-sm transition-all duration-300">
                Learn More
            </a>
        </div>
    </div>

    <!-- Scroll indicator -->
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 reveal">
        <div class="w-6 h-10 rounded-full border-2 border-white/20 flex items-start justify-center pt-2">
            <div class="w-1 h-2.5 rounded-full bg-white/50" style="animation: scrollBounce 2s ease-in-out infinite;"></div>
        </div>
    </div>

    <style>
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            25% { background-position: 100% 0%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
        }
        @keyframes scrollBounce {
            0%, 100% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(6px); opacity: 0.4; }
        }
    </style>
</section>

<!-- ═══════ WAVE DIVIDER ═══════ -->
<div class="wave-divider" style="background: var(--bg);">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none" fill="none">
        <path d="M0,0 C360,60 1080,0 1440,40 L1440,60 L0,60 Z" fill="var(--bg)"/>
    </svg>
</div>

<!-- ═══════ STATS STRIP ═══════ -->
<section class="py-16 relative" style="background: var(--bg);">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 reveal-stagger">
            <div class="reveal text-center p-6 rounded-2xl border transition-all duration-300 hover:shadow-lg"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl mx-auto mb-3 flex items-center justify-center text-xl"
                     style="background: var(--primary-light); color: var(--primary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/></svg>
                </div>
                <span class="block font-display text-3xl font-bold mb-1" style="color: var(--primary);" data-count="500+">0</span>
                <span class="block text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Students Managed</span>
            </div>

            <div class="reveal text-center p-6 rounded-2xl border transition-all duration-300 hover:shadow-lg"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl mx-auto mb-3 flex items-center justify-center"
                     style="background: var(--secondary-light); color: var(--secondary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"/></svg>
                </div>
                <span class="block font-display text-3xl font-bold mb-1" style="color: var(--primary);" data-count="50+">0</span>
                <span class="block text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Active Classes</span>
            </div>

            <div class="reveal text-center p-6 rounded-2xl border transition-all duration-300 hover:shadow-lg"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl mx-auto mb-3 flex items-center justify-center"
                     style="background: var(--primary-light); color: var(--primary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                </div>
                <span class="block font-display text-3xl font-bold mb-1" style="color: var(--primary);" data-count="20+">0</span>
                <span class="block text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Dedicated Teachers</span>
            </div>

            <div class="reveal text-center p-6 rounded-2xl border transition-all duration-300 hover:shadow-lg"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="w-12 h-12 rounded-xl mx-auto mb-3 flex items-center justify-center"
                     style="background: var(--secondary-light); color: var(--secondary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                </div>
                <span class="block font-display text-3xl font-bold mb-1" style="color: var(--primary);" data-count="99.9%">0</span>
                <span class="block text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">System Uptime</span>
            </div>
        </div>
    </div>
</section>

<!-- ═══════ FEATURES SECTION ═══════ -->
<section class="py-20" style="background: var(--surface-sunken);">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-14 reveal">
            <h2 class="font-display font-bold mb-3" style="font-size: clamp(1.5rem, 3vw, 2.25rem); color: var(--text); letter-spacing: -0.01em;">
                Everything You Need to<br class="hidden sm:block">
                <span style="color: var(--primary);">Run Your Institute</span>
            </h2>
            <p class="text-base max-w-lg mx-auto" style="color: var(--text-secondary);">
                Powerful modules designed to handle every aspect of educational institution management.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 reveal-stagger">
            <!-- Student Management -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--primary-light); color: var(--primary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Student Management</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Complete student registration, enrollment tracking, and multi-class management
                    with automated ID generation and lifecycle workflows.
                </p>
            </div>

            <!-- Payment Tracking -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--secondary-light); color: var(--secondary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Payment Tracking</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Monthly fee management with scholarship support, overdue alerts, and
                    comprehensive payment history for every student.
                </p>
            </div>

            <!-- Attendance System -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group" onclick="window.location.href='<?= BASE_URL ?>/features'"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--primary-light); color: var(--primary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <path d="M9 16l2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">QR Attendance Terminal</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    High-speed QR camera scanning with automated 5-second acoustic security alerts for deactivated or non-compliant accounts.
                </p>
            </div>

            <!-- Role-Based Access -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group" onclick="window.location.href='<?= BASE_URL ?>/features'"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--secondary-light); color: var(--secondary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Role-Based Security</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Dedicated access controls for Super Admins, Coordinators, Staff, and Teachers with branded ID generation and audit logs.
                </p>
            </div>

            <!-- Automated Workflows -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group" onclick="window.location.href='<?= BASE_URL ?>/features'"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--primary-light); color: var(--primary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
                        <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">Automated Workflows</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Smart deactivation, scheduled deletion, and multi-level approval workflows to keep your data clean and compliant.
                </p>
            </div>

            <!-- Analytics & Reports -->
            <div class="card-premium reveal rounded-2xl p-6 border cursor-pointer group" onclick="window.location.href='<?= BASE_URL ?>/features'"
                 style="background: var(--surface); border-color: var(--border);">
                <div class="card-icon-wrap w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background: var(--secondary-light); color: var(--secondary);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-base mb-2" style="color: var(--text);">16 KPI Widgets &amp; 5 Reports</h3>
                <p class="text-sm leading-relaxed" style="color: var(--text-secondary);">
                    Super Admin dashboard with 16 configurable widgets, CSV data exports, and comprehensive revenue split reporting.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════ BOTTOM CTA ═══════ -->
<section class="py-20 relative overflow-hidden" style="background: var(--bg);">
    <!-- Decorative blobs -->
    <div class="absolute top-0 left-1/4 w-64 h-64 rounded-full opacity-20 blur-3xl" style="background: var(--primary);"></div>
    <div class="absolute bottom-0 right-1/4 w-48 h-48 rounded-full opacity-15 blur-3xl" style="background: var(--secondary);"></div>

    <div class="relative max-w-2xl mx-auto text-center px-4 sm:px-6 reveal">
        <h2 class="font-display font-bold mb-4" style="font-size: clamp(1.5rem, 3vw, 2.25rem); color: var(--text); letter-spacing: -0.01em;">
            Ready to Transform<br>
            <span style="color: var(--primary);">Your Institute?</span>
        </h2>
        <p class="text-base mb-8" style="color: var(--text-secondary); max-width: 440px; margin-left: auto; margin-right: auto;">
            Join hundreds of educational institutions already using EduManager to streamline their operations.
        </p>
        <a href="<?= BASE_URL ?>/login" class="inline-flex items-center px-8 py-3.5 rounded-full text-sm font-semibold
            bg-[var(--primary)] text-[var(--text-inverse)] hover:bg-[var(--primary-hover)] transition-all duration-300
            shadow-lg btn-glow">
            Login to Get Started
            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </a>
    </div>
</section>
