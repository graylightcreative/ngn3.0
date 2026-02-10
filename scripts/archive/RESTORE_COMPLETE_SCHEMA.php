<?php
/**
 * RESTORE_COMPLETE_SCHEMA.php
 * Applies all pending core migrations to restore complete 2.0.1 beta schema
 *
 * Usage: php scripts/RESTORE_COMPLETE_SCHEMA.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\MigrationService;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$migrationSvc = new MigrationService($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESTORING COMPLETE NGN 2.0.1 BETA SCHEMA\n";
echo str_repeat("=", 80) . "\n\n";

// Step 1: Get applied and pending migrations
try {
    $appliedMigrations = $migrationSvc->getAppliedMigrations();
    echo "[OK] Connected to database\n";
    echo "[INFO] Applied migrations so far: " . count($appliedMigrations) . "\n";

    if (!empty($appliedMigrations)) {
        echo "[INFO] Previously applied:\n";
        foreach ($appliedMigrations as $migration) {
            echo "      - $migration\n";
        }
    }
} catch (Exception $e) {
    echo "[ERROR] Failed to connect: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "RUNNING PENDING MIGRATIONS\n";
echo str_repeat("-", 80) . "\n\n";

// Step 2: Run all pending migrations
$results = $migrationSvc->runPendingMigrations();

$successCount = 0;
$failureCount = 0;

if (empty($results)) {
    echo "[INFO] No pending migrations found. Schema is up to date.\n";
} else {
    foreach ($results as $migrationName => $result) {
        if ($result === true) {
            echo "[✓] Applied: $migrationName\n";
            $successCount++;
        } else {
            echo "[✗] Failed: $migrationName\n";
            echo "    Error: $result\n";
            $failureCount++;
        }
    }
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "MIGRATION SUMMARY\n";
echo str_repeat("-", 80) . "\n";
echo "Successfully applied: $successCount\n";
echo "Failed: $failureCount\n";
echo "\n";

// Step 3: Verify table counts
try {
    $db = ConnectionFactory::write($config);

    $databases = ['ngn_2025', 'ngn_rankings_2025', 'ngn_smr_2025', 'ngn_spins_2025'];

    echo "\n" . str_repeat("-", 80) . "\n";
    echo "TABLE INVENTORY\n";
    echo str_repeat("-", 80) . "\n\n";

    foreach ($databases as $dbName) {
        $stmt = $db->prepare("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?");
        $stmt->execute([$dbName]);
        $result = $stmt->fetch();
        $tableCount = $result['table_count'] ?? 0;

        echo "[$dbName]\n";
        echo "  Total tables: $tableCount\n";

        // Show table names
        $stmt = $db->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ? ORDER BY TABLE_NAME");
        $stmt->execute([$dbName]);
        $tables = $stmt->fetchAll();

        if (!empty($tables)) {
            foreach ($tables as $table) {
                echo "    - " . $table['TABLE_NAME'] . "\n";
            }
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Failed to verify tables: " . $e->getMessage() . "\n";
}

echo str_repeat("=", 80) . "\n";
echo "RESTORATION COMPLETE\n";
echo str_repeat("=", 80) . "\n\n";

if ($failureCount > 0) {
    echo "WARNING: Some migrations failed. Please review errors above.\n";
    exit(1);
} else {
    echo "SUCCESS: All migrations applied successfully!\n";
    echo "\nThe complete 2.0.1 beta schema has been restored.\n";
    exit(0);
}
?>
