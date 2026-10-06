-- ============================================================================
-- Student Management LMS - Initial Database Schema
-- Version: 1.0
-- Timezone: Asia/Colombo (Sri Lanka)
-- Currency: LKR
-- Engine: InnoDB, Character Set: utf8mb4, Collation: utf8mb4_unicode_ci
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';
SET time_zone = '+05:30';

-- ============================================================================
-- 1. users
-- Roles: super_admin, admin, coordinator, staff, teacher
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id_display` VARCHAR(20) NOT NULL COMMENT 'e.g. ADM20260001, TCH20260001',
    `role` ENUM('super_admin','admin','coordinator','staff','teacher') NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone1` VARCHAR(20) DEFAULT NULL,
    `phone2` VARCHAR(20) DEFAULT NULL,
    `whatsapp` VARCHAR(20) DEFAULT NULL,
    `home_number` VARCHAR(20) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `profile_photo` VARCHAR(500) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('pending_first_login','active','inactive') NOT NULL DEFAULT 'pending_first_login',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id_display` (`user_id_display`),
    UNIQUE KEY `uk_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_full_name` (`full_name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. students
-- ============================================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id_display` VARCHAR(20) NOT NULL COMMENT 'Auto-generated e.g. STU20260001',
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone1` VARCHAR(20) DEFAULT NULL,
    `phone2` VARCHAR(20) DEFAULT NULL,
    `whatsapp` VARCHAR(20) DEFAULT NULL,
    `home_number` VARCHAR(20) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `profile_photo` VARCHAR(500) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active','inactive','deactivated') NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_id_display` (`student_id_display`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_full_name` (`full_name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. parents
-- ============================================================================
CREATE TABLE IF NOT EXISTS `parents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `parent_name` VARCHAR(255) NOT NULL,
    `phone1` VARCHAR(20) DEFAULT NULL,
    `phone2` VARCHAR(20) DEFAULT NULL,
    `whatsapp` VARCHAR(20) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active','inactive','deactivated') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_parents_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. institutes
-- ============================================================================
CREATE TABLE IF NOT EXISTS `institutes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `coordinator_id` INT UNSIGNED DEFAULT NULL,
    `location` VARCHAR(500) DEFAULT NULL,
    `phone1` VARCHAR(20) DEFAULT NULL,
    `phone2` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_coordinator_id` (`coordinator_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_institutes_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. classes
-- ============================================================================
CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_name` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `medium` VARCHAR(50) DEFAULT NULL COMMENT 'Sinhala, Tamil, English',
    `grade` VARCHAR(50) DEFAULT NULL,
    `institute_id` INT UNSIGNED NOT NULL,
    `room` VARCHAR(100) DEFAULT NULL,
    `teacher_id` INT UNSIGNED DEFAULT NULL,
    `staff_id` INT UNSIGNED DEFAULT NULL,
    `schedule_days` JSON DEFAULT NULL COMMENT '["Monday","Wednesday"]',
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `monthly_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `teacher_revenue_split` DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage e.g. 70.00',
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_institute_id` (`institute_id`),
    INDEX `idx_teacher_id` (`teacher_id`),
    INDEX `idx_staff_id` (`staff_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_subject` (`subject`(100)),
    INDEX `idx_grade` (`grade`),
    CONSTRAINT `fk_classes_institute` FOREIGN KEY (`institute_id`) REFERENCES `institutes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_classes_staff` FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. enrollments
-- ============================================================================
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    `scholarship_type` ENUM('full_payment','half_payment','free_card') NOT NULL DEFAULT 'full_payment',
    `enrollment_status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_class` (`student_id`, `class_id`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_class_id` (`class_id`),
    INDEX `idx_enrollment_status` (`enrollment_status`),
    CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. payments
-- ============================================================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    `month_year` DATE NOT NULL COMMENT 'First day of the month e.g. 2026-09-01',
    `amount_due` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `scholarship_type` ENUM('full_payment','half_payment','free_card') NOT NULL DEFAULT 'full_payment',
    `payment_status` ENUM('pending','paid','overdue','waived') NOT NULL DEFAULT 'pending',
    `paid_at` DATETIME DEFAULT NULL,
    `marked_by` INT UNSIGNED DEFAULT NULL COMMENT 'User who clicked Paid',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_enrollment_id` (`enrollment_id`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_class_id` (`class_id`),
    INDEX `idx_month_year` (`month_year`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_marked_by` (`marked_by`),
    INDEX `idx_student_month` (`student_id`, `month_year`),
    CONSTRAINT `fk_payments_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. fee_risk_status
-- ============================================================================
CREATE TABLE IF NOT EXISTS `fee_risk_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    `risk_state` ENUM('safe','grace','high_risk','deactivated','temporary_access') NOT NULL DEFAULT 'safe',
    `grace_start_date` DATE DEFAULT NULL,
    `deactivation_date` DATE DEFAULT NULL,
    `temp_access_until` DATE DEFAULT NULL,
    `temp_granted_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_class_risk` (`student_id`, `class_id`),
    INDEX `idx_risk_state` (`risk_state`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_class_id` (`class_id`),
    CONSTRAINT `fk_fee_risk_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fee_risk_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. deactivation_settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `deactivation_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. deletion_settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `deletion_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `teacher_deletion_option` ENUM('option1','option2') NOT NULL DEFAULT 'option1',
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. workflows
-- ============================================================================
CREATE TABLE IF NOT EXISTS `workflows` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('deactivation','deletion','temp_activation') NOT NULL,
    `entity_type` ENUM('student') NOT NULL DEFAULT 'student',
    `entity_id` INT UNSIGNED NOT NULL,
    `initiated_by` INT UNSIGNED NOT NULL,
    `initiated_by_role` VARCHAR(50) NOT NULL,
    `current_step` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_steps` INT UNSIGNED NOT NULL DEFAULT 1,
    `current_approver_id` INT UNSIGNED DEFAULT NULL,
    `current_approver_role` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
    `reason` TEXT DEFAULT NULL,
    `combined_reasons` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_initiated_by` (`initiated_by`),
    INDEX `idx_current_approver` (`current_approver_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. workflow_steps
-- ============================================================================
CREATE TABLE IF NOT EXISTS `workflow_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id` INT UNSIGNED NOT NULL,
    `step_number` INT UNSIGNED NOT NULL,
    `approver_id` INT UNSIGNED NOT NULL,
    `approver_role` VARCHAR(50) NOT NULL,
    `action` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `reason` TEXT DEFAULT NULL,
    `acted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_workflow_id` (`workflow_id`),
    INDEX `idx_approver_id` (`approver_id`),
    CONSTRAINT `fk_workflow_steps_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflows`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_id` INT UNSIGNED NOT NULL,
    `recipient_type` ENUM('user','student','parent') NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `notification_type` ENUM('info','warning','workflow','deactivation','deletion','payment','system') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `related_entity_type` VARCHAR(100) DEFAULT NULL,
    `related_entity_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_recipient` (`recipient_id`, `recipient_type`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. workflow_inbox
-- ============================================================================
CREATE TABLE IF NOT EXISTS `workflow_inbox` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_id` INT UNSIGNED NOT NULL,
    `recipient_role` VARCHAR(50) NOT NULL,
    `workflow_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `status` ENUM('pending','acted') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `acted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_recipient_id` (`recipient_id`),
    INDEX `idx_recipient_role` (`recipient_role`),
    INDEX `idx_workflow_id` (`workflow_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_workflow_inbox_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflows`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. audit_log
-- ============================================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `user_type` ENUM('user','student','parent','system') NOT NULL DEFAULT 'system',
    `user_role` VARCHAR(50) DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `entity_type` VARCHAR(100) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL COMMENT 'JSON-encoded details',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_user_type` (`user_type`),
    INDEX `idx_action` (`action`(100)),
    INDEX `idx_entity` (`entity_type`(50), `entity_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 16. dashboard_settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `dashboard_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role` VARCHAR(50) NOT NULL,
    `widget_key` VARCHAR(100) NOT NULL,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_widget` (`role`, `widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 17. system_settings
-- ============================================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 18. user_classes (many-to-many: users <-> classes)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_classes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_class` (`user_id`, `class_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_class_id` (`class_id`),
    CONSTRAINT `fk_user_classes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_user_classes_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 19. attendance
-- Document 1 & 2 requirements: QR scan, Present/Absent, Red flag on deactivation
-- ============================================================================
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED DEFAULT NULL,
    `date` DATE NOT NULL,
    `check_in_time` TIME NOT NULL,
    `status` ENUM('present','late','absent','red_flag') NOT NULL DEFAULT 'present',
    `flagged_reason` VARCHAR(500) DEFAULT NULL,
    `scan_method` ENUM('qr_scan','manual','barcode') NOT NULL DEFAULT 'qr_scan',
    `marked_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_att_student` (`student_id`),
    INDEX `idx_att_class` (`class_id`),
    INDEX `idx_att_date` (`date`),
    INDEX `idx_att_status` (`status`),
    INDEX `idx_att_student_date` (`student_id`, `date`, `class_id`),
    CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_att_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 20. contact_messages
-- Public contact inquiries
-- ============================================================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('new','read','responded') NOT NULL DEFAULT 'new',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_msg_status` (`status`),
    INDEX `idx_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Default Super Admin Record
-- NOTE: The password_hash below is a PLACEHOLDER.
-- In production, generate the real hash using PHP:
--   password_hash('SuperAdmin@123', PASSWORD_DEFAULT)
-- The placeholder below corresponds to bcrypt of 'password' and MUST be replaced.
-- ============================================================================
INSERT INTO `users` (`user_id_display`, `role`, `full_name`, `email`, `phone1`, `password_hash`, `status`, `created_at`)
VALUES (
    'SA20260001',
    'super_admin',
    'System Super Admin',
    'superadmin@lms.lk',
    '+94771234567',
    '$2y$10$hOZwUwyTLdDQz9VMi1XaoOV3P3OXxD5SSP4hr4oLf/04E6a0VqguS',
    'pending_first_login',
    NOW()
);
