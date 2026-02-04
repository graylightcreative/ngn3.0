<?php
/**
 * Migrate ALL legacy images from /lib/images/ to /storage/uploads/
 * Preserves complete directory structure
 *
 * Run with: php scripts/migrate-all-legacy-images.php [local|remote]
 * Defaults to local if no argument provided
 */

$env = isset($argv[1]) ? $argv[1] : 'local';

if ($env === 'remote') {
    $baseDir = '/www/wwwroot/beta.nextgennoise.com';
} else {
    require __DIR__ . '/../lib/bootstrap.php';
    $baseDir = dirname(__DIR__);
}

$sourceBase = $baseDir . '/lib/images';
$destBase = $baseDir . '/storage/uploads';

echo "========================================\n";
echo "Migrate ALL Legacy Images\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "Base directory: $baseDir\n";
echo "========================================\n\n";

// Create base uploads directory if needed
if (!is_dir($destBase)) {
    mkdir($destBase, 0755, true);
    echo "Created $destBase\n\n";
}

$totalMigrated = 0;
$totalDirs = 0;

/**
 * Recursively copy directory structure and files
 */
function migrateDirectory($source, $destination) {
    global $totalMigrated, $totalDirs;

    if (!is_dir($source)) {
        return 0;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $items = array_diff(scandir($source), ['.', '..']);
    $count = 0;

    foreach ($items as $item) {
        $sourcePath = $source . '/' . $item;
        $destPath = $destination . '/' . $item;

        if (is_dir($sourcePath)) {
            $totalDirs++;
            $count += migrateDirectory($sourcePath, $destPath);
        } else if (is_file($sourcePath)) {
            if (!is_file($destPath)) {
                if (copy($sourcePath, $destPath)) {
                    $count++;
                    $totalMigrated++;
                } else {
                    echo "✗ Failed to copy: $sourcePath\n";
                }
            }
        }
    }

    return $count;
}

// Get all image type directories
$imageTypes = array_diff(scandir($sourceBase), ['.', '..']);

foreach ($imageTypes as $type) {
    $typePath = $sourceBase . '/' . $type;

    if (!is_dir($typePath)) {
        continue;
    }

    $destTypePath = $destBase . '/' . $type;
    echo "Processing $type/\n";

    $count = migrateDirectory($typePath, $destTypePath);

    if ($count > 0) {
        echo "  ✓ Migrated $count files\n";
    } else {
        echo "  ! No files found or already migrated\n";
    }
}

echo "\n========================================\n";
echo "Migration Complete\n";
echo "========================================\n";
echo "Total directories created: $totalDirs\n";
echo "Total files migrated: $totalMigrated\n";
echo "\nAll images now in: $destBase/\n";
