-- NGN Stations v2 Missing Tables & Columns
-- Date: 2026-02-21

-- 1. Create Listener Request Queue
CREATE TABLE IF NOT EXISTS `ngn_2025`.`station_listener_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `station_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `request_type` ENUM('song', 'shoutout', 'dedication') NOT NULL,
  `song_title` VARCHAR(255) DEFAULT NULL,
  `song_artist` VARCHAR(255) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `dedicated_to` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'played') DEFAULT 'pending',
  `played_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_station_status` (`station_id`, `status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Geo-Blocking Rules
CREATE TABLE IF NOT EXISTS `ngn_2025`.`geoblocking_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('playlist', 'station_content', 'station') NOT NULL,
  `entity_id` INT NOT NULL,
  `rule_type` ENUM('allow', 'block') DEFAULT 'block',
  `territories` JSON DEFAULT NULL, -- Array of ISO 3166-1 alpha-2 codes
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add station_id to playlists
-- Standard ALTER (Logic in runner will check if exists)
-- ALTER TABLE `ngn_2025`.`playlists` ADD COLUMN `station_id` INT DEFAULT NULL AFTER `user_id`;
-- ALTER TABLE `ngn_2025`.`playlists` ADD COLUMN `geo_restrictions` TINYINT(1) DEFAULT 0 AFTER `station_id`;
-- ALTER TABLE `ngn_2025`.`playlists` ADD INDEX `idx_station_id` (`station_id`);

-- 4. Add station_content_id to playlist_items
-- ALTER TABLE `ngn_2025`.`playlist_items` ADD COLUMN `station_content_id` INT DEFAULT NULL AFTER `track_id`;
-- ALTER TABLE `ngn_2025`.`playlist_items` ADD INDEX `idx_station_content_id` (`station_content_id`);

-- 5. Ensure 'claimed' column exists on core entity tables
-- ALTER TABLE `ngn_2025`.`artists` ADD COLUMN `claimed` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `ngn_2025`.`labels` ADD COLUMN `claimed` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `ngn_2025`.`venues` ADD COLUMN `claimed` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `ngn_2025`.`stations` ADD COLUMN `claimed` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `ngn_2025`.`users` ADD COLUMN `claimed` TINYINT(1) DEFAULT 0;
