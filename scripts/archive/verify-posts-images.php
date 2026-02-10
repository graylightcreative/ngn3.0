<?php
/**
 * Verify posts images are in the correct flat structure
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$postsDir = $baseDir . '/storage/uploads/posts';

echo "========================================\n";
echo "Posts Images Verification\n";
echo "========================================\n\n";

if (!is_dir($postsDir)) {
    echo "✗ $postsDir does not exist\n";
    exit(1);
}

$items = array_diff(scandir($postsDir), ['.', '..']);
echo "Found " . count($items) . " items in /storage/uploads/posts/\n\n";

// Count files vs directories
$files = 0;
$dirs = 0;
$sampleFiles = [];
$sampleDirs = [];

foreach ($items as $item) {
    $path = $postsDir . '/' . $item;
    if (is_dir($path)) {
        $dirs++;
        if (count($sampleDirs) < 5) {
            $sampleDirs[] = $item;
        }
    } else {
        $files++;
        if (count($sampleFiles) < 5) {
            $sampleFiles[] = $item;
        }
    }
}

echo "Structure:\n";
echo "  Files: $files\n";
echo "  Directories: $dirs\n";

if ($files > 0) {
    echo "\nSample files:\n";
    foreach ($sampleFiles as $file) {
        echo "  ✓ $file\n";
    }
}

if ($dirs > 0) {
    echo "\nSample directories (PROBLEM - should be flat!):\n";
    foreach ($sampleDirs as $dir) {
        $count = count(array_diff(scandir($postsDir . '/' . $dir), ['.', '..']));
        echo "  ✗ $dir/ ($count files inside)\n";
    }
}

echo "\n\nDatabase vs Filesystem:\n";
$pdo = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1'
);

$stmt = $pdo->query("SELECT slug, image_url FROM ngn_2025.posts WHERE image_url IS NOT NULL AND image_url != '' LIMIT 5");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($posts as $post) {
    $expectedPath = $postsDir . '/' . $post['image_url'];
    $exists = file_exists($expectedPath) ? '✓' : '✗';
    echo "$exists {$post['image_url']}\n";
}

if ($dirs > 0) {
    echo "\n⚠️  Issue found: Posts should be flat files, not in subdirectories\n";
    echo "This is why images aren't loading. Need to flatten the structure.\n";
} else {
    echo "\n✓ Posts structure is correct (flat files)\n";
}
