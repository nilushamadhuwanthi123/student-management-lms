<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Facts for the public website, read from the database only: the branches
 * the super admin has published, their active classes and the teacher who
 * teaches them. Nothing here is invented (no student counts, no results).
 */
final class PublicSite
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    private static function db(): ?PDO
    {
        try {
            return \Database::connect();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function branches(): array
    {
        if (!array_key_exists('branches', self::$cache)) {
            self::$cache['branches'] = [];
            $db = self::db();
            if ($db) {
                try {
                    self::$cache['branches'] = $db->query(
                        "SELECT i.id, i.name, i.location, i.map_url, i.phone1, i.phone2,
                                i.photo, i.public_description, i.video_url,
                                (SELECT COUNT(*) FROM classes c WHERE c.institute_id = i.id AND c.status = 'active') AS class_count
                           FROM institutes i WHERE i.status = 'active' AND i.is_public = 1 ORDER BY i.sort_order ASC, i.id ASC"
                    )->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    error_log('Public branches unavailable: ' . $e->getMessage());
                }
            }
        }
        return self::$cache['branches'];
    }

    /** The teacher of most public classes, or null. Name shown in the visitor's language when a translation exists. */
    public static function teacherName(): ?string
    {
        if (!array_key_exists('teacher', self::$cache)) {
            self::$cache['teacher'] = null;
            $db = self::db();
            if ($db) {
                try {
                    $name = $db->query(
                        "SELECT u.full_name FROM classes c
                           JOIN institutes i ON i.id = c.institute_id AND i.is_public = 1 AND i.status = 'active'
                           JOIN users u ON u.id = c.teacher_id
                          WHERE c.status = 'active'
                       GROUP BY u.id ORDER BY COUNT(*) DESC LIMIT 1"
                    )->fetchColumn();
                    self::$cache['teacher'] = $name !== false ? (string) $name : null;
                } catch (\Throwable $e) {
                    error_log('Public teacher unavailable: ' . $e->getMessage());
                }
            }
        }
        return self::$cache['teacher'] !== null ? t((string) self::$cache['teacher']) : null;
    }

    /**
     * The map to draw for a branch, as an embeddable Google Maps URL.
     *
     * A stored map_url is used only when it really is a Google Maps
     * address, since this value ends up in an iframe src -- anything
     * else falls back to searching for the branch name and town, which
     * is as precise as the address on file anyway.
     */
    public static function mapEmbedUrl(array $branch): string
    {
        $stored = trim((string) ($branch['map_url'] ?? ''));
        if ($stored !== '' && self::isGoogleMapsUrl($stored)) {
            return str_contains($stored, 'output=embed')
                ? $stored
                : $stored . (str_contains($stored, '?') ? '&' : '?') . 'output=embed';
        }

        $name = trim((string) ($branch['name'] ?? ''));
        $town = trim((string) ($branch['location'] ?? ''));

        // Branch names here already carry the town ("Sepala - Pannipitiya"),
        // so repeating it would only blur the search.
        $query = ($town !== '' && stripos($name, $town) === false)
            ? trim($name . ' ' . $town)
            : ($name !== '' ? $name : $town);

        return 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
    }

    /**
     * An http(s) address safe to put in a link, or null.
     *
     * Everything else -- javascript:, data:, a bare word somebody typed
     * into the settings box -- comes back null so the caller leaves the
     * link out rather than rendering something unclickable or unsafe.
     */
    public static function externalUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /** True only for an https Google Maps address, host checked exactly. */
    public static function isGoogleMapsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        return $host === 'maps.app.goo.gl'
            || $host === 'goo.gl'
            || $host === 'maps.google.com'
            || $host === 'www.google.com'
            || preg_match('/^(www\.)?google\.[a-z.]{2,6}$/', $host) === 1;
    }

    /**
     * @return array{branches: int, classes: int, grades: list<int>, media: list<string>, online: bool}
     */
    public static function facts(): array
    {
        if (!array_key_exists('facts', self::$cache)) {
            $facts = ['branches' => count(self::branches()), 'classes' => 0, 'grades' => [], 'media' => [], 'online' => false];
            $db = self::db();
            if ($db) {
                try {
                    $rows = $db->query(
                        "SELECT c.grade, c.medium, c.delivery_mode FROM classes c
                           JOIN institutes i ON i.id = c.institute_id AND i.is_public = 1 AND i.status = 'active'
                          WHERE c.status = 'active'"
                    )->fetchAll(PDO::FETCH_ASSOC);
                    $facts['classes'] = count($rows);
                    foreach ($rows as $r) {
                        if (preg_match('/(\d{1,2})/', (string) $r['grade'], $m)) {
                            $facts['grades'][(int) $m[1]] = (int) $m[1];
                        }
                        if (trim((string) $r['medium']) !== '') {
                            $facts['media'][strtolower(trim((string) $r['medium']))] = ucfirst(strtolower(trim((string) $r['medium'])));
                        }
                        $facts['online'] = $facts['online'] || ($r['delivery_mode'] ?? 'physical') !== 'physical';
                    }
                    ksort($facts['grades']);
                    $facts['grades'] = array_values($facts['grades']);
                    $facts['media'] = array_values($facts['media']);
                } catch (\Throwable $e) {
                    error_log('Public facts unavailable: ' . $e->getMessage());
                }
            }
            self::$cache['facts'] = $facts;
        }
        return self::$cache['facts'];
    }

    /** "6 - 11" style label from the grades actually taught. */
    public static function gradeRange(): string
    {
        $g = self::facts()['grades'];
        if ($g === []) {
            return '6 - 11';
        }
        return $g[0] === end($g) ? (string) $g[0] : $g[0] . ' - ' . end($g);
    }

    public static function telHref(?string $phone): string
    {
        return 'tel:' . preg_replace('/[^\d+]/', '', (string) $phone);
    }

    /**
     * Seminars/events the staff chose to advertise on the website: published,
     * ticked "Show on the website home page", today or later, and for a
     * public branch (or for every branch). Only public details are read --
     * the online meeting link is never selected here.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcomingSeminars(int $limit = 6): array
    {
        if (!array_key_exists('seminars', self::$cache)) {
            self::$cache['seminars'] = [];
            $db = self::db();
            if ($db) {
                try {
                    $today = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
                    $stmt = $db->prepare(
                        "SELECT s.id, s.title, s.description, s.cover_image_url, s.event_date, s.start_time, s.end_time,
                                s.venue, s.target_scope, s.grade, s.registration_required,
                                COALESCE(i1.name, i2.name) AS branch_name, c.grade AS class_grade
                           FROM seminars s
                      LEFT JOIN institutes i1 ON i1.id = s.institute_id
                      LEFT JOIN classes c ON c.id = s.class_id
                      LEFT JOIN institutes i2 ON i2.id = c.institute_id
                          WHERE s.status = 'published' AND s.show_on_website = 1 AND s.event_date >= :today
                            AND (s.target_scope = 'all'
                                 OR (s.target_scope = 'grade' AND s.institute_id IS NULL)
                                 OR (s.target_scope IN ('branch', 'grade') AND i1.is_public = 1 AND i1.status = 'active')
                                 OR (s.target_scope = 'class' AND c.status = 'active' AND i2.is_public = 1 AND i2.status = 'active'))
                       ORDER BY s.event_date ASC, s.start_time IS NULL, s.start_time ASC
                          LIMIT " . max(1, min(60, $limit))
                    );
                    $stmt->execute([':today' => $today]);
                    self::$cache['seminars'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    // Before migration 021 the column does not exist: show no events, never an error.
                    error_log('Public seminars unavailable: ' . $e->getMessage());
                }
            }
        }
        return self::$cache['seminars'];
    }

    /**
     * One combined, rotation-ready list for the home page's hero
     * banner: the super admin's standalone announcements (migration
     * 023) and the next few published, publicly-advertised seminars,
     * each reduced to the same shape so the template does not need to
     * know which kind of item it is drawing.
     *
     * @return list<array{title: string, subtitle: string, thumb: string, href: string}>
     */
    public static function homeAds(int $limit = 6): array
    {
        $items = [];

        try {
            $ads = (new \App\Services\SiteAdService())->active($limit);
        } catch (\Throwable $e) {
            $ads = [];
        }
        foreach ($ads as $ad) {
            $thumb = '';
            if (!empty($ad['image_path']) && preg_match('#^/uploads/site-ads/[A-Za-z0-9_]+\.(jpg|png|webp)$#', (string) $ad['image_path'])) {
                $thumb = BASE_URL . $ad['image_path'];
            }
            $items[] = [
                'title'    => (string) $ad['title'],
                'subtitle' => '',
                'thumb'    => $thumb,
                'href'     => !empty($ad['link_url']) ? (string) $ad['link_url'] : '#ts-ad',
            ];
        }

        foreach (self::upcomingSeminars($limit) as $sem) {
            if (count($items) >= $limit) {
                break;
            }
            $items[] = [
                'title'    => (string) $sem['title'],
                'subtitle' => lang_date('j M', (string) $sem['event_date']),
                'thumb'    => \App\Services\SeminarService::coverSrc($sem['cover_image_url'] ?? null),
                'href'     => '#events',
            ];
        }

        return array_slice($items, 0, max(1, min(12, $limit)));
    }

    /**
     * Ads marked to show as a sitewide popup (see SiteAdService::popups()),
     * reduced to the same shape as a poster: a title, an image if one was
     * uploaded, and a link if one was given. Shown once per browser
     * session on every public page (see partials/site-popup.php).
     *
     * @return array<int, array{title: string, image: string, href: ?string}>
     */
    public static function popupAds(int $limit = 6): array
    {
        $out = [];
        try {
            foreach ((new \App\Services\SiteAdService())->popups($limit) as $ad) {
                $image = '';
                if (!empty($ad['image_path']) && preg_match('#^/uploads/site-ads/[A-Za-z0-9_]+\.(jpg|png|webp)$#', (string) $ad['image_path'])) {
                    $image = BASE_URL . $ad['image_path'];
                }
                $link = trim((string) ($ad['link_url'] ?? ''));
                $out[] = [
                    'title' => (string) $ad['title'],
                    'image' => $image,
                    'href'  => preg_match('#^https?://#i', $link) === 1 ? $link : null,
                ];
            }
        } catch (\Throwable $e) {
            $out = [];
        }
        return $out;
    }

    /**
     * The name the portal shows in its header, tab titles and ID cards:
     * the "Site name" the super admin saved under Settings, otherwise the
     * teacher's name, otherwise a plain description. "EduManager" -- the
     * name of the template this system started from -- counts as unset,
     * so an install that never saved a name shows the teacher instead.
     */
    public static function brandName(): string
    {
        if (!array_key_exists('brand', self::$cache)) {
            $name = '';
            $db = self::db();
            if ($db) {
                try {
                    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name' LIMIT 1");
                    $stmt->execute();
                    $name = trim((string) ($stmt->fetchColumn() ?: ''));
                } catch (\Throwable $e) {
                    error_log('Site name unavailable: ' . $e->getMessage());
                }
            }
            if ($name === '' || strcasecmp($name, 'EduManager') === 0) {
                $name = self::teacherName() ?? t('Science classes');
            }
            self::$cache['brand'] = $name;
        }
        return (string) self::$cache['brand'];
    }

    /**
     * Ads the office has given a poster to, for showing full size.
     *
     * homeAds() above folds ads and seminars into the one-line ticker in
     * the hero, where a poster is reduced to a thumbnail the size of a
     * stamp. A seminar poster is designed to be read, so this returns
     * only the ads that actually carry an image and leaves the wording
     * to the poster itself.
     *
     * The path is checked against the shape setImage() writes, so a
     * value edited to point anywhere else is dropped rather than
     * rendered.
     *
     * @return array<int, array{title: string, image: string, href: ?string}>
     */
    public static function posterAds(int $limit = 4): array
    {
        if (!array_key_exists('posters', self::$cache)) {
            $out = [];
            try {
                foreach ((new \App\Services\SiteAdService())->active(12) as $ad) {
                    $path = (string) ($ad['image_path'] ?? '');
                    if ($path === '' || !preg_match('#^/uploads/site-ads/[A-Za-z0-9_]+\.(jpg|png|webp)$#', $path)) {
                        continue;
                    }
                    $link = trim((string) ($ad['link_url'] ?? ''));
                    $out[] = [
                        'title' => (string) $ad['title'],
                        'image' => BASE_URL . $path,
                        'href'  => preg_match('#^https?://#i', $link) === 1 ? $link : null,
                    ];
                }
            } catch (\Throwable $e) {
                $out = [];
            }
            self::$cache['posters'] = $out;
        }

        return array_slice(self::$cache['posters'], 0, max(1, $limit));
    }

    /**
     * One of the small, fixed set of editable text blocks (see
     * SiteContentService) -- the only free text anywhere on this page,
     * everything else above stays strictly from real records.
     */
    public static function content(string $key, array $params = []): string
    {
        $cacheKey = 'content:' . $key . ':' . md5(serialize($params));
        if (!array_key_exists($cacheKey, self::$cache)) {
            try {
                self::$cache[$cacheKey] = (new SiteContentService())->get($key, $params);
            } catch (\Throwable $e) {
                self::$cache[$cacheKey] = SiteContentService::FIELDS[$key]['default'] ?? '';
            }
        }
        return self::$cache[$cacheKey];
    }
}
