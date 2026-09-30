<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;
use App\Services\AuthService;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * Show the login form.
     */
    public function showLogin(): void
    {
        AuthMiddleware::requireGuest();

        $this->view('auth/login', [
            'pageTitle' => 'Login',
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

        // Rate limit check
        $rateLimiter = new RateLimiter();
        $rateLimitKey = 'login_' . $data['user_id'];
        $rateCheck = $rateLimiter->checkRateLimit($rateLimitKey, 5, 15);

        if (!$rateCheck['allowed']) {
            $minutes = (int) ceil(($rateCheck['retryAfter'] ?? 900) / 60);
            $this->flash('error', "Too many login attempts. Please try again in {$minutes} minutes.");
            $this->redirect('/login');
        }

        // Attempt authentication
        $result = $this->authService->login($data['user_id'], $data['password']);

        if ($result['success'] === false) {
            $rateLimiter->incrementAttempts($rateLimitKey);

            $message = $result['message'] ?? 'Invalid credentials. Please try again.';
            $this->flash('error', $message);
            $this->redirect('/login');
        }

        // Clear rate limit on success
        $rateLimiter->clearAttempts($rateLimitKey);

        // Regenerate session to prevent fixation
        session_regenerate();

        // Store user data in session
        $user = $result['user'];
        session_set('user_id', $user['id']);
        session_set('user_name', $user['first_name'] . ' ' . $user['last_name']);
        session_set('user_email', $user['email']);
        session_set('user_role', $user['role']);
        session_set('user_status', $user['status']);
        session_set('user_institute_id', $user['institute_id'] ?? null);
        session_set('user_display_id', $user['user_id'] ?? $user['student_id'] ?? null);

        // Check if first login (password needs to be changed)
        if ($this->authService->isFirstLogin($user)) {
            session_set('first_login', true);
            $this->redirect('/first-login-password');
        }

        // Redirect to intended URL or dashboard
        $intended = session_get('intended_url', '/dashboard');
        session_set('intended_url', null);

        $this->flash('success', 'Welcome back, ' . $this->e($user['first_name']) . '!');
        $this->redirect($intended);
    }

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
            'pageTitle' => 'Change Your Password',
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

        $errors = $this->validate($data, [
            'new_password' => 'required|min:8|confirmed',
        ]);

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/first-login-password');
        }

        // Update password via User model
        $userId = (int) session_get('user_id');
        $userModel = new \User();
        $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $userModel->updatePassword($userId, $hash);

        // Clear first_login flag in the database
        if ($this->db) {
            try {
                $sql = "UPDATE users SET first_login = 0, updated_at = NOW() WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':id' => $userId]);
            } catch (\PDOException $e) {
                error_log('Failed to clear first_login flag: ' . $e->getMessage());
            }
        }

        // Clear session flag
        session_set('first_login', null);

        $this->flash('success', 'Password updated successfully. Welcome to the system!');
        $this->redirect('/dashboard');
    }

    /**
     * Log the user out.
     */
    public function logout(): void
    {
        $this->authService->logout();

        $this->flash('success', 'You have been logged out successfully.');
        $this->redirect('/login');
    }
}
