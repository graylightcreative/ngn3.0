<?php
require_once __DIR__ . '/../lib/bootstrap.php';

echo "<pre>";
echo "=== SERVER PATH AUDIT ===
";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "
";
echo "__DIR__: " . __DIR__ . "
";
$projectRoot = dirname(__DIR__);
echo "Calculated Project Root: " . $projectRoot . "
";

$testFiles = [
    '/public/lib/images/site/2026/NGN-Emblem-Light.png',
    '/public/lib/images/posts/SMR-Chart-Shakeup-Sleep-Theorys-Fallout-Threatens-Billy-Morrisons-Reign.jpg',
    '/lib/images/posts/SMR-Chart-Shakeup-Sleep-Theorys-Fallout-Threatens-Billy-Morrisons-Reign.jpg'
];

foreach ($testFiles as $f) {
    $abs = $projectRoot . $f;
    echo "Checking: $abs
";
    echo "  Status: " . (file_exists($abs) ? "FOUND" : "NOT FOUND") . "
";
}

echo "
=== DIRECTORY LISTING (public/lib/images/posts) ===
";
$dir = $projectRoot . '/public/lib/images/posts';
if (is_dir($dir)) {
    $files = scandir($dir);
    echo "Total files: " . count($files) . "
";
    echo "Sample files:
";
    print_r(array_slice($files, 0, 10));
} else {
    echo "Directory NOT FOUND: $dir
";
}

echo "
=== DB SAMPLE CHECK ===
";
try {
    $config = new \NGN\Lib\Config();
    $pdo = \NGN\Lib\DB\ConnectionFactory::read($config);
    $post = $pdo->query("SELECT featured_image_url FROM posts WHERE featured_image_url IS NOT NULL LIMIT 1")->fetchColumn();
    echo "DB Post Image: $post
";
    echo "Resolved URL: " . post_image($post) . "
";
} catch (\Throwable $e) {
    echo "DB Error: " . $e->getMessage() . "
";
}
echo "</pre>";
