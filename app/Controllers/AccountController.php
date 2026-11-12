<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;
use App\Services\AccountService;
use App\Services\BranchScope;
use App\Services\WhatsAppService;

/**
 * Office-issued reset codes, the one-time credential slip, and the public
 * reset page. See AccountService for the recovery rules.
 */
class AccountController extends BaseController
{
    private const ISSUER_ROLES = [
        AuthMiddleware::ROLE_SUPER_ADMIN,
        AuthMiddleware::ROLE_ADMIN,
        AuthMiddleware::ROLE_COORDINATOR,
    ];

    /** POST /students/{id}/reset-code */
    public function studentCode(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $stmt = $this->db->prepare("SELECT id, student_id_display, full_name FROM students WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$student || !BranchScope::current()->allowsStudent((int) $student['id'])) {
            http_response_code(403);
            $this->flash('error', t('That student is not in your branch.'));
            $this->redirect('/students');
        }

        $this->issue('student', (int) $student['id'], $student['student_id_display'], $student['full_name'], '/students/' . (int) $student['id']);
    }

    /** POST /parents/{id}/reset-code */
    public function parentCode(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(self::ISSUER_ROLES);
        verifyCsrfToken();

        $stmt = $this->db->prepare("SELECT id, student_id, parent_name, phone1 FROM parents WHERE id = :id");
        $stmt->execute([':id' => (int) $id]);
        $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$parent || !BranchScope::current()->allowsStudent((int) $parent['student_id'])) {
            http_response_code(403);
            $this->flash('error', t('That student is not in your branch.'));
            $this->redirect('/students');
        }
        if (trim((string) $parent['phone1']) === '') {
            $this->flash('error', t('This parent has no phone number to log in with. Add one first.'));
            $this->redirect('/students/' . (int) $parent['student_id']);
        }

        $this->issue('parent', (int) $parent['id'], (string) $parent['phone1'], $parent['parent_name'], '/students/' . (int) $parent['student_id']);
    }

    /** POST /users/{id}/reset-code */
    public function userCode(string $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole([AuthMiddleware::ROLE_SUPER_ADMIN, AuthMiddleware::ROLE_ADMIN]);
        verifyCsrfToken();

        $target = (int) $id;
        $stmt = $this->db->prepare("SELECT id, user_id_display, full_name, role FROM users WHERE id = :id");
        $stmt->execute([':id' => $target]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $scope = BranchScope::current();

        if (!$user || $target === (int) session_get('user_id')
            || !$scope->allowsUser($target)
            || ($user['role'] === 'super_admin' && !$scope->isGlobal())) {
            http_response_code(403);
            $this->flash('error', t('You cannot reset that account.'));
            $this->redirect('/users');
        }

        $this->issue('user', $target, $user['user_id_display'], $user['full_name'], '/users/' . $target . '/edit');
    }

    /** GET /accounts/issued -- shows the codes/passwords once, then forgets them. */
    public function issued(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(array_merge(self::ISSUER_ROLES, [AuthMiddleware::ROLE_STAFF]));

        $items = session_get('issued_credentials') ?? [];
        $back = (string) (session_get('issued_back') ?? '/dashboard');
        session_set('issued_credentials', null);
        session_set('issued_back', null);

        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');

        $this->view('accounts/issued', [
            'pageTitle' => t('Login details'),
            'items'     => is_array($items) ? $items : [],
            'backUrl'   => $back,
        ]);
    }

    /** GET /forgot-password */
    public function showForgot(): void
    {
        $this->view('auth/forgot-password', ['pageTitle' => t('Forgotten password')], 'layouts/auth');
    }

    /**
     * POST /forgot-password
     *
     * Asks for a reset code without going through the office. The code is
     * never shown to whoever asked -- it goes to the mobile number already
     * on the account, so asking only helps somebody who holds that phone.
     *
     * Every path out of here ends in the same message and the same
     * counters, so a stranger cannot learn which IDs exist by watching
     * what changes.
     */
    public function requestCode(): void
    {
        verifyCsrfToken();

        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $limiter = new RateLimiter();
        $ipKey   = 'forgot_ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $idKey   = 'forgot_' . mb_substr($loginId, 0, 60);

        $ipCheck = $limiter->checkRateLimit($ipKey, 5, 15);
        $idCheck = $limiter->checkRateLimit($idKey, 3, 15);
        if (!$ipCheck['allowed'] || !$idCheck['allowed']) {
            $this->flash('error', t('Too many requests. Please wait 15 minutes and try again.'));
            $this->redirect('/forgot-password');
        }

        // Counted before the lookup, so a hit and a miss cost the same.
        $limiter->incrementAttempts($ipKey);
        $limiter->incrementAttempts($idKey);

        if ($loginId !== '') {
            $target = (new AccountService($this->db))->recoveryTarget($loginId);
            if ($target !== null) {
                $this->sendCode($target);
            } else {
                $this->audit(null, 'system', 'self_reset_code_missed', null, 0, [
                    'login_id' => mb_substr($loginId, 0, 40),
                ]);
            }
        }

        $this->flash('success', t('If that ID is on our records, a reset code is on its way to the phone number registered for it. It works once, for 30 minutes.'));
        $this->redirect('/reset-password');
    }

    /**
     * Issue a code for a confirmed account and get it to the phone on
     * that account.
     *
     * Where the office has configured an SMS gateway the code is texted
     * straight out, which is the only thing that scales past a handful
     * of students. With no gateway, or when the gateway refuses, the
     * code is queued as a WhatsApp message for the office to send by
     * hand -- the behaviour this had before a gateway was possible.
     * Only one of the two happens, so nobody gets the code twice.
     *
     * A failure here is swallowed on purpose: the caller must answer the
     * same way whether or not anything was sent.
     */
    private function sendCode(array $target): void
    {
        try {
            $result = (new AccountService($this->db))->issueCode($target['type'], $target['id'], null);

            $body = t(
                'Hello {name}. Your reset code for the class portal is {code}. It works once, within 30 minutes. If you did not ask for this, please tell the office.',
                ['name' => $target['name'], 'code' => $result['code']]
            );

            $texted = (new \App\Services\SmsService())->send($target['phone'], $body);

            if (!$texted) {
                (new WhatsAppService())->queue([
                    'recipient_name'  => $target['name'],
                    'recipient_phone' => $target['phone'],
                    'recipient_type'  => $target['type'] === 'parent' ? 'parent' : 'student',
                    'template_key'    => 'password_reset_code',
                    'body'            => $body,
                    'student_id'      => $target['type'] === 'student' ? $target['id'] : null,
                ]);
            }

            // The code itself never reaches the audit log.
            $this->audit(null, 'system', 'self_reset_code_requested', $target['type'], $target['id'], [
                'expires_at' => $result['expires_at'],
                'delivery'   => $texted ? 'sms' : 'whatsapp_queue',
            ]);
        } catch (\Throwable $e) {
            error_log('Self-service reset code failed: ' . $e->getMessage());
        }
    }

    /** GET /reset-password */
    public function showReset(): void
    {
        $this->view('auth/reset-password', ['pageTitle' => t('Reset your password')], 'layouts/auth');
    }

    /** POST /reset-password */
    public function reset(): void
    {
        verifyCsrfToken();

        $limiter = new RateLimiter();
        $key = 'reset_ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $check = $limiter->checkRateLimit($key, 10, 15);
        if (!$check['allowed']) {
            $this->flash('error', t('Too many attempts. Please wait 15 minutes and try again.'));
            $this->redirect('/reset-password');
        }

        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $code = (string) ($_POST['reset_code'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

        $problem = self::passwordProblem($new, $confirm);
        if ($problem !== null) {
            $this->flash('error', $problem);
            $this->redirect('/reset-password');
        }

        $done = (new AccountService($this->db))->redeemCode($loginId, $code, $new);
        if ($done === null) {
            $limiter->incrementAttempts($key);
            $this->audit(null, 'system', 'password_reset_failed', null, 0, ['login_id' => mb_substr($loginId, 0, 40)]);
            $this->flash('error', t('That reset code is not valid. Check the code, or ask the branch office for a new one.'));
            $this->redirect('/reset-password');
        }

        $this->audit($done['id'], $done['type'], 'password_reset_completed', $done['type'], $done['id'], []);
        $this->flash('success', t('Your password has been changed. Please log in with the new password.'));
        $this->redirect('/login');
    }

    /** Shared password rule (same as the change-password screens). */
    public static function passwordProblem(string $new, string $confirm): ?string
    {
        if (mb_strlen($new) < 8) {
            return t('The new password must be at least 8 characters.');
        }
        if (!hash_equals($new, $confirm)) {
            return t('The two passwords do not match.');
        }
        if (preg_match('/[A-Z]/', $new) !== 1 || preg_match('/[a-z]/', $new) !== 1
            || preg_match('/[0-9]/', $new) !== 1 || preg_match('/[^A-Za-z0-9]/', $new) !== 1) {
            return t('Use upper and lower case letters, a number and a symbol.');
        }
        if (AccountService::isKnownDefault($new)) {
            return t('That password is not allowed. Choose your own password.');
        }
        return null;
    }

    private function issue(string $type, int $id, string $loginId, string $name, string $back): void
    {
        $result = (new AccountService($this->db))->issueCode($type, $id, (int) session_get('user_id'));

        // The code itself never goes into the audit log.
        $this->audit((int) session_get('user_id'), 'user', 'issue_reset_code', $type, $id, ['expires_at' => $result['expires_at']]);

        session_set('issued_credentials', [[
            'kind'       => 'reset_code',
            'name'       => $name,
            'login_id'   => $loginId,
            'secret'     => $result['code'],
            'expires_at' => $result['expires_at'],
        ]]);
        session_set('issued_back', $back);
        $this->redirect('/accounts/issued');
    }

    private function audit(?int $userId, string $userType, string $action, ?string $entityType, int $entityId, array $details): void
    {
        try {
            (new \AuditLog())->log(
                (int) ($userId ?? 0),
                $userType,
                (string) (session_get('user_role') ?? $userType),
                $action,
                $entityType ?? 'account',
                $entityId,
                $details
            );
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
