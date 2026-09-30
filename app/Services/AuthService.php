<?php

declare(strict_types=1);

namespace App\Services;

class AuthService
{
    /**
     * Attempt to authenticate a user by their display ID and password.
     *
     * @param  string $userId   Display ID (e.g. ADM20260001 or STD202600001)
     * @param  string $password Plain-text password
     * @return array{success: bool, user?: array, message?: string}
     */
    public function login(string $userId, string $password): array
    {
        // Try the users table first (admin, coordinator, staff, teacher)
        $userModel = new \User();
        $user = $userModel->authenticate($userId, $password);

        if ($user) {
            // Check if account is deactivated
            $deactivationInfo = $this->checkDeactivated($user);
            if ($deactivationInfo !== null) {
                return [
                    'success' => false,
                    'message' => $deactivationInfo,
                ];
            }

            return [
                'success' => true,
                'user'    => $user,
            ];
        }

        // Could extend here to check student/parent login tables if needed
        // For now, only the users table is checked

        return [
            'success' => false,
            'message' => 'Invalid User ID or password. Please try again.',
        ];
    }

    /**
     * Destroy the current session (logout).
     */
    public function logout(): void
    {
        session_destroy_all();

        // Restart a clean session for flash messages
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get the currently authenticated user from the session.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentUser(): ?array
    {
        if (!session_has('user_id')) {
            return null;
        }

        return [
            'id'           => session_get('user_id'),
            'name'         => session_get('user_name'),
            'email'        => session_get('user_email'),
            'role'         => session_get('user_role'),
            'status'       => session_get('user_status'),
            'institute_id' => session_get('user_institute_id'),
            'display_id'   => session_get('user_display_id'),
        ];
    }

    /**
     * Check whether the user is logging in for the first time
     * and needs to change their password.
     */
    public function isFirstLogin(array $user): bool
    {
        // Check the first_login column if it exists
        if (isset($user['first_login'])) {
            return (bool) $user['first_login'];
        }

        return false;
    }

    /**
     * Check if a user's account is deactivated.
     *
     * @return string|null  Error message if deactivated, null if active.
     */
    public function checkDeactivated(array $user): ?string
    {
        $status = $user['status'] ?? 'active';

        if ($status === 'deactivated') {
            return 'Your account has been deactivated. Please contact the administration for assistance.';
        }

        if ($status === 'suspended') {
            return 'Your account has been suspended. Please contact the administration.';
        }

        return null;
    }
}
