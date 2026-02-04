<?php
/**
 * Check status of image migration
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';

echo "========================================\n";
echo "Image Migration Status\n";
echo "========================================\n\n";

echo "Checking /lib/images/artists/westcreek/:\n";
$legacyDir = $baseDir . '/lib/images/artists/westcreek';
if (is_dir($legacyDir)) {
    $files = array_diff(scandir($legacyDir), ['.', '..']);
    echo "  Found " . count($files) . " files:\n";
    foreach ($files as $file) {
        $path = $legacyDir . '/' . $file;
        if (is_file($path)) {
            $size = filesize($path);
            echo "    - $file ($size bytes)\n";
        }
    }
} else {
    echo "  ✗ Directory not found\n";
}

echo "\nChecking /storage/uploads/users/westcreek/:\n";
$newDir = $baseDir . '/storage/uploads/users/westcreek';
if (is_dir($newDir)) {
    $files = array_diff(scandir($newDir), ['.', '..']);
    echo "  Found " . count($files) . " files:\n";
    foreach ($files as $file) {
        $path = $newDir . '/' . $file;
        if (is_file($path)) {
            $size = filesize($path);
            echo "    - $file ($size bytes)\n";
        }
    }
} else {
    echo "  ✗ Directory not found\n";
}

echo "\n\nChecking /uploads/ directory:\n";
if (is_dir($baseDir . '/uploads')) {
    if (is_link($baseDir . '/uploads')) {
        echo "  ✓ /uploads is a symlink\n";
        echo "  Points to: " . readlink($baseDir . '/uploads') . "\n";
    } else {
        echo "  ! /uploads is a DIRECTORY (not a symlink)\n";
        $items = array_diff(scandir($baseDir . '/uploads'), ['.', '..']);
        echo "  Contains " . count($items) . " items\n";
    }
} else {
    echo "  ✗ /uploads does not exist\n";
}

echo "\n\nChecking /storage/uploads/ directory:\n";
if (is_dir($baseDir . '/storage/uploads')) {
    $items = array_diff(scandir($baseDir . '/storage/uploads'), ['.', '..']);
    echo "  ✓ /storage/uploads exists\n";
    echo "  Contains " . count($items) . " items:\n";
    foreach ($items as $item) {
        echo "    - $item/\n";
    }
} else {
    echo "  ✗ /storage/uploads does not exist\n";
}
