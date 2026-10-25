-- ---------------------------------------------------------------------
-- 010: bank-slip payments, reviewed by the branch office
-- ---------------------------------------------------------------------
--
-- A student or parent uploads proof of a bank transfer/deposit against
-- one monthly fee. Nothing about the fee changes until a member of staff
-- approves the slip; an uploaded slip is not proof of payment by itself.
-- On approval the existing `payments` row is updated (amount_paid, and
-- payment_status = 'paid' once the full amount is in), so the fee gate,
-- receipts and reports keep a single source of truth.
--
-- Additive only. Safe to run more than once.
-- Rollback: DROP TABLE IF EXISTS payment_slips;

CREATE TABLE IF NOT EXISTS payment_slips (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id         INT UNSIGNED NOT NULL,
    student_id         INT UNSIGNED NOT NULL,
    submitted_by_type  ENUM('student','parent') NOT NULL,
    submitted_by_id    INT UNSIGNED NOT NULL,
    method             ENUM('bank_transfer','bank_deposit','online_banking','other') NOT NULL DEFAULT 'bank_deposit',
    amount             DECIMAL(10,2) NOT NULL,
    paid_on            DATE NOT NULL,
    bank_reference     VARCHAR(100) NULL,
    file_path          VARCHAR(500) NOT NULL,
    original_name      VARCHAR(255) NULL,
    status             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by        INT UNSIGNED NULL,
    reviewed_at        DATETIME NULL,
    review_note        VARCHAR(500) NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_slip_status (status, created_at),
    KEY idx_slip_payment (payment_id),
    KEY idx_slip_student (student_id),
    KEY idx_slip_reference (bank_reference),
    CONSTRAINT fk_slip_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
    CONSTRAINT fk_slip_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
