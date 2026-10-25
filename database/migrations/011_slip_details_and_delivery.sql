-- ---------------------------------------------------------------------
-- 011: slip details, and online/physical class delivery
-- ---------------------------------------------------------------------
--
-- Builds on 010_payment_slips.sql (which creates payment_slips).
--
-- 1. payment_slips: two optional columns -- the bank name the family
--    typed, and the amount the office actually approved (which can be
--    less than the slip shows, e.g. for a part payment).
--
-- 2. classes: delivery mode, a private online meeting link, and an
--    optional capacity. Defaults keep every existing class exactly as it
--    was ("physical", no link, no limit). The meeting link is shown only
--    to enrolled students whose fee for the month is settled -- never on
--    public pages.
--
-- Additive only (ADD COLUMN IF NOT EXISTS, MariaDB 10.3+). Safe to re-run.
--
-- Rollback:
--   ALTER TABLE payment_slips DROP COLUMN IF EXISTS bank_name, DROP COLUMN IF EXISTS approved_amount;
--   ALTER TABLE classes DROP COLUMN IF EXISTS delivery_mode, DROP COLUMN IF EXISTS meeting_url, DROP COLUMN IF EXISTS capacity;

ALTER TABLE payment_slips
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) NULL AFTER method,
    ADD COLUMN IF NOT EXISTS approved_amount DECIMAL(10,2) NULL AFTER status;

ALTER TABLE classes
    ADD COLUMN IF NOT EXISTS delivery_mode ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical' AFTER room,
    ADD COLUMN IF NOT EXISTS meeting_url VARCHAR(500) NULL AFTER delivery_mode,
    ADD COLUMN IF NOT EXISTS capacity SMALLINT UNSIGNED NULL AFTER meeting_url;
