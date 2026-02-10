<?php
/**
 * List all files in /lib/images/
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$imagesDir = $baseDir . '/lib/images';

echo "========================================\n";
echo "Files in /lib/images/\n";
echo "========================================\n\n";

if (!is_dir($imagesDir)) {
    echo "Directory not found: $imagesDir\n";
    exit(1);
}

$files = array_diff(scandir($imagesDir), ['.', '..']);
echo "Total files: " . count($files) . "\n\n";

// Show all image files (jpg, png, gif, etc.)
$imageFiles = [];
foreach ($files as $file) {
    $path = $imagesDir . '/' . $file;
    if (is_file($path)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $imageFiles[] = $file;
        }
    }
}

sort($imageFiles);
echo "Image files:\n";
foreach ($imageFiles as $file) {
    echo "  - $file\n";
}

// Also show directories
echo "\n\nDirectories in /lib/images/:\n";
$dirs = [];
foreach ($files as $file) {
    $path = $imagesDir . '/' . $file;
    if (is_dir($path)) {
        $dirs[] = $file;
    }
}
sort($dirs);
foreach ($dirs as $dir) {
    echo "  - $dir/\n";
}
