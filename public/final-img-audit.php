<?php
require_once __DIR__ . '/../lib/bootstrap.php';

echo "<pre>";
echo "=== FINAL IMAGE AUDIT ===
";
$projectRoot = dirname(__DIR__);

function debug_resolve($filename, $type = 'post', $slug = null) {
    global $projectRoot;
    $cleanName = basename($filename);
    echo "
Resolving [$type] for file: $filename (Clean: $cleanName, Slug: $slug)
";

    $searchMatrix = [
        'post' => [
            '/lib/images/posts/' . $cleanName,
            '/uploads/posts/' . $cleanName,
            '/uploads/' . $cleanName
        ],
        'user' => [
            $slug ? "/lib/images/users/{$slug}/{$cleanName}" : null,
            "/lib/images/users/{$cleanName}",
            "/lib/images/labels/{$cleanName}",
            "/lib/images/stations/{$cleanName}",
            "/lib/images/venues/{$cleanName}",
            $slug ? "/uploads/users/{$slug}/{$cleanName}" : null,
            "/uploads/{$cleanName}"
        ],
        'release' => [
            $slug ? "/lib/images/releases/{$slug}/{$cleanName}" : null,
            "/lib/images/releases/{$cleanName}",
            "/uploads/releases/{$cleanName}",
            "/uploads/{$cleanName}"
        ]
    ];

    $candidates = array_filter($searchMatrix[$type] ?? []);

    foreach ($candidates as $relPath) {
        $absSym = $projectRoot . '/public' . $relPath;
        $absDir = $projectRoot . $relPath;
        
        echo "  Checking Sym: $absSym -> " . (file_exists($absSym) ? "FOUND" : "NOT FOUND") . "
";
        echo "  Checking Dir: $absDir -> " . (file_exists($absDir) ? "FOUND" : "NOT FOUND") . "
";
        
        if (file_exists($absSym) || file_exists($absDir)) {
            echo "  >>> SUCCESS: Using $relPath
";
            return;
        }
    }
    echo "  >>> FAILED: Default used.
";
}

// Tests
debug_resolve('SMR-Chart-Shakeup-Sleep-Theorys-Fallout-Threatens-Billy-Morrisons-Reign.jpg', 'post');
debug_resolve('coldward-logo-1.jpg', 'user', 'coldwards');
debug_resolve('MG MFMP.jpg', 'release', 'malakye-grind');

echo "</pre>";
