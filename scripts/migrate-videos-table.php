<?php
/**
 * Videos Table Schema Migration
 * Migrates the videos table from PascalCase to snake_case column names
 * This script was created because the videos table was missed in the initial 2.0.1 migration
 */

require __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "========================================\n";
echo "Videos Table Schema Migration\n";
echo "========================================\n\n";

// Column name mappings
$columnMappings = [
    'Id' => 'id',
    'ArtistId' => 'artist_id',
    'Platform' => 'platform',
    'VideoId' => 'video_id',
    'Title' => 'title',
    'Slug' => 'slug',
    'Body' => 'body',
    'Summary' => 'summary',
    'Tags' => 'tags',
    'Misc' => 'misc',
    'Created' => 'created_at',
    'Updated' => 'updated_at',
    'ReleaseDate' => 'release_date'
];

// Check current schema
echo "Checking current schema...\n";
$stmt = $pdo->query("DESCRIBE `ngn_2025`.`videos`");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$columnDetails = [];
foreach ($columns as $col) {
    $columnDetails[$col['Field']] = $col;
}

// Verify we need to migrate
$needsMigration = false;
foreach ($columnMappings as $oldName => $newName) {
    if (isset($columnDetails[$oldName])) {
        $needsMigration = true;
        break;
    }
}

if (!$needsMigration) {
    echo "✓ Videos table is already migrated to snake_case\n";
    exit(0);
}

echo "Videos table requires migration\n\n";

// Start migration
echo "Starting migration...\n";
$migrated = 0;
$failed = 0;

foreach ($columnMappings as $oldName => $newName) {
    if (!isset($columnDetails[$oldName])) {
        echo "  ✓ Column '$newName' already exists (skip)\n";
        continue;
    }

    $oldDetails = $columnDetails[$oldName];
    $type = $oldDetails['Type'];
    $null = $oldDetails['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
    $default = '';

    // Skip invalid default values for datetime columns
    if ($oldDetails['Default'] !== null && $oldDetails['Default'] !== '0000-00-00 00:00:00') {
        // Don't add default for timestamp columns with invalid defaults
        if (!in_array($oldName, ['Created', 'Updated', 'ReleaseDate'])) {
            $default = " DEFAULT '" . str_replace("'", "\'", $oldDetails['Default']) . "'";
        }
    }

    $sql = "ALTER TABLE `ngn_2025`.`videos` CHANGE COLUMN `$oldName` `$newName` $type $null$default";

    try {
        $pdo->exec($sql);
        echo "  ✓ Renamed '$oldName' → '$newName'\n";
        $migrated++;
    } catch (Throwable $e) {
        echo "  ✗ Failed to rename '$oldName' → '$newName': " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n========================================\n";
echo "Migration Complete\n";
echo "========================================\n";
echo "Migrated: $migrated columns\n";
echo "Failed: $failed columns\n";

if ($failed === 0) {
    echo "\n✓ Videos table successfully migrated to snake_case\n";
    exit(0);
} else {
    echo "\n✗ Migration completed with errors\n";
    exit(1);
}
