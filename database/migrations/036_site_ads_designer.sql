-- ---------------------------------------------------------------------
-- 036: let an ad be designed in the system instead of uploaded.
--
-- Until now an ad was a title and an uploaded picture, so every notice
-- needed someone with a design tool. The office does not have one, and
-- "enrolment closes on the 30th" does not need one -- it needs a
-- coloured strip with two lines of text and a button.
--
-- An ad with design_template set is drawn by the site from the columns
-- below. An ad with image_path and no template keeps working exactly as
-- it does today, so nothing that exists changes.
--
-- Colours are stored as #rrggbb text and validated in PHP before they
-- are written; they reach a style attribute, so nothing else is let in.
--
-- Additive only. Safe to run more than once.
--
-- Rollback:
--   ALTER TABLE site_ads
--     DROP COLUMN design_template, DROP COLUMN headline,
--     DROP COLUMN subtext, DROP COLUMN button_text,
--     DROP COLUMN bg_color, DROP COLUMN text_color, DROP COLUMN accent_color;
-- ---------------------------------------------------------------------

ALTER TABLE site_ads
    ADD COLUMN IF NOT EXISTS design_template VARCHAR(32)  NULL AFTER image_path,
    ADD COLUMN IF NOT EXISTS headline        VARCHAR(160) NULL AFTER design_template,
    ADD COLUMN IF NOT EXISTS subtext         VARCHAR(400) NULL AFTER headline,
    ADD COLUMN IF NOT EXISTS button_text     VARCHAR(60)  NULL AFTER subtext,
    ADD COLUMN IF NOT EXISTS bg_color        VARCHAR(7)   NULL AFTER button_text,
    ADD COLUMN IF NOT EXISTS text_color      VARCHAR(7)   NULL AFTER bg_color,
    ADD COLUMN IF NOT EXISTS accent_color    VARCHAR(7)   NULL AFTER text_color;
