<?php
/**
 * Normalize all post image_url values to consistent format
 * Remove leading slashes and ensure consistency
 * Format should be: filename.jpg or author/filename.jpg (no leading slash)
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
echo "Normalize Post Image URLs\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "========================================\n\n";

// Get all posts with images
$posts = $pdo->query("SELECT id, image_url FROM ngn_2025.posts WHERE image_url IS NOT NULL AND image_url != ''")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($posts) . " posts with images\n\n";

$normalized = 0;
foreach ($posts as $post) {
    $oldUrl = $post['image_url'];

    // Remove leading slash
    $newUrl = ltrim($oldUrl, '/');

    // If they're different, update
    if ($oldUrl !== $newUrl) {
        $stmt = $pdo->prepare("UPDATE ngn_2025.posts SET image_url = ? WHERE id = ?");
        $stmt->execute([$newUrl, $post['id']]);
        $normalized++;

        if ($normalized <= 5) {
            echo "Fixed: '$oldUrl' → '$newUrl'\n";
        }
    }
}

echo "\nTotal normalized: $normalized\n";

// Verify
$stmt = $pdo->query("SELECT COUNT(*) FROM ngn_2025.posts WHERE image_url LIKE '/%'");
$remaining = $stmt->fetchColumn();

echo "Remaining with leading slash: $remaining\n";

if ($remaining === 0) {
    echo "\n✓ All post image URLs normalized!\n";
} else {
    echo "\n⚠️  Some URLs still have issues\n";
}
