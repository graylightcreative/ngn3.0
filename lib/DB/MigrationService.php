<?php

namespace NGN\Lib\DB;

use NGN\Lib\Env;
use NGN\Lib\Config;
use PDO;
use PDOException;
use Pdo\Mysql;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class MigrationService
{
    private $config;
    private $db;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = ConnectionFactory::write($config);
        // Enable buffered queries to handle complex migrations with nested SELECT statements
        $this->db->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, true);
    }

    public function runPendingMigrations(): array
    {
        $baseMigrationsDir = __DIR__ . '/../../migrations/active'; // Changed base directory
        $migrationFiles = $this->getPendingMigrations($baseMigrationsDir); // Pass base dir to recursive method
        $results = [];

        foreach ($migrationFiles as $relativePath) {
            $fullPath = $baseMigrationsDir . DIRECTORY_SEPARATOR . $relativePath;
            $results[$relativePath] = $this->runMigration($fullPath, $relativePath); // Pass relative path for tracking
        }

        return $results;
    }

    private function getPendingMigrations(string $baseMigrationsDir): array
    {
        $appliedMigrations = $this->getAppliedMigrations();
        $allSqlFiles = [];
        if (!is_dir($baseMigrationsDir)) {
            return []; // Return empty if directory doesn't exist
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseMigrationsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'sql') {
                // Store path relative to baseMigrationsDir
                $relativePath = str_replace($baseMigrationsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $allSqlFiles[] = $relativePath;
            }
        }
        sort($allSqlFiles); // Ensure files are processed in alphabetical (numerical) order
        return array_diff($allSqlFiles, $appliedMigrations);
    }

    public function getAppliedMigrations(): array
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt = $this->db->prepare("SELECT migration FROM migrations");
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'migration');
    }

    public function runSingleMigration(string $relativePath): bool|string
    {
        $baseMigrationsDir = __DIR__ . '/../../migrations/active';
        $fullPath = $baseMigrationsDir . DIRECTORY_SEPARATOR . $relativePath;
        if (!file_exists($fullPath)) {
            return "File not found: " . $relativePath;
        }
        return $this->runMigration($fullPath, $relativePath);
    }

    private function runMigration(string $filePath, string $relativePath): bool|string
    {
        try {
            $sql = file_get_contents($filePath);
            $statements = [];
            $currentStatement = '';
            $delimiter = ';';

            // Split the SQL into individual statements, respecting DELIMITER
            $lines = explode("\n", $sql);
            foreach ($lines as $line) {
                $trimmedLine = trim($line);

                if (empty($trimmedLine) || substr($trimmedLine, 0, 2) === '--') { // Ignore empty lines and comments
                    continue;
                }

                if (preg_match('/DELIMITER\s+(\S+)/i', $trimmedLine, $matches)) {
                    $delimiter = $matches[1];
                    continue;
                }

                $currentStatement .= $line . "\n";

                // If the current statement ends with the current delimiter, add it to statements array
                if (substr($trimmedLine, -strlen($delimiter)) === $delimiter) {
                    $statements[] = trim(substr($currentStatement, 0, -strlen($delimiter))); // Remove delimiter
                    $currentStatement = '';
                    // Reset delimiter to default if it was custom (e.g., after a trigger block)
                    if ($delimiter !== ';') {
                        $delimiter = ';'; // Assume delimiter is reset
                    }
                }
            }
            // Add any remaining statement if file doesn't end with delimiter (e.g., last statement without ; )
            if (!empty($currentStatement)) {
                $statements[] = trim($currentStatement);
            }

            foreach ($statements as $statement) {
                if (empty($statement)) {
                    continue;
                }
                try {
                    // Use query() instead of exec() to ensure buffering is applied
                    // and results are properly consumed
                    $result = $this->db->query($statement);
                    if ($result) {
                        // Consume any result set to ensure cursor is closed
                        while ($result->fetch()) {
                            // Fetch and discard rows
                        }
                    }
                } catch (PDOException $e) {
                    // Log the error but continue to the next statement
                    error_log("Ignoring error in migration statement: " . $e->getMessage());
                }
            }

            $migrationName = $relativePath; // Use relative path for tracking
            $stmt = $this->db->prepare("INSERT INTO migrations (migration, applied_at) VALUES (?, NOW())");
            $stmt->execute([$migrationName]);
            return true;
        } catch (PDOException $e) {
            $errorMessage = sprintf(
                "Migration failed: %s File: %s Code: %s Line: %s",
                $e->getMessage(),
                $filePath,
                $e->getCode(),
                $e->getLine()
            );
            error_log($errorMessage);
            return $e->getMessage();
        }
    }
}
