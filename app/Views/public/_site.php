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
.ts-hero { background: linear-gradient(135deg, var(--ts-nav-bg) 0%, var(--primary) 60%, var(--secondary) 100%); color: #fff; padding: 6.5rem 0 3.5rem; }
.ts-hero, .ts-hero p, .ts-hero a:not(.ts-btn--yellow) { color: #fff !important; }
.ts-hero h1 { font-size: clamp(1.9rem, 4.6vw, 3.1rem); line-height: 1.15; margin: 0 0 1rem; color: #fff; }
.ts-hero p { font-size: 1.08rem; opacity: .95; max-width: 60ch; margin: 0 0 1.6rem; }
.ts-hl { color: #FACC15; }
.ts-btn { display: inline-flex; align-items: center; gap: .45rem; padding: .78rem 1.35rem; border-radius: 999px; font-weight: 700; text-decoration: none; font-size: .95rem; border: 2px solid transparent; }
.ts-btn--yellow { background: #FACC15; color: var(--ts-nav-bg) !important; }
.ts-btn--ghost { border-color: rgba(255,255,255,.55); color: #fff; }
.ts-btn--blue { background: var(--primary); color: #fff; }
.ts-btn--line { border-color: var(--primary); color: var(--primary); background: #fff; }
.ts-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1.4rem; }
.ts-chip { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); padding: .3rem .8rem; border-radius: 999px; font-size: .85rem; }
.ts-section { padding: 3.2rem 0; }
.ts-section--alt { background: var(--surface-sunken, #F3F6FC); }
.ts-section h2 { font-size: clamp(1.4rem, 3vw, 2rem); margin: 0 0 .4rem; color: var(--text, var(--ts-nav-bg)); }
.ts-lead { color: var(--text-secondary, #475569); margin: 0 0 1.6rem; max-width: 65ch; }
.ts-rich ul, .ts-rich ol { margin: 0 0 1.6rem; padding-left: 1.3rem; }
.ts-rich a { text-decoration: underline; }
.ts-lead.ts-rich ul, .ts-lead.ts-rich ol { color: inherit; max-width: 65ch; }
.ts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 1rem; }
.ts-card { background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 16px; padding: 1.15rem 1.2rem; min-width: 0; }
.ts-card h3 { margin: 0 0 .35rem; font-size: 1.05rem; color: var(--text, var(--ts-nav-bg)); }
.ts-muted { color: var(--text-muted, #64748B); font-size: .9rem; }
.ts-badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; background: rgba(var(--primary-rgb), .18); color: var(--primary); margin-right: .3rem; }
.ts-badge--online { background: #FEF3C7; color: #92400E; }
.ts-badge--grade { background: var(--primary); color: #fff; }
.ts-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: rgba(var(--primary-rgb), .18); color: var(--primary); font-size: 1.25rem; margin-bottom: .7rem; }
.ts-faq details { background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 12px; padding: .85rem 1rem; margin-bottom: .6rem; }
.ts-faq summary { font-weight: 700; cursor: pointer; }
.ts-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); gap: .85rem; }
.ts-form label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .3rem; }
.ts-form input, .ts-form select, .ts-form textarea { width: 100%; padding: .65rem .75rem; border: 1px solid var(--border, #CBD5E1); border-radius: 10px; font: inherit; background: var(--surface, #fff); color: var(--text, var(--ts-nav-bg)); box-sizing: border-box; }
.ts-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
/* ---- v3: teacher-centred design ---------------------------------- */
.public-layout, .public-layout input, .public-layout select, .public-layout textarea, .public-layout button { font-family: 'Inter', 'Noto Sans Sinhala', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
:lang(si) .ts-hero h1, :lang(si) .ts-section h2 { line-height: 1.35; letter-spacing: 0; }
.ts-hero { position: relative; overflow: hidden; padding: 6.8rem 0 4.2rem; background: radial-gradient(1200px 600px at 85% 20%, rgba(250,204,21,.18), transparent 60%), radial-gradient(900px 500px at 0% 100%, rgba(var(--secondary-rgb), .45), transparent 60%), linear-gradient(135deg, var(--ts-nav-bg) 0%, var(--primary) 55%, var(--primary) 100%); }
.ts-hero::after { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .09; background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 22px 22px; }
.ts-hero > .ts-wrap { position: relative; z-index: 1; }
.ts-hero-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 2.5rem; align-items: center; }
@media (max-width: 880px) { .ts-hero-grid { grid-template-columns: 1fr; } .ts-hero { padding-top: 5.6rem; } }
.ts-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .9rem; font-weight: 600; letter-spacing: .02em; color: #FDE68A !important; margin-bottom: .9rem; }
.ts-eyebrow::before { content: ''; width: 28px; height: 2px; background: #FACC15; border-radius: 2px; }
.ts-hero h1 { font-size: clamp(2rem, 5vw, 3.4rem); font-weight: 800; }
.ts-hero h1 .ts-name { display: block; position: relative; font-family: 'Playfair Display', 'Noto Sans Sinhala', Georgia, serif; font-weight: 700; color: #FDE9B0 !important; letter-spacing: .01em; }
.ts-hero h1 .ts-name::after { content: ''; display: block; width: 64px; height: 3px; margin-top: .55rem; border-radius: 3px; background: #FACC15; }
.ts-honor { display: inline-block; vertical-align: middle; font-family: 'Inter', sans-serif; font-size: .38em; font-weight: 700; letter-spacing: .04em; color: var(--ts-nav-bg) !important; background: #FACC15; border-radius: 6px; padding: .3em .6em; margin-left: .4em; box-shadow: 0 6px 16px -8px rgba(0,0,0,.4); }
.ts-honor--dark { font-size: .72em; background: #FACC15; box-shadow: none; }
.ts-photo__ring { position: absolute; inset: -22px; width: calc(100% + 44px); height: calc(100% + 44px); color: rgba(255,255,255,.55); animation: tsSpin 22s linear infinite; pointer-events: none; }
.ts-hero h1 .ts-honour { display: block; font-size: .55em; font-weight: 600; color: rgba(var(--primary-rgb), .18); margin-top: .35rem; }
.ts-photo { position: relative; justify-self: center; width: min(100%, 420px); }
.ts-photo::before { content: ''; position: absolute; inset: -10px; border-radius: 32px; background: linear-gradient(135deg, #FACC15, var(--secondary)); opacity: .35; filter: blur(26px); z-index: -1; }

/* Seminar posters, shown at a size somebody can actually read. The
   poster is the content, so the frame stays out of its way: the image
   keeps its own shape rather than being cropped to a tile, and the
   title sits underneath as a caption for anyone who cannot see it. */
.ts-posters__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.4rem; margin-top: 1.8rem; align-items: start; }
.ts-posters__grid--one { grid-template-columns: minmax(0, 460px); justify-content: center; }
.ts-poster { display: block; text-decoration: none; color: inherit; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 18px 40px -22px rgba(2,6,23,.45); border: 1px solid rgba(15,23,42,.07); transition: transform .25s ease, box-shadow .25s ease; }
a.ts-poster:hover, a.ts-poster:focus-visible { transform: translateY(-4px); box-shadow: 0 26px 54px -24px rgba(2,6,23,.55); }
.ts-poster img { display: block; width: 100%; height: auto; background: rgba(var(--primary-rgb), .08); }
.ts-poster__cap { display: block; padding: .8rem 1rem; font-size: .92rem; font-weight: 600; line-height: 1.35; color: var(--ts-nav-bg); }
@media (prefers-reduced-motion: reduce) { a.ts-poster:hover, a.ts-poster:focus-visible { transform: none; } }

/* A cursor that suits a Science class: a faint electron shell that
   follows the pointer, with a nucleus at the tip. Pointer-events are
   off throughout so it can never swallow a click, and the whole thing
   is only built for a real mouse (see the script), never on a phone. */
.ts-cur, .ts-cur-dot { position: fixed; top: 0; left: 0; z-index: 9998; pointer-events: none;
  border-radius: 50%; will-change: transform; opacity: 0; transition: opacity .25s ease; }
.ts-cur { width: 34px; height: 34px; margin: -17px 0 0 -17px;
  border: 1.5px solid rgba(250, 204, 21, .75); }
.ts-cur::before, .ts-cur::after { content: ''; position: absolute; inset: -1.5px; border-radius: 50%;
  border: 1.5px solid rgba(96, 165, 250, .45); }
.ts-cur::before { transform: rotate(60deg) scaleY(.38); }
.ts-cur::after { transform: rotate(-60deg) scaleY(.38); }
.ts-cur-dot { width: 6px; height: 6px; margin: -3px 0 0 -3px; background: #FACC15;
  box-shadow: 0 0 10px 2px rgba(250, 204, 21, .55); z-index: 9999; }
.ts-cur.is-on, .ts-cur-dot.is-on { opacity: 1; }
/* Over something clickable the shell swells and cools to blue. */
.ts-cur.is-hot { width: 52px; height: 52px; margin: -26px 0 0 -26px;
  border-color: rgba(96, 165, 250, .9); transition: opacity .25s ease, width .18s ease, height .18s ease, margin .18s ease, border-color .18s ease; }
@media (prefers-reduced-motion: reduce) { .ts-cur, .ts-cur-dot { display: none; } }
@keyframes tsSpin { to { transform: rotate(360deg); } }
.ts-photo__frame { border-radius: 28px; overflow: hidden; aspect-ratio: 4 / 5; box-shadow: 0 30px 60px -20px rgba(2,6,23,.55), 0 0 0 6px rgba(255,255,255,.12); background: rgba(var(--primary-rgb), .18); }
.ts-photo__frame img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 18%; display: block; }
.ts-photo__tag { position: absolute; left: -14px; bottom: 26px; background: #fff; color: var(--ts-nav-bg) !important; border-radius: 16px; padding: .7rem .95rem; box-shadow: 0 18px 40px -16px rgba(2,6,23,.45); display: flex; gap: .65rem; align-items: center; max-width: 85%; }
.ts-photo__tag strong { display: block; font-size: .98rem; color: var(--ts-nav-bg); }
.ts-photo__tag span { font-size: .8rem; color: #475569 !important; }
.ts-photo__dot { width: 40px; height: 40px; flex: 0 0 40px; border-radius: 12px; display: grid; place-items: center; background: #FACC15; color: var(--ts-nav-bg); }
.ts-photo__badge { position: absolute; right: -10px; top: 22px; background: #FACC15; color: var(--ts-nav-bg) !important; font-weight: 800; border-radius: 999px; padding: .45rem .9rem; font-size: .85rem; box-shadow: 0 12px 30px -12px rgba(2,6,23,.5); }
@media (max-width: 480px) { .ts-photo__tag { left: 8px; } .ts-photo__badge { right: 8px; } }
.ts-stats { margin-top: -2.2rem; position: relative; z-index: 2; }
.ts-stats__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: .9rem; background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 20px; padding: 1.1rem; box-shadow: 0 20px 45px -28px rgba(15,23,42,.35); }
.ts-stat { display: flex; gap: .75rem; align-items: center; padding: .4rem .5rem; min-width: 0; }
.ts-stat__icon { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 14px; display: grid; place-items: center; background: rgba(var(--primary-rgb), .08); color: var(--primary); }
.ts-stat__value { font-size: 1.35rem; font-weight: 800; color: var(--text, var(--ts-nav-bg)); line-height: 1.1; }
.ts-stat__label { font-size: .82rem; color: var(--text-muted, #64748B); }
.ts-card { transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
.ts-card:hover { transform: translateY(-3px); box-shadow: 0 18px 38px -24px rgba(15,23,42,.35); border-color: rgba(var(--primary-rgb), .28); }
.ts-icon { background: linear-gradient(135deg, rgba(var(--primary-rgb), .08), rgba(var(--primary-rgb), .18)); }
.ts-icon svg { width: 22px; height: 22px; }
.ts-teacher { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 2.5rem; align-items: center; }
@media (max-width: 880px) { .ts-teacher { grid-template-columns: 1fr; } }
/* ---- Classes landing page: branch photo + "My Classes" row -------- */
/* Desktop: photo (left, large) and info (right) side by side. Tablet/phone
   (<=880px): stacks to one column so the photo never gets squeezed. */
.ts-branch-row { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(190px, 1fr); gap: 2.2rem; align-items: center; }
@media (max-width: 880px) { .ts-branch-row { grid-template-columns: 1fr; gap: 1.1rem; } }
.ts-branch-photo { position: relative; padding: 0; overflow: hidden; }
/* Kept short on purpose: a tall banner can fill the whole first screen
   and hide the branches listed below it. A wider, shorter strip lets
   more than one branch show at once without scrolling. */
/* width:100% is pinned on purpose: with only aspect-ratio + max-height
   set, a browser derives width from the ratio too once max-height
   clamps the height, which left a blank gap down one side of the card
   instead of the photo/placeholder filling it. Pinning width keeps the
   box always full-width and lets height be the only dimension the
   ratio and the clamp are allowed to adjust. */
.ts-branch-media { width: 100%; aspect-ratio: 16 / 7; max-height: 230px; overflow: hidden; background: rgba(var(--primary-rgb), .08); }
.ts-branch-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
@media (max-width: 880px) { .ts-branch-media { aspect-ratio: 16 / 8; max-height: 200px; } }
@media (max-width: 480px) { .ts-branch-media { aspect-ratio: 16 / 9; max-height: 180px; } }
/* CMS-controlled override (Institutes > Edit > "Photo/video banner
   size"): small/large tweak the same short-banner idea up or down a
   little, without ever going back to the old full-screen-tall photo. */
.ts-branch-media--small { max-height: 160px; }
.ts-branch-media--large { max-height: 320px; }
@media (max-width: 880px) { .ts-branch-media--small { max-height: 150px; } .ts-branch-media--large { max-height: 260px; } }
@media (max-width: 480px) { .ts-branch-media--small { max-height: 130px; } .ts-branch-media--large { max-height: 220px; } }
.ts-branch-num { position: absolute; top: .8rem; left: .8rem; z-index: 2; width: 1.9rem; height: 1.9rem; border-radius: 999px; background: rgba(255,255,255,.92); color: var(--ts-nav-bg); font-weight: 800; font-size: .85rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(2,6,23,.18); }
.ts-branch-video { position: absolute; top: .8rem; right: .8rem; z-index: 2; display: flex; align-items: center; gap: .3rem; background: rgba(10,20,50,.6); backdrop-filter: blur(6px); color: #fff !important; font-size: .72rem; font-weight: 600; padding: .3rem .6rem; border-radius: 999px; text-decoration: none; }
@media (max-width: 880px) { .ts-branch-row .ts-branch-photo { min-width: 0; } }
.ts-teacher__img { border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -28px rgba(15,23,42,.5); aspect-ratio: 3 / 2; position: relative; }
.ts-teacher__img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ts-teacher__badge { position: absolute; left: 18px; bottom: 18px; background: #fff; color: var(--ts-nav-bg) !important; border-radius: 14px; padding: .55rem .9rem; box-shadow: 0 16px 34px -16px rgba(2,6,23,.5); display: flex; align-items: center; gap: .55rem; font-weight: 800; font-size: .85rem; }
.ts-teacher__badge svg { color: var(--primary); flex: 0 0 auto; }
.ts-kicker { font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--primary); margin-bottom: .4rem; }
.ts-ticks { list-style: none; padding: 0; margin: 1rem 0 1.4rem; display: grid; gap: .55rem; }
.ts-ticks li { display: flex; gap: .6rem; align-items: flex-start; color: var(--text-secondary, #334155); }
.ts-ticks li::before { content: ''; flex: 0 0 20px; height: 20px; margin-top: .1rem; border-radius: 50%; background: #DCFCE7 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2316A34A' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E") center / 12px no-repeat; }
.ts-cta { border-radius: 24px; padding: 2rem; background: linear-gradient(135deg, var(--ts-nav-bg), var(--primary)); color: #fff; display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: center; justify-content: space-between; }
.ts-cta h2, .ts-cta p { color: #fff !important; margin: 0; }
.ts-cta p { opacity: .9; margin-top: .35rem; }
.ts-chip svg, .ts-btn svg { display: inline-block; }
.ts-chip { display: inline-flex; align-items: center; gap: .3rem; }
.ts-anim .ts-reveal { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s ease; }
.ts-anim .ts-reveal.is-in { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) { .ts-reveal { opacity: 1; transform: none; transition: none; } .ts-card:hover { transform: none; } }

/* ---- v4: more colour and motion ---------------------------------- */
.ts-float { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
/* Size and fade come from the CMS as --ts-float-scale / --ts-float-fade,
   set on the wrapper; both default to 1 so an unconfigured site looks
   exactly as it always did. The drift speed is --ts-float-speed. */
.ts-float svg { position: absolute;
  color: rgba(255,255,255,calc(.16 * var(--ts-float-fade, 1)));
  animation: tsFloat var(--ts-float-speed, 9s) ease-in-out infinite; }

/* Twelve places, because the icons are now chosen in the CMS and the
   old five rules left a sixth pick stacked in the top-left corner.
   Every other one is gold, so a long list still alternates. */
.ts-float svg:nth-child(1)  { left: 4%;   top: 18%;    width: calc(54px * var(--ts-float-scale, 1)); animation-delay: 0s; }
.ts-float svg:nth-child(2)  { left: 46%;  top: 9%;     width: calc(40px * var(--ts-float-scale, 1)); animation-delay: -2s; color: rgba(250,204,21,calc(.28 * var(--ts-float-fade, 1))); }
.ts-float svg:nth-child(3)  { left: 40%;  bottom: 16%; width: calc(60px * var(--ts-float-scale, 1)); animation-delay: -4s; }
.ts-float svg:nth-child(4)  { right: 3%;  bottom: 10%; width: calc(46px * var(--ts-float-scale, 1)); animation-delay: -6s; color: rgba(250,204,21,calc(.22 * var(--ts-float-fade, 1))); }
.ts-float svg:nth-child(5)  { left: 12%;  bottom: 8%;  width: calc(36px * var(--ts-float-scale, 1)); animation-delay: -3s; }
.ts-float svg:nth-child(6)  { right: 14%; top: 14%;    width: calc(48px * var(--ts-float-scale, 1)); animation-delay: -1s; color: rgba(250,204,21,calc(.20 * var(--ts-float-fade, 1))); }
.ts-float svg:nth-child(7)  { left: 26%;  top: 36%;    width: calc(34px * var(--ts-float-scale, 1)); animation-delay: -5s; }
.ts-float svg:nth-child(8)  { right: 26%; bottom: 28%; width: calc(42px * var(--ts-float-scale, 1)); animation-delay: -7s; color: rgba(250,204,21,calc(.18 * var(--ts-float-fade, 1))); }
.ts-float svg:nth-child(9)  { left: 62%;  top: 26%;    width: calc(30px * var(--ts-float-scale, 1)); animation-delay: -2.5s; }
.ts-float svg:nth-child(10) { left: 2%;   top: 52%;    width: calc(38px * var(--ts-float-scale, 1)); animation-delay: -4.5s; color: rgba(250,204,21,calc(.16 * var(--ts-float-fade, 1))); }
.ts-float svg:nth-child(11) { right: 8%;  top: 44%;    width: calc(32px * var(--ts-float-scale, 1)); animation-delay: -6.5s; }
.ts-float svg:nth-child(12) { left: 34%;  top: 4%;     width: calc(44px * var(--ts-float-scale, 1)); animation-delay: -8s; color: rgba(250,204,21,calc(.14 * var(--ts-float-fade, 1))); }
@keyframes tsFloat { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-16px) rotate(8deg); } }
.ts-wave { position: absolute; left: 0; right: 0; bottom: -1px; width: 100%; height: 70px; z-index: 0; }
.ts-wave path { fill: var(--bg, #F5F7FB); }
.ts-hero { padding-bottom: 6rem; }
.ts-stats { margin-top: -4.2rem; }

.ts-btn { transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
.ts-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(2,6,23,.45); }
.ts-btn--yellow { box-shadow: 0 10px 24px -10px rgba(250,204,21,.7); }
.ts-section h2 { position: relative; }
.ts-title-bar { width: 56px; height: 5px; border-radius: 5px; background: linear-gradient(90deg, #FACC15, var(--primary)); margin: .2rem 0 1rem; }
.ts-card .ts-icon { transition: transform .25s ease; }
.ts-card:hover .ts-icon { transform: scale(1.08) rotate(-4deg); }
.ts-icon--c1 { background: linear-gradient(135deg,rgba(var(--primary-rgb), .18),rgba(var(--primary-rgb), .28)); color: var(--primary); }
.ts-icon--c2 { background: linear-gradient(135deg,#EDE9FE,#DDD6FE); color: #6D28D9; }
.ts-icon--c3 { background: linear-gradient(135deg,#FCE7F3,#FBCFE8); color: #BE185D; }
.ts-icon--c4 { background: linear-gradient(135deg,#FFEDD5,#FED7AA); color: #C2410C; }
.ts-icon--c5 { background: linear-gradient(135deg,#D1FAE5,#A7F3D0); color: #047857; }
.ts-icon--c6 { background: linear-gradient(135deg,#CFFAFE,#A5F3FC); color: #0E7490; }
.ts-icon--c7 { background: linear-gradient(135deg,#FEF9C3,#FDE68A); color: #A16207; }
/* steps */
.ts-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; counter-reset: step; position: relative; }
.ts-step { position: relative; background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 18px; padding: 1.3rem 1.2rem 1.2rem; min-width: 0; }
.ts-step::before { counter-increment: step; content: counter(step); position: absolute; top: -16px; left: 18px; width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: var(--ts-nav-bg); background: #FACC15; box-shadow: 0 8px 18px -8px rgba(250,204,21,.9); }
.ts-step h3 { margin: .5rem 0 .3rem; font-size: 1.02rem; color: var(--text, var(--ts-nav-bg)); }
/* grade tabs */
.ts-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin: 0 0 1.2rem; }
.ts-tab { border: 2px solid transparent; background: var(--surface, #fff); color: var(--text, var(--ts-nav-bg)); border-radius: 999px; padding: .5rem 1rem; font-weight: 700; cursor: pointer; font: inherit; font-weight: 700; box-shadow: 0 6px 16px -12px rgba(15,23,42,.5); }
.ts-tab[aria-selected="true"] { color: #fff; background: var(--g, var(--primary)); }
.ts-grade-panel[hidden] { display: none; }
.ts-g6 { --g: var(--secondary); } .ts-g7 { --g: #7C3AED; } .ts-g8 { --g: #DB2777; } .ts-g9 { --g: #EA580C; } .ts-g10 { --g: #059669; } .ts-g11 { --g: #0891B2; }
.ts-class { border-top: 5px solid var(--g, var(--primary)); }
.ts-class .ts-badge--grade { background: var(--g, var(--primary)); }
/* branch cards */
.ts-branch { padding: 0; overflow: hidden; display: flex; flex-direction: column; }
.ts-branch__top { padding: 1.1rem 1.2rem; color: #fff; background: linear-gradient(135deg, var(--ts-nav-bg), var(--primary)); position: relative; }
.ts-branch:nth-child(3n+2) .ts-branch__top { background: linear-gradient(135deg, #4C1D95, #7C3AED); }
.ts-branch:nth-child(3n+3) .ts-branch__top { background: linear-gradient(135deg, #065F46, #059669); }
.ts-branch__top h3 { color: #fff !important; margin: 0; }
.ts-branch__top span { font-size: .82rem; opacity: .9; }
.ts-branch__body { padding: 1rem 1.2rem 1.2rem; display: grid; gap: .5rem; }
.ts-branch__actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .3rem; }
/* faq */
.ts-faq details { transition: box-shadow .2s ease, border-color .2s ease; }
.ts-faq details[open] { border-color: rgba(var(--primary-rgb), .28); box-shadow: 0 14px 30px -22px rgba(15,23,42,.4); }
.ts-faq summary { list-style: none; display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
.ts-faq summary::-webkit-details-marker { display: none; }
.ts-faq summary::after { content: '+'; flex: 0 0 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: rgba(var(--primary-rgb), .08); color: var(--primary); font-weight: 800; transition: transform .2s ease; }
.ts-faq details[open] summary::after { content: '\2212'; background: var(--primary); color: #fff; }
/* inquiry */
.ts-join { display: grid; grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr); gap: 0; border-radius: 26px; overflow: hidden; box-shadow: 0 30px 60px -36px rgba(15,23,42,.45); background: var(--surface, #fff); }
@media (max-width: 880px) { .ts-join { grid-template-columns: 1fr; } }
.ts-join__side { background: linear-gradient(160deg, var(--ts-nav-bg), var(--primary)); color: #fff; padding: 2rem; position: relative; overflow: hidden; }
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
/* The 72px bottom reserve is only correct on the page that actually has
   a .ts-mbar bar (the home page) -- every other public page that merely
   includes this shared stylesheet was getting the same blank 72px gap
   with nothing in it, which pushed the real footer content up into the
   chat bubble's own reserved space and caused the two to overlap. */
@media (max-width: 700px) {
  .ts-mbar { display: flex; }
  body.public-layout:has(.ts-mbar) { padding-bottom: 72px; }
  .ts-float { display: none; }
}
.ts-mbar a { white-space: nowrap; font-size: .9rem; }
@media (prefers-reduced-motion: reduce) { .ts-float svg, .ts-photo__ring { animation: none; } .ts-btn:hover { transform: none; } }

/* ---- seminars & events advertised on the home page ----------------- */
.ts-events { background: radial-gradient(900px 380px at 100% 0%, rgba(250,204,21,.12), transparent 60%), radial-gradient(800px 380px at 0% 100%, rgba(29,78,216,.10), transparent 60%); }
.ts-events__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); gap: 1.25rem; margin-top: 1.4rem; }
.ts-event { background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; min-width: 0; box-shadow: 0 22px 44px -32px rgba(15,23,42,.45); transition: transform .2s ease, box-shadow .2s ease; }
.ts-event:hover { transform: translateY(-4px); box-shadow: 0 28px 54px -30px rgba(15,23,42,.5); }
.ts-event__media { position: relative; aspect-ratio: 16 / 9; background: var(--ts-nav-bg); overflow: hidden; }
.ts-event__media img { position: relative; z-index: 1; width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .6s ease; }
.ts-event:hover .ts-event__media img { transform: scale(1.04); }
.ts-event__media--plain { background: radial-gradient(500px 220px at 85% 10%, rgba(250,204,21,.35), transparent 60%), linear-gradient(135deg, var(--ts-nav-bg), var(--primary) 60%, #7C3AED); }
.ts-event__icon { position: absolute; right: 8%; bottom: 10%; width: 32%; height: auto; color: rgba(255,255,255,.22); z-index: 0; }
.ts-event__media:not(.ts-event__media--plain) .ts-event__icon { display: none; }
.ts-event__date { position: absolute; z-index: 2; left: 14px; top: 14px; background: #fff; color: var(--ts-nav-bg) !important; border-radius: 14px; min-width: 58px; padding: .35rem .6rem .4rem; text-align: center; box-shadow: 0 12px 26px -12px rgba(2,6,23,.6); line-height: 1.05; }
.ts-event__day { display: block; font-size: 1.55rem; font-weight: 800; color: var(--primary); }
.ts-event__mon { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #475569; }
.ts-event__tag { position: absolute; z-index: 2; right: 14px; top: 14px; background: #FACC15; color: var(--ts-nav-bg); font-size: .78rem; font-weight: 800; padding: .3rem .7rem; border-radius: 999px; box-shadow: 0 10px 22px -12px rgba(2,6,23,.6); }
.ts-event__body { padding: 1.1rem 1.2rem 1.25rem; display: flex; flex-direction: column; gap: .55rem; flex: 1; }
.ts-event__body h3 { margin: 0; font-size: 1.15rem; line-height: 1.3; color: var(--text, var(--ts-nav-bg)); }
.ts-event__meta { list-style: none; margin: 0; padding: 0; display: grid; gap: .3rem; font-size: .9rem; color: var(--text-secondary, #475569); }
.ts-event__desc { margin: 0; color: var(--text-muted, #64748B); font-size: .92rem; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.ts-event__actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: auto; padding-top: .4rem; }
.ts-event__actions .ts-btn { padding: .55rem 1.05rem; font-size: .88rem; }
/* one event: a wide "featured" banner, picture beside the text */
@media (min-width: 760px) {
  .ts-events__grid--one { grid-template-columns: 1fr; }
  .ts-events__grid--one .ts-event { flex-direction: row; }
  .ts-events__grid--one .ts-event__media { flex: 0 0 52%; aspect-ratio: auto; min-height: 300px; }
  .ts-events__grid--one .ts-event__body { padding: 1.6rem 1.8rem; justify-content: center; }
  .ts-events__grid--one .ts-event__body h3 { font-size: 1.55rem; }
  .ts-events__grid--one .ts-event__desc { -webkit-line-clamp: 5; }
  .ts-events__grid--one .ts-event__actions { margin-top: .6rem; }
}
/* the rotating "Upcoming" announcement at the top of the hero */
/* Shrink-wrapped but still on a line of its own: as an inline-flex the
   eyebrow underneath used to ride up beside it on a tablet. */
.ts-ad { position: relative; display: flex; width: fit-content; align-items: center; gap: .6rem; max-width: 100%; margin: 0 0 1.1rem; padding: .4rem .5rem .4rem .45rem; border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(250,204,21,.55); color: #fff !important; text-decoration: none; backdrop-filter: blur(6px); box-shadow: 0 10px 30px -14px rgba(250,204,21,.6); }
.ts-ad:hover { background: rgba(255,255,255,.18); }
.ts-ad__label { flex: 0 0 auto; display: inline-flex; align-items: center; gap: .35rem; background: #FACC15; color: var(--ts-nav-bg); font-weight: 800; font-size: .76rem; padding: .28rem .65rem; border-radius: 999px; text-transform: uppercase; letter-spacing: .04em; }
.ts-ad__label::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #DC2626; box-shadow: 0 0 0 0 rgba(220,38,38,.7); animation: tsPulse 1.6s infinite; }
@keyframes tsPulse { 0% { box-shadow: 0 0 0 0 rgba(220,38,38,.7); } 70% { box-shadow: 0 0 0 7px rgba(220,38,38,0); } 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0); } }
.ts-ad__thumb { flex: 0 0 34px; width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.7); }
.ts-ad__rot { position: relative; min-width: 0; display: grid; }
.ts-ad__item { grid-area: 1 / 1; display: flex; align-items: center; gap: .55rem; min-width: 0; opacity: 0; transform: translateY(6px); transition: opacity .5s ease, transform .5s ease; pointer-events: none; text-decoration: none; color: #fff; }
.ts-ad__item.is-on { opacity: 1; transform: none; pointer-events: auto; }
.ts-ad__text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .9rem; font-weight: 600; }
.ts-ad__text small { font-weight: 500; opacity: .85; margin-left: .35rem; }
.ts-ad__go { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.18); font-weight: 800; }
@media (max-width: 480px) { .ts-ad__text small { display: none; } }
@media (prefers-reduced-motion: reduce) { .ts-ad, .ts-ad__label::before { animation: none; } .ts-ad__item { transition: none; } }
@media (prefers-reduced-motion: reduce) { .ts-event, .ts-event__media img { transition: none; } .ts-event:hover, .ts-event:hover .ts-event__media img { transform: none; } }

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
.ts-hero-bg::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(var(--ts-nav-rgb), .93) 0%, rgba(var(--primary-rgb), .80) 40%, rgba(var(--primary-rgb), .52) 70%, rgba(var(--secondary-rgb), .42) 100%); }
.ts-bg-slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.12); filter: saturate(1.1); transition: opacity 1.6s ease, transform 7s ease-out; }
.ts-bg-slide.is-on { opacity: 1; transform: scale(1.02); }
.ts-hero > .ts-float, .ts-hero > .ts-wave { z-index: 1; }
.ts-hero > .ts-wrap { z-index: 2; }
.ts-hero::after { z-index: 1; }
.ts-bg-dots { position: absolute; left: 0; right: 0; bottom: 82px; z-index: 2; display: flex; justify-content: center; gap: .45rem; }
.ts-bg-dots button { width: 9px; height: 9px; border-radius: 999px; border: 0; padding: 0; cursor: pointer; background: rgba(255,255,255,.5); transition: width .3s ease, background .3s ease; }
.ts-bg-dots button[aria-current="true"] { width: 26px; background: #FACC15; }
@media (max-width: 880px) { .ts-hero-bg::after { background: linear-gradient(180deg, rgba(var(--ts-nav-rgb), .90) 0%, rgba(var(--primary-rgb), .84) 60%, rgba(var(--primary-rgb), .80) 100%); } .ts-bg-dots { bottom: 76px; } }
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

/* The electron-shell cursor. Built only for a device that actually has a
   mouse -- on a phone there is no pointer to follow and it would just be
   two stray dots -- and skipped for anyone who asks for less motion. The
   nucleus tracks the pointer exactly; the shell eases after it, which is
   what makes it feel like it is orbiting rather than glued on. */
document.addEventListener('DOMContentLoaded', function () {
    var fine = window.matchMedia('(pointer: fine)').matches;
    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!fine || still) { return; }

    var shell = document.createElement('div');
    shell.className = 'ts-cur';
    var core = document.createElement('div');
    core.className = 'ts-cur-dot';
    shell.setAttribute('aria-hidden', 'true');
    core.setAttribute('aria-hidden', 'true');
    document.body.appendChild(shell);
    document.body.appendChild(core);

    var tx = window.innerWidth / 2, ty = window.innerHeight / 2, sx = tx, sy = ty, on = false, frame = null;

    function draw() {
        // Ease the shell toward the pointer; the core is already there.
        sx += (tx - sx) * 0.18;
        sy += (ty - sy) * 0.18;
        shell.style.transform = 'translate(' + sx + 'px,' + sy + 'px)';
        core.style.transform = 'translate(' + tx + 'px,' + ty + 'px)';
        frame = requestAnimationFrame(draw);
    }

    document.addEventListener('mousemove', function (ev) {
        tx = ev.clientX;
        ty = ev.clientY;
        if (!on) {
            on = true;
            shell.classList.add('is-on');
            core.classList.add('is-on');
            if (frame === null) { frame = requestAnimationFrame(draw); }
        }
        var over = ev.target && ev.target.closest
            ? ev.target.closest('a, button, summary, input, select, textarea, [role="tab"], .ts-card')
            : null;
        shell.classList.toggle('is-hot', over !== null);
    }, { passive: true });

    // Stop drawing when the pointer or the tab leaves, so an idle
    // background tab is not running an animation frame loop forever.
    function sleep() {
        on = false;
        shell.classList.remove('is-on');
        core.classList.remove('is-on');
        if (frame !== null) { cancelAnimationFrame(frame); frame = null; }
    }
    document.addEventListener('mouseleave', sleep);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { sleep(); } });
});
</script>
