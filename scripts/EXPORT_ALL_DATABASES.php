<?php
/**
 * EXPORT_ALL_DATABASES.php
 * Exports all 4 databases with complete schema and data
 *
 * Usage: php scripts/EXPORT_ALL_DATABASES.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "EXPORTING ALL NGN 2.0.1 BETA DATABASES\n";
echo str_repeat("=", 80) . "\n\n";

$databases = [
    'ngn_2025' => 'Main application database',
    'ngn_rankings_2025' => 'Rankings and scoring database',
    'ngn_smr_2025' => 'SMR charts database',
    'ngn_spins_2025' => 'Radio spins database'
];

$exportDir = dirname(__DIR__) . '/storage/exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
    echo "[OK] Created export directory: $exportDir\n\n";
}

$exports = [];
$timestamp = date('Y-m-d_H-i-s');

foreach ($databases as $dbName => $description) {
    echo "[EXPORTING] $dbName - $description\n";

    $fileName = "{$dbName}_export_{$timestamp}.sql";
    $filePath = "$exportDir/$fileName";
    $file = fopen($filePath, 'w');

    if (!$file) {
        echo "  [ERROR] Could not open file for writing: $filePath\n";
        continue;
    }

    // Write header
    fwrite($file, "-- NGN 2.0.1 Beta Database Export\n");
    fwrite($file, "-- Database: $dbName\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Description: $description\n");
    fwrite($file, "/*!40101 SET NAMES utf8mb4 */;\n\n");

    // Get all tables
    $stmt = $db->prepare("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ? ORDER BY TABLE_NAME");
    $stmt->execute([$dbName]);
    $tables = $stmt->fetchAll();

    if (empty($tables)) {
        echo "  [WARNING] No tables found in database\n";
        fclose($file);
        continue;
    }

    $tableCount = 0;
    $rowCount = 0;

    foreach ($tables as $tableRow) {
        $tableName = $tableRow['TABLE_NAME'];
        $tableCount++;

        // Get CREATE TABLE statement
        $stmt = $db->prepare("SHOW CREATE TABLE `$dbName`.`$tableName`");
        $stmt->execute();
        $createResult = $stmt->fetch();
        if ($createResult) {
            fwrite($file, "\nDROP TABLE IF EXISTS `$tableName`;\n");
            fwrite($file, $createResult['Create Table'] . ";\n");
        }

        // Get row count
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `$dbName`.`$tableName`");
        $stmt->execute();
        $countResult = $stmt->fetch();
        $rows = $countResult['cnt'] ?? 0;
        $rowCount += $rows;

        // Get data
        if ($rows > 0) {
            $stmt = $db->prepare("SELECT * FROM `$dbName`.`$tableName`");
            $stmt->execute();
            $data = $stmt->fetchAll();

            if (!empty($data)) {
                $cols = implode('`, `', array_keys($data[0]));
                fwrite($file, "\nINSERT INTO `$tableName` (`$cols`) VALUES\n");

                $valueStrings = [];
                foreach ($data as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $valueStrings[] = "(" . implode(',', $values) . ")";
                }

                fwrite($file, implode(",\n", $valueStrings) . ";\n");
            }
        }
    }

    fclose($file);
    echo "  [OK] Tables: $tableCount, Rows: $rowCount\n";
    echo "  [OK] Exported to: storage/exports/$fileName\n";
    echo "  [OK] File size: " . formatBytes(filesize($filePath)) . "\n\n";

    $exports[$dbName] = [
        'file' => $fileName,
        'path' => $filePath,
        'tables' => $tableCount,
        'rows' => $rowCount,
        'size' => filesize($filePath)
    ];
}

// Generate summary
echo str_repeat("=", 80) . "\n";
echo "EXPORT SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

$totalSize = 0;
$totalTables = 0;
$totalRows = 0;

foreach ($exports as $dbName => $info) {
    echo "[$dbName]\n";
    echo "  File: " . $info['file'] . "\n";
    echo "  Tables: " . $info['tables'] . "\n";
    echo "  Rows: " . $info['rows'] . "\n";
    echo "  Size: " . formatBytes($info['size']) . "\n\n";

    $totalSize += $info['size'];
    $totalTables += $info['tables'];
    $totalRows += $info['rows'];
}

echo str_repeat("-", 80) . "\n";
echo "Total tables: $totalTables\n";
echo "Total rows: $totalRows\n";
echo "Total export size: " . formatBytes($totalSize) . "\n\n";

echo "All exports saved to: storage/exports/\n";
echo str_repeat("=", 80) . "\n\n";

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
