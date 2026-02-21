<?php
/**
 * Advanced Migration runner for missing station tables & columns
 */
require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) { return false; }
}

function indexExists($pdo, $table, $index) {
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) { return false; }
}

function safeAddColumn($pdo, $table, $column, $definition) {
    if (!columnExists($pdo, $table, $column)) {
        echo "Adding column $column to $table...\n";
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
            echo "✓ Success\n";
        } catch (\Throwable $e) { echo "✗ Error adding column $column: " . $e->getMessage() . "\n"; }
    } else {
        echo "Column $column already exists in $table. Skipping.\n";
    }
}

function safeAddIndex($pdo, $table, $index, $definition) {
    if (!indexExists($pdo, $table, $index)) {
        echo "Adding index $index to $table...\n";
        try {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX $index $definition");
            echo "✓ Success\n";
        } catch (\Throwable $e) { echo "✗ Error adding index $index: " . $e->getMessage() . "\n"; }
    } else {
        echo "Index $index already exists in $table. Skipping.\n";
    }
}

// 1. & 2. Create tables (handled by file exec)
$sqlFile = __DIR__ . '/../migrations/active/20260221_create_missing_station_tables.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    $queries = preg_split('/;\s*(\n|$)/', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query) || stripos($query, 'ALTER TABLE') !== false) continue;
        try {
            $pdo->exec($query);
            echo "Executed: " . substr($query, 0, 50) . "...\n";
        } catch (\Throwable $e) { echo "Info: " . $e->getMessage() . "\n"; }
    }
}

// 3. Playlists
safeAddColumn($pdo, 'playlists', 'station_id', "INT DEFAULT NULL");
safeAddColumn($pdo, 'playlists', 'geo_restrictions', "TINYINT(1) DEFAULT 0");
safeAddIndex($pdo, 'playlists', 'idx_station_id', "(`station_id`)");

// 4. Playlist Items
safeAddColumn($pdo, 'playlist_items', 'station_content_id', "INT DEFAULT NULL");
safeAddIndex($pdo, 'playlist_items', 'idx_station_content_id', "(`station_content_id`)");

// 5. Claimed column
$entityTables = ['artists', 'labels', 'venues', 'stations', 'users'];
foreach ($entityTables as $table) {
    safeAddColumn($pdo, $table, 'claimed', "TINYINT(1) DEFAULT 0");
}

echo "Advanced migrations completed.\n";
