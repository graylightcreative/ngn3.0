<?php

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\MigrationService;
use NGN\Lib\DB\SimpleLegacyMigrator;
use Pdo\Mysql;

echo "Running database migrations...\n";

try {
    $config = new Config();
    $migrationService = new MigrationService($config);
    $results = $migrationService->runPendingMigrations();

    if (empty($results)) {
        echo "No pending migrations to apply.\n";
    } else {
        echo "Applied the following migrations:\n";
        foreach ($results as $file => $result) {
            if ($result === true) {
                echo "  - " . $file . "\n";
            } else {
                echo "  - " . $file . " (failed: " . $result . ")\n";
            }
        }
    }

    // Migrate legacy data from SQL dumps
    echo "\nMigrating legacy data from SQL dumps...\n";

    try {
        $legacyMigrator = new SimpleLegacyMigrator($config);
        $legacyResults = $legacyMigrator->migrateAllData();

        foreach ($legacyResults as $database => $details) {
            if ($details['status'] === 'success') {
                echo "  ✓ $database ({$details['name']})\n";
                echo "    " . $details['message'] . "\n";
            } elseif ($details['status'] === 'skipped') {
                echo "  ⊘ $database - {$details['message']}\n";
            } else {
                echo "  ⚠ Error in $database ({$details['name']}): {$details['message']}\n";
            }
        }

    } catch (Exception $e) {
        echo "  ⚠ Error during legacy data migration: " . $e->getMessage() . "\n";
        error_log("Legacy migration error: " . $e->getMessage());
    }

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

