<?php
/**
 * Verbose image migration with detailed logging
 */

$pdo = new PDO(
    'mysql:host=server.starrship1.com;dbname=ngn_2025',
    'nextgennoise',
    'NextGenNoise!1',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$baseDir = '/www/wwwroot/beta.nextgennoise.com';
$uploadsDir = $baseDir . '/storage/uploads/users';

echo "========================================\n";
echo "Verbose Image Migration\n";
echo "========================================\n\n";

// Test with westcreek artist
$legacyDir = $baseDir . '/lib/images/artists/westcreek';
$newDir = $uploadsDir . '/westcreek';

echo "Source: $legacyDir\n";
echo "Destination: $newDir\n\n";

echo "Checking source directory:\n";
if (!is_dir($legacyDir)) {
    echo "  ✗ Source directory does not exist\n";
    exit(1);
}
echo "  ✓ Source directory exists\n";

$files = array_diff(scandir($legacyDir), ['.', '..']);
echo "  Found " . count($files) . " items\n";
foreach ($files as $file) {
    $path = $legacyDir . '/' . $file;
    if (is_file($path)) {
        echo "    - $file (file, " . filesize($path) . " bytes)\n";
    } else {
        echo "    - $file (directory)\n";
    }
}

echo "\nCreating destination directory:\n";
if (is_dir($newDir)) {
    echo "  ! Directory already exists\n";
} else {
    echo "  Creating $newDir...\n";
    if (mkdir($newDir, 0755, true)) {
        echo "  ✓ Directory created\n";
    } else {
        echo "  ✗ Failed to create directory\n";
        exit(1);
    }
}

echo "\nCopying files:\n";
foreach ($files as $file) {
    $legacyFile = $legacyDir . '/' . $file;
    $newFile = $newDir . '/' . $file;

    echo "  Processing: $file\n";
    echo "    From: $legacyFile\n";
    echo "    To: $newFile\n";

    if (!is_file($legacyFile)) {
        echo "    ✗ Source is not a file\n";
        continue;
    }
    echo "    ✓ Source is a file\n";

    if (is_file($newFile)) {
        echo "    ! File already exists at destination\n";
        continue;
    }
    echo "    ! File does not exist at destination yet\n";

    echo "    Attempting copy...\n";
    if (copy($legacyFile, $newFile)) {
        echo "    ✓ Copy successful\n";
        if (is_file($newFile)) {
            echo "    ✓ File verified at destination\n";
        } else {
            echo "    ✗ File not found at destination after copy\n";
        }
    } else {
        echo "    ✗ Copy failed\n";
        $error = error_get_last();
        if ($error) {
            echo "    Error: " . $error['message'] . "\n";
        }
    }
}

echo "\nFinal verification:\n";
if (is_dir($newDir)) {
    $newFiles = array_diff(scandir($newDir), ['.', '..']);
    echo "  Destination contains " . count($newFiles) . " files:\n";
    foreach ($newFiles as $file) {
        echo "    - $file\n";
    }
} else {
    echo "  Destination directory not found\n";
}
