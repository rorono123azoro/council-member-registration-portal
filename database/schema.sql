-- Council Member Registration Portal — Database Schema
-- MySQL 8.0 | utf8mb4 | InnoDB
-- Run: mysql -u root -p < database/schema.sql

SET NAMES utf8mb4;
SET time_zone = '+05:30';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `council_portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `council_portal`;

-- ─────────────────────────────────────────
--  council_categories
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `council_categories` (
    `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)     NOT NULL,
    `code`       VARCHAR(20)      NOT NULL,
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_category_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  members
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `members` (
    `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `reg_number`        VARCHAR(20)      NOT NULL,
    `full_name`         VARCHAR(120)     NOT NULL,
    `father_spouse_name`VARCHAR(120)     NOT NULL,
    `dob`               DATE             NOT NULL,
    `gender`            ENUM('M','F','O') NOT NULL,
    `email`             VARCHAR(150)     NOT NULL,
    `mobile`            VARCHAR(10)      NOT NULL,
    `address`           TEXT             NOT NULL,
    `city`              VARCHAR(80)      NOT NULL,
    `state`             VARCHAR(80)      NOT NULL,
    `pin`               CHAR(6)          NOT NULL,
    `category_id`       TINYINT UNSIGNED NOT NULL,
    `id_proof_number`   VARCHAR(60)      NOT NULL,
    `photo_path`        VARCHAR(255)     NOT NULL,
    `id_proof_path`     VARCHAR(255)     NOT NULL,
    `consent`           TINYINT(1)       NOT NULL DEFAULT 0,
    `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason`  TEXT             NULL,
    `ip_address`        VARCHAR(45)      NOT NULL,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reg_number` (`reg_number`),
    UNIQUE KEY `uq_email`      (`email`),
    UNIQUE KEY `uq_mobile`     (`mobile`),
    KEY `idx_status`           (`status`),
    KEY `idx_category`         (`category_id`),
    KEY `idx_created`          (`created_at`),
    CONSTRAINT `fk_member_category`
        FOREIGN KEY (`category_id`) REFERENCES `council_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  admins
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(60)    NOT NULL,
    `email`          VARCHAR(150)   NOT NULL,
    `password_hash`  VARCHAR(255)   NOT NULL,
    `is_active`      TINYINT(1)     NOT NULL DEFAULT 1,
    `failed_attempts`TINYINT        NOT NULL DEFAULT 0,
    `locked_until`   DATETIME       NULL,
    `last_login`     DATETIME       NULL,
    `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_username` (`username`),
    UNIQUE KEY `uq_admin_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  audit_logs
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED    NULL,
    `action`     VARCHAR(80)     NOT NULL,
    `target_type`VARCHAR(40)     NOT NULL,
    `target_id`  BIGINT UNSIGNED NULL,
    `meta`       JSON            NULL,
    `ip_address` VARCHAR(45)     NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_admin`  (`admin_id`),
    KEY `idx_audit_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_audit_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
--  rate_limits
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_hash`   VARCHAR(64)     NOT NULL,
    `action`     VARCHAR(40)     NOT NULL,
    `attempts`   SMALLINT        NOT NULL DEFAULT 1,
    `window_start` DATETIME      NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_key_action` (`key_hash`, `action`),
    KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

