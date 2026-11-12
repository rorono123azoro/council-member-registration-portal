-- Council Member Registration Portal — Database Schema
-- MySQL 8.0 / MariaDB 10.4+ | utf8mb4 | InnoDB
-- Run: mysql -u root -p < database/schema.sql

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `council_portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `council_portal`;

-- ─────────────────────────────────────────
--  categories
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,
    `code`        VARCHAR(20)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_code` (`code`),
    KEY `idx_categories_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  admins
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(120) NOT NULL,
    `username`             VARCHAR(60)  NOT NULL,
    `email`                VARCHAR(150) NOT NULL,
    `password_hash`        VARCHAR(255) NOT NULL,
    `role`                 ENUM('reviewer','super_admin') NOT NULL DEFAULT 'reviewer',
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1)   NOT NULL DEFAULT 0,
    `last_login`           DATETIME     NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_username` (`username`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  members
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `members` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reg_no`             VARCHAR(25)     NOT NULL,
    `full_name`          VARCHAR(120)    NOT NULL,
    `father_spouse_name` VARCHAR(120)    NOT NULL,
    `dob`                DATE            NOT NULL,
    `gender`             ENUM('male','female','other') NOT NULL,
    `email`              VARCHAR(150)    NOT NULL,
    `mobile`             VARCHAR(10)     NOT NULL,
    `address`            VARCHAR(500)    NOT NULL,
    `city`               VARCHAR(80)     NOT NULL,
    `state`              VARCHAR(80)     NOT NULL,
    `pincode`            CHAR(6)         NOT NULL,
    `category_id`        INT UNSIGNED    NOT NULL,
    `id_proof_type`      VARCHAR(40)     NOT NULL DEFAULT 'PAN Card',
    `id_proof_no`        VARCHAR(60)     NOT NULL,
    `consent`            TINYINT(1)      NOT NULL DEFAULT 0,
    `status`             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `decision_reason`    VARCHAR(500)    NULL,
    `reviewed_by`        INT UNSIGNED    NULL,
    `reviewed_at`        DATETIME        NULL,
    `ip_address`         VARCHAR(45)     NOT NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_members_reg_no` (`reg_no`),
    UNIQUE KEY `uq_members_email`  (`email`),
    UNIQUE KEY `uq_members_mobile` (`mobile`),
    KEY `idx_members_status_created` (`status`, `created_at`),
    KEY `idx_members_created_at`     (`created_at`),
    KEY `idx_members_category`       (`category_id`),
    CONSTRAINT `fk_members_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_members_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  documents
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`     BIGINT UNSIGNED NOT NULL,
    `doc_type`      ENUM('photo','id_proof') NOT NULL,
    `original_name` VARCHAR(255)    NOT NULL,
    `stored_path`   VARCHAR(255)    NOT NULL,
    `mime_type`     VARCHAR(100)    NOT NULL,
    `file_size`     INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_documents_member_type` (`member_id`, `doc_type`),
    KEY `idx_documents_member` (`member_id`),
    CONSTRAINT `fk_documents_member`
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  audit_log (append-only)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED    NULL,
    `action`      VARCHAR(80)     NOT NULL,
    `target_type` VARCHAR(40)     NOT NULL,
    `target_id`   BIGINT UNSIGNED NULL,
    `old_value`   TEXT            NULL,
    `new_value`   TEXT            NULL,
    `ip_address`  VARCHAR(45)     NOT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_admin`   (`admin_id`),
    KEY `idx_audit_target`  (`target_type`, `target_id`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  login_attempts
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `ip_address`      VARCHAR(45)      NOT NULL,
    `identifier`      VARCHAR(150)     NOT NULL,
    `attempts`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`    DATETIME         NULL,
    `last_attempt_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_login_ip_identifier` (`ip_address`, `identifier`),
    KEY `idx_login_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  rate_limits
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `key_hash`     VARCHAR(64)       NOT NULL,
    `action`       VARCHAR(40)       NOT NULL,
    `attempts`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` DATETIME          NOT NULL,
    `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_key_action` (`key_hash`, `action`),
    KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
