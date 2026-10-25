<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\FileUploadService;

/**
 * FileController — serves uploaded files through an auth-gated route
 * so that storage/uploads/ never needs to be web-accessible.
 */
class FileController extends BaseController
{
    /**
     * Serve a stored file.
     *
     * GET /files/{path}  where path = subdir/filename
     */
    public function serve(string $subDir, string $filename): void
    {
        AuthMiddleware::requireAuth();

        // Sanitise inputs to prevent traversal
        $subDir   = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir);
        $filename = basename($filename); // strip any path components

        $uploadService = new FileUploadService();
        $fullPath      = $uploadService->getFullPath($subDir . '/' . $filename);

        if ($fullPath === null) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        // Determine MIME type
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath);

        // Only allow image MIME types to be served
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            http_response_code(403);
            echo 'File type not permitted.';
            exit;
        }

        // Set caching and security headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        readfile($fullPath);
        exit;
    }

    /**
     * Handle profile photo upload for a student.
     *
     * POST /students/{id}/photo
     */
    public function uploadStudentPhoto(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([
            AuthMiddleware::ROLE_SUPER_ADMIN,
            AuthMiddleware::ROLE_ADMIN,
            AuthMiddleware::ROLE_COORDINATOR,
            AuthMiddleware::ROLE_STAFF,
        ]);
        verifyCsrfToken();

        $studentModel = new \Student();
        $student      = $studentModel->find((int) $id);

        if (!$student) {
            $this->flash('error', 'Student not found.');
            $this->redirect(BASE_URL . '/students');
        }

        if (!\App\Services\BranchScope::current()->allowsStudent((int) $id)) {
            http_response_code(403);
            $this->flash('error', 'That student is not in your branch.');
            $this->redirect(BASE_URL . '/students');
        }

        $uploadService = new FileUploadService();
        $result        = $uploadService->upload('profile_photo', 'profiles');

        if (!$result['success']) {
            $this->flash('error', $result['error']);
            $this->redirect(BASE_URL . '/students/' . $id);
        }

        // Delete the old photo if it exists
        $oldPhoto = $student['profile_photo'] ?? '';
        if ($oldPhoto !== '' && $oldPhoto !== null) {
            $uploadService->delete($oldPhoto);
        }

        // Update the student record
        try {
            $studentModel->update((int) $id, [
                'profile_photo' => $result['path'],
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->flash('success', 'Profile photo updated successfully.');
        } catch (\Throwable $e) {
            error_log('Photo upload DB update failed: ' . $e->getMessage());
            // Clean up the uploaded file since DB failed
            $uploadService->delete($result['path']);
            $this->flash('error', 'Failed to save photo. Please try again.');
        }

        $this->redirect(BASE_URL . '/students/' . $id);
    }
}
