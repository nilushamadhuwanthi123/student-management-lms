-- ---------------------------------------------------------------------
-- 007: what students ask the assistant
-- ---------------------------------------------------------------------
--
-- Kept for one reason: the questions it could NOT answer are the most
-- useful thing it produces. If twenty students a week ask "when is the
-- paper class?" and get "I didn't understand", that is a keyword to add
-- -- or a thing the teacher should be announcing. The log is how anyone
-- finds that out.
--
-- It stores who asked so the teacher can follow up, but the assistant
-- never shows one student's questions to another.

CREATE TABLE IF NOT EXISTS assistant_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asker_type    ENUM('student','parent') NOT NULL,
    asker_id      INT UNSIGNED NOT NULL,
    student_id    INT UNSIGNED NOT NULL,
    question      VARCHAR(300) NOT NULL,
    intent        VARCHAR(40) NOT NULL,
    answered      TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_al_answered (answered, created_at),
    KEY idx_al_student (student_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
