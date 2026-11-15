<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;
use App\Services\AccountService;
use App\Services\AuthService;

/**
 * AuthController — handles login, logout, first-login password change,
 * and general password change for all user types.
 */
class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    // ─── Login ───────────────────────────────────────────────────────

    /**
     * Show the login form.
     */
    public function showLogin(): void
    {
        AuthMiddleware::requireGuest();

        $this->view('auth/login', [
            'pageTitle' => t('Login'),
        ], 'layouts/auth');
    }

    /**
     * Process the login form submission.
     */
    public function login(): void
    {
        verifyCsrfToken();

        $data = [
            'user_id'  => trim($_POST['user_id'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ];

        // Validate input
        $errors = $this->validate($data, [
            'user_id'  => 'required',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/login');
        }

        // Rate limit: max 5 attempts per 15 minutes per user ID
        $rateLimiter  = new RateLimiter();
        $rateLimitKey = 'login_' . $data['user_id'];
        $rateCheck    = $rateLimiter->checkRateLimit($rateLimitKey, 5, 15);

        // ...and at most 30 per 15 minutes from one address, so guessing
        // across many IDs is slowed down too.
        $ipKey   = 'login_ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipCheck = $rateLimiter->checkRateLimit($ipKey, 30, 15);
        if (!$ipCheck['allowed']) {
            $rateCheck = $ipCheck;
        }

        if (!$rateCheck['allowed']) {
            $minutes = (int) ceil(($rateCheck['retryAfter'] ?? 900) / 60);
            $this->flash('error', t('Too many login attempts. Please try again in {n} minutes.', ['n' => $minutes]));
            $this->redirect('/login');
        }

        // Attempt authentication
        $result = $this->authService->login($data['user_id'], $data['password']);

        if ($result['success'] === false) {
            $rateLimiter->incrementAttempts($rateLimitKey);
            $rateLimiter->incrementAttempts($ipKey);

            $message = $result['message'] ?? 'Invalid credentials. Please try again.';
            $this->flash('error', $message);
            $this->redirect('/login');
        }

        // ── Success ──────────────────────────────────────────────────

        // Clear rate limit on successful login
        $rateLimiter->clearAttempts($rateLimitKey);

        // Regenerate session to prevent fixation attacks
        session_regenerate();

        $user     = $result['user'];
        $userType = $result['user_type']; // 'user', 'student', or 'parent'

        // Determine display name
        $displayName = match ($userType) {
            'parent' => $user['parent_name'] ?? 'Parent',
            default  => $user['full_name'] ?? 'User',
        };

        // Determine display ID
        $displayId = match ($userType) {
            'user'    => $user['user_id_display'] ?? null,
            'student' => $user['student_id_display'] ?? null,
            'parent'  => $user['phone1'] ?? null,
        };

        // Determine role
        $role = match ($userType) {
            'user'    => $user['role'] ?? 'staff',
            'student' => 'student',
            'parent'  => 'parent',
        };

        // Store user data in session
        session_set('user_id', $user['id']);
        session_set('user_name', $displayName);
        session_set('user_email', $user['email'] ?? null);
        session_set('user_role', $role);
        session_set('user_status', $user['status'] ?? 'active');
        session_set('user_institute_id', ($userType === 'user') ? ($user['institute_id'] ?? null) : null);
        session_set('user_display_id', $displayId);
        session_set('user_type', $userType);

        // Generate session fingerprint (IP + User-Agent hash)
        $fingerprint = hash('sha256',
            ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
        session_set('session_fingerprint', $fingerprint);

        // Track session activity
        session_set('last_activity', time());

        // Sessions end when the password changes (see AuthMiddleware).
        session_set('credential_version', (int) ($user['credential_version'] ?? 1));

        // A temporary password must be replaced before anything else:
        // staff with pending_first_login, students and parents flagged.
        $mustChange = $this->authService->isFirstLogin($user, $userType)
            || (in_array($userType, ['student', 'parent'], true) && !empty($user['must_change_password']));
        if ($mustChange) {
            session_set('first_login', true);
            $this->redirect('/first-login-password');
        }

        // Redirect to intended URL or dashboard
        $intended = session_get('intended_url') ?? '/dashboard';
        session_set('intended_url', null);

        // Not escaped here: the flash component escapes on output, and
        // escaping twice showed names with "&" as "&amp;".
        $this->flash('success', t('Welcome back, {name}!', ['name' => $displayName]));
        $this->redirect($intended);
    }

    // ─── First Login Password ────────────────────────────────────────

    /**
     * Show the first-login password change form.
     */
    public function showFirstLoginPassword(): void
    {
        AuthMiddleware::requireAuth();

        if (!session_get('first_login')) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/first-login-password', [
            'pageTitle' => t('Change Your Password'),
        ], 'layouts/auth');
    }

    /**
     * Process the first-login password update.
     */
    public function updateFirstLoginPassword(): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        if (!session_get('first_login')) {
            $this->redirect('/dashboard');
        }

        $data = [
            'new_password'              => $_POST['new_password'] ?? '',
            'new_password_confirmation' => $_POST['new_password_confirmation'] ?? '',
        ];

        // Validate password fields
        $errors = $this->validate($data, [
            'new_password' => 'required|min:8|confirmed',
        ]);

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/first-login-password');
        }

        $problem = AccountController::passwordProblem($data['new_password'], $data['new_password_confirmation']);
        if ($problem !== null) {
            $this->flash('error', $problem);
            $this->redirect('/first-login-password');
        }

        // Save it (users leave pending_first_login; students/parents drop the flag)
        $userId   = (int) session_get('user_id');
        $userType = (string) (session_get('user_type') ?? 'user');

        try {
            $accounts = new AccountService();
            $accounts->setPassword($userType, $userId, $data['new_password'], false);
            session_set('credential_version', $accounts->liveState($userType, $userId)['version'] ?? 1);
        } catch (\Throwable $e) {
            error_log('Failed to update first-login password: ' . $e->getMessage());
            $this->flash('error', 'Failed to update password. Please try again.');
            $this->redirect('/first-login-password');
        }

        // Clear first_login session flag and update session status
        session_set('first_login', null);
        session_set('user_status', 'active');

        // Log audit event
        $this->logPasswordChange($userId, $userType);

        $this->flash('success', t('Password updated successfully. Welcome!'));
        $this->redirect('/dashboard');
    }

    // ─── Change Password (General) ───────────────────────────────────

    /**
     * Show the change password form (available to all authenticated user types).
     */
    public function showChangePassword(): void
    {
        AuthMiddleware::requireAuth();

        $userType = session_get('user_type') ?? 'user';

        // Choose layout based on user type
        $layout = match ($userType) {
            'student' => 'layouts/admin',
            'parent'  => 'layouts/admin',
            default   => 'layouts/admin',
        };

        $this->view('auth/change-password', [
            'pageTitle' => t('Change Password'),
        ], $layout);
    }

    /**
     * Process the password change for any authenticated user type.
     */
    public function changePassword(): void
    {
        AuthMiddleware::requireAuth();
        verifyCsrfToken();

        $data = [
            'current_password'          => $_POST['current_password'] ?? '',
            'new_password'              => $_POST['new_password'] ?? '',
            'new_password_confirmation' => $_POST['new_password_confirmation'] ?? '',
        ];

        // Validate fields
        $errors = $this->validate($data, [
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->back();
        }

        $problem = AccountController::passwordProblem($data['new_password'], $data['new_password_confirmation']);
        if ($problem !== null) {
            $this->flash('error', $problem);
            $this->back();
        }

        $userId   = (int) session_get('user_id');
        $userType = session_get('user_type') ?? 'user';

        // Determine table and fetch current password hash
        $table = match ($userType) {
            'student' => 'students',
            'parent'  => 'parents',
            default   => 'users',
        };

        try {
            $db = \Database::connect();

            // Fetch current password hash from DB
            $sql  = "SELECT password_hash FROM {$table} WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->flash('error', 'User account not found.');
                $this->back();
            }

            // Verify current password
            if (!password_verify($data['current_password'], $row['password_hash'])) {
                $this->flash('error', 'Current password is incorrect.');
                $this->back();
            }

            // Update the password; other sessions of this account end,
            // this one carries on with the new credential version.
            $accounts = new AccountService($db);
            $accounts->setPassword($userType, $userId, $data['new_password'], false);
            session_set('credential_version', $accounts->liveState($userType, $userId)['version'] ?? 1);
        } catch (\Throwable $e) {
            error_log('Failed to change password: ' . $e->getMessage());
            $this->flash('error', 'Failed to update password. Please try again.');
            $this->back();
        }

        // Log audit event
        $this->logPasswordChange($userId, $userType);

        $this->flash('success', 'Password changed successfully.');
        $this->back();
    }

    // ─── Logout ──────────────────────────────────────────────────────

    /**
     * Log the user out and redirect to login.
     */
    public function logout(): void
    {
        $this->authService->logout();

        $this->flash('success', 'You have been logged out successfully.');
        $this->redirect('/login');
    }

    // ─── Private Helpers ─────────────────────────────────────────────

    /**
     * Check password complexity: at least 1 uppercase, 1 lowercase, 1 digit, 1 special character.
     */
    private function isPasswordComplex(string $password): bool
    {
        return preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    /**
     * Log a password change event to the audit log via direct SQL.
     */
    private function logPasswordChange(int $userId, string $userType): void
    {
        try {
            $db = \Database::connect();

            $sql = "INSERT INTO audit_log (user_id, user_type, user_role, action, ip_address, details, created_at)
                    VALUES (:user_id, :user_type, :user_role, :action, :ip_address, :details, :created_at)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id'    => $userId,
                ':user_type'  => $userType,
                ':user_role'  => session_get('user_role'),
                ':action'     => 'password_changed',
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':details'    => null,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            error_log('AuthController::logPasswordChange failed: ' . $e->getMessage());
        }
    }
}
