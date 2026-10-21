-- ============================================================
-- 005_whatsapp.sql
--
-- Message templates and a send log for WhatsApp.
--
-- There is no WhatsApp Business API behind this, and the schema does
-- not pretend otherwise. Messages are composed here and handed to
-- WhatsApp through a click-to-chat link, which is how a tuition class
-- of this size actually sends them. What the system owns is the part
-- worth owning: the wording, who it was meant for, and whether anyone
-- has sent it.
--
-- `sent` therefore means "a person opened this and said they sent it",
-- not "a server confirmed delivery". The column is named and commented
-- so nobody later mistakes the one for the other.
-- ============================================================

CREATE TABLE IF NOT EXISTS `message_templates` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key`  VARCHAR(60) NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `description`   VARCHAR(500) DEFAULT NULL,

  -- Placeholders are {student}, {class}, {month}, {amount}, {quiz},
  -- {marks}, {percentage}, {teacher}, {branch}. Anything unrecognised
  -- is left alone rather than blanked, so a typo shows up in the
  -- preview instead of silently deleting half the sentence.
  `body`          TEXT NOT NULL,

  `category`      ENUM('fee','result','attendance','announcement','other')
                  NOT NULL DEFAULT 'other',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_key` (`template_key`),
  KEY `idx_category` (`category`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_templates_creator` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Who it is about, and who it goes to. These differ: a fee reminder
  -- is about the student but is sent to the guardian's number.
  `student_id`   INT UNSIGNED DEFAULT NULL,
  `class_id`     INT UNSIGNED DEFAULT NULL,
  `payment_id`   INT UNSIGNED DEFAULT NULL,

  `recipient_name`  VARCHAR(255) NOT NULL,
  `recipient_phone` VARCHAR(30) NOT NULL COMMENT 'Normalised to 94XXXXXXXXX',
  `recipient_type`  ENUM('student','parent','other') NOT NULL DEFAULT 'parent',

  `template_key` VARCHAR(60) DEFAULT NULL,

  -- The text as it was at the moment of sending, not a reference to a
  -- template that may since have been reworded. A log that changes
  -- retrospectively cannot answer "what did we actually tell them".
  `body`         TEXT NOT NULL,

  `send_status`  ENUM('queued','sent','skipped','failed') NOT NULL DEFAULT 'queued'
                 COMMENT 'sent = a person opened the link and confirmed; not a delivery receipt',
  `sent_at`      DATETIME DEFAULT NULL,
  `sent_by`      INT UNSIGNED DEFAULT NULL,
  `note`         VARCHAR(500) DEFAULT NULL,

  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_send_status` (`send_status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_template_key` (`template_key`),
  CONSTRAINT `fk_wa_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wa_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wa_payment` FOREIGN KEY (`payment_id`)
    REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_wa_sender` FOREIGN KEY (`sent_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting templates. Wording is plain and short on purpose: these go
-- to parents on a phone, often in a hurry.
INSERT IGNORE INTO `message_templates` (`template_key`, `name`, `description`, `body`, `category`) VALUES
('fee_reminder', 'Fee reminder', 'Sent to a guardian when a month is unsettled.',
 'Hello, this is a reminder that the {month} class fee for {student} ({class}) is Rs. {amount} and is still outstanding. Class material and quizzes stay locked until it is settled. Thank you. - {teacher}',
 'fee'),
('fee_overdue', 'Overdue fee', 'Firmer wording for a month already past due.',
 'Hello, the {month} class fee for {student} ({class}), Rs. {amount}, is now overdue. Please settle it so {student} can get back into the class material and quizzes. If there is a difficulty, please let us know. - {teacher}',
 'fee'),
('fee_received', 'Payment received', 'Confirmation once a fee is recorded.',
 'Thank you. The {month} class fee for {student} ({class}) has been received. The class material and quizzes are open again. - {teacher}',
 'fee'),
('quiz_result', 'Quiz result', 'Sent after a quiz is marked.',
 'Hello, {student} scored {marks} ({percentage}) on {quiz} in {class}. The full paper and the correct answers are on the student portal. - {teacher}',
 'result'),
('absence_alert', 'Absence', 'Sent when a student misses a class.',
 'Hello, {student} was marked absent from {class} today. The lesson recording is on the portal. - {teacher}',
 'attendance'),
('announcement', 'Announcement', 'General notice to a class.',
 'Hello, a note about {class}: ', 'announcement');
