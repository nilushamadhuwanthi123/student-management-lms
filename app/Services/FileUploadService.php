<?php

declare(strict_types=1);

namespace App\Services;

/**
 * FileUploadService — secure file upload handling.
 *
 * - Validates MIME type against an allowlist (not just extension)
 * - Renames files to random tokens to prevent directory traversal & enumeration
 * - Stores uploads OUTSIDE the web root in storage/uploads/
 * - Provides a serving route through a controller (never direct file access)
 */
class FileUploadService
{
    /** Where uploaded files live (outside web root). */
    private string $uploadDir;

    /** Maximum file size in bytes (default 5 MB). */
    private int $maxSize;

    /** Allowed MIME types mapped to safe extensions. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(?string $uploadDir = null, int $maxSizeMb = 5)
    {
        $this->uploadDir = $uploadDir ?? (defined('BASE_PATH') ? BASE_PATH . '/storage/uploads' : __DIR__ . '/../../storage/uploads');
        $this->maxSize   = $maxSizeMb * 1024 * 1024;

        // Ensure the directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0750, true);
        }
    }

    /**
     * Handle a file upload from $_FILES.
     *
     * @param  string $fieldName  The form field name (e.g. 'profile_photo')
     * @param  string $subDir     Subdirectory within uploads/ (e.g. 'profiles', 'documents')
     * @return array{success: bool, filename?: string, path?: string, error?: string}
     */
    public function upload(string $fieldName, string $subDir = 'general'): array
    {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'No file uploaded.'];
        }

        $file = $_FILES[$fieldName];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->uploadErrorMessage($file['error'])];
        }

        // Validate file size
        if ($file['size'] > $this->maxSize) {
            $maxMb = $this->maxSize / (1024 * 1024);
            return ['success' => false, 'error' => "File exceeds maximum size of {$maxMb} MB."];
        }

        // Validate MIME type using finfo (not the user-provided type)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED_TYPES[$mimeType])) {
            return ['success' => false, 'error' => 'File type not allowed. Accepted: JPEG, PNG, GIF, WebP.'];
        }

        $extension = self::ALLOWED_TYPES[$mimeType];

        // Sanitise subdirectory name — alphanumeric + hyphens only
        $subDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir);

        $targetDir = $this->uploadDir . '/' . $subDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        // Generate a random, non-guessable filename
        $randomName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $randomName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        // Restrict file permissions
        chmod($targetPath, 0640);

        return [
            'success'  => true,
            'filename' => $randomName,
            'path'     => $subDir . '/' . $randomName,
        ];
    }

    /**
     * Delete a previously uploaded file.
     *
     * @param  string $relativePath  e.g. "profiles/abc123.jpg"
     * @return bool
     */
    public function delete(string $relativePath): bool
    {
        // Prevent directory traversal
        $relativePath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath     = $this->uploadDir . '/' . $relativePath;

        if (file_exists($fullPath) && str_starts_with(realpath($fullPath), realpath($this->uploadDir))) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Get the full filesystem path for a stored file.
     *
     * @param  string $relativePath  e.g. "profiles/abc123.jpg"
     * @return string|null  Full path if file exists, null otherwise
     */
    public function getFullPath(string $relativePath): ?string
    {
        $relativePath = str_replace(['..', "\0"], '', $relativePath);
        $fullPath     = $this->uploadDir . '/' . $relativePath;

        if (file_exists($fullPath) && str_starts_with(realpath($fullPath), realpath($this->uploadDir))) {
            return $fullPath;
        }

        return null;
    }

    /**
     * Translate PHP upload error codes to human-readable messages.
     */
    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server\'s maximum upload size.',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form\'s maximum upload size.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error.',
        };
    }
}
