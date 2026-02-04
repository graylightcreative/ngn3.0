<?php

namespace NGN\Lib\DB;

use NGN\Lib\Config;
use PDO;
use Exception;

/**
 * SimpleLegacyMigrator
 *
 * Execute legacy SQL files directly without parsing.
 * Much simpler and more reliable than trying to parse complex SQL.
 */
class SimpleLegacyMigrator
{
    private $config;
    private $connections = [];
    private $newDb;

    public function __construct(Config $config)
    {
        $this->config = $config;
        // Get main database connection for running seed migrations
        $this->newDb = ConnectionFactory::write($config);
    }

    public function migrateAllData(): array
    {
        $results = [];
        $legacyDir = realpath(__DIR__ . '/../../storage/uploads/legacy backups');

        if (!$legacyDir || !is_dir($legacyDir)) {
            return ['error' => 'Legacy backups directory not found'];
        }

        // Map legacy SQL files to their target databases
        $migrations = [
            '032925.sql' => ['db' => 'ngn_2025', 'name' => 'General (users, posts, videos, releases, tracks)'],
            'RANKINGS_032925.sql' => ['db' => 'ngn_rankings_2025', 'name' => 'Rankings'],
            'SMR-032925.sql' => ['db' => 'ngn_smr_2025', 'name' => 'SMR Chart'],
            'SPINS_032925.sql' => ['db' => 'ngn_spins_2025', 'name' => 'Spins'],
        ];

        foreach ($migrations as $file => $config) {
            $filePath = $legacyDir . '/' . $file;
            if (file_exists($filePath)) {
                $result = $this->executeSqlFile($filePath, $config['db']);
                $results[$config['db']] = [
                    'name' => $config['name'],
                    'status' => $result['status'],
                    'message' => $result['message'],
                ];
            } else {
                $results[$config['db']] = [
                    'name' => $config['name'],
                    'status' => 'skipped',
                    'message' => "File not found: $file",
                ];
            }
        }

        // Run data transformations for the main database
        error_log("Running legacy data transformations...");
        $transformer = new LegacyDataTransformer($this->newDb);
        $postsTransform = $transformer->transformPosts();
        error_log("Posts transformation: " . $postsTransform['message']);

        // Run seed migrations to transform legacy data to new schema
        $this->runSeedMigrations();

        return $results;
    }

    /**
     * Run seed migrations to transform legacy data to new CDM schema
     */
    private function runSeedMigrations(): void
    {
        $seedsDir = realpath(__DIR__ . '/../../migrations/active/seeds');
        if (!$seedsDir) return;

        // Run transformation migrations (e.g., 30_seed_posts.sql)
        $transformMigrations = [
            '30_seed_posts.sql',
        ];

        foreach ($transformMigrations as $seedFile) {
            $filePath = $seedsDir . '/' . $seedFile;
            if (!file_exists($filePath)) continue;

            try {
                error_log("Running seed migration: $seedFile");
                $sql = file_get_contents($filePath);
                $statements = array_filter(array_map('trim', explode(';', $sql)));

                foreach ($statements as $statement) {
                    if (!empty($statement) && stripos(trim($statement), '--') !== 0) {
                        try {
                            $this->newDb->exec($statement);
                        } catch (\Exception $e) {
                            error_log("Seed migration statement failed: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Error running seed migration $seedFile: " . $e->getMessage());
            }
        }
    }

    private function executeSqlFile(string $filePath, string $dbName): array
    {
        try {
            $pdo = $this->getConnection($dbName);
            $sql = file_get_contents($filePath);

            // Execute the entire SQL file
            // Set error mode to throw exceptions
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Use robust parser for SQL statements
            $statements = SqlStatementParser::parse($sql);

            $transformer = new LegacySqlTransformer();
            $count = 0;
            $transformed = 0;

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        // Try to transform INSERT statements for mapped tables
                        $transformed_statement = $this->tryTransformStatement($statement, $transformer);
                        $was_transformed = false;
                        if ($transformed_statement) {
                            if (stripos($statement, 'posts') !== false) {
                                error_log("Executing transformed posts statement. First 150 chars: " . substr($transformed_statement, 0, 150));
                            }
                            $statement = $transformed_statement;
                            $transformed++;
                            $was_transformed = true;
                        }

                        // Use exec for non-SELECT statements
                        if (stripos(trim($statement), 'SELECT') === 0) {
                            $result = $pdo->query($statement);
                            if ($result) {
                                while ($result->fetch()) {
                                    // Consume result
                                }
                            }
                        } else {
                            $pdo->exec($statement);
                        }
                        $count++;
                        if ($was_transformed && stripos($statement, 'posts') !== false) {
                            error_log("✓ Transformed posts INSERT executed successfully");
                        }
                    } catch (\PDOException $e) {
                        // Log but continue - some statements may fail due to duplicates or constraints
                        if ($was_transformed && stripos($statement, 'posts') !== false) {
                            error_log("✗ Transformed posts INSERT FAILED: " . $e->getMessage());
                            error_log("  Statement: " . substr($statement, 0, 200));
                        }
                        error_log("Statement in $filePath failed: " . $e->getMessage());
                    }
                }
            }

            $msg = "Executed $count SQL statements";
            if ($transformed > 0) {
                $msg .= " ($transformed transformed with legacy column mapping)";
            }

            return [
                'status' => 'success',
                'message' => $msg,
            ];

        } catch (\Exception $e) {
            error_log("Error executing $filePath: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Try to transform a statement using column mappings
     */
    private function tryTransformStatement(string $statement, LegacySqlTransformer $transformer): ?string
    {
        // Extract table name from INSERT statement
        if (preg_match('/^CREATE\s+TABLE/i', $statement)) {
            return null;  // Skip CREATE TABLE statements
        }

        if (!preg_match('/^INSERT\s+INTO\s+`?(\w+)`?/i', $statement, $matches)) {
            return null;  // Not an INSERT statement
        }

        $tableName = $matches[1];

        // Try to transform
        if ($transformer->hasMapping($tableName)) {
            error_log("Attempting to transform INSERT for table: $tableName");
            $transformed = $transformer->transformStatement($statement, $tableName);
            if ($transformed) {
                error_log("✓ Successfully transformed INSERT for table: $tableName");
                return $transformed;
            } else {
                error_log("⚠ Transform returned null for table: $tableName");
            }
        }

        return null;
    }

    private function getConnection(string $dbName): PDO
    {
        if (!isset($this->connections[$dbName])) {
            $mainDb = $this->config->db();
            $host = $mainDb['host'];
            $port = $mainDb['port'];
            $pass = $mainDb['pass'];

            // Try two strategies:
            // 1. aapanel: username = database name (e.g., 'ngn_2025' user for 'ngn_2025' database)
            // 2. Local/Other: use main database user for all databases

            $users = [
                $dbName,           // Strategy 1: aapanel style (username = dbname)
                $mainDb['user'],   // Strategy 2: main user for all databases
            ];

            $lastError = null;
            foreach ($users as $user) {
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ];

                    $this->connections[$dbName] = new PDO($dsn, $user, $pass, $options);
                    return $this->connections[$dbName];
                } catch (\PDOException $e) {
                    $lastError = $e;
                }
            }

            // If both strategies failed, throw the last error
            throw new \Exception("Failed to connect to database $dbName: " . $lastError->getMessage());
        }

        return $this->connections[$dbName];
    }
}
