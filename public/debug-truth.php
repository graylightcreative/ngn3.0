<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: text/plain');

echo "=== NGN GROUND TRUTH DIAGNOSTIC v2 ===\n\n";

$projectRoot = dirname(__DIR__);
$testPaths = [
    'Coldwards Logo' => '/lib/images/users/coldwards/coldward-logo-1.jpg',
    'Default Avatar' => '/lib/images/site/2026/default-avatar.png',
    'Public Symlink' => '/public/lib'
];

foreach ($testPaths as $name => $rel) {
    $abs = $projectRoot . $rel;
    echo "[$name]: $abs\n";
    echo "  Exists: " . (file_exists($abs) ? "YES" : "NO") . "\n";
}

echo "\n=== DATABASE CHECKS ===\n";
try {
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    echo "[Main DB]: CONNECTED\n";
    
    $stmt = $pdo->query("SELECT name, slug, image_url FROM artists WHERE slug = 'coldwards' LIMIT 1");
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Coldwards Data: " . json_encode($artist) . "\n";

    echo "\n[Rankings Check]:\n";
    $rankPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'rankings2025');
    $tables = $rankPdo->query("SHOW TABLES LIKE 'ranking_items'")->fetch();
    echo "  Table Exists: " . ($tables ? "YES" : "NO") . "\n";
    
    $count = $rankPdo->query("SELECT COUNT(*) FROM ranking_items")->fetchColumn();
    echo "  Raw Row Count: $count\n";

    echo "\n[Testing get_top_rankings helper]:\n";
    $_GET['debug_rankings'] = 1;
    $rankings = get_top_rankings($pdo, 'artist', 5);
    echo "  Artist Rankings Found: " . count($rankings) . "\n";
    if (count($rankings) > 0) {
        echo "  Sample: " . $rankings[0]['Name'] . " (Score: " . $rankings[0]['Score'] . ")\n";
    }

} catch (\Throwable $e) {
    echo "[ERROR]: " . $e->getMessage() . "\n";
}
