<?php
/**
 * Institute Model
 */

require_once __DIR__ . '/BaseModel.php';

class Institute extends BaseModel
{
    protected string $table = 'institutes';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'coordinator_id', 'location', 'phone1', 'phone2',
        'notes', 'status', 'created_at', 'updated_at',
        'is_public', 'public_show_fees', 'public_description', 'video_url',
        'sort_order', 'banner_size',
    ];

    /**
     * The address to put in a video link for a branch: a plain http(s)
     * URL (YouTube, Facebook, or any playable link). Anything else --
     * blank, or a value that doesn't look like a link -- gives '' so no
     * video button is shown. Mirrors SeminarService::coverSrc()'s shape.
     */
    public static function videoHref(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        // An uploaded video file's own stored path (see setVideo()).
        if (str_starts_with($value, '/uploads/institutes/videos/')) {
            return $value;
        }
        return '';
    }

    /**
     * Upload a video file for a branch (shown as the "Watch video" button
     * on the public branches/classes/about pages, same as a pasted link).
     * Replaces any previously uploaded video file for this branch; a link
     * typed into the video URL field always wins over an uploaded file if
     * both are submitted (see InstituteController).
     *
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on an unknown id, bad file type/size, or a failed move
     */
    public function setVideo(int $id, array $file): void
    {
        $inst = $this->find($id);
        if ($inst === null) {
            throw new \InvalidArgumentException('That branch no longer exists.');
        }

        $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'];
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
            throw new \InvalidArgumentException('Video must be an MP4, WEBM, MOV or OGG file.');
        }
        if ((int) ($file['size'] ?? 0) > 35 * 1024 * 1024) {
            throw new \InvalidArgumentException('Video must be 35MB or smaller -- paste a YouTube/Facebook link instead for a longer video.');
        }

        $targetDir = BASE_PATH . '/public/uploads/institutes/videos';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = 'inst_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded video.');
        }

        $oldPath = (string) ($inst['video_url'] ?? '');
        $this->db->prepare('UPDATE institutes SET video_url = :v, updated_at = NOW() WHERE id = :id')
            ->execute([':v' => '/uploads/institutes/videos/' . $filename, ':id' => $id]);

        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/institutes/videos/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /**
     * @param array{tmp_name: string, name: string, type: string, size: int} $file
     * @throws \InvalidArgumentException on an unknown id, bad file type/size, or a failed move
     */
    public function setPhoto(int $id, array $file): void
    {
        $inst = $this->find($id);
        if ($inst === null) {
            throw new \InvalidArgumentException('That branch no longer exists.');
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

        $targetDir = BASE_PATH . '/public/uploads/institutes';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = 'inst_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \InvalidArgumentException('Could not save the uploaded photo.');
        }

        $oldPath = (string) ($inst['photo'] ?? '');
        $this->db->prepare('UPDATE institutes SET photo = :p, updated_at = NOW() WHERE id = :id')
            ->execute([':p' => '/uploads/institutes/' . $filename, ':id' => $id]);

        if ($oldPath !== '' && str_starts_with($oldPath, '/uploads/institutes/')) {
            $old = BASE_PATH . '/public' . $oldPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /**
     * Get all institutes with coordinator information and class counts.
     */
    public function getAllWithCoordinator(string $search = ''): array
    {
        $params = [];
        $where = '';

        if ($search !== '') {
            // One placeholder per use -- see ClassModel::getGridList.
            $where = "WHERE i.name LIKE :search1 OR i.location LIKE :search2"
                . " OR u.full_name LIKE :search3";
            $needle = '%' . trim($search) . '%';
            foreach (['search1', 'search2', 'search3'] as $ph) {
                $params[':' . $ph] = $needle;
            }
        }

        $sql = "SELECT 
                    i.*,
                    u.full_name AS coordinator_name,
                    u.user_id_display AS coordinator_display_id,
                    u.email AS coordinator_email,
                    u.phone1 AS coordinator_phone,
                    (SELECT COUNT(*) FROM classes c WHERE c.institute_id = i.id) AS class_count,
                    (SELECT COUNT(DISTINCT e.student_id) 
                     FROM classes c 
                     JOIN enrollments e ON c.id = e.class_id 
                     WHERE c.institute_id = i.id) AS student_count
                FROM {$this->table} i
                LEFT JOIN users u ON i.coordinator_id = u.id
                {$where}
                ORDER BY i.sort_order ASC, i.name ASC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get single institute with coordinator details.
     */
    public function getWithCoordinator(int $id): ?array
    {
        $sql = "SELECT 
                    i.*,
                    u.full_name AS coordinator_name,
                    u.user_id_display AS coordinator_display_id,
                    u.email AS coordinator_email,
                    u.phone1 AS coordinator_phone
                FROM {$this->table} i
                LEFT JOIN users u ON i.coordinator_id = u.id
                WHERE i.id = :id
                LIMIT 1";
        $row = $this->query($sql, [':id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Get active institutes for DDL / dropdown selection.
     */
    public function getActiveInstitutes(): array
    {
        return $this->where(['status' => 'active'], 'name ASC');
    }

    /**
     * Get all classes belonging to an institute.
     */
    public function getClasses(int $instituteId): array
    {
        $sql = "SELECT * FROM classes WHERE institute_id = :iid ORDER BY class_name ASC";
        return $this->query($sql, [':iid' => $instituteId])->fetchAll();
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleStatus(int $id): string
    {
        $inst = $this->find($id);
        if (!$inst) {
            return 'inactive';
        }

        $newStatus = ($inst['status'] === 'active') ? 'inactive' : 'active';
        $sql = "UPDATE {$this->table} SET status = :st, updated_at = NOW() WHERE id = :id";
        $this->query($sql, [':st' => $newStatus, ':id' => $id]);
        return $newStatus;
    }
}
