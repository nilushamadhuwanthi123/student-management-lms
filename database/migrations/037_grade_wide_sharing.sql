-- ---------------------------------------------------------------------
-- 037: let one recording or one piece of material go to a whole grade.
--
-- A recording belongs to one class. That is right for a lesson taught
-- to that class, and wrong for the thing the teacher actually makes
-- most often: a lab demonstration that every Grade 10 class should
-- watch, whichever branch they sit in. Until now she had to add the
-- same video once per class and remember to keep the copies in step.
--
-- A grade-wide share writes one row per class, as before -- so every
-- query that reads "what can this class see" is untouched and student
-- visibility cannot drift -- and stamps the rows with one shared_batch
-- so they can be recognised as one thing: shown as "shared with Grade
-- 10, 4 classes" rather than four unrelated entries, and withdrawn
-- together.
--
-- Additive only. Safe to run more than once. Nothing that exists
-- changes: a row with shared_batch NULL is an ordinary single-class
-- entry and behaves exactly as it does today.
--
-- Rollback:
--   ALTER TABLE recordings      DROP COLUMN shared_batch;
--   ALTER TABLE class_materials DROP COLUMN shared_batch;
-- ---------------------------------------------------------------------

ALTER TABLE recordings
    ADD COLUMN IF NOT EXISTS shared_batch CHAR(32) NULL AFTER class_id,
    ADD INDEX IF NOT EXISTS idx_recordings_shared_batch (shared_batch);

ALTER TABLE class_materials
    ADD COLUMN IF NOT EXISTS shared_batch CHAR(32) NULL AFTER class_id,
    ADD INDEX IF NOT EXISTS idx_materials_shared_batch (shared_batch);
