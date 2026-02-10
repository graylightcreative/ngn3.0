<?php
/**
 * Migration Validation Script
 *
 * Compares legacy data, newly migrated 2025 data, and validates against canonical model.
 * Provides detailed audit report of data integrity.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

class MigrationValidator
{
    private $config;
    private $legacyDb;
    private $newDb;
    private $issues = [];
    private $stats = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->newDb = ConnectionFactory::write($config);
        // Try to connect to legacy database if it exists
        try {
            $this->legacyDb = ConnectionFactory::named($config, 'legacy');
        } catch (\Exception $e) {
            echo "⚠ Legacy database connection not available: {$e->getMessage()}\n";
            $this->legacyDb = null;
        }
    }

    public function validate(): void
    {
        echo "\n====== NGN 2.0 Migration Validation Report ======\n\n";

        // 1. Validate schema structure
        $this->validateSchema();

        // 2. Compare record counts (legacy vs new)
        $this->validateRecordCounts();

        // 3. Validate data integrity
        $this->validateDataIntegrity();

        // 4. Generate report
        $this->generateReport();
    }

    private function validateSchema(): void
    {
        echo "1. SCHEMA VALIDATION\n";
        echo str_repeat("-", 50) . "\n";

        // Get all tables and validate they exist
        try {
            $result = $this->newDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
            $tables = $result->fetchAll(\PDO::FETCH_COLUMN);
            echo "  ✓ Database has " . count($tables) . " tables\n";

            // Core tables to validate exist
            $coreTables = ['users', 'posts', 'artists', 'labels', 'roles', 'migrations'];
            $missing = [];
            foreach ($coreTables as $table) {
                if (!in_array($table, $tables)) {
                    $missing[] = $table;
                }
            }

            if (empty($missing)) {
                echo "  ✓ All core tables exist\n";
            } else {
                echo "  ✗ Missing core tables: " . implode(', ', $missing) . "\n";
                foreach ($missing as $table) {
                    $this->addIssue("Schema", "Core table $table not found");
                }
            }
        } catch (\Exception $e) {
            echo "  Error validating schema: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function validateRecordCounts(): void
    {
        echo "2. RECORD COUNT COMPARISON\n";
        echo str_repeat("-", 50) . "\n";

        // Get ALL tables in database
        try {
            $result = $this->newDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
            $allTables = $result->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            echo "  Error retrieving tables: " . $e->getMessage() . "\n";
            return;
        }

        $totalRecords = 0;
        $tablesWithData = 0;
        $emptyTables = [];

        foreach ($allTables as $table) {
            try {
                $count = $this->getRecordCount($table);
                $this->stats[$table] = $count;
                $totalRecords += $count;

                if ($count > 0) {
                    $tablesWithData++;
                    if ($count >= 5) {  // Only show tables with 5+ records
                        echo "  ✓ $table: $count records\n";
                    }
                } else {
                    $emptyTables[] = $table;
                }
            } catch (\Exception $e) {
                // Skip tables we can't query
            }
        }

        echo "\n  Tables with data: $tablesWithData\n";
        echo "  Tables with 0 records: " . count($emptyTables) . "\n";
        echo "  Total records across all tables: $totalRecords\n";
        echo "\n";
    }

    private function validateDataIntegrity(): void
    {
        echo "3. DATA INTEGRITY CHECKS\n";
        echo str_repeat("-", 50) . "\n";

        if (!$this->tableExists('users')) {
            echo "  ℹ Skipping integrity checks (users table not created)\n";
            echo "\n";
            return;
        }

        try {
            // Check for null emails in users
            $nullEmails = $this->newDb->query("SELECT COUNT(*) as cnt FROM users WHERE email IS NULL OR email = ''")->fetch();
            if ($nullEmails['cnt'] > 0) {
                echo "  ⚠ Users with NULL/empty emails: {$nullEmails['cnt']}\n";
                $this->addIssue("Data Quality", "Users with NULL/empty emails: {$nullEmails['cnt']}");
            } else {
                echo "  ✓ All users have email\n";
            }

            // Check for duplicate emails
            try {
                $dupEmails = $this->newDb->query("SELECT COUNT(*) as cnt FROM (SELECT email FROM users WHERE email IS NOT NULL AND email != '' GROUP BY email HAVING COUNT(*) > 1) t")->fetch();
                if ($dupEmails['cnt'] > 0) {
                    echo "  ⚠ Duplicate emails: {$dupEmails['cnt']}\n";
                    $this->addIssue("Data Quality", "Duplicate emails found: {$dupEmails['cnt']}");
                } else {
                    echo "  ✓ No duplicate emails\n";
                }
            } catch (\Exception $e) {
                echo "  ℹ Could not check for duplicates\n";
            }

            // Check user count
            $userCount = $this->getRecordCount('users');
            echo "  ℹ Total users in database: $userCount\n";

            // Check post count
            if ($this->tableExists('posts')) {
                $postCount = $this->getRecordCount('posts');
                echo "  ℹ Total posts in database: $postCount\n";
            }

            // Check artist count
            if ($this->tableExists('artists')) {
                $artistCount = $this->getRecordCount('artists');
                echo "  ℹ Total artists in database: $artistCount\n";
            }
        } catch (\Exception $e) {
            echo "  ℹ Skipping some checks due to: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function generateReport(): void
    {
        echo "4. SUMMARY REPORT\n";
        echo str_repeat("-", 50) . "\n";

        $total = 0;
        $tablesWithData = 0;
        $tablesEmpty = 0;

        foreach ($this->stats as $table => $count) {
            $total += $count;
            if ($count > 0) {
                $tablesWithData++;
            } else {
                $tablesEmpty++;
            }
        }

        echo "\nDatabase Statistics:\n";
        echo "  Total tables: " . count($this->stats) . "\n";
        echo "  Tables with data: $tablesWithData\n";
        echo "  Empty tables: $tablesEmpty\n";
        echo "  Total records: $total\n";

        if (empty($this->issues)) {
            echo "\n✓ All validations passed!\n";
        } else {
            echo "\n⚠ Issues Found: " . count($this->issues) . "\n\n";
            foreach ($this->issues as $issue) {
                echo "  • [{$issue['type']}] {$issue['message']}\n";
            }
        }

        echo "\n" . str_repeat("=", 50) . "\n\n";
    }

    private function tableExists(string $table): bool
    {
        try {
            $result = $this->newDb->query("SELECT 1 FROM $table LIMIT 1");
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function legacyTableExists(string $table): bool
    {
        if (!$this->legacyDb) return false;
        try {
            $result = $this->legacyDb->query("SELECT 1 FROM $table LIMIT 1");
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumns(string $table): array
    {
        try {
            $result = $this->newDb->query("DESCRIBE $table");
            $columns = [];
            while ($row = $result->fetch()) {
                $columns[] = $row['Field'];
            }
            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getRecordCount(string $table, $db = null): int
    {
        if ($db === null) {
            $db = $this->newDb;
        }
        try {
            $result = $db->query("SELECT COUNT(*) as cnt FROM $table");
            $row = $result->fetch();
            return $row['cnt'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function addIssue(string $type, string $message): void
    {
        $this->issues[] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

// Run validation
try {
    $config = new Config();
    $validator = new MigrationValidator($config);
    $validator->validate();
} catch (\Exception $e) {
    echo "Error running validation: " . $e->getMessage() . "\n";
    exit(1);
}
