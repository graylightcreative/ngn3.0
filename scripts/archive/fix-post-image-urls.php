<?php
/**
 * Fix post image_url values - remove leading slash to prevent //
 * image_url should be: filename.jpg or author/filename.jpg
 * NOT: /filename.jpg or /author/filename.jpg
 */

$env = isset($argv[1]) ? $argv[1] : 'local';

if ($env === 'remote') {
    $pdo = new PDO(
        'mysql:host=server.starrship1.com;dbname=ngn_2025',
        'nextgennoise',
        'NextGenNoise!1',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} else {
    require __DIR__ . '/../lib/bootstrap.php';
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::write($config);
}

echo "========================================\n";
echo "Fix Post Image URLs\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "========================================\n\n";

// Find posts with leading slash in image_url
$stmt = $pdo->query("SELECT id, image_url FROM ngn_2025.posts WHERE image_url LIKE '/%'");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($posts) . " posts with leading slash in image_url\n\n";

$fixed = 0;
foreach ($posts as $post) {
    $oldUrl = $post['image_url'];
    $newUrl = ltrim($oldUrl, '/');

    $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET image_url = ? WHERE id = ?");
    $updateStmt->execute([$newUrl, $post['id']]);
    $fixed++;

    if ($fixed <= 5) {
        echo "Fixed: '$oldUrl' -> '$newUrl'\n";
    }
}

echo "\nTotal fixed: $fixed\n";

// Verify
$stmt = $pdo->query("SELECT COUNT(*) FROM ngn_2025.posts WHERE image_url LIKE '/%'");
$remaining = $stmt->fetchColumn();

echo "Remaining with leading slash: $remaining\n";

if ($remaining === 0) {
    echo "\n✓ All post image URLs fixed!\n";
} else {
    echo "\n✗ Some URLs still have issues\n";
}
