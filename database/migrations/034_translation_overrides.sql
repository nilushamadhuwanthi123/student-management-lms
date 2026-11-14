-- Lets the super admin re-word ANY fixed text on the site (nav labels, button
-- text, headings, "Miss"/"with Science" style template phrases, etc.) from one
-- CMS screen, in either language, without a code change. Every such string
-- already passes through t()/te() with its English wording as the lookup key
-- (see config/lang.php), so overriding by that same key here covers the whole
-- site -- admin UI included -- from a single table instead of touching each
-- view individually.
--
-- source_hash exists only so the unique key fits MySQL's index byte limit:
-- a VARCHAR(500) utf8mb4 column is too wide to index directly, but its MD5 is
-- a fixed 32 chars. source_text itself stays unindexed and is only read back
-- for display in the admin screen.
CREATE TABLE IF NOT EXISTS translation_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_text VARCHAR(500) NOT NULL,
    source_hash CHAR(32) GENERATED ALWAYS AS (MD5(source_text)) STORED,
    lang ENUM('en', 'si') NOT NULL,
    value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_translation_overrides_hash_lang (source_hash, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
