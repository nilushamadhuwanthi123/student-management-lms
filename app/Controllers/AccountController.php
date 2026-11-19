<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;
use App\Services\AccountService;
use App\Services\BranchScope;
use App\Services\EmailService;
use App\Services\SmsService;
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
        $stmt = $this->db->prepare("SELECT id, user_id_display, full_name, role, email, phone1 FROM users WHERE id = :id");
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

        $this->issue(
            'user',
            $target,
            $user['user_id_display'],
            $user['full_name'],
            '/users/' . $target . '/edit',
            (string) ($user['phone1'] ?? ''),
            (string) ($user['email'] ?? '')
        );
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

    /**
     * POST /accounts/issued/send (AJAX, JSON)
     *
     * Sends one already-shown credential item by SMS or email, as an
     * alternative to the office reading it out by phone or in person.
     * The secret cannot be re-read from the session (issued() clears it
     * on load), so the issued.php page posts the item's own fields back
     * here -- the same values already visible on screen to this admin,
     * nothing new is exposed.
     */
    public function sendIssued(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(array_merge(self::ISSUER_ROLES, [AuthMiddleware::ROLE_STAFF]));
        header('Content-Type: application/json');
        verifyCsrfToken(); // exits with 403 for a script call if the token is stale/missing

        $via = (string) ($_POST['via'] ?? '');
        $kind = (string) ($_POST['kind'] ?? 'password');
        $name = trim((string) ($_POST['name'] ?? ''));
        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $secret = (string) ($_POST['secret'] ?? '');
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));

        if (!in_array($via, ['sms', 'email'], true) || $secret === '' || $loginId === '') {
            echo json_encode(['ok' => false, 'message' => t('Missing details. Reload the page and try again.')]);
            return;
        }

        $body = self::credentialMessage($kind, $name, $loginId, $secret);

        if ($via === 'sms') {
            $phone = trim((string) ($_POST['phone'] ?? ''));
            if ($phone === '') {
                echo json_encode(['ok' => false, 'message' => t('No phone number on file for this person.')]);
                return;
            }
            $sent = (new SmsService())->send($phone, $body);
            $message = $sent
                ? t('Sent by SMS.')
                : t('Could not send by SMS. Check the SMS gateway in Settings, or use the manual option instead.');
        } else {
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'message' => t('No valid email address on file for this person.')]);
                return;
            }
            $sent = (new EmailService())->send($email, t('Your class portal login details'), $body);
            $message = $sent
                ? t('Sent by email.')
                : t('Could not send by email. Email sending may not be set up on this server, or no "From" address is configured. Use the manual option instead.');
        }

        // The secret itself never goes into the audit log.
        $this->audit((int) session_get('user_id'), 'user', 'send_issued_credential', 'account', 0, [
            'via' => $via, 'kind' => $kind, 'sent' => $sent,
        ]);

        echo json_encode(['ok' => (bool) $sent, 'message' => $message]);
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

        $sentTo = null;

        if ($loginId !== '') {
            $target = (new AccountService($this->db))->recoveryTarget($loginId);
            if ($target !== null) {
                $sentTo = $this->sendCode($target);
            } else {
                $this->audit(null, 'system', 'self_reset_code_missed', null, 0, [
                    'login_id' => mb_substr($loginId, 0, 40),
                ]);
            }
        }

        // Naming where it went, masked, is what lets somebody tell "it is
        // on its way to the phone in my hand" from "it went to a number I
        // no longer use" -- the difference between waiting and calling the
        // office. The trade-off is that it confirms the ID exists, which
        // the branch office accepted for this install.
        if ($sentTo !== null && $sentTo !== '') {
            $this->flash('success', t('A reset code is on its way to {where}. It works once, for 30 minutes.', ['where' => $sentTo]));
        } else {
            $this->flash('success', t('If that ID is on our records, a reset code is on its way to the phone number or email address registered for it. It works once, for 30 minutes.'));
        }

        // The login ID carries over so the next page is already half filled.
        session_flash('reset_old', ['login_id' => $loginId, 'reset_code' => '']);
        $this->redirect('/reset-password');
    }

    /**
     * Issue a code for a confirmed account and get it to the phone on
     * that account.
     *
     * Tried in order, and it stops at the first one that works, so
     * nobody gets the code twice:
     *
     *  1. SMS, where the office has configured a gateway. The only one
     *     that scales past a handful of students -- and the only one
     *     that costs money, which is why it is so often absent.
     *  2. Email, where the account has an address and SMTP is set up.
     *     This exists because staff accounts are routinely created with
     *     an address and no mobile: with SMS-only delivery, pressing
     *     "Send me a reset code" did nothing at all for them, and there
     *     was no way to tell that from the outside.
     *  3. The WhatsApp queue, for the office to send by hand. The
     *     behaviour this had before any gateway was possible.
     *
     * A failure here is swallowed on purpose: the caller must answer the
     * same way whether or not anything was sent.
     */
    private function sendCode(array $target): ?string
    {
        try {
            $result = (new AccountService($this->db))->issueCode($target['type'], $target['id'], null);

            $body = t(
                'Hello {name}. Your reset code for the class portal is {code}. It works once, within 30 minutes. If you did not ask for this, please tell the office.',
                ['name' => $target['name'], 'code' => $result['code']]
            );

            $phone = $target['phone'] ?? null;
            $email = $target['email'] ?? null;

            $delivery = 'none';
            $sentTo = null;

            if ($phone !== null && (new \App\Services\SmsService())->send($phone, $body)) {
                $delivery = 'sms';
                $sentTo = self::maskPhone($phone);
            } elseif ($email !== null
                && (new EmailService())->send($email, t('Your class portal reset code'), $body)) {
                $delivery = 'email';
                $sentTo = self::maskEmail($email);
            } elseif ($phone !== null) {
                (new WhatsAppService())->queue([
                    'recipient_name'  => $target['name'],
                    'recipient_phone' => $phone,
                    'recipient_type'  => $target['type'] === 'parent' ? 'parent' : 'student',
                    'template_key'    => 'password_reset_code',
                    'body'            => $body,
                    'student_id'      => $target['type'] === 'student' ? $target['id'] : null,
                ]);
                $delivery = 'whatsapp_queue';
                $sentTo = self::maskPhone($phone);
            }

            // The code itself never reaches the audit log.
            $this->audit(null, 'system', 'self_reset_code_requested', $target['type'], $target['id'], [
                'expires_at' => $result['expires_at'],
                'delivery'   => $delivery,
            ]);

            return $sentTo;
        } catch (\Throwable $e) {
            error_log('Self-service reset code failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * "0771234567" -> "••• ••• •567".
     *
     * Enough for somebody to recognise their own number, not enough to
     * read one off the screen.
     */
    private static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $last = mb_substr($digits, -3);

        return $last === '' ? '' : '••• ••• •' . $last;
    }

    /** "nilusha@example.com" -> "ni•••@example.com". */
    private static function maskEmail(string $email): string
    {
        $at = mb_strpos($email, '@');
        if ($at === false || $at < 1) {
            return '';
        }

        $name = mb_substr($email, 0, $at);
        $domain = mb_substr($email, $at);
        $head = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $head . '•••' . $domain;
    }

    /** GET /reset-password */
    public function showReset(): void
    {
        // A failed attempt used to clear the whole form, so a mistyped
        // password meant typing the login ID and the code again from
        // scratch -- and the code is one-use, so people assumed it had
        // been burned. Put back what they already typed.
        $old = session_get_flash('reset_old');
        $old = is_array($old) ? $old : [];

        $this->view('auth/reset-password', [
            'pageTitle'   => t('Reset your password'),
            // ?id=... lets the office hand over a link that is already
            // filled in. The code itself is never put in a URL: it would
            // end up in browser history and referrer headers.
            'oldLoginId'  => (string) ($old['login_id'] ?? ($_GET['id'] ?? '')),
            'oldResetCode' => (string) ($old['reset_code'] ?? ''),
        ], 'layouts/auth');
    }

    /** POST /reset-password */
    public function reset(): void
    {
        verifyCsrfToken();

        $limiter = new RateLimiter();
        $key = 'reset_ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $check = $limiter->checkRateLimit($key, 10, 15);
        if (!$check['allowed']) {
            session_flash('reset_old', [
                'login_id'   => trim((string) ($_POST['login_id'] ?? '')),
                'reset_code' => (string) ($_POST['reset_code'] ?? ''),
            ]);
            $this->flash('error', t('Too many attempts. Please wait 15 minutes and try again.'));
            $this->redirect('/reset-password');
        }

        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $code = (string) ($_POST['reset_code'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

        $problem = self::passwordProblem($new, $confirm);
        if ($problem !== null) {
            session_flash('reset_old', ['login_id' => $loginId, 'reset_code' => $code]);
            $this->flash('error', $problem);
            $this->redirect('/reset-password');
        }

        $done = (new AccountService($this->db))->redeemCode($loginId, $code, $new);
        if ($done === null) {
            $limiter->incrementAttempts($key);
            $this->audit(null, 'system', 'password_reset_failed', null, 0, ['login_id' => mb_substr($loginId, 0, 40)]);
            session_flash('reset_old', ['login_id' => $loginId, 'reset_code' => $code]);
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

    /**
     * The page where an office-issued reset code is actually redeemed.
     *
     * Not the same page as "Forgot password?", which only sends a NEW code
     * by SMS and has no field for a code the office already gave out --
     * pointing people there is what made an issued code look unusable.
     */
    public static function resetUrl(): string
    {
        return rtrim((string) env('APP_URL', ''), '/') . '/reset-password';
    }

    /**
     * The exact wording sent for a credential, shared by every issuer so
     * a parent sees the same message whether it went out from this
     * screen's "Send by..." buttons, the enrolment-approval auto-send,
     * or the WhatsApp click-to-chat link on the issued.php page.
     */
    public static function credentialMessage(string $kind, string $name, string $loginId, string $secret): string
    {
        return $kind === 'reset_code'
            ? t(
                'Hello {name}. Your reset code for the class portal is {code}. Open {url}, then enter login ID {id}, this code and a new password, within 30 minutes.',
                ['name' => $name !== '' ? $name : $loginId, 'code' => $secret, 'id' => $loginId, 'url' => self::resetUrl()]
            )
            : t(
                'Hello {name}. Your class portal login: ID {id}, temporary password {password}. You must choose your own password the first time you log in.',
                ['name' => $name !== '' ? $name : $loginId, 'id' => $loginId, 'password' => $secret]
            );
    }

    private function issue(string $type, int $id, string $loginId, string $name, string $back, string $phone = '', string $email = ''): void
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
            'phone'      => $phone,
            'email'      => $email,
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
