-- 015: student motivation.
--  * student_achievements: the date each achievement was first earned, so the
--    date shown never moves. Achievements are computed from real records
--    (quiz attempts, homework, attendance, material opened); this table only
--    remembers when each one first became true.
--  * student_goals: simple goals set by the student or by a teacher. Progress
--    is always computed live from the records, never typed in.
-- Streaks and levels are computed, not stored.
-- MariaDB syntax (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS student_achievements (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED NOT NULL,
    code        VARCHAR(40) NOT NULL,
    earned_at   DATETIME NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_achievement (student_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_goals (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    goal_type       ENUM('quiz_average','attendance','homework','topic') NOT NULL,
    target_value    DECIMAL(5,2) NOT NULL DEFAULT 0,
    class_id        INT UNSIGNED NULL,
    topic_id        INT UNSIGNED NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    set_by_type     ENUM('student','user') NOT NULL DEFAULT 'student',
    set_by_id       INT UNSIGNED NULL,
    note            VARCHAR(255) NULL,
    status          ENUM('active','achieved','missed','removed') NOT NULL DEFAULT 'active',
    achieved_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_goal_student (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
