-- ---------------------------------------------------------------------
-- 016: a Class Recordings module, kept deliberately separate from
-- class_materials -- a published lesson recording lives in its own
-- tab, its own list, its own "add recording" form, never mixed in with
-- homework, assignments, quizzes or general learning material.
--
-- It reuses the same targeting and fee-gate pattern every other class
-- feature already uses (class_id -> ContentAccessService), so nothing
-- new has to be taught to "who can see this".
--
-- Additive only: one new table. No existing table, column or row is
-- touched. Safe to run more than once.
--
-- Rollback (removes only this feature and anything stored in it):
--   DROP TABLE IF EXISTS recordings;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS recordings (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id        INT UNSIGNED NOT NULL,
    topic_id        INT UNSIGNED NULL COMMENT 'The lesson/topic this recording covers, if any',
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    video_url       VARCHAR(1000) NOT NULL COMMENT 'YouTube / Google Drive / other hosting link',
    thumbnail_url   VARCHAR(500) NULL,
    recording_date  DATE NULL,

    -- Which month this recording counts against for the payment gate,
    -- same convention as class_materials.month_year.
    month_year      DATE NOT NULL,

    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    notified_at     DATETIME NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rec_class (class_id, status),
    KEY idx_rec_topic (topic_id),
    CONSTRAINT fk_rec_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
