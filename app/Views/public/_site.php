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
</style>
