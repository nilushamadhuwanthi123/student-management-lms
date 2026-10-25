<?php

declare(strict_types=1);

namespace App\Services;

/**
 * AuthService — handles authentication for all user types
 * (users, students, parents) with audit logging.
 */
class AuthService
{
    // ─── Login ───────────────────────────────────────────────────────

    /**
     * Attempt to authenticate by trying users, students, then parents tables.
     *
     * @param  string $loginId   Display ID, student ID, or phone number
     * @param  string $password  Plain-text password
     * @return array{success: bool, user?: array, user_type?: string, message?: string}
     */
    public function login(string $loginId, string $password): array
    {
        // 1. Try users table first (admin, coordinator, staff, teacher)
        $userModel = new \User();
        $user = $userModel->authenticate($loginId, $password);

        if ($user) {
            $deactivationMsg = $this->checkDeactivated($user, 'user');
            if ($deactivationMsg !== null) {
                $this->logAuthEvent('login_failed', null, 'user', json_encode([
                    'attempted_id' => $loginId,
                    'reason'       => 'account_deactivated',
                ]));
                return [
                    'success' => false,
                    'message' => $deactivationMsg,
                ];
            }

            if (($blocked = $this->refuseDefaultPassword($password, (int) $user['id'], 'user')) !== null) {
                return $blocked;
            }

            $this->logAuthEvent('login_success', (int) $user['id'], 'user', null);

            return [
                'success'   => true,
                'user'      => $user,
                'user_type' => 'user',
                'message'   => 'Login successful.',
            ];
        }

        // 2. Try students table
        $studentModel = new \Student();
        $student = $studentModel->authenticate($loginId, $password);

        if ($student) {
            $deactivationMsg = $this->checkDeactivated($student, 'student');
            if ($deactivationMsg !== null) {
                $this->logAuthEvent('login_failed', null, 'student', json_encode([
                    'attempted_id' => $loginId,
                    'reason'       => 'account_deactivated',
                ]));
                return [
                    'success' => false,
                    'message' => $deactivationMsg,
                ];
            }

            if (($blocked = $this->refuseDefaultPassword($password, (int) $student['id'], 'student')) !== null) {
                return $blocked;
            }

            $this->logAuthEvent('login_success', (int) $student['id'], 'student', null);

            return [
                'success'   => true,
                'user'      => $student,
                'user_type' => 'student',
                'message'   => 'Login successful.',
            ];
        }

        // 3. Try parents table if loginId looks like a phone number
        if ($this->looksLikePhone($loginId)) {
            $parentModel = new \Parent_();
            $parent = $parentModel->authenticate($loginId, $password);

            if ($parent) {
                $deactivationMsg = $this->checkDeactivated($parent, 'parent');
                if ($deactivationMsg !== null) {
                    $this->logAuthEvent('login_failed', null, 'parent', json_encode([
                        'attempted_id' => $loginId,
                        'reason'       => 'account_deactivated',
                    ]));
                    return [
                        'success' => false,
                        'message' => $deactivationMsg,
                    ];
                }

                if (($blocked = $this->refuseDefaultPassword($password, (int) $parent['id'], 'parent')) !== null) {
                    return $blocked;
                }

                $this->logAuthEvent('login_success', (int) $parent['id'], 'parent', null);

                return [
                    'success'   => true,
                    'user'      => $parent,
                    'user_type' => 'parent',
                    'message'   => 'Login successful.',
                ];
            }
        }

        // All attempts failed
        $this->logAuthEvent('login_failed', null, 'unknown', json_encode([
            'attempted_id' => $loginId,
        ]));

        return [
            'success' => false,
            'message' => 'Invalid User ID or password. Please try again.',
        ];
    }

    // ─── Logout ──────────────────────────────────────────────────────

    /**
     * Log the logout event and destroy the session.
     */
    public function logout(): void
    {
        $userId   = session_has('user_id') ? (int) session_get('user_id') : null;
        $userType = session_get('user_type') ?? 'user';

        $this->logAuthEvent('logout', $userId, $userType, null);

        session_destroy_all();

        // Restart a clean session for flash messages
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ─── Current User ────────────────────────────────────────────────

    /**
     * Get the currently authenticated user data from session.
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
            'display_id'   => session_get('user_display_id'),
            'user_type'    => session_get('user_type'),
            'institute_id' => session_get('user_institute_id'),
        ];
    }

    // ─── First Login Check ───────────────────────────────────────────

    /**
     * Determine if this is the user's first login (password change required).
     *
     * Only applies to 'user' type (admin/coordinator/staff/teacher).
     * Students and parents have no first-login flow.
     */
    public function isFirstLogin(array $user, string $userType): bool
    {
        if ($userType !== 'user') {
            return false;
        }

        return ($user['status'] ?? '') === 'pending_first_login';
    }

    // ─── Deactivation Check ──────────────────────────────────────────

    /**
     * Check if a user/student/parent account is deactivated.
     *
     * @param  array  $user      Row data from the database
     * @param  string $userType  'user', 'student', or 'parent'
     * @return string|null       Error message if deactivated, null if active
     */
    public function checkDeactivated(array $user, string $userType): ?string
    {
        $status = $user['status'] ?? 'active';

        switch ($userType) {
            case 'user':
                if ($status === 'inactive') {
                    return 'Your account has been deactivated. Please contact the administration for assistance.';
                }
                break;

            case 'student':
                if ($status === 'deactivated') {
                    return 'Your student account has been deactivated. Please contact the administration for assistance.';
                }
                break;

            case 'parent':
                if ($status === 'deactivated') {
                    return 'Your parent account has been deactivated. Please contact the administration for assistance.';
                }
                break;
        }

        return null;
    }

    // ─── Private Helpers ─────────────────────────────────────────────

    /**
     * On a live server, an account that still has a demo or old default
     * password cannot be used until the office issues a reset code.
     */
    private function refuseDefaultPassword(string $password, int $id, string $type): ?array
    {
        if (env('APP_ENV', 'development') !== 'production' || !AccountService::isKnownDefault($password)) {
            return null;
        }
        $this->logAuthEvent('login_failed', $id, $type, json_encode(['reason' => 'default_password']));

        return [
            'success' => false,
            'message' => t('This account still has a default password, so it cannot be used. Please ask the branch office for a reset code.'),
        ];
    }

    /**
     * Check if a login ID looks like a phone number.
     */
    private function looksLikePhone(string $loginId): bool
    {
        return str_starts_with($loginId, '0') || str_starts_with($loginId, '+');
    }

    /**
     * Log an authentication event directly via SQL to avoid circular dependencies.
     *
     * @param string  $action    e.g. 'login_success', 'login_failed', 'logout', 'password_changed'
     * @param int|null $userId   The user's primary key (null for failed attempts)
     * @param string  $userType  'user', 'student', 'parent', or 'unknown'
     * @param string|null $details  JSON-encoded extra details
     */
    private function logAuthEvent(string $action, ?int $userId, string $userType, ?string $details): void
    {
        try {
            $db = \Database::connect();

            $userRole = session_has('user_role') ? session_get('user_role') : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $sql = "INSERT INTO audit_log (user_id, user_type, user_role, action, ip_address, details, created_at)
                    VALUES (:user_id, :user_type, :user_role, :action, :ip_address, :details, :created_at)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id'    => $userId,
                ':user_type'  => $userType,
                ':user_role'  => $userRole,
                ':action'     => $action,
                ':ip_address' => $ipAddress,
                ':details'    => $details,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            // Logging should never break the authentication flow
            error_log('AuthService::logAuthEvent failed: ' . $e->getMessage());
        }
    }
}
