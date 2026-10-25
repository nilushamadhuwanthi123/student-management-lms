<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Files attached to homework hand-ins and help-desk messages.
 *
 * Same rules as class material: the first bytes of the file decide what
 * it is (not its name or the browser's claim), the stored name is random,
 * and files live under storage/ -- outside the web root -- so they are
 * only ever reached through a controller that has checked who is asking.
 */
class AttachmentService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** extension => [mime, magic bytes] */
    private const TYPES = [
        'pdf' => ['application/pdf', "%PDF"],
        'png' => ['image/png', "\x89PNG"],
        'jpg' => ['image/jpeg', "\xFF\xD8\xFF"],
    ];

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? BASE_PATH . '/storage/uploads/attachments';
    }

    /**
     * Store an uploaded file under a sub-folder ("assignments", "help").
     *
     * @param array<string, mixed>|null $file  one entry of $_FILES
     * @return array{0: string, 1: string}|null  [relative path, display name], or null when no file was sent
     * @throws \RuntimeException with a message safe to show the user
     */
    public function store(?array $file, string $folder, int $maxBytes = self::MAX_BYTES): ?array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new \RuntimeException('That file did not upload cleanly. Try again.');
        }
        $size = (int) $file['size'];
        if ($size <= 0) {
            throw new \RuntimeException('That file is empty.');
        }
        if ($size > $maxBytes) {
            throw new \RuntimeException('Files are capped at ' . (int) round($maxBytes / 1048576) . ' MB.');
        }

        $head = (string) file_get_contents((string) $file['tmp_name'], false, null, 0, 8);
        $ext = null;
        foreach (self::TYPES as $e => [, $magic]) {
            if (str_starts_with($head, $magic)) {
                $ext = $e;
                break;
            }
        }
        if ($ext === null) {
            throw new \RuntimeException('Only PDF, PNG and JPG files can be attached.');
        }

        $folder = preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?: 'general';
        $dir = $this->root . '/' . $folder . '/' . date('Y-m');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $stored)) {
            throw new \RuntimeException('That file could not be saved.');
        }

        $name = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '_', basename((string) ($file['name'] ?? 'file'))) ?? 'file';
        $name = mb_substr(trim($name) !== '' ? $name : 'file', 0, 120);

        return [$folder . '/' . date('Y-m') . '/' . $stored, $name];
    }

    /**
     * Send a stored file. The caller must already have checked access.
     */
    public function send(string $relativePath, string $displayName): never
    {
        $base = realpath($this->root);
        $full = $base !== false ? realpath($base . '/' . $relativePath) : false;

        if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Not found.');
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = self::TYPES[$ext][0] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: attachment; filename="' . rawurlencode($displayName) . '"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($full);
        exit;
    }
}
