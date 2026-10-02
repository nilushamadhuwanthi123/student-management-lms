-- Auth & Security Enhancement Migration
-- Adds login_attempts table for DB-based rate limiting
-- Adds remember_tokens table for persistent login
-- Adds password_changed_at columns

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- login_attempts: DB-based rate limiting
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `key` VARCHAR(100) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`),
    INDEX `idx_first_attempt` (`first_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- remember_tokens: "Remember Me" persistent login
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `user_type` ENUM('user','student','parent') NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_token_hash` (`token_hash`(64)),
    INDEX `idx_user` (`user_id`, `user_type`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add password_changed_at to users
ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME DEFAULT NULL AFTER `password_hash`;

-- Add password_changed_at to students
ALTER TABLE `students` ADD COLUMN `password_changed_at` DATETIME DEFAULT NULL AFTER `password_hash`;

-- Clean up expired login attempts (can be run periodically)
-- DELETE FROM login_attempts WHERE first_attempt_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Clean up expired remember tokens
-- DELETE FROM remember_tokens WHERE expires_at < NOW();
