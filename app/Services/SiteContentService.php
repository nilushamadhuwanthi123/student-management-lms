<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The small, fixed set of editable content blocks on the public website
 * (see migration 019). Deliberately not a general page builder: the
 * key list below is the only thing that can ever be written here, each
 * text/richtext key has a default equal to the text that was hard-coded
 * on the page before this existed (so an untouched install looks
 * identical to today), and nothing else on the public site -- branch
 * names, class counts, the teacher's name -- is ever sourced from here.
 * Those still come only from real records, via PublicSite.
 *
 * Four field types:
 *  - 'text'     a single plain line, stored and shown escaped.
 *  - 'richtext' a short formatted passage (bold/italic/lists/links only),
 *               sanitised to that whitelist on save and safe to echo
 *               unescaped wherever it is shown.
 *  - 'image'    an uploaded photo that replaces a fixed site image;
 *               the stored value is a public-relative path such as
 *               '/uploads/site-content/teacher_photo_image_xxx.jpg',
 *               or '' to keep using the original fixed photo.
 *  - 'media'    the same as 'image', but a short video upload is also
 *               accepted; isVideoPath() tells a view which one the
 *               stored path is, so it can render <video> instead of
 *               <img>.
 */
final class SiteContentService
{
    public const TYPE_TEXT = 'text';
    public const TYPE_RICHTEXT = 'richtext';
    public const TYPE_IMAGE = 'image';
    public const TYPE_MEDIA = 'media';
    /** A colour, stored as #rrggbb. Edited with a picker and a palette. */
    public const TYPE_COLOR = 'color';
    /** On/off. Stored as '1' or '0'; read back with isOn(). */
    public const TYPE_TOGGLE = 'toggle';
    /** A plain number (timings, speeds). Bounds live on the field. */
    public const TYPE_NUMBER = 'number';
    /** One of a fixed list, named by the field's 'choices' constant. */
    public const TYPE_CHOICE = 'choice';
    /** Several of a fixed list, stored comma-separated. */
    public const TYPE_ICONSET = 'iconset';

    /**
     * Ready-made colour sets, offered as one-click choices next to the
     * pickers so the site owner is not left guessing at hex codes.
     *
     * key => [label, primary, secondary, page background, nav background]
     */
    public const THEMES = [
        'royal' => [
            'name' => 'Royal Blue', 'note' => 'The look the site ships with.',
            'primary' => '#1D4ED8', 'secondary' => '#2563EB', 'bg' => '#F4F7FC',
            'nav' => '#15307E', 'navText' => '#FFFFFF', 'accent' => '#FACC15',
            'radius' => 14, 'tint' => 100, 'font' => 'inter',
        ],
        'midnight' => [
            'name' => 'Midnight', 'note' => 'Deep navy, calm and formal.',
            'primary' => '#3B82F6', 'secondary' => '#60A5FA', 'bg' => '#EEF2F9',
            'nav' => '#0B1220', 'navText' => '#FFFFFF', 'accent' => '#38BDF8',
            'radius' => 10, 'tint' => 100, 'font' => 'inter',
        ],
        'emerald' => [
            'name' => 'Emerald', 'note' => 'Fresh green, easy on the eye.',
            'primary' => '#15803D', 'secondary' => '#22C55E', 'bg' => '#F2FAF4',
            'nav' => '#14532D', 'navText' => '#FFFFFF', 'accent' => '#FDE047',
            'radius' => 16, 'tint' => 85, 'font' => 'inter',
        ],
        'teal' => [
            'name' => 'Teal', 'note' => 'Blue-green, modern and clean.',
            'primary' => '#0F766E', 'secondary' => '#14B8A6', 'bg' => '#F1FAF9',
            'nav' => '#0B3F3A', 'navText' => '#FFFFFF', 'accent' => '#FBBF24',
            'radius' => 14, 'tint' => 90, 'font' => 'inter',
        ],
        'plum' => [
            'name' => 'Plum', 'note' => 'Purple, distinctive and warm.',
            'primary' => '#7E22CE', 'secondary' => '#A855F7', 'bg' => '#FAF5FF',
            'nav' => '#3B0764', 'navText' => '#FFFFFF', 'accent' => '#FACC15',
            'radius' => 18, 'tint' => 95, 'font' => 'poppins',
        ],
        'sunset' => [
            'name' => 'Sunset', 'note' => 'Orange, bright and friendly.',
            'primary' => '#C2410C', 'secondary' => '#F97316', 'bg' => '#FFF8F2',
            'nav' => '#7C2D12', 'navText' => '#FFFFFF', 'accent' => '#FACC15',
            'radius' => 16, 'tint' => 85, 'font' => 'poppins',
        ],
        'crimson' => [
            'name' => 'Crimson', 'note' => 'Strong red, high energy.',
            'primary' => '#BE123C', 'secondary' => '#F43F5E', 'bg' => '#FFF5F6',
            'nav' => '#4C0519', 'navText' => '#FFFFFF', 'accent' => '#FDE047',
            'radius' => 12, 'tint' => 100, 'font' => 'inter',
        ],
        'gold' => [
            'name' => 'Gold & Navy', 'note' => 'Classic, a prize-giving feel.',
            'primary' => '#B45309', 'secondary' => '#F59E0B', 'bg' => '#FFFBEB',
            'nav' => '#1E293B', 'navText' => '#FFFFFF', 'accent' => '#FDE68A',
            'radius' => 10, 'tint' => 95, 'font' => 'playfair',
        ],
        'graphite' => [
            'name' => 'Graphite', 'note' => 'Grey and quiet; photos stand out.',
            'primary' => '#334155', 'secondary' => '#64748B', 'bg' => '#F6F8FB',
            'nav' => '#0F172A', 'navText' => '#FFFFFF', 'accent' => '#38BDF8',
            'radius' => 8, 'tint' => 70, 'font' => 'inter',
        ],
        'ocean' => [
            'name' => 'Ocean', 'note' => 'Soft sky blue, light and airy.',
            'primary' => '#0369A1', 'secondary' => '#0EA5E9', 'bg' => '#F0F9FF',
            'nav' => '#0C4A6E', 'navText' => '#FFFFFF', 'accent' => '#FACC15',
            'radius' => 20, 'tint' => 75, 'font' => 'poppins',
        ],
    ];

    /** Which Downloads folders start open on the public page. */
    public const DOWNLOAD_OPEN_MODES = [
        'first' => ['Open the first folder, keep the rest closed'],
        'all'   => ['Open every folder'],
        'none'  => ['Keep every folder closed'],
    ];

    /** How the top menu bar behaves over the page. */
    public const NAV_STYLES = [
        'glass' => ['See-through, frosting as you scroll (the current look)'],
        'solid' => ['Always solid, in the colour chosen below'],
    ];

    /** Heading/body font pairings a theme may select. */
    public const FONTS = [
        'inter'    => ['Inter (clean, the current one)', "'Inter', system-ui, sans-serif", "'Playfair Display', Georgia, serif"],
        'poppins'  => ['Poppins (rounded, friendly)', "'Poppins', system-ui, sans-serif", "'Poppins', system-ui, sans-serif"],
        'playfair' => ['Playfair (formal, traditional)', "'Inter', system-ui, sans-serif", "'Playfair Display', Georgia, serif"],
    ];

    public const PALETTES = [
        'royal'   => ['Royal blue (current)', '#1D4ED8', '#2563EB', '#F4F7FC', '#15307E'],
        'teal'    => ['Teal',                 '#0F766E', '#14B8A6', '#F2FAF9', '#0B3F3A'],
        'plum'    => ['Plum',                 '#7E22CE', '#A855F7', '#FAF5FF', '#3B0764'],
        'sunset'  => ['Sunset orange',        '#C2410C', '#F97316', '#FFF7ED', '#7C2D12'],
        'forest'  => ['Forest green',         '#15803D', '#22C55E', '#F3FAF4', '#14532D'],
        'crimson' => ['Crimson',              '#BE123C', '#F43F5E', '#FFF1F3', '#4C0519'],
        'slate'   => ['Graphite',             '#334155', '#64748B', '#F6F8FB', '#0F172A'],
        'gold'    => ['Gold and navy',        '#B45309', '#F59E0B', '#FFFBEB', '#1E293B'],
    ];

    /**
     * The language whose wording is stored under the plain key, and the
     * languages the site owner can write wording for. A photo is shared
     * by every language, so only text/richtext fields are translatable.
     */
    public const BASE_LANG = 'en';
    public const LANGS = ['en', 'si'];

    /**
     * The groups the fields below are shown in, in this order. Purely for
     * the editing screen -- a group has no effect on what a page renders.
     */
    public const GROUPS = [
        'appearance' => 'Site design: themes, colours and the menu bar',
        'background' => 'Home page background: photos, tint and motion',
        'visibility' => 'Show or hide parts of the site',
        'hero'     => 'Home page: the top of the page',
        'teacher'  => 'Home page: "Your teacher"',
        'how'      => 'Home page: how the classes work',
        'joining'  => 'Home page: the four steps to join',
        'posters'  => 'Home page: seminar posters and notices',
        'lists'    => 'Home page: branches and classes',
        'faq'      => 'Home page: questions parents ask',
        'inquiry'  => 'Home page: the inquiry form',
        'about'    => 'About page',
        'contact'  => 'Contact page',
        'classes'  => 'Classes page',
        'events'   => 'Events page',
        'downloads' => 'Downloads page',
        'photos'   => 'Photos',
    ];

    /** @var array<string, array{label: string, default: string, type: string, group?: string}> */
    public const FIELDS = [
        // -- Look and feel: colours ------------------------------------------
        'theme_accent' => [
            'label'   => 'Highlight colour (buttons that must stand out)',
            'default' => '#FACC15',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],
        'theme_radius' => [
            'label'   => 'Corner roundness',
            'default' => '14',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'appearance',
            'min'     => 0,
            'max'     => 28,
            'step'    => 2,
            'suffix'  => 'px (0 = square corners)',
        ],
        'theme_font' => [
            'label'   => 'Lettering',
            'default' => 'inter',
            'type'    => self::TYPE_CHOICE,
            'group'   => 'appearance',
            'choices' => 'FONTS',
        ],
        'theme_admin_follows' => [
            'label'   => 'Use these colours inside the admin system as well',
            'default' => '0',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'appearance',
            'help'    => 'Off keeps the admin dashboard in its standard blue, which many offices prefer so staff can tell the two apart at a glance.',
        ],
        'theme_primary' => [
            'label'   => 'Main colour (buttons, links, highlights)',
            'default' => '#1D4ED8',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],
        'theme_secondary' => [
            'label'   => 'Second colour (gradients and accents)',
            'default' => '#2563EB',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],
        'theme_page_bg' => [
            'label'   => 'Page background colour',
            'default' => '#F4F7FC',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],
        'nav_style' => [
            'label'   => 'Top menu bar: how it looks',
            'default' => 'glass',
            'type'    => self::TYPE_CHOICE,
            'group'   => 'appearance',
            'choices' => 'NAV_STYLES',
            'help'    => 'See-through lets the background photo show behind the menu. Solid is easier to read, and is the safer choice with a busy photo.',
        ],
        'theme_nav_bg' => [
            'label'   => 'Top menu bar: background colour',
            'default' => '#15307E',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],
        'theme_nav_text' => [
            'label'   => 'Top menu bar: text colour',
            'default' => '#FFFFFF',
            'type'    => self::TYPE_COLOR,
            'group'   => 'appearance',
        ],

        // -- Look and feel: motion -------------------------------------------
        'intro_enabled' => [
            'label'   => 'Show the opening animation when the site is first opened',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'background',
        ],
        'intro_hold_ms' => [
            'label'   => 'Opening animation: how long it stays on screen',
            'default' => '2200',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 600,
            'max'     => 10000,
            'step'    => 100,
            'suffix'  => 'milliseconds (1000 = 1 second)',
            'help'    => 'Raise this to let people read it; lower it to get to the page sooner.',
        ],
        'hero_tint_percent' => [
            'label'   => 'Colour tint over the background photo',
            'default' => '100',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 0,
            'max'     => 100,
            'step'    => 5,
            'suffix'  => '%',
            'help'    => '100% is the tint the site ships with. Lower it to let more of the photo through; 0% removes it. Keep enough tint that the white heading stays readable.',
        ],
        'hero_interval_ms' => [
            'label'   => 'Background photos: how long each one is shown',
            'default' => '5000',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 1500,
            'max'     => 30000,
            'step'    => 500,
            'suffix'  => 'milliseconds (1000 = 1 second)',
            'help'    => 'Only matters when more than one background photo is active.',
        ],
        'hero_float_enabled' => [
            'label'   => 'Show the drifting science icons over the background',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'background',
        ],
        'hero_float_icons' => [
            'label'   => 'Which icons drift over the background',
            'default' => 'atom,flask,dna,microscope,molecule',
            'type'    => self::TYPE_ICONSET,
            'group'   => 'background',
            'help'    => 'Tick the ones you want. They are placed and timed automatically, so any number works -- four or five looks best.',
        ],
        'hero_float_size' => [
            'label'   => 'Drifting icons: size',
            'default' => '100',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 40,
            'max'     => 220,
            'step'    => 10,
            'suffix'  => '% of the normal size',
        ],
        'hero_float_opacity' => [
            'label'   => 'Drifting icons: how faint they are',
            'default' => '100',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 10,
            'max'     => 220,
            'step'    => 10,
            'suffix'  => '% (lower is fainter)',
        ],
        'hero_float_speed' => [
            'label'   => 'Drifting icons: how long one full drift takes',
            'default' => '26',
            'type'    => self::TYPE_NUMBER,
            'group'   => 'background',
            'min'     => 6,
            'max'     => 120,
            'step'    => 1,
            'suffix'  => 'seconds',
            'help'    => 'A bigger number drifts more slowly.',
        ],

        // -- Look and feel: what is shown ------------------------------------
        'show_nav_enrol' => [
            'label'   => 'Menu: show the "Enrol now" button',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_nav_login' => [
            'label'   => 'Menu: show the "Login" button',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
            'help'    => 'Students and parents can still reach the login page directly; this only hides the button.',
        ],
        'show_nav_events' => [
            'label'   => 'Menu: show "Events"',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_nav_downloads' => [
            'label'   => 'Menu: show "Downloads"',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_nav_facebook' => [
            'label'   => 'Menu: show a Facebook button (uses the Facebook address on the Contact tab)',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_hero_text' => [
            'label'   => 'Home page: show the wording over the background photo',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_how_section' => [
            'label'   => 'Home page: show "How the classes work"',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],
        'show_seminar_banner' => [
            'label'   => 'Home page: show the upcoming seminar strip at the top',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'visibility',
        ],

        // -- Home page: hero -------------------------------------------------
        'home_hero_text' => [
            'label'   => 'Hero paragraph (under the heading)',
            'default' => 'Weekly Science classes for Grades {r}, following the school syllabus. Every lesson is backed by recordings, notes, quizzes and homework in the portal, and parents can follow their child\'s progress.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'hero',
        ],
        'home_hero_cta_1' => [
            'label'   => 'Hero: first button',
            'default' => 'Join a class',
            'type'    => self::TYPE_TEXT,
            'group'   => 'hero',
        ],
        'home_hero_cta_2' => [
            'label'   => 'Hero: second button',
            'default' => 'See the timetable',
            'type'    => self::TYPE_TEXT,
            'group'   => 'hero',
        ],

        // -- Home page: your teacher -----------------------------------------
        'teacher_name_override' => [
            'label'   => 'Teacher\'s name (overrides the name worked out from classes/branches below -- set this so the teacher photo and "Your teacher" section show even before any class has a teacher assigned)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_kicker' => [
            'label'   => 'Small label above the teacher\'s name',
            'default' => 'Your teacher',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_text' => [
            'label'   => 'Paragraph about the teacher',
            'default' => '{name} teaches Science for Grades {r} at {n} branches, following the school syllabus step by step.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_point_1' => [
            'label'   => 'Tick 1',
            'default' => 'Lessons with smart-board presentations',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_point_2' => [
            'label'   => 'Tick 2',
            'default' => 'Recordings and notes for every month, to revise at home',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_point_3' => [
            'label'   => 'Tick 3',
            'default' => 'Quizzes, homework and past papers, marked with feedback',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],
        'home_teacher_point_4' => [
            'label'   => 'Tick 4',
            'default' => 'Parents can follow marks, attendance and fees in the portal',
            'type'    => self::TYPE_TEXT,
            'group'   => 'teacher',
        ],

        // -- Home page: how the classes work ---------------------------------
        'home_how_title' => [
            'label'   => 'Section heading',
            'default' => 'How the classes work',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_how_text' => [
            'label'   => 'Section intro line',
            'default' => 'Each grade meets every week at a branch. Between classes, students keep learning from home.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],

        // -- Home page: joining ----------------------------------------------
        'show_feature_weekly' => [
            'label'   => 'Show the "Weekly classes" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_weekly_title' => [
            'label'   => '-- its heading',
            'default' => 'Weekly classes',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_weekly_text' => [
            'label'   => '-- its wording',
            'default' => 'Regular classes by grade at each branch, following the school Science syllabus.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_recordings' => [
            'label'   => 'Show the "Recordings and notes" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_recordings_title' => [
            'label'   => '-- its heading',
            'default' => 'Recordings and notes',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_recordings_text' => [
            'label'   => '-- its wording',
            'default' => 'Missed a class or need a second look? Recordings, notes and videos are posted for each month.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_quizzes' => [
            'label'   => 'Show the "Quizzes and homework" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_quizzes_title' => [
            'label'   => '-- its heading',
            'default' => 'Quizzes and homework',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_quizzes_text' => [
            'label'   => '-- its wording',
            'default' => 'Timed online quizzes with marks, and homework the teacher marks with feedback.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_papers' => [
            'label'   => 'Show the "Past papers" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_papers_title' => [
            'label'   => '-- its heading',
            'default' => 'Past papers',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_papers_text' => [
            'label'   => '-- its wording',
            'default' => 'Term test and model papers for each grade, with marking schemes released after the class has tried them.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_parents' => [
            'label'   => 'Show the "Parent portal" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_parents_title' => [
            'label'   => '-- its heading',
            'default' => 'Parent portal',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_parents_text' => [
            'label'   => '-- its wording',
            'default' => 'Parents see marks, attendance, progress, fees and receipts for their child.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_absence' => [
            'label'   => 'Show the "Absence alerts" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_absence_title' => [
            'label'   => '-- its heading',
            'default' => 'Absence alerts',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_absence_text' => [
            'label'   => '-- its wording',
            'default' => 'If a child is marked absent, the family gets an alert in the parent portal.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_online' => [
            'label'   => 'Show the "Online classes" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
            'help'    => 'Only ever shown when at least one class is actually marked as online, so this cannot advertise something the institute does not run.',
        ],
        'home_feature_online_title' => [
            'label'   => '-- its heading',
            'default' => 'Online classes',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_online_text' => [
            'label'   => '-- its wording',
            'default' => 'Some classes also run online. The joining link is given privately to enrolled students.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'show_feature_language' => [
            'label'   => 'Show the "Sinhala or English" card',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'how',
        ],
        'home_feature_language_title' => [
            'label'   => '-- its heading',
            'default' => 'Sinhala or English',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],
        'home_feature_language_text' => [
            'label'   => '-- its wording',
            'default' => 'Students and parents can switch the portal between Sinhala and English.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'how',
        ],

        'home_join_title' => [
            'label'   => 'Section heading',
            'default' => 'Four steps to join',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'show_step_1' => [
            'label'   => 'Show step 1',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'joining',
        ],
        'show_step_2' => [
            'label'   => 'Show step 2',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'joining',
        ],
        'show_step_3' => [
            'label'   => 'Show step 3',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'joining',
        ],
        'show_step_4' => [
            'label'   => 'Show step 4',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'joining',
        ],
        'home_step_1_title' => [
            'label'   => 'Step 1 heading',
            'default' => 'Send an inquiry',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_1_text' => [
            'label'   => 'Step 1 text',
            'default' => 'Fill in the form below or call the branch nearest to you.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_2_title' => [
            'label'   => 'Step 2 heading',
            'default' => 'Register at the office',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_2_text' => [
            'label'   => 'Step 2 text',
            'default' => 'The office registers your child and gives the student ID and a first password.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_3_title' => [
            'label'   => 'Step 3 heading',
            'default' => 'Come to class every week',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_3_text' => [
            'label'   => 'Step 3 text',
            'default' => 'Weekly class at the branch, with notes, recordings and quizzes online.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_4_title' => [
            'label'   => 'Step 4 heading',
            'default' => 'Follow the progress',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],
        'home_step_4_text' => [
            'label'   => 'Step 4 text',
            'default' => 'Parents log in with their phone number to see marks, attendance and fees.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'joining',
        ],

        // -- Home page: posters ----------------------------------------------
        'home_posters_title' => [
            'label'   => 'Heading above the posters',
            'default' => 'Notices',
            'type'    => self::TYPE_TEXT,
            'group'   => 'posters',
        ],
        'home_posters_text' => [
            'label'   => 'Line under that heading (leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'posters',
        ],

        // -- Home page: branches and classes ---------------------------------
        'home_branches_title' => [
            'label'   => 'Branches: heading',
            'default' => 'Our branches',
            'type'    => self::TYPE_TEXT,
            'group'   => 'lists',
        ],
        'home_branches_text' => [
            'label'   => 'Branches: intro line',
            'default' => 'Choose the branch nearest to you.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'lists',
        ],
        'home_classes_title' => [
            'label'   => 'Classes teaser: heading',
            'default' => 'Explore our classes',
            'type'    => self::TYPE_TEXT,
            'group'   => 'lists',
        ],
        'home_classes_text' => [
            'label'   => 'Classes teaser: intro line',
            'default' => 'Every branch, grade, day and time -- pick a branch and see its full class list.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'lists',
        ],

        // -- Home page: questions parents ask --------------------------------
        'home_faq_title' => [
            'label'   => 'Section heading',
            'default' => 'Questions parents ask',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_1_q' => [
            'label'   => 'Question 1',
            'default' => 'How do I join a class?',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_1_a' => [
            'label'   => 'Answer 1',
            'default' => 'Fill the enrol form (pick a branch and class, upload your bank deposit slip) and the branch office approves it -- or send an inquiry below and the office will call you back. Either way you get a student ID and password for the portal.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'faq',
        ],
        'home_faq_2_q' => [
            'label'   => 'Question 2',
            'default' => 'How are fees paid?',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_2_a' => [
            'label'   => 'Answer 2',
            'default' => 'Fees are monthly. Pay at the branch office, or pay by bank and upload the slip in the portal. The office checks every slip before the payment is recorded, and a receipt is then available to download.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'faq',
        ],
        'home_faq_3_q' => [
            'label'   => 'Question 3',
            'default' => 'What happens if a student misses a class?',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_3_a' => [
            'label'   => 'Answer 3',
            'default' => 'Recordings and notes for the month are posted in the portal, so the lesson can be caught up at home.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'faq',
        ],
        'home_faq_4_q' => [
            'label'   => 'Question 4',
            'default' => 'Can parents see how their child is doing?',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_4_a' => [
            'label'   => 'Answer 4',
            'default' => 'Yes. Parents log in with their phone number to see marks, attendance, progress, homework and fees.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'faq',
        ],
        'home_faq_5_q' => [
            'label'   => 'Question 5 (leave blank to hide)',
            'default' => 'Is the portal available in Sinhala?',
            'type'    => self::TYPE_TEXT,
            'group'   => 'faq',
        ],
        'home_faq_5_a' => [
            'label'   => 'Answer 5',
            'default' => 'Yes. Use the EN / Sinhala button at the top of the portal to switch.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'faq',
        ],

        // -- Home page: the inquiry form --------------------------------------
        'home_inquiry_title' => [
            'label'   => 'Heading beside the form',
            'default' => 'Enquire about a class',
            'type'    => self::TYPE_TEXT,
            'group'   => 'inquiry',
        ],
        'home_inquiry_text' => [
            'label'   => 'Line under that heading',
            'default' => 'Leave your details and the branch office will call you back.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'inquiry',
        ],
        'home_inquiry_point_1' => [
            'label'   => 'Tick 1',
            'default' => 'Weekly classes by grade',
            'type'    => self::TYPE_TEXT,
            'group'   => 'inquiry',
        ],
        'home_inquiry_point_2' => [
            'label'   => 'Tick 2',
            'default' => 'Recordings, notes and quizzes online',
            'type'    => self::TYPE_TEXT,
            'group'   => 'inquiry',
        ],
        'home_inquiry_point_3' => [
            'label'   => 'Tick 3',
            'default' => 'A parent login to follow progress',
            'type'    => self::TYPE_TEXT,
            'group'   => 'inquiry',
        ],

        // -- About page -------------------------------------------------------
        'show_about_teacher' => [
            'label'   => 'Show the teacher section',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'about',
        ],
        'show_about_inclass' => [
            'label'   => 'Show the "In class" section',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'about',
        ],
        'show_about_branches' => [
            'label'   => 'Show the branch list',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'about',
            'help'    => 'Only ever shown when branches have actually been added, so this cannot produce an empty list.',
        ],
        'show_about_inquiry' => [
            'label'   => 'Show the "Would you like your child to join?" panel at the end',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'about',
        ],
        'about_in_class_text' => [
            'label'   => '"In class" paragraph',
            'default' => 'Each grade has its own weekly class at the branch. Students who miss a lesson can catch up from the recording and notes, and the class register lets parents know about an absence.',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'about',
        ],
        'about_extra_text' => [
            'label'   => 'Extra paragraph (optional -- leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_RICHTEXT,
            'group'   => 'about',
        ],

        // -- Contact page ------------------------------------------------------
        'show_contact_online' => [
            'label'   => 'Show the "Find us online" section',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'contact',
        ],
        'show_contact_form' => [
            'label'   => 'Show the "Send a message" form',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'contact',
            'help'    => 'Turn this off if the office would rather families phoned or used WhatsApp.',
        ],
        'contact_intro_text' => [
            'label'   => 'Intro line (under the heading)',
            'default' => 'Call the branch nearest to you, or send a message and the office will reply.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'contact',
        ],
        'contact_whatsapp' => [
            'label'   => 'WhatsApp number (leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'contact',
        ],
        'contact_email' => [
            'label'   => 'Email address (leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'contact',
        ],
        'contact_website_url' => [
            'label'   => 'Website address (leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'contact',
        ],
        'contact_facebook_url' => [
            'label'   => 'Facebook page address (leave blank to hide)',
            'default' => '',
            'type'    => self::TYPE_TEXT,
            'group'   => 'contact',
        ],

        // -- Classes page (/our-classes) ---------------------------------------
        'classes_eyebrow' => [
            'label'   => 'Small label above the heading',
            'default' => 'Classes',
            'type'    => self::TYPE_TEXT,
            'group'   => 'classes',
        ],
        'classes_title' => [
            'label'   => 'Heading',
            'default' => 'Pick a branch',
            'type'    => self::TYPE_TEXT,
            'group'   => 'classes',
        ],
        'classes_text' => [
            'label'   => 'Intro line (under the heading)',
            'default' => 'See the classes, photos and timings for each branch, then view that branch\'s classes.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'classes',
        ],
        'classes_empty_text' => [
            'label'   => 'Shown when no branch is published yet',
            'default' => 'No branch is published on the website yet.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'classes',
        ],

        // -- Events page (/events) ----------------------------------------------
        'events_eyebrow' => [
            'label'   => 'Small label above the heading',
            'default' => 'Events',
            'type'    => self::TYPE_TEXT,
            'group'   => 'events',
        ],
        'events_title' => [
            'label'   => 'Heading',
            'default' => 'Seminars & events',
            'type'    => self::TYPE_TEXT,
            'group'   => 'events',
        ],
        'events_text' => [
            'label'   => 'Intro line (under the heading)',
            'default' => 'Upcoming seminars, revision classes and events for our students.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'events',
        ],
        'events_empty_text' => [
            'label'   => 'Shown when no event is published yet',
            'default' => 'No upcoming events are published yet. Check back soon.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'events',
        ],

        // -- Downloads page (/downloads) ------------------------------------------
        'downloads_eyebrow' => [
            'label'   => 'Small label above the heading',
            'default' => 'Resources',
            'type'    => self::TYPE_TEXT,
            'group'   => 'downloads',
        ],
        'downloads_title' => [
            'label'   => 'Heading',
            'default' => 'Downloads',
            'type'    => self::TYPE_TEXT,
            'group'   => 'downloads',
        ],
        'downloads_text' => [
            'label'   => 'Intro line (under the heading)',
            'default' => 'Past papers, notes and other files the teacher or super admin has shared.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'downloads',
        ],
        'downloads_open_mode' => [
            'label'   => 'Downloads page: which folders start open',
            'default' => 'first',
            'type'    => self::TYPE_CHOICE,
            'group'   => 'downloads',
            'choices' => 'DOWNLOAD_OPEN_MODES',
            'help'    => 'With only a few folders, opening all of them saves a tap. With many, all closed is easier to scan.',
        ],
        'downloads_show_counts' => [
            'label'   => 'Downloads page: show how many files each folder holds',
            'default' => '1',
            'type'    => self::TYPE_TOGGLE,
            'group'   => 'downloads',
        ],
        'downloads_empty_text' => [
            'label'   => 'Shown when nothing has been shared yet',
            'default' => 'Nothing has been shared here yet.',
            'type'    => self::TYPE_TEXT,
            'group'   => 'downloads',
        ],

        // -- Photos ------------------------------------------------------------
        'teacher_photo_image' => [
            'label'   => 'Teacher photo or short video (home page hero and "Your teacher" section on the About page)',
            'default' => '',
            'type'    => self::TYPE_MEDIA,
            'group'   => 'photos',
        ],
        'about_class_image' => [
            'label'   => 'About page: "In class" section photo',
            'default' => '',
            'type'    => self::TYPE_IMAGE,
            'group'   => 'photos',
        ],
    ];

    /** Allowed HTML for richtext fields: inline formatting, lists and links -- nothing that can script or embed. */
    private const RICHTEXT_ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a>';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * Is an on/off field switched on?
     *
     * Anything unset falls back to the field's own default, so a site
     * that has never been edited behaves exactly as it did before these
     * switches existed.
     */
    public function isOn(string $key): bool
    {
        $raw = trim($this->get($key));
        if ($raw === '') {
            $raw = (string) (self::FIELDS[$key]['default'] ?? '1');
        }

        return $raw === '1';
    }

    /**
     * A numeric field, clamped to the bounds declared on the field so a
     * stray value cannot freeze an animation on screen or blink it past
     * before anyone sees it.
     */
    public function num(string $key): int
    {
        $field = self::FIELDS[$key] ?? [];
        $value = trim($this->get($key));
        $value = $value === '' ? (string) ($field['default'] ?? '0') : $value;
        $value = (int) $value;

        $min = (int) ($field['min'] ?? PHP_INT_MIN);
        $max = (int) ($field['max'] ?? PHP_INT_MAX);

        return max($min, min($max, $value));
    }

    /**
     * A colour field as #rrggbb.
     *
     * The value is going straight into a stylesheet, so anything that is
     * not plainly a hex colour is refused and the default used instead --
     * that keeps a stray value from closing the CSS rule and injecting
     * its own.
     */
    public function color(string $key): string
    {
        $default = (string) (self::FIELDS[$key]['default'] ?? '#000000');
        $value = trim($this->get($key));

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $default;
    }

    /**
     * The wording to show for $key on the page being rendered: the site
     * owner's own text for the current language where they have written
     * one, else their language-neutral text, else the original default --
     * failing open to the default on any error, never to a blank page.
     *
     * Text and richtext go through the dictionary on the way out, so a
     * field nobody has edited reads in the language of the page rather
     * than in English. $params are then substituted the same way as
     * te()'s placeholders -- {r}, {n}, etc. Neither applies to an
     * 'image'/'media' field, whose value is a path shared by every language.
     */
    public function get(string $key, array $params = []): string
    {
        $type = self::FIELDS[$key]['type'] ?? self::TYPE_TEXT;
        $default = (string) (self::FIELDS[$key]['default'] ?? '');
        $lang = self::currentLang();

        // The wording the site owner saved for the language being viewed
        // wins; failing that, the one they saved without a language; and
        // failing that, the original default below.
        $stored = null;
        $candidates = $lang === '' ? [$key] : [self::langKey($key, $lang), $key];
        foreach ($candidates as $candidate) {
            $found = $this->fetch($candidate);
            if ($found !== null) {
                $stored = $found;
                break;
            }
        }

        if ($type === self::TYPE_IMAGE || $type === self::TYPE_MEDIA) {
            return $stored ?? $default;
        }

        $value = $stored ?? $default;

        // A field the owner has never touched falls back to its default,
        // which is the English wording that used to be hard-coded on the
        // page -- so it has to go through the dictionary, or the Sinhala
        // site shows English here. The owner's own wording goes through
        // t() as well, which hands anything it has no entry for straight
        // back, so their text is never altered.
        if (function_exists('t')) {
            $value = (string) t($value);
        }

        foreach ($params as $ph => $v) {
            $value = str_replace('{' . $ph . '}', (string) $v, $value);
        }

        return $value;
    }

    /**
     * The prefix for the per-sentence show/hide switches.
     *
     * The group switches ('show_how_section' and the like) take whole
     * sections down. That is too blunt for the office's actual question,
     * which is always about one line: the enrolment paragraph is wrong
     * for this term, the fourth bullet names a branch that closed, the
     * hero button should not be there until the forms are printed. Short
     * of asking a developer, the only answer used to be blanking the
     * field, which loses the wording -- so next term it has to be typed
     * again from memory.
     *
     * So every piece of wording gets its own switch, stored under
     * 'hide_<key>'. Hidden, not deleted: the words stay in the editor
     * where they were, and turning it back on restores them exactly.
     *
     * Stored as "hide" rather than "show" on purpose. An absent setting
     * then means visible, so every sentence that exists today keeps
     * showing with nothing written to the database -- a site that has
     * never used this reads exactly as it does now.
     */
    public const HIDE_PREFIX = 'hide_';

    /** The storage key holding the switch for one piece of wording. */
    public static function hideKey(string $key): string
    {
        return self::HIDE_PREFIX . $key;
    }

    /**
     * The fields that are wording, so can be hidden one at a time.
     *
     * Colours, numbers and the switches themselves are not sentences:
     * hiding "Corner roundness" would mean nothing, and offering it
     * would make the editor look careless.
     *
     * @return list<string>
     */
    public static function textKeys(): array
    {
        $keys = [];
        foreach (self::FIELDS as $key => $field) {
            $type = $field['type'] ?? self::TYPE_TEXT;
            if ($type === self::TYPE_TEXT || $type === self::TYPE_RICHTEXT) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Has the office hidden this piece of wording?
     *
     * Fails to "visible" on any problem. A settings table that cannot be
     * read must not blank the public site: a page with its words missing
     * looks broken to a parent, while a page showing wording the office
     * meant to hide is merely out of date.
     */
    public function isHidden(string $key): bool
    {
        return $this->fetch(self::hideKey($key)) === '1';
    }

    /**
     * Every piece of wording currently hidden, for the editor.
     *
     * One query rather than 78: the editing screen needs the state of
     * all of them at once to draw the switches.
     *
     * @return array<string, true>
     */
    public function hiddenMap(): array
    {
        $map = [];

        try {
            $sql = 'SELECT content_key FROM site_content
                     WHERE content_key LIKE :p AND content_value = :one';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':p' => self::HIDE_PREFIX . '%', ':one' => '1']);
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $storageKey) {
                $map[substr((string) $storageKey, strlen(self::HIDE_PREFIX))] = true;
            }
        } catch (\Throwable $e) {
            error_log('Hidden-wording map unavailable: ' . $e->getMessage());
        }

        return $map;
    }

    /**
     * Show or hide one piece of wording.
     *
     * Only a key that is actually wording is accepted, so a crafted form
     * post cannot write arbitrary rows into the settings table.
     */
    public function setHidden(string $key, bool $hidden, int $userId): void
    {
        if (!in_array($key, self::textKeys(), true)) {
            return;
        }

        // Written straight here rather than through set(): the switch is
        // not one of the declared fields, and it is deliberately not
        // added to FIELDS -- it would then appear in the editor as a
        // field of its own, 78 times over, instead of as the small eye
        // beside the wording it belongs to.
        $this->db->prepare(
            'INSERT INTO site_content (content_key, content_value, updated_by, updated_at)
             VALUES (:k, :v, :by, NOW())
             ON DUPLICATE KEY UPDATE content_value = :v2, updated_by = :by2, updated_at = NOW()'
        )->execute([
            ':k'  => self::hideKey($key),
            ':v'  => $hidden ? '1' : '0',
            ':by' => $userId,
            ':v2' => $hidden ? '1' : '0',
            ':by2' => $userId,
        ]);
    }

    /** The stored value for an exact storage key, or null when unset, blank or unreadable. */
    private function fetch(string $storageKey): ?string
    {
        try {
            $stmt = $this->db->prepare('SELECT content_value FROM site_content WHERE content_key = :k');
            $stmt->execute([':k' => $storageKey]);
            $stored = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('Site content unavailable: ' . $e->getMessage());
            return null;
        }
        if ($stored === false || trim((string) $stored) === '') {
            return null;
        }
        return (string) $stored;
    }

    /**
     * The storage key holding $key's wording for $lang. The base key keeps
     * its own name, so everything saved before this existed still reads
     * back unchanged; a translation is suffixed. content_key is 64 chars,
     * which every key in FIELDS leaves room for.
     */
    public static function langKey(string $key, string $lang): string
    {
        return $lang === '' || $lang === self::BASE_LANG ? $key : $key . '::' . $lang;
    }

    /** The language the page is being rendered in, or '' when there is no i18n. */
    private static function currentLang(): string
    {
        if (!function_exists('lang_current')) {
            return '';
        }
        $lang = (string) lang_current();
        return in_array($lang, self::LANGS, true) ? $lang : '';
    }

    /** @return array<string, string> every known key => its current (or default) value */
    public function getAll(): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    /**
     * Every known key => the owner's own stored wording per language,
     * with '' where they have not written one (so the form can show the
     * default as a placeholder rather than as a value to re-save).
     * Untranslated and shared by every language.
     *
     * @return array<string, array<string, string>> key => lang => stored value
     */
    public function getAllForEditing(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => $field) {
            $out[$key] = [];
            if (($field['type'] ?? self::TYPE_TEXT) === self::TYPE_IMAGE) {
                $out[$key][self::BASE_LANG] = $this->fetch($key) ?? '';
                continue;
            }
            foreach (self::LANGS as $lang) {
                $out[$key][$lang] = $this->fetch(self::langKey($key, $lang)) ?? '';
            }
        }
        return $out;
    }

    /**
     * @throws \InvalidArgumentException if $key is not one of the fixed, known
     *         fields, or $lang is not one this site is written in
     */
    public function set(string $key, string $value, int $updatedBy, string $lang = self::BASE_LANG): void
    {
        if (!array_key_exists($key, self::FIELDS)) {
            throw new \InvalidArgumentException("Unknown site content key: {$key}");
        }
        if (!in_array($lang, self::LANGS, true)) {
            throw new \InvalidArgumentException("Unknown site content language: {$lang}");
        }
        $type = self::FIELDS[$key]['type'] ?? self::TYPE_TEXT;
        if ($type === self::TYPE_IMAGE && $lang !== self::BASE_LANG) {
            throw new \InvalidArgumentException("Photos are shared by every language: {$key}");
        }
        if ($type === self::TYPE_RICHTEXT) {
            $value = self::sanitizeRichText($value);
        }
        $key = self::langKey($key, $lang);
        $this->db->prepare(
            'INSERT INTO site_content (content_key, content_value, updated_by, updated_at)
             VALUES (:k, :v, :by, NOW())
             ON DUPLICATE KEY UPDATE content_value = :v2, updated_by = :by2, updated_at = NOW()'
        )->execute([
            ':k' => $key, ':v' => $value, ':by' => $updatedBy,
            ':v2' => $value, ':by2' => $updatedBy,
        ]);
    }

    /**
     * Save an uploaded photo for an 'image' field, replacing any previous
     * upload for that key. $file is one entry of $_FILES (already checked
     * by the caller to be present with error === UPLOAD_ERR_OK).
     *
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on an unknown key, wrong field type, bad file type/size, or a failed move
     */
    public function setImage(string $key, array $file, int $updatedBy): void
    {
        if (!array_key_exists($key, self::FIELDS)) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }
        $fieldType = self::FIELDS[$key]['type'];
        if ($fieldType !== self::TYPE_IMAGE && $fieldType !== self::TYPE_MEDIA) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }

        $imageAllowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $videoAllowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'];

        $mime = $file['type'] ?? '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected !== false) {
                $mime = $detected;
            }
        }

        if (isset($imageAllowed[$mime])) {
            $ext = $imageAllowed[$mime];
            if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
                throw new \InvalidArgumentException('Photo must be 3MB or smaller.');
            }
        } elseif ($fieldType === self::TYPE_MEDIA && isset($videoAllowed[$mime])) {
            $ext = $videoAllowed[$mime];
            if ((int) ($file['size'] ?? 0) > 35 * 1024 * 1024) {
                throw new \InvalidArgumentException('Video must be 35MB or smaller -- trim it or compress it first.');
            }
        } elseif ($fieldType === self::TYPE_MEDIA) {
            throw new \InvalidArgumentException('File must be a JPG/PNG/WEBP photo or an MP4/WEBM/MOV/OGG video.');
        } else {
            throw new \InvalidArgumentException('Photo must be a JPG, PNG or WEBP image.');
        }

        $targetDir = BASE_PATH . '/public/uploads/site-content';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = preg_replace('/[^a-z0-9_]/', '', strtolower($key)) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded file.');
        }

        $oldPath = $this->get($key);
        $this->set($key, '/uploads/site-content/' . $filename, $updatedBy);

        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/site-content/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /** True if a stored site-content media path is a video file rather than a photo. */
    public static function isVideoPath(string $path): bool
    {
        return (bool) preg_match('/\.(mp4|webm|mov|ogv)$/i', $path);
    }

    /** Clear an 'image'/'media' field back to the original fixed photo. */
    public function clearImage(string $key, int $updatedBy): void
    {
        if (!array_key_exists($key, self::FIELDS)) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }
        $fieldType = self::FIELDS[$key]['type'];
        if ($fieldType !== self::TYPE_IMAGE && $fieldType !== self::TYPE_MEDIA) {
            throw new \InvalidArgumentException("Unknown site content image key: {$key}");
        }
        $oldPath = $this->get($key);
        $this->set($key, '', $updatedBy);
        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/site-content/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /**
     * Reduce $html to the richtext whitelist (paragraphs, line breaks, bold,
     * italic, underline, lists and links with an http(s)/mailto href only),
     * dropping every other tag, and every attribute except href/target/rel
     * on <a>. Safe to store and to echo unescaped afterwards.
     */
    public static function sanitizeRichText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $clean = strip_tags($html, self::RICHTEXT_ALLOWED_TAGS);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $clean . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::stripDisallowedAttributes($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Collapse an editor's empty paragraphs/line breaks left over from backspacing.
        $out = preg_replace('/(<p>(\s|&nbsp;|<br\s*\/?>)*<\/p>)+$/i', '', $out) ?? $out;

        return mb_substr(trim($out), 0, 4000);
    }

    private static function stripDisallowedAttributes(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            $toRemove = [];
            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                $keep = false;
                if ($tag === 'a' && $name === 'href') {
                    $keep = (bool) preg_match('#^(https?://|mailto:|/)#i', trim($attr->value));
                } elseif ($tag === 'a' && in_array($name, ['target', 'rel'], true)) {
                    $keep = true;
                }
                if (!$keep) {
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $name) {
                $node->removeAttribute($name);
            }
            if ($tag === 'a' && $node->hasAttribute('href')) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            self::stripDisallowedAttributes($child);
        }
    }
}
