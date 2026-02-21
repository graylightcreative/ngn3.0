<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: text/plain');

echo "=== NGN GROUND TRUTH DIAGNOSTIC ===

";

// 1. Path Resolution Check
$projectRoot = dirname(__DIR__);
$testPaths = [
    'Coldwards Logo' => '/lib/images/users/coldwards/coldward-logo-1.jpg',
    'Default Avatar' => '/lib/images/site/2026/default-avatar.png',
    'Public Symlink' => '/public/lib'
];

foreach ($testPaths as $name => $rel) {
    $abs = $projectRoot . $rel;
    echo "[$name]: $abs
";
    echo "  Exists: " . (file_exists($abs) ? "YES" : "NO") . "
";
    if (is_link($abs)) {
        echo "  Is Link: YES -> " . readlink($abs) . "
";
    }
}

// 2. Database Connectivity & Permissions
echo "
=== DATABASE CHECKS ===
";
try {
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    echo "[Main DB]: CONNECTED
";
    
    $stmt = $pdo->query("SELECT name, slug, image_url FROM artists WHERE slug = 'coldwards' LIMIT 1");
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Coldwards Data: " . json_encode($artist) . "
";

    $rankPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
    echo "[Rankings DB]: CONNECTED
";
    
    $tables = $rankPdo->query("SHOW TABLES LIKE 'ranking_items'")->fetch();
    echo "  ranking_items Table: " . ($tables ? "EXISTS" : "MISSING") . "
";
    
    $count = $rankPdo->query("SELECT COUNT(*) FROM ranking_items")->fetchColumn();
    echo "  ranking_items Row Count: $count
";

} catch (\Throwable $e) {
    echo "[ERROR]: " . $e->getMessage() . "
";
}
