<?php
/**
 * remote-finalize.php - Helper to run migrations and recompute on server
 */
require_once __DIR__ . '/../lib/bootstrap.php';
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

echo "🛠️ Server Finalization
";
echo "======================
";

// 1. Run Migrations
echo "Applying migrations...
";
$m1 = file_get_contents(__DIR__ . '/../migrations/active/047_profile_disputes.sql');
$m2 = file_get_contents(__DIR__ . '/../migrations/active/048_royalty_integrity.sql');

try {
    $pdo->exec($m1);
    echo "   [OK] 047 applied.
";
} catch (Exception $e) {
    echo "   [INFO] 047 skip: " . $e->getMessage() . "
";
}

try {
    $pdo->exec($m2);
    echo "   [OK] 048 applied.
";
} catch (Exception $e) {
    echo "   [INFO] 048 skip: " . $e->getMessage() . "
";
}

// 2. Recalculate Rankings
echo "Recalculating Rankings...
";
passthru('php ' . __DIR__ . '/recalculate-rankings.php --force');

echo "
✅ Server Finalization Complete.
";
