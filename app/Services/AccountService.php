<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Credentials for the three kinds of account (staff users, students, parents).
 *
 * Recovery works through the branch office, because the LMS has no verified
 * email or SMS channel: a student ID or phone number is public knowledge, so
 * it can never be enough on its own to take over an account.
 *
 *  1. The person asks the branch office. The office confirms who they are
 *     (in person, or by calling the phone number already on file).
 *  2. The office issues a one-time reset code. It is shown once, only a hash
 *     is stored, it expires after 30 minutes and allows five wrong tries.
 *  3. The person enters their login ID, the code and a new password on
 *     /reset-password. Every other session for that account then ends.
 *
 * Temporary passwords (new registrations) work the same way: shown once, and
 * the person must choose their own password at the first login.
 */
final class AccountService
{
    public const CODE_MINUTES = 30;
    public const MAX_CODE_ATTEMPTS = 5;

    /** Passwords that ship with the demo data or old defaults. */
    public const KNOWN_DEFAULTS = ['Demo@12345', 'Password@123'];

    private const TABLES = ['user' => 'users', 'student' => 'students', 'parent' => 'parents'];
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    public static function table(string $type): string
    {
        if (!isset(self::TABLES[$type])) {
            throw new \InvalidArgumentException('Unknown account type.');
        }
        return self::TABLES[$type];
    }

    /** A readable temporary password that meets the complexity rule, e.g. "Kp7m-Qx4t". */
    public static function temporaryPassword(): string
    {
        $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digit = '23456789';
        $all = $upper . $lower . $digit;
        $pick = static fn (string $set): string => $set[random_int(0, strlen($set) - 1)];

        $chars = [$pick($upper), $pick($lower), $pick($digit), $pick($upper), $pick($lower), $pick($digit)];
        for ($i = 0; $i < 2; $i++) {
            $chars[] = $pick($all);
        }
        // Fisher-Yates with random_int, so the order is not predictable either.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', array_slice($chars, 0, 4)) . '-' . implode('', array_slice($chars, 4));
    }

    public static function isKnownDefault(string $password): bool
    {
        foreach (self::KNOWN_DEFAULTS as $default) {
            if (hash_equals($default, $password)) {
                return true;
            }
        }
        return false;
    }

    /** Does a stored hash belong to one of the known default passwords? */
    public static function hashIsKnownDefault(string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        foreach (self::KNOWN_DEFAULTS as $default) {
            if (password_verify($default, $hash)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Issue a one-time reset code. Earlier unused codes for the account are revoked.
     *
     * @return array{code: string, expires_at: string}
     */
    public function issueCode(string $type, int $accountId, ?int $issuedBy): array
    {
        self::table($type);

        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }
        $expires = date('Y-m-d H:i:s', time() + self::CODE_MINUTES * 60);

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE password_resets SET revoked_at = NOW()
                  WHERE account_type = :t AND account_id = :a AND used_at IS NULL AND revoked_at IS NULL"
            )->execute([':t' => $type, ':a' => $accountId]);

            $this->db->prepare(
                "INSERT INTO password_resets (account_type, account_id, code_hash, issued_by, expires_at)
                 VALUES (:t, :a, :h, :by, :e)"
            )->execute([
                ':t' => $type, ':a' => $accountId, ':h' => password_hash($raw, PASSWORD_DEFAULT),
                ':by' => $issuedBy, ':e' => $expires,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['code' => substr($raw, 0, 4) . '-' . substr($raw, 4), 'expires_at' => $expires];
    }

    /**
     * Redeem a reset code. Returns the account it reset, or null. Callers show
     * one generic message on failure, so nobody learns which IDs exist.
     *
     * @return array{type: string, id: int}|null
     */
    public function redeemCode(string $loginId, string $code, string $newPassword): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) !== 8) {
            return null;
        }

        foreach ($this->accountsFor($loginId) as [$type, $id]) {
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare(
                    "SELECT id, code_hash, attempts FROM password_resets
                      WHERE account_type = :t AND account_id = :a
                        AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()
                      ORDER BY id DESC LIMIT 1 FOR UPDATE"
                );
                $stmt->execute([':t' => $type, ':a' => $id]);
                $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($reset === false || (int) $reset['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                    $this->db->commit();
                    continue;
                }

                if (!password_verify($code, (string) $reset['code_hash'])) {
                    $this->db->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = :id")
                        ->execute([':id' => $reset['id']]);
                    $this->db->commit();
                    continue;
                }

                $this->db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id")
                    ->execute([':id' => $reset['id']]);
                $this->setPassword($type, $id, $newPassword, false);
                $this->db->commit();

                return ['type' => $type, 'id' => $id];
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log('Reset code redemption failed: ' . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Set a password and end every existing session for the account.
     * $temporary = true makes the person choose a new one at the next login.
     */
    public function setPassword(string $type, int $id, string $password, bool $temporary): void
    {
        $table = self::table($type);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($type === 'user') {
            $status = $temporary ? "'pending_first_login'" : "IF(status = 'pending_first_login', 'active', status)";
            $this->db->prepare(
                "UPDATE users SET password_hash = :h, status = $status, password_changed_at = NOW(),
                        credential_version = credential_version + 1, updated_at = NOW()
                  WHERE id = :id"
            )->execute([':h' => $hash, ':id' => $id]);
            return;
        }

        $this->db->prepare(
            "UPDATE $table SET password_hash = :h, must_change_password = :m,
                    credential_version = credential_version + 1, updated_at = NOW()
              WHERE id = :id"
        )->execute([':h' => $hash, ':m' => $temporary ? 1 : 0, ':id' => $id]);
    }

    /**
     * The live state of a signed-in account, read fresh on every request.
     *
     * @return array{status: string, role: ?string, version: int, must_change: bool}|null
     */
    public function liveState(string $type, int $id): ?array
    {
        $table = self::table($type);
        $cols = $type === 'user'
            ? "status, role, credential_version, (status = 'pending_first_login') AS must_change"
            : "status, NULL AS role, credential_version, must_change_password AS must_change";
        $stmt = $this->db->prepare("SELECT $cols FROM $table WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'status'      => (string) $row['status'],
            'role'        => $row['role'] !== null ? (string) $row['role'] : null,
            'version'     => (int) $row['credential_version'],
            'must_change' => (bool) $row['must_change'],
        ];
    }

    /**
     * Accounts a login ID could refer to (staff ID/email, student ID, parent phone).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function accountsFor(string $loginId): array
    {
        $loginId = trim($loginId);
        if ($loginId === '') {
            return [];
        }
        $found = [];

        $stmt = $this->db->prepare("SELECT id FROM users WHERE user_id_display = :a OR email = :b LIMIT 1");
        $stmt->execute([':a' => $loginId, ':b' => $loginId]);
        if (($id = $stmt->fetchColumn()) !== false) {
            $found[] = ['user', (int) $id];
        }

        $stmt = $this->db->prepare("SELECT id FROM students WHERE student_id_display = :a LIMIT 1");
        $stmt->execute([':a' => $loginId]);
        if (($id = $stmt->fetchColumn()) !== false) {
            $found[] = ['student', (int) $id];
        }

        if (str_starts_with($loginId, '0') || str_starts_with($loginId, '+')) {
            $stmt = $this->db->prepare("SELECT id FROM parents WHERE phone1 = :a ORDER BY id");
            $stmt->execute([':a' => $loginId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $found[] = ['parent', (int) $id];
            }
        }

        return $found;
    }

    /**
     * Find the one account a self-service reset request may go to.
     *
     * Returns null when the ID matches nothing, matches more than one
     * account, the account is switched off, or there is no mobile number
     * on file. The caller shows the same message whatever the answer, so
     * none of those reasons tells a stranger whether an ID exists.
     *
     * @return array{type: string, id: int, name: string, phone: string}|null
     */
    public function recoveryTarget(string $loginId): ?array
    {
        $found = $this->accountsFor($loginId);
        if (count($found) !== 1) {
            return null;
        }

        [$type, $id] = $found[0];

        // The email is fetched as well as the phone: staff accounts are
        // routinely created with an address and no mobile, and an
        // SMS-only recovery left those with no self-service route at all.
        // The parents table has no email column, so that one is null.
        $sql = match ($type) {
            'user' => "SELECT full_name AS name, status, NULLIF(email, '') AS email,
                              COALESCE(NULLIF(whatsapp, ''), NULLIF(phone1, '')) AS phone
                         FROM users WHERE id = :id",
            'student' => "SELECT full_name AS name, status, NULLIF(email, '') AS email,
                                 COALESCE(NULLIF(whatsapp, ''), NULLIF(phone1, '')) AS phone
                            FROM students WHERE id = :id",
            'parent' => "SELECT parent_name AS name, status, NULL AS email,
                                COALESCE(NULLIF(whatsapp, ''), NULLIF(phone1, '')) AS phone
                           FROM parents WHERE id = :id",
            default => null,
        };
        if ($sql === null) {
            return null;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || in_array((string) $row['status'], ['inactive', 'deactivated'], true)) {
            return null;
        }

        $phone = WhatsAppService::normalisePhone($row['phone'] ?? null);

        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = null;
        }

        // One usable way to reach them is enough; it used to insist on a
        // phone, so an address-only account could not recover at all.
        if ($phone === null && $email === null) {
            return null;
        }

        return [
            'type'  => $type,
            'id'    => $id,
            'name'  => (string) $row['name'],
            'phone' => $phone,
            'email' => $email,
        ];
    }
}
