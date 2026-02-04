<?php

// Migrator is already autoloaded via bootstrap.php in the migration runner

use NGN\Lib\DB\Migrator;
use NGN\Lib\Config; // Add Config use statement

return new class($config) extends Migrator {
    public function __construct(Config $config)
    {
        parent::__construct($config);
    }

    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ngn_2025.live_streams (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                event_id VARCHAR(36) NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                stream_url VARCHAR(2048) NOT NULL COMMENT 'URL to the actual stream content (e.g., CDN link)',
                is_ppv BOOLEAN DEFAULT FALSE,
                price_cents INT NULL COMMENT 'Price in cents if is_ppv is TRUE',
                currency VARCHAR(3) DEFAULT 'USD' COMMENT 'Currency for PPV stream',
                access_type ENUM('free', 'ticketed', 'subscription') DEFAULT 'free' NOT NULL,
                status ENUM('draft', 'scheduled', 'live', 'ended', 'cancelled') DEFAULT 'draft' NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME NULL,
                max_concurrency INT DEFAULT 0 COMMENT 'Max concurrent viewers per user/device. 0 for unlimited.',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                FOREIGN KEY (event_id) REFERENCES ngn_2025.events(id) ON DELETE SET NULL
            );
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ngn_2025.live_stream_access (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                stream_id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                access_granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL COMMENT 'Signed token for stream access',
                is_active BOOLEAN DEFAULT TRUE,
                
                FOREIGN KEY (stream_id) REFERENCES ngn_2025.live_streams(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES ngn_2025.users(id) ON DELETE CASCADE
            );
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ngn_2025.live_stream_sessions (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                stream_id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                device_id VARCHAR(255) NOT NULL COMMENT 'Identifier for the user''s device/browser session',
                session_start DATETIME DEFAULT CURRENT_TIMESTAMP,
                session_end DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                
                UNIQUE KEY unique_session (stream_id, user_id, device_id),
                FOREIGN KEY (stream_id) REFERENCES ngn_2025.live_streams(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES ngn_2025.users(id) ON DELETE CASCADE
            );
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS ngn_2025.live_stream_sessions;");
        $this->db->exec("DROP TABLE IF EXISTS ngn_2025.live_stream_access;");
        $this->db->exec("DROP TABLE IF EXISTS ngn_2025.live_streams;");
    }
};