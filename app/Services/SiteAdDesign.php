<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ads the office designs here, instead of uploading a picture.
 *
 * An ad used to be a title and an uploaded image, which meant every
 * notice needed somebody with a design tool. The office does not have
 * one. "Enrolment closes on the 30th" does not need one either -- it
 * needs a coloured strip, two lines and a button -- so it was simply
 * not getting posted.
 *
 * This holds the handful of looks an ad can take and the one function
 * that draws them. Drawing happens here and nowhere else: the editor's
 * preview and the public page call the same method, so a preview cannot
 * promise something the site then renders differently. That is the only
 * reason to keep a renderer in a service rather than in the views.
 *
 * Everything that reaches the markup is escaped here, and a colour is
 * refused unless it is exactly #rrggbb -- these values land in a style
 * attribute, where a stray quote would be an opening.
 */
final class SiteAdDesign
{
    /**
     * The looks, in the order the office sees them.
     *
     * key => [label, what it is good for]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const TEMPLATES = [
        'strip'    => ['Plain strip',     'One line across the page. Quietest option.'],
        'banner'   => ['Banner',          'A heading, a sentence and a button.'],
        'split'    => ['Side by side',    'Words on the left, the button on the right.'],
        'card'     => ['Notice card',     'A boxed notice with a coloured edge.'],
        'ticket'   => ['Ticket',          'A torn-edge look, for an event or a seminar.'],
        'spotlight' => ['Spotlight',      'Centred, with a soft glow. For something big.'],
    ];

    /**
     * Ready-made colourways, so nobody has to invent a colour scheme.
     *
     * key => [label, background, text, accent]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const COLORWAYS = [
        'royal'   => ['Royal blue',   '#15307E', '#FFFFFF', '#FACC15'],
        'teal'    => ['Teal',         '#0F766E', '#FFFFFF', '#FDE68A'],
        'crimson' => ['Crimson',      '#9F1239', '#FFFFFF', '#FECDD3'],
        'forest'  => ['Forest',       '#14532D', '#FFFFFF', '#BBF7D0'],
        'sunset'  => ['Sunset',       '#C2410C', '#FFFFFF', '#FFEDD5'],
        'plum'    => ['Plum',         '#4C1D95', '#FFFFFF', '#DDD6FE'],
        'ink'     => ['Ink',          '#0F172A', '#FFFFFF', '#38BDF8'],
        'paper'   => ['Paper',        '#FFFBEB', '#422006', '#B45309'],
        'mint'    => ['Soft mint',    '#ECFDF5', '#064E3B', '#0F766E'],
        'sky'     => ['Soft sky',     '#EFF6FF', '#15307E', '#1D4ED8'],
    ];

    /** The look an ad gets when the office has not chosen one. */
    public const DEFAULT_TEMPLATE = 'banner';

    /** The colours an ad gets when the office has not chosen any. */
    public const DEFAULT_COLORS = ['#15307E', '#FFFFFF', '#FACC15'];

    /** Is this one of the looks above? */
    public static function isTemplate(?string $key): bool
    {
        return $key !== null && $key !== '' && isset(self::TEMPLATES[$key]);
    }

    /** Is this ad drawn by us, rather than an uploaded picture? */
    public static function isDesigned(array $ad): bool
    {
        return self::isTemplate(isset($ad['design_template']) ? (string) $ad['design_template'] : null);
    }

    /**
     * A colour, or the fallback.
     *
     * Anything that is not exactly #rrggbb is refused rather than
     * repaired: this value is written into a style attribute, and a
     * half-understood value there is a way into the page.
     */
    public static function color(?string $value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * The ad as HTML.
     *
     * $href is where the button goes; null means no button is drawn even
     * if button text was typed, because a button that goes nowhere is
     * worse than no button.
     *
     * Returns '' for an ad that is not designed, so a caller can fall
     * back to the uploaded image without checking twice.
     */
    public static function render(array $ad, ?string $href = null, bool $compact = false): string
    {
        if (!self::isDesigned($ad)) {
            return '';
        }

        $template = (string) $ad['design_template'];
        [$bgD, $textD, $accentD] = self::DEFAULT_COLORS;

        $bg     = self::color($ad['bg_color'] ?? null, $bgD);
        $text   = self::color($ad['text_color'] ?? null, $textD);
        $accent = self::color($ad['accent_color'] ?? null, $accentD);

        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        // The headline falls back to the title: the title is required, so
        // an ad can never render with no words at all.
        $headline = trim((string) ($ad['headline'] ?? ''));
        if ($headline === '') {
            $headline = trim((string) ($ad['title'] ?? ''));
        }
        $subtext = trim((string) ($ad['subtext'] ?? ''));
        $button  = trim((string) ($ad['button_text'] ?? ''));

        $style = sprintf(
            '--ad-bg:%s;--ad-text:%s;--ad-accent:%s',
            $e($bg),
            $e($text),
            $e($accent)
        );

        $classes = 'ad-design ad-design--' . $e($template) . ($compact ? ' ad-design--compact' : '');

        $buttonHtml = '';
        if ($button !== '' && $href !== null && $href !== '') {
            $buttonHtml = '<a class="ad-design__btn" href="' . $e($href) . '"'
                . (preg_match('#^https?://#i', $href) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '')
                . '>' . $e($button) . '</a>';
        } elseif ($button !== '') {
            // No link: still show it, as a label rather than a promise.
            $buttonHtml = '<span class="ad-design__btn ad-design__btn--flat">' . $e($button) . '</span>';
        }

        $body = '<span class="ad-design__headline">' . $e($headline) . '</span>';
        if ($subtext !== '' && $template !== 'strip') {
            $body .= '<span class="ad-design__sub">' . $e($subtext) . '</span>';
        }

        return '<div class="' . $classes . '" style="' . $style . '">'
            . '<span class="ad-design__words">' . $body . '</span>'
            . $buttonHtml
            . '</div>';
    }

    /**
     * The stylesheet for every look above.
     *
     * Printed once per page by whoever renders an ad. Kept here beside
     * render() so a new look cannot be added without its styling, which
     * is exactly how a template ends up looking broken only in
     * production.
     */
    public static function css(): string
    {
        return <<<'CSS'
.ad-design { --ad-bg:#15307E; --ad-text:#FFF; --ad-accent:#FACC15;
    display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
    background:var(--ad-bg); color:var(--ad-text);
    padding:1rem 1.25rem; border-radius:14px; line-height:1.4; }
.ad-design__words { display:grid; gap:.25rem; flex:1 1 220px; min-width:0; }
.ad-design__headline { font-weight:800; font-size:1.05rem; }
.ad-design__sub { font-size:.88rem; opacity:.9; }
.ad-design__btn { flex:none; display:inline-block; text-decoration:none;
    background:var(--ad-accent); color:var(--ad-bg); font-weight:800; font-size:.85rem;
    padding:.5rem .95rem; border-radius:999px; white-space:nowrap; }
.ad-design__btn:hover { filter:brightness(.94); }
.ad-design__btn--flat { cursor:default; }
.ad-design--compact { padding:.75rem .9rem; border-radius:12px; }
.ad-design--compact .ad-design__headline { font-size:.95rem; }

/* Plain strip: one line, nothing else. */
.ad-design--strip { border-radius:10px; padding:.7rem 1.1rem; }
.ad-design--strip .ad-design__headline { font-size:.95rem; font-weight:700; }

/* Banner: the default. Heading, sentence, button. */
.ad-design--banner { border-left:6px solid var(--ad-accent); }

/* Side by side: words left, button hard right. */
.ad-design--split { justify-content:space-between; }
.ad-design--split .ad-design__words { flex:1 1 60%; }

/* Notice card: boxed, with a coloured top edge. */
.ad-design--card { flex-direction:column; align-items:flex-start;
    border-top:5px solid var(--ad-accent); border-radius:12px; padding:1.1rem 1.2rem 1.2rem; }
.ad-design--card .ad-design__btn { margin-top:.35rem; }

/* Ticket: notched edges, for an event. */
.ad-design--ticket { border-radius:12px; position:relative;
    background:
        radial-gradient(circle at 0 50%, transparent 9px, var(--ad-bg) 10px),
        radial-gradient(circle at 100% 50%, transparent 9px, var(--ad-bg) 10px);
    background-size:51% 100%; background-position:left, right; background-repeat:no-repeat;
    padding:1rem 1.6rem; }
.ad-design--ticket .ad-design__headline { letter-spacing:.01em; text-transform:uppercase; font-size:.98rem; }

/* Spotlight: centred, glowing. For something big. */
.ad-design--spotlight { flex-direction:column; align-items:center; text-align:center;
    padding:1.6rem 1.3rem; border-radius:18px;
    /* No color-mix(): it needs a 2023 browser, and an older phone drops
       the whole declaration -- here that is only a lost glow, but the
       same function lit the hero overlay, so it is gone from the site
       entirely rather than left in one corner to be forgotten. */
    box-shadow:0 0 0 1px var(--ad-accent), 0 18px 44px -18px var(--ad-accent); }
.ad-design--spotlight .ad-design__words { justify-items:center; }
.ad-design--spotlight .ad-design__headline { font-size:1.3rem; }

@media (max-width:560px) {
    .ad-design { padding:.9rem 1rem; }
    .ad-design__btn { width:100%; text-align:center; }
}
CSS;
    }
}
