-- ---------------------------------------------------------------------
-- 018: Seminar / Event management.
--
-- Deliberately its own table, not a repurposed notice: a seminar has an
-- event date/time, a venue or an online link, optional registration,
-- and -- the one thing notices cannot already do -- can target a whole
-- GRADE across every branch at once (e.g. "every Grade 8 student,
-- Pannipitiya/Galle/Kandy alike"), not just one branch or one class.
--
-- target_scope mirrors notices.audience but adds 'grade':
--   all    every student/parent/staff (global only)
--   branch one institute (institute_id)
--   grade  one grade, optionally narrowed to one branch
--          (institute_id NULL = every branch, set = just that branch)
--   class  one class (class_id)
--
-- Posting/notifying/registration reuse the exact same services
-- (AnnouncementService::notifyAll/familiesFor, BranchScope) the notice
-- board already uses -- no parallel notification machinery.
--
-- Additive only: two new tables. No existing table, column or row is
-- touched. Safe to run more than once.
--
-- Rollback (removes only this feature and anything stored in it):
--   DROP TABLE IF EXISTS seminar_registrations;
--   DROP TABLE IF EXISTS seminars;
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS seminars (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title                 VARCHAR(255) NOT NULL,
    description           TEXT NULL,
    cover_image_url       VARCHAR(1000) NULL,
    event_date            DATE NOT NULL,
    start_time            TIME NULL,
    end_time              TIME NULL,
    venue                 VARCHAR(255) NULL COMMENT 'Physical location, if any',
    online_url            VARCHAR(1000) NULL COMMENT 'Online meeting link, if any',
    target_scope          ENUM('all','branch','grade','class') NOT NULL DEFAULT 'branch',
    institute_id          INT UNSIGNED NULL,
    grade                 VARCHAR(50) NULL COMMENT 'Matches classes.grade, e.g. "Grade 8"',
    class_id              INT UNSIGNED NULL,
    for_students          TINYINT(1) NOT NULL DEFAULT 1,
    for_parents           TINYINT(1) NOT NULL DEFAULT 1,
    for_staff             TINYINT(1) NOT NULL DEFAULT 0,
    registration_required TINYINT(1) NOT NULL DEFAULT 0,
    status                ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',
    notified_at           DATETIME NULL,
    created_by            INT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_seminar_scope (status, target_scope, institute_id, grade, class_id),
    KEY idx_seminar_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One RSVP per person per seminar. user_type keeps this one table
-- usable for a student, a parent or a staff member registering.
CREATE TABLE IF NOT EXISTS seminar_registrations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seminar_id    INT UNSIGNED NOT NULL,
    user_type     ENUM('student','parent','user') NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seminar_registration (seminar_id, user_type, user_id),
    CONSTRAINT fk_seminarreg_seminar FOREIGN KEY (seminar_id) REFERENCES seminars (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
