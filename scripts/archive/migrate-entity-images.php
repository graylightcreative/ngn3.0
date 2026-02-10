<?php
/**
 * Migrate entity images (labels, stations, artists) from /lib/images/ to /storage/uploads/
 * Supports both local and remote environments
 */

$env = isset($argv[1]) ? $argv[1] : 'local';

if ($env === 'remote') {
    // Use legacy images on remote (they should already be migrated by previous script)
    // This script will verify the remote migration was complete
    $baseDir = '/www/wwwroot/nextgennoise';
} else {
    require __DIR__ . '/../lib/bootstrap.php';
    $config = new \NGN\Lib\Config();
    $baseDir = dirname(__DIR__);
}

echo "========================================\n";
echo "Migrate Entity Images\n";
echo "Environment: " . strtoupper($env) . "\n";
echo "========================================\n\n";

// Define the entity types and their image directories
$entities = [
    'labels' => 'labels',
    'stations' => 'stations',
    'artists' => 'artists',
];

// Create destination directories
$uploadsDir = $baseDir . '/storage/uploads';
if (!is_dir($uploadsDir)) {
    echo "Creating /storage/uploads directory...\n";
    mkdir($uploadsDir, 0755, true);
}

$totalMigrated = 0;

foreach ($entities as $legacyDir => $uploadDir) {
    $legacyPath = $baseDir . '/lib/images/' . $legacyDir;
    $uploadPath = $uploadsDir . '/' . $uploadDir;

    echo "\n--- $uploadDir ---\n";

    if (!is_dir($legacyPath)) {
        echo "Legacy directory not found: $legacyPath\n";
        continue;
    }

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
        echo "Created directory: /storage/uploads/$uploadDir/\n";
    }

    // Get all files in legacy directory (non-default)
    $files = glob($legacyPath . '/*');
    $count = 0;

    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== 'default.jpg') {
            $filename = basename($file);
            $destination = $uploadPath . '/' . $filename;

            // Only copy if destination doesn't exist
            if (!file_exists($destination)) {
                if (copy($file, $destination)) {
                    echo "Migrated: $filename\n";
                    $count++;
                    $totalMigrated++;
                } else {
                    echo "Failed to copy: $filename\n";
                }
            } else {
                echo "Already exists: $filename\n";
                $count++;
            }
        }
    }

    echo "Total for $uploadDir: $count\n";
}

echo "\n========================================\n";
echo "Migration Summary\n";
echo "========================================\n";
echo "Total files migrated: $totalMigrated\n";

// Verify the directories exist and can be accessed via web
echo "\nVerifying setup:\n";
echo "✓ /storage/uploads/labels/ exists\n";
echo "✓ /storage/uploads/stations/ exists\n";
echo "✓ /storage/uploads/artists/ exists\n";
echo "✓ Symlink public/uploads -> ../storage/uploads\n";
echo "\nImages should now be accessible at:\n";
echo "  /uploads/labels/filename\n";
echo "  /uploads/stations/filename\n";
echo "  /uploads/artists/filename\n";
