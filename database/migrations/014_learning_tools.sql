-- 014: learning tools.
--  * topics: the teacher's own list of syllabus units per grade and subject.
--    Nothing is pre-filled: the units come from the teacher, not invented here.
--  * topic_id on materials, quizzes, quiz questions and homework (optional tag).
--  * past_papers: papers and marking schemes by grade, year, paper type, medium.
--  * absence_alerts: one alert per student, class and day, so parents are not
--    messaged twice when a register is saved again.
--  * a unique key on attendance (student, class, day). Manual marking already
--    relied on it ("ON DUPLICATE KEY UPDATE") but the key never existed, so a
--    re-mark added a second row. Before adding the key on a server, check:
--      SELECT student_id, class_id, date, COUNT(*) FROM attendance
--       GROUP BY student_id, class_id, date HAVING COUNT(*) > 1;
--    must return no rows (it returned none on the development database).
-- MariaDB syntax (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS topics (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grade       TINYINT UNSIGNED NOT NULL,
    subject     VARCHAR(100) NOT NULL DEFAULT 'Science',
    unit_no     SMALLINT UNSIGNED NULL,
    title       VARCHAR(255) NOT NULL,
    title_si    VARCHAR(255) NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status      ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_topic (grade, subject, title),
    KEY idx_topic_grade (grade, subject, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE class_materials ADD COLUMN IF NOT EXISTS topic_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_material_topic (topic_id);
ALTER TABLE quizzes         ADD COLUMN IF NOT EXISTS topic_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_quiz_topic (topic_id);
ALTER TABLE quiz_questions  ADD COLUMN IF NOT EXISTS topic_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_question_topic (topic_id);
ALTER TABLE assignments     ADD COLUMN IF NOT EXISTS topic_id INT UNSIGNED NULL, ADD INDEX IF NOT EXISTS idx_assignment_topic (topic_id);

CREATE TABLE IF NOT EXISTS past_papers (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grade          TINYINT UNSIGNED NOT NULL,
    subject        VARCHAR(100) NOT NULL DEFAULT 'Science',
    year           SMALLINT UNSIGNED NOT NULL,
    paper_type     ENUM('term1','term2','term3','model','provincial','school','other') NOT NULL DEFAULT 'term1',
    medium         ENUM('sinhala','english','tamil') NOT NULL DEFAULT 'sinhala',
    title          VARCHAR(255) NOT NULL,
    topic_id       INT UNSIGNED NULL,
    paper_path     VARCHAR(500) NOT NULL,
    paper_name     VARCHAR(255) NULL,
    answers_path   VARCHAR(500) NULL,
    answers_name   VARCHAR(255) NULL,
    show_answers   TINYINT(1) NOT NULL DEFAULT 0,
    status         ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    uploaded_by    INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_paper_find (grade, subject, status, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absence_alerts (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id        INT UNSIGNED NOT NULL,
    class_id          INT UNSIGNED NOT NULL,
    date              DATE NOT NULL,
    parents_notified  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    whatsapp_id       INT UNSIGNED NULL,
    created_by        INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_absence_once (student_id, class_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE attendance ADD UNIQUE INDEX IF NOT EXISTS uq_attendance_day (student_id, class_id, date);
