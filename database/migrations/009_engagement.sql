-- ---------------------------------------------------------------------
-- 009: homework, questions to the teacher, support requests, notices
-- ---------------------------------------------------------------------
--
-- Additive only: five new tables. No existing table, column or row is
-- touched. Safe to run more than once.
--
-- Rollback (removes only these features and anything stored in them):
--   DROP TABLE IF EXISTS help_messages;
--   DROP TABLE IF EXISTS help_threads;
--   DROP TABLE IF EXISTS assignment_submissions;
--   DROP TABLE IF EXISTS assignments;
--   DROP TABLE IF EXISTS notices;

-- Homework set for one class.
CREATE TABLE IF NOT EXISTS assignments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id      INT UNSIGNED NOT NULL,
    title         VARCHAR(255) NOT NULL,
    instructions  TEXT NULL,
    due_at        DATETIME NOT NULL,
    total_marks   DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    allow_file    TINYINT(1) NOT NULL DEFAULT 1,
    status        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    notified_at   DATETIME NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asg_class (class_id, status),
    CONSTRAINT fk_asg_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One hand-in per student per assignment. Re-submitting before the
-- deadline replaces the earlier answer; marking is kept separately.
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id  INT UNSIGNED NOT NULL,
    student_id     INT UNSIGNED NOT NULL,
    answer_text    TEXT NULL,
    file_path      VARCHAR(500) NULL,
    original_name  VARCHAR(255) NULL,
    submitted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late        TINYINT(1) NOT NULL DEFAULT 0,
    marks          DECIMAL(6,2) NULL,
    feedback       TEXT NULL,
    marked_by      INT UNSIGNED NULL,
    marked_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission (assignment_id, student_id),
    KEY idx_sub_student (student_id),
    CONSTRAINT fk_sub_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_student    FOREIGN KEY (student_id)    REFERENCES students (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A conversation: either a student's question about a class (answered
-- by that class's teacher) or a support request to the branch office
-- (from a student or a parent).
CREATE TABLE IF NOT EXISTS help_threads (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind           ENUM('question','support') NOT NULL,
    student_id     INT UNSIGNED NOT NULL,
    parent_id      INT UNSIGNED NULL,
    class_id       INT UNSIGNED NULL,
    institute_id   INT UNSIGNED NULL,
    subject        VARCHAR(255) NOT NULL,
    status         ENUM('open','in_progress','answered','resolved','closed') NOT NULL DEFAULT 'open',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_reply_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ht_student (student_id, kind),
    KEY idx_ht_class (class_id, status),
    KEY idx_ht_institute (institute_id, status),
    CONSTRAINT fk_ht_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_messages (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id      INT UNSIGNED NOT NULL,
    author_type    ENUM('student','parent','user') NOT NULL,
    author_id      INT UNSIGNED NOT NULL,
    body           TEXT NOT NULL,
    file_path      VARCHAR(500) NULL,
    original_name  VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hm_thread (thread_id),
    CONSTRAINT fk_hm_thread FOREIGN KEY (thread_id) REFERENCES help_threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notice board. audience decides the reach:
--   all    - every branch (super admin only)
--   branch - one branch (institute_id)
--   class  - one class (class_id)
-- publish_at in the future keeps a published notice hidden until then.
CREATE TABLE IF NOT EXISTS notices (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(255) NOT NULL,
    body          TEXT NOT NULL,
    audience      ENUM('all','branch','class') NOT NULL DEFAULT 'branch',
    institute_id  INT UNSIGNED NULL,
    class_id      INT UNSIGNED NULL,
    for_students  TINYINT(1) NOT NULL DEFAULT 1,
    for_parents   TINYINT(1) NOT NULL DEFAULT 1,
    for_staff     TINYINT(1) NOT NULL DEFAULT 0,
    status        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    publish_at    DATETIME NULL,
    notified_at   DATETIME NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notice_scope (status, audience, institute_id, class_id),
    KEY idx_notice_publish (publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
