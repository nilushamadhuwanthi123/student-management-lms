-- Self-service enrolment: a new student/parent picks a branch + class on
-- the public site, uploads a bank deposit slip as proof of payment, and
-- it waits here for that branch's admin/coordinator to review. Approving
-- a request creates the student (and parent, if a guardian phone was
-- given) account and enrols them in the class, in one step -- same as
-- Students > Add New, just auto-run instead of retyped by hand.
CREATE TABLE IF NOT EXISTS enrollment_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    institute_id        INT UNSIGNED NOT NULL,
    class_id            INT UNSIGNED NOT NULL,
    student_name        VARCHAR(255) NOT NULL,
    date_of_birth       DATE NULL,
    guardian_name       VARCHAR(255) NOT NULL,
    guardian_phone      VARCHAR(50) NOT NULL,
    guardian_email      VARCHAR(255) NULL,
    address             VARCHAR(500) NULL,
    scholarship_type    ENUM('full_payment','half_payment','free_card') NOT NULL DEFAULT 'full_payment',
    amount              DECIMAL(10,2) NULL,
    paid_on             DATE NULL,
    bank_name           VARCHAR(100) NULL,
    bank_reference      VARCHAR(100) NULL,
    slip_path           VARCHAR(255) NOT NULL,
    slip_original_name  VARCHAR(255) NULL,
    status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    review_note         VARCHAR(500) NULL,
    reviewed_by         INT UNSIGNED NULL,
    reviewed_at         DATETIME NULL,
    created_student_id  INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_er_institute (institute_id),
    INDEX idx_er_status (status),
    INDEX idx_er_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
