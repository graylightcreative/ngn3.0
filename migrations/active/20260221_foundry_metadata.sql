-- NGN 3.0: Foundry Metadata & Merchant Fields
-- Facilitates transition to in-house DTF printing.

ALTER TABLE `ngn_2025`.`products` 
ADD COLUMN `fulfillment_source` VARCHAR(50) DEFAULT 'foundry' AFTER `type`,
ADD COLUMN `requires_quest` TINYINT(1) DEFAULT 1 AFTER `fulfillment_source`,
ADD COLUMN `design_slot_id` INT NULL AFTER `requires_quest`,
ADD COLUMN `design_owner_id` INT NULL AFTER `design_slot_id`;

-- Add slots to subscription tiers
ALTER TABLE `ngn_2025`.`subscription_tiers`
ADD COLUMN `merch_design_slots` INT DEFAULT 0 AFTER `price_cents`;

-- Example Board Rake Table (if not exists)
CREATE TABLE IF NOT EXISTS `ngn_2025`.`board_settlements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `amount_cents` INT NOT NULL,
    `status` ENUM('pending', 'completed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
