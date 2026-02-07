-- NGN 2.0.2: Digital Safety Seal / Content Ledger Migration
-- Corrected for MySQL syntax compatibility

-- 1. Create content_ledger table
CREATE TABLE IF NOT EXISTS `content_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `content_hash` VARCHAR(64) NOT NULL UNIQUE,
    `metadata_hash` VARCHAR(64) NOT NULL,
    `owner_id` BIGINT UNSIGNED NOT NULL,
    `upload_source` VARCHAR(64) NOT NULL,
    `source_record_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255),
    `artist_name` VARCHAR(255),
    `credits` JSON,
    `rights_split` JSON,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(128) NOT NULL,
    `original_filename` VARCHAR(512) NOT NULL,
    `certificate_id` VARCHAR(64) NOT NULL UNIQUE,
    `certificate_issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verification_count` BIGINT UNSIGNED DEFAULT 0,
    `last_verified_at` TIMESTAMP NULL,
    `blockchain_tx_hash` VARCHAR(128) NULL,
    `blockchain_anchored_at` TIMESTAMP NULL,
    `status` ENUM('active', 'disputed', 'revoked', 'transferred') DEFAULT 'active',
    `dispute_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX `idx_owner_id` (`owner_id`),
    INDEX `idx_metadata_hash` (`metadata_hash`),
    INDEX `idx_upload_source` (`upload_source`, `source_record_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create content_ledger_verification_log table
CREATE TABLE IF NOT EXISTS `content_ledger_verification_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ledger_id` BIGINT UNSIGNED NOT NULL,
    `verified_by` VARCHAR(128) NULL,
    `verification_type` ENUM('public_api', 'certificate_scan', 'third_party', 'internal', 'admin'),
    `verification_result` ENUM('match', 'mismatch', 'not_found', 'error'),
    `request_ip` VARCHAR(45) NULL,
    `request_user_agent` VARCHAR(512) NULL,
    `request_referer` VARCHAR(512) NULL,
    `request_metadata` JSON NULL,
    `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ledger_id`) REFERENCES `content_ledger`(`id`) ON DELETE CASCADE,
    INDEX `idx_ledger_id` (`ledger_id`),
    INDEX `idx_verified_at` (`verified_at`),
    INDEX `idx_verification_type` (`verification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add certificate_id to smr_ingestions if missing
-- Using a procedure to safely add column
DROP PROCEDURE IF EXISTS AddCertificateIdToSmrIngestions;
DELIMITER //
CREATE PROCEDURE AddCertificateIdToSmrIngestions()
BEGIN
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='smr_ingestions' AND COLUMN_NAME='certificate_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `smr_ingestions` ADD COLUMN `certificate_id` VARCHAR(64) NULL;
        ALTER TABLE `smr_ingestions` ADD INDEX `idx_certificate_id` (`certificate_id`);
    END IF;
END //
DELIMITER ;
CALL AddCertificateIdToSmrIngestions();
DROP PROCEDURE IF EXISTS AddCertificateIdToSmrIngestions;

-- 4. Add certificate_id to station_content if missing
DROP PROCEDURE IF EXISTS AddCertificateIdToStationContent;
DELIMITER //
CREATE PROCEDURE AddCertificateIdToStationContent()
BEGIN
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='station_content' AND COLUMN_NAME='certificate_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `station_content` ADD COLUMN `certificate_id` VARCHAR(64) NULL;
        ALTER TABLE `station_content` ADD INDEX `idx_certificate_id` (`certificate_id`);
    END IF;
END //
DELIMITER ;
CALL AddCertificateIdToStationContent();
DROP PROCEDURE IF EXISTS AddCertificateIdToStationContent;