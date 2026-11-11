<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Standalone promotional banners for the public home page (migration
 * 023) -- an enrolment call, a scholarship notice, a limited-time
 * offer -- anything that is not a specific class or seminar. Managed
 * by the super admin only, since it is site-wide, not branch-scoped.
 */
final class SiteAdService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /** Every ad, newest first, for the Settings > Website Ads list. */
    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM site_ads ORDER BY sort_order ASC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM site_ads WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Ads live on the public site right now: active, and within their date window if one is set. */
    public function active(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM site_ads
              WHERE is_active = 1
                AND (starts_on IS NULL OR starts_on <= CURDATE())
                AND (ends_on IS NULL OR ends_on >= CURDATE())
           ORDER BY sort_order ASC, id DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @throws \InvalidArgumentException on bad input */
    public function create(array $in, int $userId): int
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A title is required.');
        }
        $linkUrl = self::validLink((string) ($in['link_url'] ?? ''));
        [$startsOn, $endsOn] = self::validDates($in['starts_on'] ?? null, $in['ends_on'] ?? null);

        $this->db->prepare(
            'INSERT INTO site_ads (title, link_url, starts_on, ends_on, is_active, sort_order, created_by, created_at, updated_at)
             VALUES (:title, :link, :starts, :ends, :active, :sort, :by, NOW(), NOW())'
        )->execute([
            ':title'  => mb_substr($title, 0, 160),
            ':link'   => $linkUrl,
            ':starts' => $startsOn,
            ':ends'   => $endsOn,
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':by'     => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @throws \InvalidArgumentException on bad input or an unknown id */
    public function update(int $id, array $in, int $userId): void
    {
        if ($this->find($id) === null) {
            throw new \InvalidArgumentException('That ad no longer exists.');
        }
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A title is required.');
        }
        $linkUrl = self::validLink((string) ($in['link_url'] ?? ''));
        [$startsOn, $endsOn] = self::validDates($in['starts_on'] ?? null, $in['ends_on'] ?? null);

        $this->db->prepare(
            'UPDATE site_ads SET title = :title, link_url = :link, starts_on = :starts, ends_on = :ends,
                    is_active = :active, sort_order = :sort, updated_at = NOW()
              WHERE id = :id'
        )->execute([
            ':title'  => mb_substr($title, 0, 160),
            ':link'   => $linkUrl,
            ':starts' => $startsOn,
            ':ends'   => $endsOn,
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':id'     => $id,
        ]);
    }

    public function toggle(int $id): void
    {
        $this->db->prepare('UPDATE site_ads SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $ad = $this->find($id);
        if ($ad === null) {
            return;
        }
        $this->db->prepare('DELETE FROM site_ads WHERE id = :id')->execute([':id' => $id]);
        if (!empty($ad['image_path']) && str_starts_with((string) $ad['image_path'], '/uploads/site-ads/')) {
            $path = BASE_PATH . '/public' . $ad['image_path'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on an unknown id, bad file type/size, or a failed move
     */
    public function setImage(int $id, array $file): void
    {
        $ad = $this->find($id);
        if ($ad === null) {
            throw new \InvalidArgumentException('That ad no longer exists.');
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $file['type'] ?? '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected !== false) {
                $mime = $detected;
            }
        }
        if (!isset($allowed[$mime])) {
            throw new \InvalidArgumentException('Photo must be a JPG, PNG or WEBP image.');
        }
        if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new \InvalidArgumentException('Photo must be 3MB or smaller.');
        }

        $targetDir = BASE_PATH . '/public/uploads/site-ads';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = 'ad_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded photo.');
        }

        $oldPath = (string) ($ad['image_path'] ?? '');
        $this->db->prepare('UPDATE site_ads SET image_path = :p, updated_at = NOW() WHERE id = :id')
            ->execute([':p' => '/uploads/site-ads/' . $filename, ':id' => $id]);

        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/site-ads/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /** Only an http(s) link, or empty to mean "no link" (the banner is informational only). */
    private static function validLink(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('The link must be a full http:// or https:// address.');
        }
        return mb_substr($url, 0, 500);
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function validDates($starts, $ends): array
    {
        $norm = static function ($v): ?string {
            $v = trim((string) ($v ?? ''));
            if ($v === '') {
                return null;
            }
            $d = \DateTime::createFromFormat('Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                throw new \InvalidArgumentException('Dates must be in YYYY-MM-DD form.');
            }
            return $v;
        };
        $startsOn = $norm($starts);
        $endsOn = $norm($ends);
        if ($startsOn !== null && $endsOn !== null && $startsOn > $endsOn) {
            throw new \InvalidArgumentException('The start date must be on or before the end date.');
        }
        return [$startsOn, $endsOn];
    }
}
