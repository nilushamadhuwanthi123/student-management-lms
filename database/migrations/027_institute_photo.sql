-- ---------------------------------------------------------------------
-- 027: A photo and a short public description per branch (institute),
-- shown on the public "Our branches" cards and the new Classes landing
-- page. Editable from Institutes > Edit, super admin or that branch's
-- own admin.
--
-- Additive only. Safe to run more than once.
--
-- Rollback:
--   ALTER TABLE institutes DROP COLUMN photo, DROP COLUMN public_description;
-- ---------------------------------------------------------------------

ALTER TABLE institutes
    ADD COLUMN IF NOT EXISTS photo VARCHAR(500) NULL AFTER location,
    ADD COLUMN IF NOT EXISTS public_description VARCHAR(500) NULL AFTER photo;
