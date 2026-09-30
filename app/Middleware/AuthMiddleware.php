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
            header('Location: /login');
            exit;
        }

        self::checkDeactivated();
    }

    /**
     * Check if the current user is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return session_has('user_id') && session_get('user_id') !== null;
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
            header('Location: /dashboard');
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

        if ($status === 'deactivated') {
            $role = self::getUserRole();

            if (in_array($role, [self::ROLE_STUDENT, self::ROLE_PARENT], true)) {
                session_destroy_all();
                session_start();
                session_flash(
                    'error',
                    'Your account has been deactivated. Please contact the administration for assistance.'
                );
                header('Location: /login');
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
            header('Location: /dashboard');
            exit;
        }
    }
}
