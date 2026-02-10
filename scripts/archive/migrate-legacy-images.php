<?php
/**
 * Migrate legacy user images from lib/images/users/{slug}/ to storage/uploads/users/{slug}/
 * Both artist and label images are stored in lib/images/users/
 * Run this script on the remote server to migrate image files
 * Usage: php scripts/migrate-legacy-images.php
 */

$pdo = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$uploadsDir = $baseDir . '/uploads/users';

echo "========================================\n";
echo "Migrate Legacy User Images\n";
echo "========================================\n\n";

// Create uploads/users directory if it doesn't exist
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
    echo "Created $uploadsDir\n\n";
}

// Migrate artist images
echo "Processing Artists...\n";
$artists = $pdo->query("SELECT id, slug FROM ngn_2025.artists WHERE slug IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$artistsMigrated = 0;
$artistsChecked = 0;

foreach ($artists as $artist) {
    $legacyDir = $baseDir . '/lib/images/users/' . $artist['slug'];
    $newDir = $uploadsDir . '/' . $artist['slug'];

    // Check if legacy directory exists
    if (!is_dir($legacyDir)) {
        continue;
    }

    $artistsChecked++;

    // Create new directory
    if (!is_dir($newDir)) {
        if (!mkdir($newDir, 0755, true)) {
            echo "✗ Failed to create directory: $newDir\n";
            continue;
        }
    }

    // Copy all files from legacy to new location
    $files = array_diff(scandir($legacyDir), ['.', '..']);
    foreach ($files as $file) {
        $legacyFile = $legacyDir . '/' . $file;
        $newFile = $newDir . '/' . $file;

        if (is_file($legacyFile) && !is_file($newFile)) {
            if (!copy($legacyFile, $newFile)) {
                echo "✗ Failed to copy artist image: $legacyFile\n";
            } else {
                $artistsMigrated++;
            }
        }
    }
}

echo "✓ Found legacy images for $artistsChecked artists\n";
echo "✓ Migrated $artistsMigrated artist image files\n\n";

// Migrate label images
echo "Processing Labels...\n";
$labels = $pdo->query("SELECT id, slug FROM ngn_2025.labels WHERE slug IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

$labelsMigrated = 0;
$labelsChecked = 0;

foreach ($labels as $label) {
    $legacyDir = $baseDir . '/lib/images/users/' . $label['slug'];
    $newDir = $uploadsDir . '/' . $label['slug'];

    // Check if legacy directory exists
    if (!is_dir($legacyDir)) {
        continue;
    }

    $labelsChecked++;

    // Create new directory
    if (!is_dir($newDir)) {
        if (!mkdir($newDir, 0755, true)) {
            echo "✗ Failed to create directory: $newDir\n";
            continue;
        }
    }

    // Copy all files from legacy to new location
    $files = array_diff(scandir($legacyDir), ['.', '..']);
    foreach ($files as $file) {
        $legacyFile = $legacyDir . '/' . $file;
        $newFile = $newDir . '/' . $file;

        if (is_file($legacyFile) && !is_file($newFile)) {
            if (!copy($legacyFile, $newFile)) {
                echo "✗ Failed to copy label image: $legacyFile\n";
            } else {
                $labelsMigrated++;
            }
        }
    }
}

echo "✓ Found legacy images for $labelsChecked labels\n";
echo "✓ Migrated $labelsMigrated label image files\n";

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Artist images: $artistsMigrated files migrated\n";
echo "Label images: $labelsMigrated files migrated\n";
echo "\nImages are now in: $uploadsDir/\n";
