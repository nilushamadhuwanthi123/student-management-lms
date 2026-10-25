-- ---------------------------------------------------------------------
-- 012: public website -- which branches are shown, and enrolment inquiries
-- ---------------------------------------------------------------------
--
-- institutes.is_public       a branch (and its active classes) appears on
--                            the public site only when this is ticked by
--                            the super admin. Default 0: nothing becomes
--                            public by accident.
-- institutes.public_show_fees  whether that branch's monthly fees may be
--                            shown publicly. Default 0.
-- contact_messages: four nullable columns so an enrolment inquiry can
--                   carry the grade, branch and student name, and be
--                   routed to that branch's office only.
--
-- Additive only, safe to re-run (MariaDB 10.3+).
--
-- Rollback:
--   ALTER TABLE institutes DROP COLUMN IF EXISTS is_public, DROP COLUMN IF EXISTS public_show_fees;
--   ALTER TABLE contact_messages DROP COLUMN IF EXISTS kind, DROP COLUMN IF EXISTS institute_id,
--                                DROP COLUMN IF EXISTS grade, DROP COLUMN IF EXISTS student_name;

ALTER TABLE institutes
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS public_show_fees TINYINT(1) NOT NULL DEFAULT 0 AFTER is_public;

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS kind ENUM('contact','enrollment') NOT NULL DEFAULT 'contact' AFTER id,
    ADD COLUMN IF NOT EXISTS institute_id INT UNSIGNED NULL AFTER kind,
    ADD COLUMN IF NOT EXISTS grade VARCHAR(20) NULL AFTER institute_id,
    ADD COLUMN IF NOT EXISTS student_name VARCHAR(255) NULL AFTER grade;
