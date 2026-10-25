-- 013: account security and recovery.
--  * must_change_password: students and parents given a temporary password
--    must choose their own at the next login (staff already use
--    users.status = 'pending_first_login').
--  * credential_version: bumped on every password change or reset, so a
--    session opened with the old password stops working on its next request.
--  * password_resets: one-time reset codes issued by the branch office after
--    it has confirmed who is asking. Only a hash of the code is stored.
-- Safe to re-run on MariaDB (IF NOT EXISTS).

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS credential_version INT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE parents
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS credential_version INT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS credential_version INT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS password_resets (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_type  ENUM('user','student','parent') NOT NULL,
    account_id    INT UNSIGNED NOT NULL,
    code_hash     VARCHAR(255) NOT NULL,
    issued_by     INT UNSIGNED NULL,
    expires_at    DATETIME NOT NULL,
    used_at       DATETIME NULL,
    revoked_at    DATETIME NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reset_account (account_type, account_id),
    KEY idx_reset_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applied-migration ledger used by bin/migrate.php.
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename    VARCHAR(190) NOT NULL PRIMARY KEY,
    checksum    CHAR(64) NOT NULL,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
