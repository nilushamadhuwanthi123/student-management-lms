-- ---------------------------------------------------------------------
-- 019: A small, fixed set of editable text blocks for the public
-- website -- the hero paragraph on the home page, the "In class"
-- paragraph and an optional extra story paragraph on the About page,
-- and the intro line on the Contact page.
--
-- This is deliberately narrow, not a general page builder: every
-- number, branch and class name on the public site still comes from
-- real records (see PublicSite), never from free text here. What this
-- table holds is editorial copy only -- the kind of wording a school
-- would want to tweak themselves without asking a developer -- and the
-- fixed key list (SiteContentService::FIELDS) is the only thing that
-- can ever be written here, so this can never grow into an arbitrary
-- HTML injection point.
--
-- Additive only: one new table. No existing table, column or row is
-- touched. Safe to run more than once.
--
-- Rollback (removes only this feature and anything stored in it):
--   DROP TABLE IF EXISTS site_content;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS site_content (
    content_key   VARCHAR(64) NOT NULL,
    content_value TEXT NULL,
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
