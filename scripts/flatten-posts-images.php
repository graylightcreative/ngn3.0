<?php
/**
 * Flatten posts images - move files from subdirectories to flat structure
 */

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$postsDir = $baseDir . '/storage/uploads/posts';

echo "========================================\n";
echo "Flatten Posts Images\n";
echo "========================================\n\n";

if (!is_dir($postsDir)) {
    echo "✗ $postsDir does not exist\n";
    exit(1);
}

$items = array_diff(scandir($postsDir), ['.', '..']);
$moved = 0;
$failed = 0;

foreach ($items as $item) {
    $path = $postsDir . '/' . $item;

    if (is_dir($path)) {
        echo "Processing directory: $item/\n";

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $source = $path . '/' . $file;
            $dest = $postsDir . '/' . $file;

            if (is_file($source)) {
                echo "  Moving: $file\n";

                if (file_exists($dest)) {
                    echo "    ⚠️  File already exists, skipping\n";
                    continue;
                }

                if (rename($source, $dest)) {
                    echo "    ✓ Moved\n";
                    $moved++;
                } else {
                    echo "    ✗ Failed to move\n";
                    $failed++;
                }
            }
        }

        // Remove empty directory
        if (rmdir($path)) {
            echo "  ✓ Removed empty directory\n";
        } else {
            echo "  ⚠️  Could not remove directory (may not be empty)\n";
        }
    }
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Files moved: $moved\n";
echo "Failed: $failed\n";

// Verify final structure
$finalItems = array_diff(scandir($postsDir), ['.', '..']);
$files = 0;
$dirs = 0;

foreach ($finalItems as $item) {
    if (is_dir($postsDir . '/' . $item)) {
        $dirs++;
    } else {
        $files++;
    }
}

echo "\nFinal structure:\n";
echo "  Files: $files\n";
echo "  Directories: $dirs\n";

if ($dirs === 0) {
    echo "\n✓ Posts images now flat!\n";
} else {
    echo "\n⚠️  Still have $dirs directories\n";
}
