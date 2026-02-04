<?php
/**
 * Count and list ALL legacy images
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';

echo "========================================\n";
echo "Legacy Images Inventory\n";
echo "========================================\n\n";

function countImages($dir, $type) {
    $path = $dir . '/lib/images/' . $type;
    echo "Scanning: $path\n";

    if (!is_dir($path)) {
        echo "  ✗ Directory not found\n\n";
        return 0;
    }

    $totalFiles = 0;
    $dirs = array_diff(scandir($path), ['.', '..']);
    echo "  Found " . count($dirs) . " subdirectories\n\n";

    foreach ($dirs as $subdir) {
        $subdirPath = $path . '/' . $subdir;
        if (is_dir($subdirPath)) {
            $files = array_diff(scandir($subdirPath), ['.', '..']);
            $fileCount = 0;
            foreach ($files as $file) {
                if (is_file($subdirPath . '/' . $file)) {
                    $fileCount++;
                }
            }
            if ($fileCount > 0) {
                echo "  $subdir/ ($fileCount files)\n";
                $totalFiles += $fileCount;
            }
        }
    }

    echo "\n  Total: $totalFiles image files\n\n";
    return $totalFiles;
}

$artistCount = countImages($baseDir, 'artists');
$labelCount = countImages($baseDir, 'labels');

echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Artists: $artistCount image files\n";
echo "Labels: $labelCount image files\n";
echo "Total: " . ($artistCount + $labelCount) . " image files\n";
