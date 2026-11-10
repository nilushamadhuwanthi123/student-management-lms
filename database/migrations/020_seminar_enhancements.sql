-- ---------------------------------------------------------------------
-- 020: Seminar / Event enhancements -- capacity, waitlist, attendance.
--
-- Registration alone does not tell a branch office who actually turned
-- up, and a popular open day has no way to stop registering people past
-- the room's capacity. This migration adds both, on top of the
-- existing seminars / seminar_registrations tables from 018:
--
--   seminars.max_capacity          NULL = unlimited (today's behaviour,
--                                   unchanged for every existing row).
--   seminar_registrations.status   'registered' (counts against
--                                   capacity) or 'waitlisted' (did not
--                                   fit when they registered; promoted
--                                   automatically if a spot frees up).
--   seminar_registrations.attended /
--   seminar_registrations.attended_at
--                                   set by staff at or after the event,
--                                   separate from registering -- a
--                                   registration is an RSVP, attendance
--                                   is what actually happened.
--
-- Additive only: new nullable/defaulted columns, no existing column,
-- row or constraint is touched. Every existing registration becomes
-- status='registered', attended=0, which is exactly today's behaviour.
-- Safe to run more than once.
--
-- Rollback:
--   ALTER TABLE seminar_registrations DROP COLUMN attended_at, DROP COLUMN attended, DROP COLUMN status;
--   ALTER TABLE seminars DROP COLUMN max_capacity;
-- ---------------------------------------------------------------------

ALTER TABLE seminars
    ADD COLUMN IF NOT EXISTS max_capacity INT UNSIGNED NULL COMMENT 'NULL = unlimited' AFTER registration_required;

ALTER TABLE seminar_registrations
    ADD COLUMN IF NOT EXISTS status ENUM('registered','waitlisted') NOT NULL DEFAULT 'registered' AFTER user_id,
    ADD COLUMN IF NOT EXISTS attended TINYINT(1) NOT NULL DEFAULT 0 AFTER registered_at,
    ADD COLUMN IF NOT EXISTS attended_at DATETIME NULL AFTER attended;
