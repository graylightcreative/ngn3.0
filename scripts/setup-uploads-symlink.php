<?php
/**
 * Setup /uploads symlink to point to /storage/uploads
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$uploadsDir = $baseDir . '/uploads';
$storageUploadsDir = $baseDir . '/storage/uploads';

echo "========================================\n";
echo "Setup /uploads Symlink\n";
echo "========================================\n\n";

echo "Checking current state:\n";
if (is_link($uploadsDir)) {
    echo "  ✓ /uploads is already a symlink\n";
    echo "  Points to: " . readlink($uploadsDir) . "\n";
} else if (is_dir($uploadsDir)) {
    echo "  ! /uploads is a directory\n";
    $items = array_diff(scandir($uploadsDir), ['.', '..']);
    echo "  Contains " . count($items) . " items:\n";
    foreach ($items as $item) {
        echo "    - $item/\n";
    }
    echo "\n  Need to remove this directory and create symlink instead\n";
    echo "  Checking /storage/uploads/ contents:\n";
    if (is_dir($storageUploadsDir)) {
        $storageItems = array_diff(scandir($storageUploadsDir), ['.', '..']);
        echo "  /storage/uploads contains " . count($storageItems) . " items:\n";
        foreach ($storageItems as $item) {
            echo "    - $item/\n";
        }
    }
} else {
    echo "  ✗ /uploads does not exist\n";
}

echo "\n\nTo complete the setup, run on remote server:\n";
echo "  cd $baseDir\n";
echo "  rm -rf uploads\n";
echo "  ln -s storage/uploads uploads\n";
echo "\nThis will:\n";
echo "  1. Remove the existing /uploads directory\n";
echo "  2. Create a symlink from /uploads -> storage/uploads\n";
echo "  3. Allow all paths like /uploads/users/{slug}/{image} to work\n";
