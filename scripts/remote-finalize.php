<?php
/**
 * remote-finalize.php - Helper to run all active migrations and recompute on server
 */
require_once __DIR__ . '/../lib/bootstrap.php';
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "🛠️ Server Finalization\n";
echo "======================\n";

// 1. Run Migrations
echo "Scanning for active migrations...\n";
$migrationFiles = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../migrations/active'));
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'sql') {
        $migrationFiles[] = $file->getPathname();
    }
}
sort($migrationFiles);

foreach ($migrationFiles as $mFile) {
    $name = basename($mFile);
    echo "Applying $name... ";
    try {
        $sql = file_get_contents($mFile);
        $pdo->exec($sql);
        echo "[OK]\n";
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'Duplicate column')) {
            echo "[SKIP] (Already applied)\n";
        } else {
            echo "[FAIL] " . $e->getMessage() . "\n";
        }
    }
}

// 2. Recalculate Rankings
echo "\nRecalculating Rankings...\n";
passthru('php ' . __DIR__ . '/recalculate-rankings.php --force');

echo "\n✅ Server Finalization Complete.\n";
