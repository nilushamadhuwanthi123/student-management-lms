-- ---------------------------------------------------------------------
-- 035: CMS-managed photos/videos for the home page hero background
-- slideshow (previously four fixed files in public/assets/img), so the
-- super admin can add, remove, reorder and replace the background
-- slides from Settings > Homepage Background without a code change.
--
-- Each row is one slide: an uploaded photo OR a short uploaded video,
-- played in the same crossfading slideshow that already existed. If no
-- active row exists, the page falls back to the original four photos,
-- so an untouched install looks identical to today.
--
-- Additive only. Safe to run more than once.
--
-- Rollback:
--   DROP TABLE IF EXISTS hero_media;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS hero_media (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_type       ENUM('image','video') NOT NULL DEFAULT 'image',
    file_path        VARCHAR(500) NOT NULL,
    object_position  VARCHAR(40) NOT NULL DEFAULT '50% 50%',
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    sort_order       INT NOT NULL DEFAULT 0,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hero_media_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
