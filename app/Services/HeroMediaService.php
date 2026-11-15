<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * CMS-managed photos and short videos for the home page hero background
 * slideshow (migration 035). Replaces what used to be four fixed files
 * in public/assets/img -- the super admin can now add, reorder, swap
 * and remove slides from Settings > Homepage Background.
 *
 * Deliberately a flat, ordered list, same shape as SiteAdService: each
 * row is one slide, either an uploaded photo or a short uploaded video,
 * shown in sort_order. If no active row exists, the public page falls
 * back to the original four photos, so an untouched install looks
 * exactly like it did before this existed.
 */
final class HeroMediaService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /** Every slide, in display order, for the Settings > Homepage Background list. */
    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM hero_media ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hero_media WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Slides live on the public home page right now, in display order. */
    public function active(): array
    {
        return $this->db->query(
            'SELECT * FROM hero_media WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upload a new slide (photo or video) and add it to the list.
     *
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on a bad file type/size or a failed move
     */
    public function create(array $file, array $in, int $userId): int
    {
        [$mediaType, $filename] = $this->storeFile($file);

        $this->db->prepare(
            'INSERT INTO hero_media (media_type, file_path, object_position, is_active, sort_order, created_by, created_at, updated_at)
             VALUES (:type, :path, :pos, :active, :sort, :by, NOW(), NOW())'
        )->execute([
            ':type'   => $mediaType,
            ':path'   => '/uploads/hero-media/' . $filename,
            ':pos'    => self::validPosition((string) ($in['object_position'] ?? '')),
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':by'     => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a slide's placement/visibility, and optionally replace its file.
     *
     * @param array{tmp_name: string, name: string, type: string, size: int}|null $file
     * @throws \InvalidArgumentException on an unknown id or a bad file type/size
     */
    public function update(int $id, array $in, ?array $file = null): void
    {
        $slide = $this->find($id);
        if ($slide === null) {
            throw new \InvalidArgumentException('That slide no longer exists.');
        }

        $mediaType = $slide['media_type'];
        $filePath = $slide['file_path'];
        $oldPath = null;

        if ($file !== null) {
            [$mediaType, $filename] = $this->storeFile($file);
            $oldPath = $filePath;
            $filePath = '/uploads/hero-media/' . $filename;
        }

        $this->db->prepare(
            'UPDATE hero_media SET media_type = :type, file_path = :path, object_position = :pos,
                    is_active = :active, sort_order = :sort, updated_at = NOW()
              WHERE id = :id'
        )->execute([
            ':type'   => $mediaType,
            ':path'   => $filePath,
            ':pos'    => self::validPosition((string) ($in['object_position'] ?? '')),
            ':active' => !empty($in['is_active']) ? 1 : 0,
            ':sort'   => (int) ($in['sort_order'] ?? 0),
            ':id'     => $id,
        ]);

        if ($oldPath !== null && str_starts_with($oldPath, '/uploads/hero-media/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    public function toggle(int $id): void
    {
        $this->db->prepare('UPDATE hero_media SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $slide = $this->find($id);
        if ($slide === null) {
            return;
        }
        $this->db->prepare('DELETE FROM hero_media WHERE id = :id')->execute([':id' => $id]);
        if (str_starts_with((string) $slide['file_path'], '/uploads/hero-media/')) {
            $path = BASE_PATH . '/public' . $slide['file_path'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Validate and save an uploaded photo or short video.
     *
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @return array{0: string, 1: string} [media_type ('image'|'video'), stored filename]
     * @throws \InvalidArgumentException on a bad file type/size or a failed move
     */
    private function storeFile(array $file): array
    {
        $imageExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $videoExt = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'];

        $mime = $file['type'] ?? '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected !== false) {
                $mime = $detected;
            }
        }

        if (isset($imageExt[$mime])) {
            $mediaType = 'image';
            $ext = $imageExt[$mime];
            if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new \InvalidArgumentException('Photo must be 5MB or smaller.');
            }
        } elseif (isset($videoExt[$mime])) {
            $mediaType = 'video';
            $ext = $videoExt[$mime];
            if ((int) ($file['size'] ?? 0) > 35 * 1024 * 1024) {
                throw new \InvalidArgumentException('Video must be 35MB or smaller -- trim it or compress it first.');
            }
        } else {
            throw new \InvalidArgumentException('File must be a JPG/PNG/WEBP photo or an MP4/WEBM/MOV/OGG video.');
        }

        $targetDir = BASE_PATH . '/public/uploads/hero-media';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = 'hero_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded file.');
        }

        return [$mediaType, $filename];
    }

    /** A CSS object-position value, or the centered default if blank/invalid. */
    private static function validPosition(string $pos): string
    {
        $pos = trim($pos);
        if ($pos === '' || !preg_match('/^-?\d{1,3}%\s+-?\d{1,3}%$/', $pos)) {
            return '50% 50%';
        }
        return $pos;
    }
}
