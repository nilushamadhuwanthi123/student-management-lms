-- ---------------------------------------------------------------------
-- 028: An optional video link per branch (institute), shown next to the
-- branch photo on the public "Our branches" cards and the Classes
-- landing page. Editable from Institutes > Edit, super admin or that
-- branch's own admin -- same place the photo and description are set.
--
-- A link only (YouTube, Facebook, or any playable URL), not an upload:
-- keeps this light on disk and avoids re-building video-file validation.
--
-- Additive only. Safe to run more than once.
--
-- Rollback:
--   ALTER TABLE institutes DROP COLUMN video_url;
-- ---------------------------------------------------------------------

ALTER TABLE institutes
    ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) NULL AFTER public_description;
