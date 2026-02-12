-- Migration: 048_royalty_integrity.sql
-- Add cryptographic hash column to royalty ledger for audit integrity

ALTER TABLE `ngn_2025`.`cdm_royalty_transactions`
ADD COLUMN `integrity_hash` CHAR(64) DEFAULT NULL COMMENT 'SHA-256 Hash of TransactionID + Amount + UserID + Timestamp' AFTER `notes`;

-- Index for quick verification scans
CREATE INDEX `idx_integrity` ON `ngn_2025`.`cdm_royalty_transactions` (`integrity_hash`);
