-- ---------------------------------------------------------------------
-- 023: Standalone promotional banners for the public home page --
-- separate from seminars/events (020-022). A seminar's "show on the
-- website" flag advertises a specific dated event; this table is for
-- a plain announcement (an enrolment call, a scholarship notice, a
-- limited-time offer) that is not tied to any one class session.
--
-- Both kinds of ad end up in the same rotating banner on the home
-- page (see PublicSite::homeAds()), so the super admin has one single
-- place -- Settings > Website Ads -- to manage anything that is not
-- a specific class or seminar.
--
-- Additive only: one new table. Safe to run more than once.
--
-- Rollback (removes only this feature and anything stored in it):
--   DROP TABLE IF EXISTS site_ads;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS site_ads (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(160) NOT NULL,
    image_path  VARCHAR(500) NULL,
    link_url    VARCHAR(500) NULL,
    starts_on   DATE NULL,
    ends_on     DATE NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_ads_active_window (is_active, starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
