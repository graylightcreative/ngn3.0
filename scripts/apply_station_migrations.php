<?php
/**
 * Migration runner for missing station tables
 */
require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

$sqlFile = __DIR__ . '/../migrations/active/20260221_create_missing_station_tables.sql';

if (!file_exists($sqlFile)) {
    die("Migration file not found: $sqlFile
");
}

$sql = file_get_contents($sqlFile);

// Split into individual queries (basic split by semicolon + newline)
$queries = preg_split('/;\s*(
|$)/', $sql);

echo "Starting migrations...
";

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;
    
    try {
        echo "Executing: " . substr($query, 0, 50) . "...
";
        $pdo->exec($query);
        echo "✓ Success
";
    } catch (\Throwable $e) {
        echo "✗ Failed: " . $e->getMessage() . "
";
    }
}

echo "Migrations completed.
";
