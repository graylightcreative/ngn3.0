-- Migration: 047_profile_disputes.sql
-- Create table for profile ownership disputes

CREATE TABLE IF NOT EXISTS `ngn_2025`.`profile_disputes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('artist', 'label', 'venue', 'station') NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `claim_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Optional link to the claim being disputed',
    `disputant_user_id` BIGINT UNSIGNED DEFAULT NULL,
    `disputant_name` VARCHAR(255) NOT NULL,
    `disputant_email` VARCHAR(255) NOT NULL,
    `disputant_phone` VARCHAR(64) DEFAULT NULL,
    `relationship` VARCHAR(100) NOT NULL COMMENT 'Relationship to the entity',
    `reason` TEXT NOT NULL,
    `evidence_url` VARCHAR(1024) DEFAULT NULL COMMENT 'Link to supporting evidence (e.g. storage/vault)',
    `status` ENUM('pending', 'under_review', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    `resolution_notes` TEXT DEFAULT NULL,
    `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`disputant_email`),
    FOREIGN KEY (`claim_id`) REFERENCES `ngn_2025`.`pending_claims`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
