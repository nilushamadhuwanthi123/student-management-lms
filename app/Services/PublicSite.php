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
                        "SELECT i.id, i.name, i.location, i.phone1, i.phone2,
                                (SELECT COUNT(*) FROM classes c WHERE c.institute_id = i.id AND c.status = 'active') AS class_count
                           FROM institutes i WHERE i.status = 'active' AND i.is_public = 1 ORDER BY i.id"
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
