<?php
/**
 * Shared bits for the tuition pages: styles and the class card.
 * Plain CSS on the site's colour tokens, so the pages stay light on a
 * phone connection (no images, no extra scripts).
 */
if (!function_exists('site_days')) {
    function site_days(?string $json): string
    {
        $days = json_decode((string) $json, true) ?: [];
        return implode(' & ', array_map(static fn ($d): string => t((string) $d), $days));
    }
    function site_time(?string $t): string
    {
        return $t ? lang_date('g:i a', '2000-01-01 ' . $t) : '';
    }
}
?>
<style>
.ts-wrap { max-width: 1120px; margin: 0 auto; padding: 0 1rem; }
.ts-hero { background: linear-gradient(135deg, #0B3D91 0%, #1D4ED8 60%, #2563EB 100%); color: #fff; padding: 6.5rem 0 3.5rem; }
.ts-hero, .ts-hero p, .ts-hero a:not(.ts-btn--yellow) { color: #fff !important; }
.ts-hero h1 { font-size: clamp(1.9rem, 4.6vw, 3.1rem); line-height: 1.15; margin: 0 0 1rem; color: #fff; }
.ts-hero p { font-size: 1.08rem; opacity: .95; max-width: 60ch; margin: 0 0 1.6rem; }
.ts-hl { color: #FACC15; }
.ts-btn { display: inline-flex; align-items: center; gap: .45rem; padding: .78rem 1.35rem; border-radius: 999px; font-weight: 700; text-decoration: none; font-size: .95rem; border: 2px solid transparent; }
.ts-btn--yellow { background: #FACC15; color: #0B1B3F !important; }
.ts-btn--ghost { border-color: rgba(255,255,255,.55); color: #fff; }
.ts-btn--blue { background: #1D4ED8; color: #fff; }
.ts-btn--line { border-color: #1D4ED8; color: #1D4ED8; background: #fff; }
.ts-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1.4rem; }
.ts-chip { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); padding: .3rem .8rem; border-radius: 999px; font-size: .85rem; }
.ts-section { padding: 3.2rem 0; }
.ts-section--alt { background: var(--surface-sunken, #F3F6FC); }
.ts-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); margin: 0 0 .4rem; color: var(--text, #0B1B3F); }
.ts-lead { color: var(--text-secondary, #475569); margin: 0 0 1.6rem; max-width: 65ch; }
.ts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1rem; }
.ts-card { background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 16px; padding: 1.15rem 1.2rem; min-width: 0; }
.ts-card h3 { margin: 0 0 .35rem; font-size: 1.05rem; color: var(--text, #0B1B3F); }
.ts-muted { color: var(--text-muted, #64748B); font-size: .9rem; }
.ts-badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; background: #DBEAFE; color: #1D4ED8; margin-right: .3rem; }
.ts-badge--online { background: #FEF3C7; color: #92400E; }
.ts-badge--grade { background: #1D4ED8; color: #fff; }
.ts-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: #DBEAFE; color: #1D4ED8; font-size: 1.25rem; margin-bottom: .7rem; }
.ts-faq details { background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 12px; padding: .85rem 1rem; margin-bottom: .6rem; }
.ts-faq summary { font-weight: 700; cursor: pointer; }
.ts-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: .85rem; }
.ts-form label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .3rem; }
.ts-form input, .ts-form select, .ts-form textarea { width: 100%; padding: .65rem .75rem; border: 1px solid var(--border, #CBD5E1); border-radius: 10px; font: inherit; background: var(--surface, #fff); color: var(--text, #0B1B3F); box-sizing: border-box; }
.ts-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
/* ---- v3: teacher-centred design ---------------------------------- */
.public-layout, .public-layout input, .public-layout select, .public-layout textarea, .public-layout button { font-family: 'Inter', 'Noto Sans Sinhala', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
:lang(si) .ts-hero h1, :lang(si) .ts-section h2 { line-height: 1.35; letter-spacing: 0; }
.ts-hero { position: relative; overflow: hidden; padding: 6.8rem 0 4.2rem; background: radial-gradient(1200px 600px at 85% 20%, rgba(250,204,21,.18), transparent 60%), radial-gradient(900px 500px at 0% 100%, rgba(96,165,250,.35), transparent 60%), linear-gradient(135deg, #0A2A6B 0%, #1340B5 55%, #1D4ED8 100%); }
.ts-hero::after { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .09; background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 22px 22px; }
.ts-hero > .ts-wrap { position: relative; z-index: 1; }
.ts-hero-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 2.5rem; align-items: center; }
@media (max-width: 880px) { .ts-hero-grid { grid-template-columns: 1fr; } .ts-hero { padding-top: 5.6rem; } }
.ts-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .9rem; font-weight: 600; letter-spacing: .02em; color: #FDE68A !important; margin-bottom: .9rem; }
.ts-eyebrow::before { content: ''; width: 28px; height: 2px; background: #FACC15; border-radius: 2px; }
.ts-hero h1 { font-size: clamp(2rem, 5vw, 3.4rem); font-weight: 800; }
.ts-hero h1 .ts-name { display: block; color: #FACC15; }
.ts-hero h1 .ts-honour { display: block; font-size: .55em; font-weight: 600; color: #DBEAFE; margin-top: .35rem; }
.ts-photo { position: relative; justify-self: center; width: min(100%, 420px); }
.ts-photo__frame { border-radius: 28px; overflow: hidden; aspect-ratio: 4 / 5; box-shadow: 0 30px 60px -20px rgba(2,6,23,.55), 0 0 0 6px rgba(255,255,255,.12); background: #DBEAFE; }
.ts-photo__frame img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 18%; display: block; }
.ts-photo__tag { position: absolute; left: -14px; bottom: 26px; background: #fff; color: #0B1B3F !important; border-radius: 16px; padding: .7rem .95rem; box-shadow: 0 18px 40px -16px rgba(2,6,23,.45); display: flex; gap: .65rem; align-items: center; max-width: 85%; }
.ts-photo__tag strong { display: block; font-size: .98rem; color: #0B1B3F; }
.ts-photo__tag span { font-size: .8rem; color: #475569 !important; }
.ts-photo__dot { width: 40px; height: 40px; flex: 0 0 40px; border-radius: 12px; display: grid; place-items: center; background: #FACC15; color: #0B1B3F; }
.ts-photo__badge { position: absolute; right: -10px; top: 22px; background: #FACC15; color: #0B1B3F !important; font-weight: 800; border-radius: 999px; padding: .45rem .9rem; font-size: .85rem; box-shadow: 0 12px 30px -12px rgba(2,6,23,.5); }
@media (max-width: 480px) { .ts-photo__tag { left: 8px; } .ts-photo__badge { right: 8px; } }
.ts-stats { margin-top: -2.2rem; position: relative; z-index: 2; }
.ts-stats__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: .9rem; background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 20px; padding: 1.1rem; box-shadow: 0 20px 45px -28px rgba(15,23,42,.35); }
.ts-stat { display: flex; gap: .75rem; align-items: center; padding: .4rem .5rem; min-width: 0; }
.ts-stat__icon { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 14px; display: grid; place-items: center; background: #EFF6FF; color: #1D4ED8; }
.ts-stat__value { font-size: 1.35rem; font-weight: 800; color: var(--text, #0B1B3F); line-height: 1.1; }
.ts-stat__label { font-size: .82rem; color: var(--text-muted, #64748B); }
.ts-card { transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
.ts-card:hover { transform: translateY(-3px); box-shadow: 0 18px 38px -24px rgba(15,23,42,.35); border-color: #BFDBFE; }
.ts-icon { background: linear-gradient(135deg, #EFF6FF, #DBEAFE); }
.ts-icon svg { width: 22px; height: 22px; }
.ts-teacher { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 2.5rem; align-items: center; }
@media (max-width: 880px) { .ts-teacher { grid-template-columns: 1fr; } }
.ts-teacher__img { border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -28px rgba(15,23,42,.5); aspect-ratio: 3 / 2; }
.ts-teacher__img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ts-kicker { font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #1D4ED8; margin-bottom: .4rem; }
.ts-ticks { list-style: none; padding: 0; margin: 1rem 0 1.4rem; display: grid; gap: .55rem; }
.ts-ticks li { display: flex; gap: .6rem; align-items: flex-start; color: var(--text-secondary, #334155); }
.ts-ticks li::before { content: ''; flex: 0 0 20px; height: 20px; margin-top: .1rem; border-radius: 50%; background: #DCFCE7 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2316A34A' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E") center / 12px no-repeat; }
.ts-cta { border-radius: 24px; padding: 2rem; background: linear-gradient(135deg, #0A2A6B, #1D4ED8); color: #fff; display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: center; justify-content: space-between; }
.ts-cta h2, .ts-cta p { color: #fff !important; margin: 0; }
.ts-cta p { opacity: .9; margin-top: .35rem; }
.ts-chip svg, .ts-btn svg { display: inline-block; }
.ts-chip { display: inline-flex; align-items: center; gap: .3rem; }
.ts-anim .ts-reveal { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
.ts-anim .ts-reveal.is-in { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) { .ts-reveal { opacity: 1; transform: none; transition: none; } .ts-card:hover { transform: none; } }

/* ---- v4: more colour and motion ---------------------------------- */
.ts-float { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
.ts-float svg { position: absolute; color: rgba(255,255,255,.16); animation: tsFloat 9s ease-in-out infinite; }
.ts-float svg:nth-child(1) { left: 4%; top: 18%; width: 54px; animation-delay: 0s; }
.ts-float svg:nth-child(2) { left: 46%; top: 9%; width: 40px; animation-delay: -2s; color: rgba(250,204,21,.28); }
.ts-float svg:nth-child(3) { left: 40%; bottom: 16%; width: 60px; animation-delay: -4s; }
.ts-float svg:nth-child(4) { right: 3%; bottom: 10%; width: 46px; animation-delay: -6s; color: rgba(250,204,21,.22); }
.ts-float svg:nth-child(5) { left: 12%; bottom: 8%; width: 36px; animation-delay: -3s; }
@keyframes tsFloat { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-16px) rotate(8deg); } }
.ts-wave { position: absolute; left: 0; right: 0; bottom: -1px; height: 70px; z-index: 0; }
.ts-wave path { fill: var(--bg, #F5F7FB); }
.ts-hero { padding-bottom: 6rem; }
.ts-stats { margin-top: -4.2rem; }
.ts-photo__frame { animation: tsRise .9s ease both; }
@keyframes tsRise { from { opacity: 0; transform: translateY(24px) scale(.98); } to { opacity: 1; transform: none; } }
.ts-hero h1, .ts-hero p, .ts-eyebrow { animation: tsRise .8s ease both; }
.ts-hero p { animation-delay: .12s; }
.ts-btn { transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
.ts-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(2,6,23,.45); }
.ts-btn--yellow { box-shadow: 0 10px 24px -10px rgba(250,204,21,.7); }
.ts-section h2 { position: relative; }
.ts-title-bar { width: 56px; height: 5px; border-radius: 5px; background: linear-gradient(90deg, #FACC15, #1D4ED8); margin: .2rem 0 1rem; }
.ts-card .ts-icon { transition: transform .25s ease; }
.ts-card:hover .ts-icon { transform: scale(1.08) rotate(-4deg); }
.ts-icon--c1 { background: linear-gradient(135deg,#DBEAFE,#BFDBFE); color: #1D4ED8; }
.ts-icon--c2 { background: linear-gradient(135deg,#EDE9FE,#DDD6FE); color: #6D28D9; }
.ts-icon--c3 { background: linear-gradient(135deg,#FCE7F3,#FBCFE8); color: #BE185D; }
.ts-icon--c4 { background: linear-gradient(135deg,#FFEDD5,#FED7AA); color: #C2410C; }
.ts-icon--c5 { background: linear-gradient(135deg,#D1FAE5,#A7F3D0); color: #047857; }
.ts-icon--c6 { background: linear-gradient(135deg,#CFFAFE,#A5F3FC); color: #0E7490; }
.ts-icon--c7 { background: linear-gradient(135deg,#FEF9C3,#FDE68A); color: #A16207; }
/* steps */
.ts-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; counter-reset: step; position: relative; }
.ts-step { position: relative; background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 18px; padding: 1.3rem 1.2rem 1.2rem; min-width: 0; }
.ts-step::before { counter-increment: step; content: counter(step); position: absolute; top: -16px; left: 18px; width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #0B1B3F; background: #FACC15; box-shadow: 0 8px 18px -8px rgba(250,204,21,.9); }
.ts-step h3 { margin: .5rem 0 .3rem; font-size: 1.02rem; color: var(--text, #0B1B3F); }
/* grade tabs */
.ts-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin: 0 0 1.2rem; }
.ts-tab { border: 2px solid transparent; background: var(--surface, #fff); color: var(--text, #0B1B3F); border-radius: 999px; padding: .5rem 1rem; font-weight: 700; cursor: pointer; font: inherit; font-weight: 700; box-shadow: 0 6px 16px -12px rgba(15,23,42,.5); }
.ts-tab[aria-selected="true"] { color: #fff; background: var(--g, #1D4ED8); }
.ts-grade-panel[hidden] { display: none; }
.ts-g6 { --g: #2563EB; } .ts-g7 { --g: #7C3AED; } .ts-g8 { --g: #DB2777; } .ts-g9 { --g: #EA580C; } .ts-g10 { --g: #059669; } .ts-g11 { --g: #0891B2; }
.ts-class { border-top: 5px solid var(--g, #1D4ED8); }
.ts-class .ts-badge--grade { background: var(--g, #1D4ED8); }
/* branch cards */
.ts-branch { padding: 0; overflow: hidden; display: flex; flex-direction: column; }
.ts-branch__top { padding: 1.1rem 1.2rem; color: #fff; background: linear-gradient(135deg, #0A2A6B, #1D4ED8); position: relative; }
.ts-branch:nth-child(3n+2) .ts-branch__top { background: linear-gradient(135deg, #4C1D95, #7C3AED); }
.ts-branch:nth-child(3n+3) .ts-branch__top { background: linear-gradient(135deg, #065F46, #059669); }
.ts-branch__top h3 { color: #fff !important; margin: 0; }
.ts-branch__top span { font-size: .82rem; opacity: .9; }
.ts-branch__body { padding: 1rem 1.2rem 1.2rem; display: grid; gap: .5rem; }
.ts-branch__actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .3rem; }
/* faq */
.ts-faq details { transition: box-shadow .2s ease, border-color .2s ease; }
.ts-faq details[open] { border-color: #BFDBFE; box-shadow: 0 14px 30px -22px rgba(15,23,42,.4); }
.ts-faq summary { list-style: none; display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
.ts-faq summary::-webkit-details-marker { display: none; }
.ts-faq summary::after { content: '+'; flex: 0 0 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: #EFF6FF; color: #1D4ED8; font-weight: 800; transition: transform .2s ease; }
.ts-faq details[open] summary::after { content: '\2212'; background: #1D4ED8; color: #fff; }
/* inquiry */
.ts-join { display: grid; grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr); gap: 0; border-radius: 26px; overflow: hidden; box-shadow: 0 30px 60px -36px rgba(15,23,42,.45); background: var(--surface, #fff); }
@media (max-width: 880px) { .ts-join { grid-template-columns: 1fr; } }
.ts-join__side { background: linear-gradient(160deg, #0A2A6B, #1D4ED8); color: #fff; padding: 2rem; position: relative; overflow: hidden; }
.ts-join__side h2, .ts-join__side p, .ts-join__side li, .ts-join__side a { color: #fff !important; }
.ts-join__avatar { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; border: 4px solid #FACC15; margin-bottom: 1rem; }
.ts-join__avatar img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 15%; transform: scale(2.3); transform-origin: 52% 19%; }
.ts-join__side .ts-ticks li { color: #E0E7FF !important; }
.ts-join__form { padding: 2rem; }
.ts-join__form .ts-card { border: 0; padding: 0; box-shadow: none; }
.ts-join__form .ts-card:hover { transform: none; box-shadow: none; }
/* sticky mobile bar */
.ts-mbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 60; display: none; gap: .5rem; padding: .6rem .8rem calc(.6rem + env(safe-area-inset-bottom)); background: rgba(255,255,255,.96); backdrop-filter: blur(10px); border-top: 1px solid #E2E8F0; box-shadow: 0 -10px 24px -18px rgba(15,23,42,.5); }
.ts-mbar a { flex: 1; justify-content: center; }
@media (max-width: 700px) { .ts-mbar { display: flex; } body.public-layout { padding-bottom: 72px; } .ts-float { display: none; } }
.ts-mbar a { white-space: nowrap; font-size: .9rem; }
@media (prefers-reduced-motion: reduce) { .ts-float svg, .ts-photo__frame, .ts-hero h1, .ts-hero p, .ts-eyebrow { animation: none; } .ts-btn:hover { transform: none; } }

/* ---- hero slideshow ------------------------------------------------ */
.ts-photo__frame { position: relative; }
.ts-slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.06); transition: opacity 1.1s ease, transform 6s ease-out; }
.ts-slide.is-on { opacity: 1; transform: scale(1); }
.ts-slide:first-child { position: relative; }
.ts-slide__cap { position: absolute; left: 14px; top: 14px; z-index: 3; background: rgba(11,27,63,.72); color: #fff !important; font-size: .8rem; font-weight: 700; padding: .35rem .75rem; border-radius: 999px; backdrop-filter: blur(6px); transition: opacity .4s ease; }
.ts-dots { position: absolute; left: 0; right: 0; bottom: -30px; display: flex; justify-content: center; gap: .45rem; z-index: 3; }
.ts-dots button { width: 9px; height: 9px; border-radius: 999px; border: 0; padding: 0; cursor: pointer; background: rgba(255,255,255,.45); transition: width .3s ease, background .3s ease; }
.ts-dots button[aria-current="true"] { width: 26px; background: #FACC15; }
.ts-photo { margin-bottom: 1.6rem; }
@media (prefers-reduced-motion: reduce) { .ts-slide { transition: none; transform: none; } }

/* ---- hero background slideshow (photos behind the blue) -------------- */
.ts-hero-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
.ts-hero-bg::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(8,32,88,.93) 0%, rgba(14,52,150,.80) 40%, rgba(19,64,181,.52) 70%, rgba(29,78,216,.42) 100%); }
.ts-bg-slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.12); filter: saturate(1.1); transition: opacity 1.6s ease, transform 7s ease-out; }
.ts-bg-slide.is-on { opacity: 1; transform: scale(1.02); }
.ts-hero > .ts-float, .ts-hero > .ts-wave { z-index: 1; }
.ts-hero > .ts-wrap { z-index: 2; }
.ts-hero::after { z-index: 1; }
.ts-bg-dots { position: absolute; left: 0; right: 0; bottom: 82px; z-index: 2; display: flex; justify-content: center; gap: .45rem; }
.ts-bg-dots button { width: 9px; height: 9px; border-radius: 999px; border: 0; padding: 0; cursor: pointer; background: rgba(255,255,255,.5); transition: width .3s ease, background .3s ease; }
.ts-bg-dots button[aria-current="true"] { width: 26px; background: #FACC15; }
@media (max-width: 880px) { .ts-hero-bg::after { background: linear-gradient(180deg, rgba(8,32,88,.9) 0%, rgba(14,52,150,.84) 60%, rgba(19,64,181,.8) 100%); } .ts-bg-dots { bottom: 76px; } }
@media (prefers-reduced-motion: reduce) { .ts-bg-slide { transition: none; transform: none; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var els = document.querySelectorAll('.ts-reveal');
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
    document.documentElement.classList.add('ts-anim');
    // Never leave anything hidden: everything is shown after 2.5 s regardless.
    setTimeout(function () { els.forEach(function (e) { e.classList.add('is-in'); }); }, 2500);
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (e) { io.observe(e); });
});
</script>
