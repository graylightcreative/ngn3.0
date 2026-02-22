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

-- Add custom CSS support for label tenants
ALTER TABLE `ngn_2025`.`labels`
ADD COLUMN `custom_css` TEXT NULL AFTER `slug`;

-- Example Board Rake Table (if not exists)
CREATE TABLE IF NOT EXISTS `ngn_2025`.`board_settlements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `rule_key` VARCHAR(50) NOT NULL,
    `amount_cents` INT NOT NULL,
    `status` ENUM('pending', 'completed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Infrastructure Node Registry
CREATE TABLE IF NOT EXISTS `ngn_2025`.`node_registry` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `node_id` VARCHAR(100) UNIQUE NOT NULL,
    `hostname` VARCHAR(255) NOT NULL,
    `region` VARCHAR(50) NOT NULL,
    `status` ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    `last_heartbeat` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DAO Governance Proposals
CREATE TABLE IF NOT EXISTS `ngn_2025`.`dao_proposals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `funding_target_cents` INT DEFAULT 0,
    `status` ENUM('voting', 'passed', 'rejected', 'executed') DEFAULT 'voting',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- DAO Votes (Quadratic)
CREATE TABLE IF NOT EXISTS `ngn_2025`.`dao_votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `proposal_id` INT NOT NULL,
    `power` DECIMAL(10,4) NOT NULL,
    `support` TINYINT(1) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `one_vote_per_proposal` (`user_id`, `proposal_id`)
);

-- User Locale Preference
ALTER TABLE `ngn_2025`.`users`
ADD COLUMN `locale` VARCHAR(10) DEFAULT 'en' AFTER `RoleId`;

-- Secondary Equity Market
CREATE TABLE IF NOT EXISTS `ngn_2025`.`equity_market_listings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `investment_id` INT NOT NULL,
    `amount_cents` INT NOT NULL,
    `status` ENUM('open', 'sold', 'cancelled', 'locked') DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
