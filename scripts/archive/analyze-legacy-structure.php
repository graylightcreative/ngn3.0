<?php
/**
 * Analyze legacy data structure for images, relationships, and metadata
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n=== LEGACY DATA STRUCTURE ANALYSIS ===\n\n";

// 1. Posts legacy structure
echo "1. POSTS LEGACY TABLE\n";
echo str_repeat("-", 70) . "\n";
$result = $db->query("DESCRIBE posts_legacy");
$columns = [];
while ($row = $result->fetch()) {
    $columns[] = $row['Field'];
    echo sprintf("  %-20s %-20s\n", $row['Field'], $row['Type']);
}

// 2. Check image data in posts
echo "\n2. IMAGE DATA IN LEGACY POSTS\n";
echo str_repeat("-", 70) . "\n";
$withImages = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy WHERE Image IS NOT NULL AND Image != ''")->fetch()['cnt'];
$total = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy")->fetch()['cnt'];
printf("Posts with Image column: %d / %d\n", $withImages, $total);

if ($withImages > 0) {
    echo "\nSample post images:\n";
    $result = $db->query("SELECT Id, Title, Image FROM posts_legacy WHERE Image IS NOT NULL AND Image != '' LIMIT 5");
    while ($row = $result->fetch()) {
        echo sprintf("  [%-3d] %s → %s\n", $row['Id'], substr($row['Title'], 0, 30), $row['Image']);
    }
}

// 3. Check author/writer relationship
echo "\n3. WRITER RELATIONSHIPS\n";
echo str_repeat("-", 70) . "\n";
$withAuthor = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy WHERE Author IS NOT NULL AND Author != ''")->fetch()['cnt'];
printf("Posts with Author field: %d / %d\n", $withAuthor, $total);

if ($withAuthor > 0) {
    echo "\nSample post authors:\n";
    $result = $db->query("SELECT Id, Title, Author FROM posts_legacy WHERE Author IS NOT NULL AND Author != '' LIMIT 5");
    while ($row = $result->fetch()) {
        echo sprintf("  [%-3d] Author: %s\n", $row['Id'], $row['Author']);
    }

    echo "\nUnique authors in legacy posts:\n";
    $result = $db->query("SELECT DISTINCT Author FROM posts_legacy WHERE Author IS NOT NULL AND Author != '' ORDER BY Author");
    while ($row = $result->fetch()) {
        echo sprintf("  - %s\n", $row['Author']);
    }
}

// 4. Check current writers table
echo "\n4. WRITERS IN SYSTEM\n";
echo str_repeat("-", 70) . "\n";
$result = $db->query("SELECT Id, username FROM writers ORDER BY username");
echo "Current writers:\n";
while ($row = $result->fetch()) {
    echo sprintf("  [%-3d] %s\n", $row['Id'], $row['username']);
}

// 5. Check artist table structure
echo "\n5. ARTISTS TABLE STRUCTURE\n";
echo str_repeat("-", 70) . "\n";
$result = $db->query("DESCRIBE artists");
$artistCols = [];
while ($row = $result->fetch()) {
    $artistCols[] = $row['Field'];
}
echo "Columns: " . implode(", ", $artistCols) . "\n";

// Check for artist images
$withImages = $db->query("SELECT COUNT(*) as cnt FROM artists WHERE image_url IS NOT NULL AND image_url != ''")->fetch()['cnt'];
$total = $db->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt'];
printf("Artists with image_url: %d / %d\n", $withImages, $total);

// 6. Check labels table structure
echo "\n6. LABELS TABLE STRUCTURE\n";
echo str_repeat("-", 70) . "\n";
$result = $db->query("DESCRIBE labels");
$labelCols = [];
while ($row = $result->fetch()) {
    $labelCols[] = $row['Field'];
}
echo "Columns: " . implode(", ", $labelCols) . "\n";

$withImages = $db->query("SELECT COUNT(*) as cnt FROM labels WHERE image_url IS NOT NULL AND image_url != ''")->fetch()['cnt'];
$total = $db->query("SELECT COUNT(*) as cnt FROM labels")->fetch()['cnt'];
printf("Labels with image_url: %d / %d\n", $withImages, $total);

// 7. Check stations table
echo "\n7. STATIONS TABLE STRUCTURE\n";
echo str_repeat("-", 70) . "\n";
$result = $db->query("DESCRIBE stations");
$stationCols = [];
while ($row = $result->fetch()) {
    $stationCols[] = $row['Field'];
}
echo "Columns: " . implode(", ", $stationCols) . "\n";

$withImages = $db->query("SELECT COUNT(*) as cnt FROM stations WHERE image_url IS NOT NULL AND image_url != ''")->fetch()['cnt'];
$total = $db->query("SELECT COUNT(*) as cnt FROM stations")->fetch()['cnt'];
printf("Stations with image_url: %d / %d\n", $withImages, $total);

// 8. File system check
echo "\n8. FILE SYSTEM IMAGES\n";
echo str_repeat("-", 70) . "\n";
$imagesPath = __DIR__ . '/../lib/images';
if (is_dir($imagesPath)) {
    $dirs = array_diff(scandir($imagesPath), ['.', '..']);
    foreach ($dirs as $dir) {
        $fullPath = $imagesPath . '/' . $dir;
        if (is_dir($fullPath)) {
            $files = array_diff(scandir($fullPath), ['.', '..']);
            printf("  %s/: %d files\n", $dir, count($files));

            // Show first few files
            $shown = 0;
            foreach ($files as $file) {
                if ($shown >= 3) break;
                echo sprintf("    - %s\n", $file);
                $shown++;
            }
            if (count($files) > 3) {
                echo sprintf("    ... and %d more\n", count($files) - 3);
            }
        }
    }
}

echo "\n" . str_repeat("=", 70) . "\n\n";
