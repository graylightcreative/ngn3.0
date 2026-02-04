<?php
/**
 * Check posts table schema
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$config = new Config();
$db = ConnectionFactory::write($config);

// Check if posts table exists and its structure
try {
    $result = $db->query("DESCRIBE posts");
    echo "Posts table exists. Columns:\n";
    $columns = [];
    while ($row = $result->fetch()) {
        $columns[] = $row['Field'];
        echo sprintf("  - %s (%s) %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'] === 'NO' ? '[NOT NULL]' : ''
        );
    }
    echo "\nTotal columns: " . count($columns) . "\n";
} catch (Exception $e) {
    echo "Posts table error: " . $e->getMessage() . "\n";
}
