<?php
/**
 * Detailed Data Comparison Tool
 *
 * Compare legacy and migrated data side-by-side for specific tables.
 * Usage: php scripts/compare-data.php [table] [limit]
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

class DataComparator
{
    private $config;
    private $legacyDb;
    private $newDb;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->newDb = ConnectionFactory::write($config);
        try {
            $this->legacyDb = ConnectionFactory::named($config, 'legacy');
        } catch (\Exception $e) {
            echo "Error: Legacy database not available: {$e->getMessage()}\n";
            exit(1);
        }
    }

    public function compare(string $table, int $limit = 10): void
    {
        echo "\n====== Data Comparison: $table ======\n\n";

        // Define legacy-to-new table mappings
        $mappings = [
            'users' => ['legacy' => 'users', 'new' => 'cdm_users', 'key' => 'Id', 'showColumns' => ['Id', 'Email', 'Title', 'StatusId', 'created_at']],
            'artists' => ['legacy' => 'Artists', 'new' => 'cdm_artists', 'key' => 'Id', 'showColumns' => ['Id', 'Name', 'Slug', 'created_at']],
            'posts' => ['legacy' => 'posts', 'new' => 'cdm_posts', 'key' => 'Id', 'showColumns' => ['Id', 'Title', 'Slug', 'AuthorId', 'published_at']],
            'stations' => ['legacy' => 'Stations', 'new' => 'cdm_stations', 'key' => 'Id', 'showColumns' => ['Id', 'CallSign', 'Market', 'created_at']],
        ];

        if (!isset($mappings[$table])) {
            echo "Available tables for comparison:\n";
            foreach (array_keys($mappings) as $t) {
                echo "  - $t\n";
            }
            exit(1);
        }

        $mapping = $mappings[$table];

        // Get legacy data
        echo "LEGACY DATA (from {$mapping['legacy']}):\n";
        echo str_repeat("-", 100) . "\n";

        try {
            $legacyRecords = $this->getLegacyRecords($mapping['legacy'], $mapping['showColumns'], $limit);
            $this->displayRecords($legacyRecords, $mapping['showColumns']);
        } catch (\Exception $e) {
            echo "Error reading legacy data: {$e->getMessage()}\n";
            return;
        }

        // Get new data
        echo "\nMIGRATED DATA (from {$mapping['new']}):\n";
        echo str_repeat("-", 100) . "\n";

        try {
            $newRecords = $this->getNewRecords($mapping['new'], $limit);
            $this->displayRecords($newRecords, array_keys($newRecords[0] ?? []));
        } catch (\Exception $e) {
            echo "Error reading new data: {$e->getMessage()}\n";
            return;
        }

        // Compare
        echo "\nCOMPARISON ANALYSIS:\n";
        echo str_repeat("-", 100) . "\n";
        $this->analyzeComparison($legacyRecords, $newRecords, $mapping);
    }

    private function getLegacyRecords(string $table, array $columns, int $limit): array
    {
        $columnList = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $stmt = $this->legacyDb->query("SELECT $columnList FROM `$table` LIMIT $limit");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?? [];
    }

    private function getNewRecords(string $table, int $limit): array
    {
        $stmt = $this->newDb->query("SELECT * FROM `$table` LIMIT $limit");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?? [];
    }

    private function displayRecords(array $records, array $columns): void
    {
        if (empty($records)) {
            echo "  (no records)\n";
            return;
        }

        // Calculate column widths
        $widths = [];
        foreach ($columns as $col) {
            $widths[$col] = max(strlen($col), 20);
        }

        // Header
        foreach ($columns as $col) {
            echo str_pad($col, $widths[$col]) . " | ";
        }
        echo "\n";
        echo str_repeat("-", array_sum($widths) + (count($columns) * 3)) . "\n";

        // Rows
        foreach ($records as $record) {
            foreach ($columns as $col) {
                $value = $record[$col] ?? '';
                if (is_null($value)) {
                    $value = '(NULL)';
                } elseif (strlen($value) > $widths[$col] - 2) {
                    $value = substr($value, 0, $widths[$col] - 5) . '...';
                }
                echo str_pad($value, $widths[$col]) . " | ";
            }
            echo "\n";
        }
    }

    private function analyzeComparison(array $legacyRecords, array $newRecords, array $mapping): void
    {
        echo "  Legacy Records: " . count($legacyRecords) . "\n";
        echo "  Migrated Records: " . count($newRecords) . "\n";

        if (count($legacyRecords) !== count($newRecords)) {
            echo "  ⚠ Record count mismatch!\n";
        } else {
            echo "  ✓ Record counts match\n";
        }

        // Sample data quality checks
        $nullCount = 0;
        foreach ($newRecords as $record) {
            foreach ($record as $value) {
                if ($value === null) {
                    $nullCount++;
                }
            }
        }

        if ($nullCount > 0) {
            echo "  ⚠ Found $nullCount NULL values in migrated data\n";
        }
    }
}

// Parse arguments
$table = $argv[1] ?? null;
$limit = isset($argv[2]) ? (int)$argv[2] : 10;

if (!$table) {
    echo "Usage: php scripts/compare-data.php [table] [limit]\n";
    echo "Example: php scripts/compare-data.php users 20\n\n";

    $config = new Config();
    $comparator = new DataComparator($config);
    $comparator->compare('users', 5); // Show sample
} else {
    $config = new Config();
    $comparator = new DataComparator($config);
    $comparator->compare($table, $limit);
}
