<?php
require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "Verifying Fleet Health...
";
$config = new Config();

try {
    $pdo = ConnectionFactory::read($config);
    $pdo->query("SELECT 1");
    echo "✅ Primary DB Connection: OK
";
} catch (Exception $e) {
    echo "❌ Primary DB Connection: FAIL
";
    echo "   " . $e->getMessage() . "
";
    exit(1);
}

echo "Fleet is operational.
";
