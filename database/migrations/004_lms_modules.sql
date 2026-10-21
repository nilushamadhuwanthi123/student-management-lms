-- ============================================================
-- 004_lms_modules.sql
--
-- Turns the student management system into a teaching platform:
-- class material (Zoom recordings, reading material, videos) and
-- online quizzes that students sit inside a scheduled window.
--
-- Access to all of it is gated on the month's fee for that class
-- being settled. The gate is not stored here -- it is derived from
-- `payments`, so there is one source of truth for whether a student
-- has paid, and no second copy to drift out of step with it.
-- ============================================================

-- ---------- Class material ----------

CREATE TABLE IF NOT EXISTS `class_materials` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id`        INT UNSIGNED NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `material_type`   ENUM('recording','reading','video','link') NOT NULL DEFAULT 'reading',
  `description`     TEXT DEFAULT NULL,

  -- A material is either an uploaded file or a link to somewhere else
  -- (a Zoom cloud recording, a YouTube video). Both columns are
  -- nullable; the application requires exactly one of them.
  `file_path`       VARCHAR(500) DEFAULT NULL COMMENT 'Relative to storage/uploads/materials',
  `original_name`   VARCHAR(255) DEFAULT NULL,
  `file_size_bytes` INT UNSIGNED DEFAULT NULL,
  `external_url`    VARCHAR(1000) DEFAULT NULL COMMENT 'Zoom / YouTube / Drive link',

  `lesson_date`     DATE DEFAULT NULL COMMENT 'The lesson this belongs to',
  `duration_minutes` SMALLINT UNSIGNED DEFAULT NULL,

  -- Which month this material counts against for the payment gate.
  -- First day of the month, matching payments.month_year.
  `month_year`      DATE NOT NULL,

  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`          ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  `uploaded_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_status` (`status`),
  KEY `idx_class_month` (`class_id`, `month_year`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_materials_uploader` FOREIGN KEY (`uploaded_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which students have opened what. Feeds the engagement half of the
-- progress measure, and lets the teacher see who never watched the
-- recording before sitting the quiz.
CREATE TABLE IF NOT EXISTS `material_views` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_id` INT UNSIGNED NOT NULL,
  `student_id`  INT UNSIGNED NOT NULL,
  `viewed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `view_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_material_student` (`material_id`, `student_id`),
  KEY `idx_student_id` (`student_id`),
  CONSTRAINT `fk_views_material` FOREIGN KEY (`material_id`)
    REFERENCES `class_materials` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_views_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Quizzes ----------

CREATE TABLE IF NOT EXISTS `quizzes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id`      INT UNSIGNED NOT NULL,
  `title`         VARCHAR(255) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `instructions`  TEXT DEFAULT NULL,

  -- The sitting window. A student may only start between these two
  -- moments; time_limit_minutes then caps how long one attempt runs,
  -- so a quiz opened at 19:59 for a 20:00 close still gets its full
  -- time rather than being cut off mid-answer.
  `opens_at`      DATETIME NOT NULL,
  `closes_at`     DATETIME NOT NULL,
  `time_limit_minutes` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'NULL = no limit',

  `total_marks`   DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `pass_mark`     DECIMAL(6,2) DEFAULT NULL,
  `max_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `shuffle_questions` TINYINT(1) NOT NULL DEFAULT 0,
  `show_answers_after` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Reveal correct answers once the window closes',

  `month_year`    DATE NOT NULL COMMENT 'Month this quiz is gated against',
  `quiz_status`   ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_quiz_status` (`quiz_status`),
  KEY `idx_opens_at` (`opens_at`),
  KEY `idx_closes_at` (`closes_at`),
  KEY `idx_month_year` (`month_year`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_quizzes_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_quizzes_creator` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id`         INT UNSIGNED NOT NULL,
  `question_number` SMALLINT UNSIGNED NOT NULL,
  `question_text`   TEXT NOT NULL,
  `question_type`   ENUM('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
  `marks`           DECIMAL(5,2) NOT NULL DEFAULT 1.00,

  `option_a`        VARCHAR(500) DEFAULT NULL,
  `option_b`        VARCHAR(500) DEFAULT NULL,
  `option_c`        VARCHAR(500) DEFAULT NULL,
  `option_d`        VARCHAR(500) DEFAULT NULL,

  -- 'a'..'d' for mcq, 'true'/'false' for true_false. Short answers
  -- carry their expected text instead and are compared case- and
  -- space-insensitively; anything subtler the teacher marks by hand.
  `correct_option`  VARCHAR(10) DEFAULT NULL,
  `correct_text`    VARCHAR(500) DEFAULT NULL,
  `explanation`     TEXT DEFAULT NULL COMMENT 'Shown after the window closes',

  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quiz_question_no` (`quiz_id`, `question_number`),
  KEY `idx_quiz_id` (`quiz_id`),
  CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`)
    REFERENCES `quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quiz_id`        INT UNSIGNED NOT NULL,
  `student_id`     INT UNSIGNED NOT NULL,
  `attempt_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,

  `started_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at`   DATETIME DEFAULT NULL,
  `expires_at`     DATETIME DEFAULT NULL
    COMMENT 'started_at + time limit, fixed at start so a page reload cannot extend it',

  `score`          DECIMAL(6,2) DEFAULT NULL,
  `total_marks`    DECIMAL(6,2) DEFAULT NULL,
  `percentage`     DECIMAL(5,2) DEFAULT NULL,

  -- auto_submitted marks a run the clock ended rather than the student.
  -- Kept separate from 'submitted' because it changes how a low score
  -- should be read.
  `attempt_status` ENUM('in_progress','submitted','auto_submitted','graded') NOT NULL DEFAULT 'in_progress',
  `needs_marking`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Has short answers awaiting the teacher',

  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quiz_student_attempt` (`quiz_id`, `student_id`, `attempt_number`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_attempt_status` (`attempt_status`),
  CONSTRAINT `fk_attempts_quiz` FOREIGN KEY (`quiz_id`)
    REFERENCES `quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quiz_answers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`      INT UNSIGNED NOT NULL,
  `question_id`     INT UNSIGNED NOT NULL,

  `selected_option` VARCHAR(10) DEFAULT NULL,
  `answer_text`     TEXT DEFAULT NULL,

  -- NULL while a short answer is still waiting on the teacher, so an
  -- unmarked answer is distinguishable from one marked wrong.
  `is_correct`      TINYINT(1) DEFAULT NULL,
  `marks_awarded`   DECIMAL(5,2) DEFAULT NULL,
  `marked_by`       INT UNSIGNED DEFAULT NULL,
  `teacher_comment` VARCHAR(500) DEFAULT NULL,

  `answered_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_marked_by` (`marked_by`),
  CONSTRAINT `fk_answers_attempt` FOREIGN KEY (`attempt_id`)
    REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`)
    REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_answers_marker` FOREIGN KEY (`marked_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Parent access ----------
-- `parents` already exists and already links to students. All that is
-- missing for a parent login is somewhere to record that they looked,
-- which the audit log cannot carry because it keys on `users`.

CREATE TABLE IF NOT EXISTS `parent_access_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED NOT NULL,
  `student_id`  INT UNSIGNED NOT NULL,
  `viewed_page` VARCHAR(100) NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_paccess_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `parents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_paccess_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
