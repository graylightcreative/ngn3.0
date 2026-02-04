<?php
/**
 * Check the actual directory structure of migrated uploads
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$uploadsDir = $baseDir . '/storage/uploads';

echo "========================================\n";
echo "Uploads Directory Structure\n";
echo "========================================\n\n";

function showStructure($path, $prefix = '', $maxDepth = 3, $currentDepth = 0) {
    if ($currentDepth >= $maxDepth) {
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $items = array_diff(scandir($path), ['.', '..']);
    $count = 0;

    foreach ($items as $item) {
        if ($count > 20) {
            echo $prefix . "... and more\n";
            break;
        }

        $itemPath = $path . '/' . $item;
        if (is_dir($itemPath)) {
            echo $prefix . "📁 $item/\n";
            $count++;
            showStructure($itemPath, $prefix . "  ", $maxDepth, $currentDepth + 1);
        } else {
            echo $prefix . "📄 $item\n";
            $count++;
        }
    }
}

echo "Contents of /storage/uploads/:\n";
showStructure($uploadsDir);

echo "\n\nSpecific check - Posts directory:\n";
$postsDir = $uploadsDir . '/posts';
if (is_dir($postsDir)) {
    $items = array_diff(scandir($postsDir), ['.', '..']);
    echo "Found " . count($items) . " items in /storage/uploads/posts/\n";

    // Show first 10 items
    $count = 0;
    foreach ($items as $item) {
        if ($count >= 10) break;
        $path = $postsDir . '/' . $item;
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            echo "  📁 $item/ (" . count($files) . " files)\n";
        } else {
            echo "  📄 $item\n";
        }
        $count++;
    }
} else {
    echo "  ✗ /storage/uploads/posts/ not found\n";
}

echo "\n\nTest symlink:\n";
$symlinkTest = $baseDir . '/uploads';
if (is_link($symlinkTest)) {
    echo "  ✓ /uploads is a symlink\n";
    echo "  Points to: " . readlink($symlinkTest) . "\n";

    if (is_dir($baseDir . '/uploads/posts')) {
        echo "  ✓ /uploads/posts/ is accessible\n";
        $files = array_diff(scandir($baseDir . '/uploads/posts'), ['.', '..']);
        echo "  Contains " . count($files) . " items\n";
    } else {
        echo "  ✗ /uploads/posts/ not accessible\n";
    }
} else {
    echo "  ✗ /uploads is NOT a symlink\n";
}
