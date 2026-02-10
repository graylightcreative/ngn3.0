<?php
/**
 * Diagnose where legacy images actually are
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';

echo "========================================\n";
echo "Legacy Images Diagnostic\n";
echo "========================================\n\n";

// Check various possible locations
$possiblePaths = [
    'Artists' => [
        $baseDir . '/lib/images/artists',
        $baseDir . '/lib/images/Users/Artists',
        $baseDir . '/lib/Artists',
        $baseDir . '/storage/images/artists',
        $baseDir . '/public/uploads/users/artists',
    ],
    'Labels' => [
        $baseDir . '/lib/images/labels',
        $baseDir . '/lib/images/Users/Labels',
        $baseDir . '/lib/Labels',
        $baseDir . '/storage/images/labels',
        $baseDir . '/public/uploads/users/labels',
    ]
];

foreach ($possiblePaths as $type => $paths) {
    echo "$type:\n";
    foreach ($paths as $path) {
        if (is_dir($path)) {
            $count = count(array_diff(scandir($path), ['.', '..']));
            echo "  ✓ $path ($count items)\n";

            // List subdirectories
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                if (is_dir("$path/$item")) {
                    $fileCount = count(array_diff(scandir("$path/$item"), ['.', '..']));
                    echo "    - $item/ ($fileCount files)\n";
                }
            }
        } else {
            echo "  ✗ $path (not found)\n";
        }
    }
    echo "\n";
}

// Also check what the database says about image_url
echo "Database image_url patterns:\n";
$pdo = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1'
);

$stmt = $pdo->query("SELECT DISTINCT image_url FROM ngn_2025.artists WHERE image_url IS NOT NULL LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($results)) {
    echo "Artists:\n";
    foreach ($results as $row) {
        echo "  - " . $row['image_url'] . "\n";
    }
}

$stmt = $pdo->query("SELECT DISTINCT image_url FROM ngn_2025.labels WHERE image_url IS NOT NULL LIMIT 10");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($results)) {
    echo "Labels:\n";
    foreach ($results as $row) {
        echo "  - " . $row['image_url'] . "\n";
    }
}
