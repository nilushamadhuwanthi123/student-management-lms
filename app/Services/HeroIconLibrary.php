<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The science icons that drift over the home page background.
 *
 * These used to be five SVGs typed straight into the home page, so
 * changing which ones appeared -- or adding one -- meant editing the
 * template. They are listed here instead so the office can pick them
 * from the CMS.
 *
 * Each entry is a label and the inside of an <svg>. The paths are drawn
 * on a 24x24 grid with no fill and a stroked outline, so they inherit
 * whatever colour and size the page gives them and stay crisp at any
 * size. Keeping only the inner markup here means the wrapper -- and the
 * escaping rules around it -- live in one place in the view.
 */
final class HeroIconLibrary
{
    /** @var array<string, array{0: string, 1: string}> key => [label, inner svg] */
    public const ICONS = [
        'atom' => ['Atom', '<circle cx="12" cy="12" r="1.6"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/>'],
        'flask' => ['Flask', '<path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/>'],
        'dna' => ['DNA strand', '<path d="M7 3c0 6 10 6 10 12s-10 6-10 6"/><path d="M17 3c0 6-10 6-10 12s10 6 10 6"/><path d="M8 7h8M8 17h8"/>'],
        'microscope' => ['Microscope', '<path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/>'],
        'molecule' => ['Molecule', '<path d="M12 2v6"/><path d="m4.9 4.9 4.2 4.2"/><path d="M2 12h6"/><circle cx="12" cy="12" r="4"/><path d="M16 12h6"/><path d="m14.9 14.9 4.2 4.2"/><path d="M12 16v6"/>'],
        'bulb' => ['Light bulb', '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V18h8v-3.3A7 7 0 0 0 12 2z"/>'],
        'book' => ['Open book', '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>'],
        'leaf' => ['Leaf', '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>'],
        'magnet' => ['Magnet', '<path d="M6 15v-4a6 6 0 1 1 12 0v4"/><path d="M2 15h8v4a4 4 0 0 1-8 0z"/><path d="M14 15h8v4a4 4 0 0 1-8 0z"/>'],
        'rocket' => ['Rocket', '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91 0z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/>'],
        'globe' => ['Globe', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
        'calculator' => ['Calculator', '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8"/><path d="M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>'],
    ];

    /** The set used before any of this was choosable, so nothing changes until it is. */
    public const DEFAULT_KEYS = ['atom', 'flask', 'dna', 'microscope', 'molecule'];

    /**
     * The chosen keys, from a comma-separated setting.
     *
     * Unknown keys are dropped rather than trusted: the value reaches a
     * template, and a key that is not in the list above has no markup to
     * render anyway. An empty or unreadable setting falls back to the
     * original five, so a blank value never leaves the hero bare by
     * accident -- switching them off is what the on/off field is for.
     *
     * @return list<string>
     */
    public static function chosen(string $raw): array
    {
        $keys = array_filter(array_map('trim', explode(',', $raw)));
        $keys = array_values(array_filter(
            $keys,
            static fn (string $k): bool => isset(self::ICONS[$k])
        ));

        return $keys === [] ? self::DEFAULT_KEYS : $keys;
    }
}
