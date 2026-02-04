<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/DB/Migrator.php'; // Ensure Migrator base class is loaded

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\DB\Migrator; // Use the Migrator class

echo "Running PHP database migrations...\n";

try {
    $config = new Config();
    $pdo = ConnectionFactory::write($config);

    // Ensure the php_migrations table exists to track PHP migrations
    $pdo->exec(
        "\n        CREATE TABLE IF NOT EXISTS ngn_2025.php_migrations (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            migration VARCHAR(255) NOT NULL UNIQUE,\n            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");

    // Get list of applied PHP migrations
    $stmt = $pdo->query("SELECT migration FROM ngn_2025.php_migrations");
    $appliedMigrations = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'migration');

    // Scan migrations/active directory recursively for PHP files
    $baseMigrationsDir = __DIR__ . '/../migrations/active';
    $phpMigrationFiles = [];

    if (is_dir($baseMigrationsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseMigrationsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Store relative path from base migrations dir
                $relativePath = str_replace($baseMigrationsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $phpMigrationFiles[] = $relativePath;
            }
        }
    }

    sort($phpMigrationFiles); // Ensure migrations run in order

    $newlyApplied = [];

    foreach ($phpMigrationFiles as $file) {
        if (in_array($file, $appliedMigrations)) {
            echo "  - Skipping {$file} (already applied)\n";
            continue;
        }

        echo "  - Applying {$file}...\n";
        try {
            $filePath = $baseMigrationsDir . DIRECTORY_SEPARATOR . $file;
            // Use a helper function to execute the require in the correct scope with $config available
            $migration = (function() use ($filePath, $config) {
                return require $filePath;
            })();

            // If the migration class extends Migrator, it needs a PDO connection
            // Pass the current PDO connection to it.
            // The anonymous class extends NGN\Lib\DB\Migrator, and its constructor
            // in lib/DB/Migrator.php takes a Config object.
            // So, for now, we'll instantiate it with the config.
            // If the anonymous class returns an *instance* of Migrator, then it's already constructed.
            // If it returns a class definition, we need to instantiate it.
            // The "return new class extends Migration { ... }" syntax returns an instance.
            // So we just need to call its `up()` method.

            if ($migration instanceof Migrator) {
                // The anonymous class already has its constructor called, and it sets up its PDO.
                // We just need to ensure it's using the correct connection as expected.
                // For a simpler case, we assume the anonymous class's up() method
                // uses the $this->pdo which is set by its parent Migrator's constructor.
                $migration->up();
            } else {
                // Fallback for non-Migrator classes with an 'up' method
                if (is_callable([$migration, 'up'])) {
                    $migration->up();
                } else {
                    throw new \Exception("Invalid migration format for file: {$file}");
                }
            }
            
            // Record as applied
            $stmt = $pdo->prepare("INSERT INTO ngn_2025.php_migrations (migration) VALUES (?)");
            $stmt->execute([$file]);
            $newlyApplied[] = $file;
            echo "    [OK]\n";

        } catch (Exception $e) {
            echo "    [FAILED] Error: " . $e->getMessage() . "\n";
            // Optionally re-throw or log and continue
        }
    }

    if (empty($newlyApplied)) {
        echo "No new PHP migrations to apply.\n";
    } else {
        echo "Successfully applied " . count($newlyApplied) . " PHP migration(s).\n";
        foreach ($newlyApplied as $mig) {
            echo "  - {$mig}\n";
        }
    }

} catch (Exception $e) {
    echo "An error occurred during PHP migrations: " . $e->getMessage() . "\n";
}
