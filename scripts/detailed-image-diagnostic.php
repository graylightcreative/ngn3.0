<?php
/**
 * Detailed diagnostic of legacy image structure
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$pdo = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1'
);

echo "========================================\n";
echo "Detailed Legacy Images Diagnostic\n";
echo "========================================\n\n";

// Get all artists from database
$artists = $pdo->query("SELECT id, slug FROM ngn_2025.artists WHERE slug IS NOT NULL ORDER BY slug LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

echo "Artists in database (showing first 20):\n";
foreach ($artists as $artist) {
    echo "  - ID: {$artist['id']}, Slug: {$artist['slug']}\n";

    $dir1 = $baseDir . '/lib/images/artists/' . $artist['slug'];
    $dir2 = $baseDir . '/lib/Artists/' . $artist['slug'];

    if (is_dir($dir1)) {
        $files = array_diff(scandir($dir1), ['.', '..']);
        echo "    ✓ Found in /lib/images/artists/{$artist['slug']}/ (" . count($files) . " files)\n";
        foreach ($files as $file) {
            echo "      - $file\n";
        }
    }
    if (is_dir($dir2)) {
        $files = array_diff(scandir($dir2), ['.', '..']);
        echo "    ✓ Found in /lib/Artists/{$artist['slug']}/ (" . count($files) . " files)\n";
        foreach ($files as $file) {
            echo "      - $file\n";
        }
    }
    if (!is_dir($dir1) && !is_dir($dir2)) {
        echo "    ✗ No directory found\n";
    }
}

echo "\n\nLabels in database:\n";
$labels = $pdo->query("SELECT id, slug FROM ngn_2025.labels WHERE slug IS NOT NULL ORDER BY slug LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

foreach ($labels as $label) {
    echo "  - ID: {$label['id']}, Slug: {$label['slug']}\n";

    $dir1 = $baseDir . '/lib/images/labels/' . $label['slug'];
    $dir2 = $baseDir . '/lib/Labels/' . $label['slug'];

    if (is_dir($dir1)) {
        $files = array_diff(scandir($dir1), ['.', '..']);
        echo "    ✓ Found in /lib/images/labels/{$label['slug']}/ (" . count($files) . " files)\n";
        foreach ($files as $file) {
            echo "      - $file\n";
        }
    }
    if (is_dir($dir2)) {
        $files = array_diff(scandir($dir2), ['.', '..']);
        echo "    ✓ Found in /lib/Labels/{$label['slug']}/ (" . count($files) . " files)\n";
        foreach ($files as $file) {
            echo "      - $file\n";
        }
    }
    if (!is_dir($dir1) && !is_dir($dir2)) {
        echo "    ✗ No directory found\n";
    }
}

echo "\n\nChecking what's actually in /lib/Artists/:\n";
if (is_dir($baseDir . '/lib/Artists')) {
    $items = array_diff(scandir($baseDir . '/lib/Artists'), ['.', '..']);
    foreach ($items as $item) {
        $path = $baseDir . '/lib/Artists/' . $item;
        if (is_dir($path)) {
            echo "  - $item/ (directory)\n";
        } else {
            echo "  - $item (file)\n";
        }
    }
}

echo "\nChecking what's actually in /lib/Labels/:\n";
if (is_dir($baseDir . '/lib/Labels')) {
    $items = array_diff(scandir($baseDir . '/lib/Labels'), ['.', '..']);
    foreach ($items as $item) {
        $path = $baseDir . '/lib/Labels/' . $item;
        if (is_dir($path)) {
            echo "  - $item/ (directory)\n";
        } else {
            echo "  - $item (file)\n";
        }
    }
}
