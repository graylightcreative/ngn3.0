<?php
/**
 * Organize post images by entity into separate directories
 * System posts stay in /storage/uploads/posts/
 * User posts go into /storage/uploads/{entity_type}s/{slug}/posts/
 * Updates database featured_image_url to include full relative path
 *
 * Run with: php scripts/organize-posts-by-author.php [local|remote]
 */

// Determine environment
$env = isset($argv[1]) ? $argv[1] : 'local';

if ($env === 'remote') {
    $baseDir = '/www/wwwroot/beta.nextgennoise.com';
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
    $baseDir = dirname(__DIR__);
}

$uploadsDir = $baseDir . '/storage/uploads';

echo "========================================\n";
echo "Organize Posts by Entity (Hierarchical)\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "Base directory: $baseDir\n";
echo "========================================\n\n";

// Get all posts with images and their entity info
$stmt = $pdo->query("
    SELECT
        p.id,
        p.slug as post_slug,
        p.featured_image_url,
        p.entity_type,
        p.entity_id,
        COALESCE(a.slug, l.slug, s.slug, v.slug) as entity_slug
    FROM ngn_2025.posts p
    LEFT JOIN ngn_2025.artists a ON p.entity_type = 'artist' AND p.entity_id = a.id
    LEFT JOIN ngn_2025.labels l ON p.entity_type = 'label' AND p.entity_id = l.id
    LEFT JOIN ngn_2025.stations s ON p.entity_type = 'station' AND p.entity_id = s.id
    LEFT JOIN ngn_2025.venues v ON p.entity_type = 'venue' AND p.entity_id = v.id
    WHERE p.featured_image_url IS NOT NULL AND p.featured_image_url != ''
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($posts) . " posts with images\n\n";

$moved = 0;
$failed = 0;

foreach ($posts as $post) {
    $currentRelPath = $post['featured_image_url'];
    $oldPath = $uploadsDir . '/' . $currentRelPath;
    
    // If it's already in a subdirectory (contains /), skip unless it's in the old 'posts/' flat dir
    $filename = basename($currentRelPath);
    
    // Determine target path
    if ($post['entity_slug']) {
        $typePlural = $post['entity_type'] . 's';
        $targetRelDir = "{$typePlural}/{$post['entity_slug']}/posts";
        $newRelPath = "{$targetRelDir}/{$filename}";
    } else {
        // System posts stay in posts/
        $targetRelDir = "posts";
        $newRelPath = "posts/{$filename}";
    }

    $targetFullDir = $uploadsDir . '/' . $targetRelDir;
    $newPath = $uploadsDir . '/' . $newRelPath;

    if ($oldPath === $newPath) continue;

    if (file_exists($oldPath)) {
        if (!is_dir($targetFullDir)) {
            mkdir($targetFullDir, 0775, true);
        }

        if (rename($oldPath, $newPath)) {
            echo "  ✓ Moved: {$currentRelPath} → {$newRelPath}\n";
            $moved++;
            $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET featured_image_url = ? WHERE id = ?");
            $updateStmt->execute([$newRelPath, $post['id']]);
        } else {
            echo "  ✗ Failed to move: {$currentRelPath}\n";
            $failed++;
        }
    } else {
        // Source doesn't exist, check if already at destination
        if (file_exists($newPath)) {
            if ($currentRelPath !== $newRelPath) {
                echo "  ✓ Updating DB (already moved): {$newRelPath}\n";
                $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET featured_image_url = ? WHERE id = ?");
                $updateStmt->execute([$newRelPath, $post['id']]);
            }
        } else {
            echo "  ⚠️  File not found anywhere: {$filename}\n";
        }
    }
}

echo "\nSummary: Moved $moved, Failed $failed\n";
echo "Done!\n";