<?php
/**
 * Database Setup Script for Admin v2
 * Creates all required tables for SMR, Rights, Royalties, Chart QA
 *
 * Usage: php setup/create_admin_tables.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$config = new NGN\Lib\Config();
$pdo = $config->getDatabase();

// Disable foreign key checks temporarily
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

echo "🚀 Creating Admin v2 Tables...\n\n";

$tables = [
    'smr_ingestions' => "
        CREATE TABLE IF NOT EXISTS smr_ingestions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            file_hash VARCHAR(64) UNIQUE,
            file_size INT,
            status ENUM('pending_review', 'pending_finalize', 'finalized', 'error') DEFAULT 'pending_review',
            uploaded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'smr_records' => "
        CREATE TABLE IF NOT EXISTS smr_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ingestion_id BIGINT UNSIGNED NOT NULL,
            artist_name VARCHAR(255) NOT NULL,
            track_title VARCHAR(255) NOT NULL,
            spin_count INT DEFAULT 0,
            add_count INT DEFAULT 0,
            isrc VARCHAR(12),
            station_id INT,
            cdm_artist_id INT,
            status ENUM('pending_mapping', 'mapped', 'imported') DEFAULT 'pending_mapping',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ingestion_id (ingestion_id),
            INDEX idx_status (status),
            INDEX idx_artist_name (artist_name),
            INDEX idx_cdm_artist_id (cdm_artist_id),
            FOREIGN KEY (ingestion_id) REFERENCES smr_ingestions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'smr_identity_map' => "
        CREATE TABLE IF NOT EXISTS smr_identity_map (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            artist_id INT NOT NULL,
            alias_name VARCHAR(255) NOT NULL,
            alias_type ENUM('smr_typo', 'user_submitted', 'system') DEFAULT 'system',
            verified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_alias (artist_id, alias_name),
            INDEX idx_artist_id (artist_id),
            INDEX idx_verified (verified)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_chart_entries' => "
        CREATE TABLE IF NOT EXISTS cdm_chart_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ingestion_id BIGINT UNSIGNED,
            artist_id INT NOT NULL,
            track_title VARCHAR(255) NOT NULL,
            spin_count INT DEFAULT 0,
            add_count INT DEFAULT 0,
            isrc VARCHAR(12),
            week_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_artist_id (artist_id),
            INDEX idx_week_date (week_date),
            INDEX idx_ingestion_id (ingestion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_rights_ledger' => "
        CREATE TABLE IF NOT EXISTS cdm_rights_ledger (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            artist_id INT NOT NULL,
            track_id INT,
            isrc VARCHAR(12),
            owner_id INT NOT NULL,
            status ENUM('pending', 'verified', 'disputed', 'rejected') DEFAULT 'pending',
            verified_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_artist_id (artist_id),
            INDEX idx_status (status),
            INDEX idx_isrc (isrc)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_rights_splits' => "
        CREATE TABLE IF NOT EXISTS cdm_rights_splits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            right_id BIGINT UNSIGNED NOT NULL,
            contributor_id INT NOT NULL,
            percentage DECIMAL(5,2) NOT NULL,
            role VARCHAR(50),
            verified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_right_id (right_id),
            INDEX idx_contributor_id (contributor_id),
            FOREIGN KEY (right_id) REFERENCES cdm_rights_ledger(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_rights_disputes' => "
        CREATE TABLE IF NOT EXISTS cdm_rights_disputes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            right_id BIGINT UNSIGNED NOT NULL,
            reason TEXT,
            resolution TEXT,
            status ENUM('open', 'resolved', 'rejected') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_right_id (right_id),
            INDEX idx_status (status),
            FOREIGN KEY (right_id) REFERENCES cdm_rights_ledger(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_royalty_transactions' => "
        CREATE TABLE IF NOT EXISTS cdm_royalty_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            ingestion_id BIGINT UNSIGNED,
            amount DECIMAL(10,2) NOT NULL,
            eqs_pool_share DECIMAL(5,4),
            calculation_type ENUM('eqs', 'flat', 'adjusted') DEFAULT 'eqs',
            period_start DATE,
            period_end DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_period_start (period_start),
            INDEX idx_period_end (period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'cdm_payout_requests' => "
        CREATE TABLE IF NOT EXISTS cdm_payout_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            stripe_transfer_id VARCHAR(255),
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_requested_at (requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'ngn_score_corrections' => "
        CREATE TABLE IF NOT EXISTS ngn_score_corrections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ingestion_id BIGINT UNSIGNED,
            artist_id BIGINT UNSIGNED NOT NULL,
            original_score DECIMAL(8,2),
            corrected_score DECIMAL(8,2),
            reason VARCHAR(255),
            corrected_by BIGINT UNSIGNED,
            approved TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ingestion_id (ingestion_id),
            INDEX idx_artist_id (artist_id),
            INDEX idx_approved (approved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'ngn_score_disputes' => "
        CREATE TABLE IF NOT EXISTS ngn_score_disputes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ingestion_id BIGINT UNSIGNED,
            artist_id BIGINT UNSIGNED NOT NULL,
            reported_by BIGINT UNSIGNED,
            reason TEXT,
            resolution TEXT,
            status ENUM('open', 'resolved', 'rejected') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_artist_id (artist_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

$created = 0;
$failed = 0;

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ $name\n";
        $created++;
    } catch (PDOException $e) {
        echo "❌ $name - " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Re-enable foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo "\n" . str_repeat("=", 50) . "\n";
echo "Results: $created created, $failed failed\n";
echo str_repeat("=", 50) . "\n";

// Verify
echo "\n📋 Verification:\n";
$stmt = $pdo->query("SHOW TABLES");
$allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$existingTables = array_filter($allTables, function($table) {
    return str_starts_with($table, 'smr_') || str_starts_with($table, 'cdm_');
});

echo "Tables found: " . count($existingTables) . "\n";

foreach ($existingTables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    $count = $stmt->fetchColumn();
    echo "  - $table: $count rows\n";
}

echo "\n✨ Database setup complete!\n";
