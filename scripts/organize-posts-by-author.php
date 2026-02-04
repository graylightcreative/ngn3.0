<?php
/**
 * Organize post images by author into separate directories
 * System posts stay in /storage/uploads/posts/
 * User posts go into /storage/uploads/posts/{username}/
 * Updates database image_url to include author path
 *
 * Run with: php scripts/organize-posts-by-author.php [local|remote]
 * Defaults to local if no argument provided
 */

// Determine environment
$env = isset($argv[1]) ? $argv[1] : 'local';

if ($env === 'remote') {
    // Remote database connection
    $baseDir = '/www/wwwroot/beta.nextgennoise.com';
    $pdo = new PDO(
        'mysql:host=server.starrship1.com;dbname=ngn_2025',
        'nextgennoise',
        'NextGenNoise!1',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} else {
    // Local environment
    require __DIR__ . '/../lib/bootstrap.php';

    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::write($config);
    $baseDir = dirname(__DIR__);
}

$postsDir = $baseDir . '/storage/uploads/posts';

echo "========================================\n";
echo "Organize Posts by Author\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "Base directory: $baseDir\n";
echo "========================================\n\n";

// Get all posts with images and their authors
$stmt = $pdo->query("
    SELECT
        p.id,
        p.slug,
        p.image_url,
        p.author_id,
        u.username
    FROM ngn_2025.posts p
    LEFT JOIN ngn_2025.users u ON p.author_id = u.id
    WHERE p.image_url IS NOT NULL AND p.image_url != ''
    ORDER BY p.author_id
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($posts) . " posts with images\n\n";

$moved = 0;
$failed = 0;
$currentAuthor = null;
$currentDir = null;

foreach ($posts as $post) {
    $isSystemPost = empty($post['author_id']) || $post['author_id'] === null;
    $authorName = $isSystemPost ? 'SYSTEM' : $post['username'];

    // Show progress for new author
    if ($currentAuthor !== $authorName) {
        echo "Processing posts by: $authorName\n";
        $currentAuthor = $authorName;
    }

    $oldPath = $postsDir . '/' . $post['image_url'];

    // Determine new path
    if ($isSystemPost) {
        // System posts stay flat
        $newPath = $oldPath;
        $newImageUrl = $post['image_url'];
    } else {
        // User posts go in author directory
        $authorDir = $postsDir . '/' . $post['username'];
        if (!is_dir($authorDir)) {
            mkdir($authorDir, 0755, true);
        }

        $filename = basename($post['image_url']);
        $newPath = $authorDir . '/' . $filename;
        $newImageUrl = $post['username'] . '/' . $filename;
    }

    // Move file if needed
    if ($oldPath !== $newPath) {
        if (file_exists($oldPath)) {
            if (!file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    echo "  ✓ Moved to {$authorName}/\n";
                    $moved++;

                    // Update database
                    $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET image_url = ? WHERE id = ?");
                    $updateStmt->execute([$newImageUrl, $post['id']]);
                } else {
                    echo "  ✗ Failed to move: {$post['image_url']}\n";
                    $failed++;
                }
            } else {
                echo "  ⚠️  File already at destination: {$newImageUrl}\n";
                // Still update database if not already updated
                if ($post['image_url'] !== $newImageUrl) {
                    $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET image_url = ? WHERE id = ?");
                    $updateStmt->execute([$newImageUrl, $post['id']]);
                }
            }
        } else {
            echo "  ⚠️  Source file not found: {$post['image_url']}\n";
            // Still update database reference
            if ($post['image_url'] !== $newImageUrl) {
                $updateStmt = $pdo->prepare("UPDATE ngn_2025.posts SET image_url = ? WHERE id = ?");
                $updateStmt->execute([$newImageUrl, $post['id']]);
            }
        }
    }
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Files moved: $moved\n";
echo "Failed: $failed\n";

// Verify final structure
echo "\nFinal directory structure:\n";
$items = array_diff(scandir($postsDir), ['.', '..']);
$systemFiles = 0;
$authorDirs = [];

foreach ($items as $item) {
    $path = $postsDir . '/' . $item;
    if (is_dir($path)) {
        $files = count(array_diff(scandir($path), ['.', '..']));
        $authorDirs[$item] = $files;
        echo "  📁 $item/ ($files files)\n";
    } else {
        $systemFiles++;
    }
}

echo "  📁 System posts (flat): $systemFiles files\n";
echo "\n✓ Organization complete!\n";
