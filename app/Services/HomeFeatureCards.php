<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The "How the classes work" cards on the home page.
 *
 * These were eight blocks of markup in the template, so an office that
 * did not offer, say, absence alerts had no way to take that card down
 * short of editing code -- and a card promising something the institute
 * does not do is worse than no card at all.
 *
 * Each card is listed here with its icon and its original wording. The
 * wording and an on/off switch live in the CMS under the matching
 * home_feature_* keys, so the icons stay in one place and the template
 * becomes a loop.
 *
 * The order here is the order on the page.
 */
final class HomeFeatureCards
{
    /**
     * key => [tone, title, text, inner svg]
     *
     * 'tone' picks the existing icon colour class (ts-icon--c1..c7).
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const CARDS = [
        'weekly' => ['c1', 'Weekly classes',
            'Regular classes by grade at each branch, following the school Science syllabus.',
            '<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>'],

        'recordings' => ['c2', 'Recordings and notes',
            'Missed a class or need a second look? Recordings, notes and videos are posted for each month.',
            '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/>'],

        'quizzes' => ['c3', 'Quizzes and homework',
            'Timed online quizzes with marks, and homework the teacher marks with feedback.',
            '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'],

        'papers' => ['c4', 'Past papers',
            'Term test and model papers for each grade, with marking schemes released after the class has tried them.',
            '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>'],

        'parents' => ['c5', 'Parent portal',
            'Parents see marks, attendance, progress, fees and receipts for their child.',
            '<circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-1a6 6 0 0 1 12 0v1"/><path d="M15 21v-1a4 4 0 0 1 6-3.5"/>'],

        'absence' => ['c6', 'Absence alerts',
            'If a child is marked absent, the family gets an alert in the parent portal.',
            '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>'],

        'online' => ['c7', 'Online classes',
            'Some classes also run online. The joining link is given privately to enrolled students.',
            '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/>'],

        'language' => ['c1', 'Sinhala or English',
            'Students and parents can switch the portal between Sinhala and English.',
            '<path d="M4 5h9"/><path d="M8.5 3v2c0 5-3 8-5 9"/><path d="M6 9c1 2.5 3 4 6 5"/><path d="m13 20 4-9 4 9"/><path d="M14.5 17h5"/>'],
    ];

    /**
     * Cards that only make sense when the institute actually does that
     * thing. The Online classes card used to appear only when a class
     * was marked online; keeping that rule means switching the card on
     * by hand still cannot advertise online classes that do not exist.
     *
     * @var array<string, string>  card key => condition name
     */
    public const CONDITIONAL = [
        'online' => 'hasOnline',
    ];

    /** The CMS keys for one card. */
    public static function showKey(string $key): string  { return 'show_feature_' . $key; }
    public static function titleKey(string $key): string { return 'home_feature_' . $key . '_title'; }
    public static function textKey(string $key): string  { return 'home_feature_' . $key . '_text'; }
}
