-- ---------------------------------------------------------------------
-- 026: Sitewide popup flag for website ads, and a public Downloads tab.
--
-- show_as_popup: an existing site_ads row (migration 023) can now also
-- be shown as a dismissible popup on every public page, once per
-- browser session, instead of only in the home page's rotating banner.
--
-- site_downloads: a small, flat list of external links (e.g. a Google
-- Drive folder/file) for past papers and similar documents, managed by
-- the super admin or a teacher, shown on the public Downloads page.
-- Deliberately just a title + link, like the existing site_ads table --
-- the file itself is never stored on this server.
--
-- Additive only. Safe to run more than once.
--
-- Rollback:
--   ALTER TABLE site_ads DROP COLUMN show_as_popup;
--   DROP TABLE IF EXISTS site_downloads;
-- ---------------------------------------------------------------------

ALTER TABLE site_ads
    ADD COLUMN IF NOT EXISTS show_as_popup TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

CREATE TABLE IF NOT EXISTS site_downloads (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(190) NOT NULL,
    drive_url   VARCHAR(500) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_downloads_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
