<?php
require_once __DIR__ . '/../lib/bootstrap.php';
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::read($config);

echo "=== IMAGE DIAGNOSTIC ===
";
echo "Project Root: " . dirname(__DIR__) . "
";
echo "Public Dir: " . dirname(__DIR__) . "/public
";

// 1. Check a Post
$post = $pdo->query("SELECT id, title, featured_image_url FROM posts WHERE featured_image_url IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($post) {
    echo "
POST: {$post['title']}
";
    echo "  DB Image: {$post['featured_image_url']}
";
    $resolved = post_image($post['featured_image_url']);
    echo "  Resolved: {$resolved}
";
}

// 2. Check an Artist
$artist = $pdo->query("SELECT id, name, slug, image_url FROM artists WHERE image_url IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($artist) {
    echo "
ARTIST: {$artist['name']}
";
    echo "  Slug: {$artist['slug']}
";
    echo "  DB Image: {$artist['image_url']}
";
    $resolved = user_image($artist['slug'], $artist['image_url']);
    echo "  Resolved: {$resolved}
";
}

// 3. Manual Probe
echo "
PROBING FILESYSTEM:
";
$dirs = [
    '/public/lib/images/posts',
    '/public/uploads/artists',
    '/storage/uploads/artists'
];

foreach ($dirs as $dir) {
    $abs = dirname(__DIR__) . $dir;
    echo "  Checking Dir: {$abs}
";
    if (is_dir($abs)) {
        $files = array_slice(scandir($abs), 0, 5);
        echo "    Status: FOUND (" . count(scandir($abs)) . " files)
";
        echo "    Sample: " . implode(", ", array_filter($files, fn($f) => $f[0] !== '.')) . "
";
    } else {
        echo "    Status: NOT FOUND
";
    }
}
