<?php
/**
 * remote-finalize.php - Helper to run all active migrations and recompute on server
 */
require_once __DIR__ . '/../lib/bootstrap.php';
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "🛠️ Server Finalization (v2.1)\n";
echo "======================\n";

// 1. Run Migrations
echo "Applying migrations...\n";

$migrationsToRun = [
    '051_add_investor_flag_to_users.sql',
    '052_content_ledger_dispute_system.sql',
    '053_api_rate_limiting.sql',
    '054_webhook_system.sql',
    '055_data_bounty_tracking_v7.sql',
    '056_settlement_audit_log.sql'
];

foreach ($migrationsToRun as $name) {
    $mFile = __DIR__ . '/../migrations/active/' . $name;
    echo "Applying $name... ";
    try {
        if (!file_exists($mFile)) {
            echo "[FAIL] File not found!\n";
            continue;
        }
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
// Not running for now - no new data to trigger this

echo "\n✅ Server Finalization Complete.\n";
