-- ---------------------------------------------------------------------
-- 017: Super Admin role-based feature show/hide.
--
-- This only ever controls whether a NAVIGATION LINK is rendered for a
-- role. It is a UI convenience, never an authorization check: every
-- route this touches keeps exactly the same backend guard
-- (BranchScope, ownership, role) it already had, completely
-- independent of this table. Hiding a link here does not and must
-- never make the underlying page/action unreachable or un-guarded --
-- someone who already has it bookmarked, or who types the URL
-- directly, is still subject to the real (unchanged) authorization
-- check in that controller.
--
-- The Settings area itself is deliberately never a row in this table
-- (see Setting::FEATURE_MODULES) -- it is shown to super_admin only,
-- by role, in the layout, independent of this table -- so there is no
-- way to use this feature to lock Super Admin out of the page that
-- manages it.
--
-- Additive only: one new table. No existing table, column or row is
-- touched. Safe to run more than once.
--
-- Rollback (removes only this feature and anything stored in it):
--   DROP TABLE IF EXISTS feature_settings;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS feature_settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role          VARCHAR(32) NOT NULL,
    feature_key   VARCHAR(64) NOT NULL,
    is_visible    TINYINT(1) NOT NULL DEFAULT 1,
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feature_role (role, feature_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
