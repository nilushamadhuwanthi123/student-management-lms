<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_STAFF = 'staff';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_STUDENT = 'student';
    public const ROLE_PARENT = 'parent';

    public const ALL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_COORDINATOR,
        self::ROLE_STAFF,
        self::ROLE_TEACHER,
        self::ROLE_STUDENT,
        self::ROLE_PARENT,
    ];

    /**
     * Require authentication. Redirects to login if not authenticated.
     */
    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            session_flash('error', 'Please log in to access this page.');
            session_set('intended_url', $_SERVER['REQUEST_URI']);
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        self::checkSessionActivity();
        self::verifyFingerprint();
        self::checkDeactivated();
        self::revalidateAccount();
        self::enforcePasswordChange();
    }

    /**
     * Re-read the account on every request, so that deactivating someone,
     * changing their role or resetting their password takes effect at once
     * instead of when their session happens to end.
     */
    public static function revalidateAccount(): void
    {
        $type = (string) session_get('user_type');
        $id = (int) session_get('user_id');
        if (!in_array($type, ['user', 'student', 'parent'], true) || $id <= 0) {
            return;
        }

        try {
            $state = (new \App\Services\AccountService())->liveState($type, $id);
        } catch (\Throwable $e) {
            // Fails open only when the 013 migration has not been applied.
            error_log('Account revalidation unavailable: ' . $e->getMessage());
            return;
        }

        $message = null;
        if ($state === null) {
            $message = 'Please log in again.';
        } elseif ($type === 'user' ? $state['status'] === 'inactive' : $state['status'] === 'deactivated') {
            $message = 'Your account has been deactivated. Please contact the administration for assistance.';
        } elseif (session_get('credential_version') !== null && (int) session_get('credential_version') !== $state['version']) {
            $message = 'Your password was changed. Please log in again.';
        } elseif ($type === 'user' && $state['role'] !== session_get('user_role')) {
            $message = 'Your access was changed. Please log in again.';
        }

        if ($message !== null) {
            session_destroy_all();
            session_start();
            session_flash('error', t($message));
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        if (session_get('credential_version') === null) {
            session_set('credential_version', $state['version']);
        }
        if ($state['must_change']) {
            session_set('first_login', true);
        }
    }

    /**
     * While a temporary password is in use, the only pages are the
     * password form itself, logout and the language switch.
     */
    public static function enforcePasswordChange(): void
    {
        if (!session_get('first_login')) {
            return;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        foreach ([BASE_URL . '/public', BASE_URL] as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }
        if ($path === '/first-login-password' || $path === '/logout' || str_starts_with($path, '/lang/')) {
            return;
        }
        header('Location: ' . BASE_URL . '/first-login-password');
        exit;
    }

    /**
     * Check if the current user is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return session_has('user_id') && session_get('user_id') !== null;
    }

    /**
     * Check session activity and expire if inactive too long.
     */
    public static function checkSessionActivity(): void
    {
        $lastActivity = session_get('last_activity');
        $timeout = (int) env('SESSION_TIMEOUT', 1800);

        if ($lastActivity !== null && (time() - (int) $lastActivity) > $timeout) {
            session_destroy_all();
            session_start();
            session_flash('error', 'Your session has expired due to inactivity. Please log in again.');
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        session_set('last_activity', time());
    }

    /**
     * Verify the session fingerprint to detect session hijacking.
     */
    public static function verifyFingerprint(): void
    {
        $storedFingerprint = session_get('session_fingerprint');

        if ($storedFingerprint !== null) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $currentFingerprint = hash('sha256', $ip . $userAgent);

            if ($storedFingerprint !== $currentFingerprint) {
                session_destroy_all();
                session_start();
                session_flash('error', 'Your session has been invalidated for security reasons.');
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
        }
    }

    /**
     * Require the user to have one of the specified roles.
     *
     * @param string|array<string> $roles
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireAuth();

        $roles = is_string($roles) ? [$roles] : $roles;
        $userRole = self::getUserRole();

        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            session_flash('error', 'You do not have permission to access this page.');
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }

    /**
     * Convenience: require super_admin role.
     */
    public static function requireSuperAdmin(): void
    {
        self::requireRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Convenience: require admin or super_admin role.
     */
    public static function requireAdmin(): void
    {
        self::requireRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * Convenience: require coordinator role or above.
     */
    public static function requireCoordinator(): void
    {
        self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COORDINATOR,
        ]);
    }

    /**
     * Convenience: require staff role or above.
     */
    public static function requireStaff(): void
    {
        self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COORDINATOR,
            self::ROLE_STAFF,
        ]);
    }

    /**
     * Convenience: require teacher role or above.
     */
    public static function requireTeacher(): void
    {
        self::requireRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_COORDINATOR,
            self::ROLE_STAFF,
            self::ROLE_TEACHER,
        ]);
    }

    /**
     * Check if the user's account is deactivated and block access.
     */
    public static function checkDeactivated(): void
    {
        $status = session_get('user_status');
        $userType = session_get('user_type');
        $role = self::getUserRole();

        if ($status === 'deactivated') {
            if (
                in_array($role, [self::ROLE_STUDENT, self::ROLE_PARENT], true)
                || in_array($userType, ['student', 'parent'], true)
            ) {
                session_destroy_all();
                session_start();
                session_flash(
                    'error',
                    'Your account has been deactivated. Please contact the administration for assistance.'
                );
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
        }
    }

    /**
     * Get the currently authenticated user data from the session.
     *
     * @return array<string, mixed>|null
     */
    public static function getCurrentUser(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id' => session_get('user_id'),
            'name' => session_get('user_name'),
            'email' => session_get('user_email'),
            'role' => session_get('user_role'),
            'status' => session_get('user_status'),
            'institute_id' => session_get('user_institute_id'),
            'user_type' => session_get('user_type'),
            'display_id' => session_get('user_display_id'),
        ];
    }

    /**
     * Get the current user's role.
     */
    public static function getUserRole(): ?string
    {
        return session_get('user_role');
    }

    /**
     * Check if the current user has a specific role.
     */
    public static function hasRole(string $role): bool
    {
        return self::getUserRole() === $role;
    }

    /**
     * Check if the current user has any of the specified roles.
     *
     * @param array<string> $roles
     */
    public static function hasAnyRole(array $roles): bool
    {
        return in_array(self::getUserRole(), $roles, true);
    }

    /**
     * Require that the user is a guest (not authenticated).
     * Redirects to dashboard if already logged in.
     */
    public static function requireGuest(): void
    {
        if (self::isAuthenticated()) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }
}
