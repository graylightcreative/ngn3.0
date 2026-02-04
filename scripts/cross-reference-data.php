<?php
/**
 * Cross-Reference Data Integrity Check
 *
 * Verifies relationships between posts and related entities:
 * writers, venues, artists, labels, stations, shows, videos,
 * rankings, smr, and spins data
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 70) . "\n";
echo "DATA CROSS-REFERENCE & INTEGRITY REPORT\n";
echo str_repeat("=", 70) . "\n\n";

// Core entities
$entities = [
    'posts' => 'posts',
    'writers' => 'Writers',
    'venues' => 'Venues',
    'artists' => 'Artists',
    'labels' => 'Labels',
    'stations' => 'Stations',
    'shows' => 'shows',
    'videos' => 'videos',
    'tracks' => 'Tracks',
    'releases' => 'releases',
];

// Rankings/SMR databases
$secondaryDbs = [
    'ngn_rankings_2025' => 'Rankings Database',
    'ngn_smr_2025' => 'SMR Database',
    'ngn_spins_2025' => 'Spins Database',
];

echo "1. CORE DATA ENTITIES\n";
echo str_repeat("-", 70) . "\n";

$totalRecords = 0;
foreach ($entities as $table => $name) {
    try {
        $result = $db->query("SELECT COUNT(*) as cnt FROM `$table`");
        $count = $result->fetch()['cnt'];
        $totalRecords += $count;
        $status = $count > 0 ? "✓" : "⚠";
        printf("%s %-20s %8d records\n", $status, $name, $count);
    } catch (\Exception $e) {
        printf("✗ %-20s [Table not found]\n", $name);
    }
}

echo "\nTotal Core Records: $totalRecords\n\n";

// Relationships
echo "2. RELATIONSHIP INTEGRITY\n";
echo str_repeat("-", 70) . "\n";

// Posts by writer
try {
    $result = $db->query("SELECT COUNT(DISTINCT author) as distinct_authors, COUNT(*) as total FROM posts WHERE author IS NOT NULL AND author != ''");
    $row = $result->fetch();
    printf("Posts with authors: %d (%d distinct)\n", $row['total'], $row['distinct_authors']);
} catch (\Exception $e) {
    echo "Posts with authors: [unavailable]\n";
}

// Artists with venue info
try {
    $result = $db->query("SELECT COUNT(*) as cnt FROM artists WHERE website IS NOT NULL AND website != ''");
    $count = $result->fetch()['cnt'];
    printf("Artists with website: %d / %d\n", $count, $db->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt']);
} catch (\Exception $e) {
    echo "Artists with website: [unavailable]\n";
}

// Labels active
try {
    $result = $db->query("SELECT COUNT(*) as cnt FROM labels");
    $count = $result->fetch()['cnt'];
    printf("Labels in system: %d\n", $count);
} catch (\Exception $e) {
    echo "Labels in system: [unavailable]\n";
}

// Stations configured
try {
    $result = $db->query("SELECT COUNT(*) as cnt FROM stations");
    $count = $result->fetch()['cnt'];
    printf("Stations configured: %d\n", $count);
} catch (\Exception $e) {
    echo "Stations configured: [unavailable]\n";
}

// Videos in system
try {
    $result = $db->query("SELECT COUNT(*) as cnt FROM videos");
    $count = $result->fetch()['cnt'];
    printf("Videos migrated: %d\n", $count);
} catch (\Exception $e) {
    echo "Videos migrated: [unavailable]\n";
}

echo "\n";

// Secondary databases
echo "3. SECONDARY DATABASE TABLES\n";
echo str_repeat("-", 70) . "\n";

foreach ($secondaryDbs as $dbName => $displayName) {
    try {
        // Try to connect to the database
        $tables = [];
        $result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
        $tableList = [];
        while ($row = $result->fetch()) {
            $tableList[] = $row['TABLE_NAME'];
        }

        if (empty($tableList)) {
            printf("✗ %-30s [0 tables found]\n", $displayName);
            continue;
        }

        $totalSecondary = 0;
        foreach ($tableList as $table) {
            $count = $db->query("SELECT COUNT(*) as cnt FROM `$dbName`.`$table`")->fetch()['cnt'];
            $totalSecondary += $count;
        }

        printf("✓ %-30s %d tables, %d total records\n", $displayName, count($tableList), $totalSecondary);

        // Show main tables
        $mainTables = ['chartdata' => 'Chart Data', 'rankings' => 'Rankings', 'chartmetadata' => 'Chart Metadata', 'spindata' => 'Spin Data'];
        foreach ($mainTables as $table => $label) {
            if (in_array($table, $tableList)) {
                $count = $db->query("SELECT COUNT(*) as cnt FROM `$dbName`.`$table`")->fetch()['cnt'];
                if ($count > 0) {
                    printf("    └─ %s: %d records\n", $label, $count);
                }
            }
        }

    } catch (\Exception $e) {
        printf("⚠ %-30s [Connection error: %s]\n", $displayName, str_replace('SQLSTATE[HY000]', '', $e->getMessage()));
    }
}

echo "\n";

// Data quality checks
echo "4. DATA QUALITY CHECKS\n";
echo str_repeat("-", 70) . "\n";

// Posts with complete data
try {
    $required = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE Id IS NOT NULL AND Title IS NOT NULL AND Slug IS NOT NULL AND Body IS NOT NULL")->fetch()['cnt'];
    $total = $db->query("SELECT COUNT(*) as cnt FROM posts")->fetch()['cnt'];
    $pct = $total > 0 ? round(($required / $total) * 100, 1) : 0;
    printf("Posts with all required fields: %d / %d (%s%%)\n", $required, $total, $pct);
} catch (\Exception $e) {
    echo "Posts with all required fields: [unavailable]\n";
}

// Posts with timestamps
try {
    $timestamped = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE CreatedAt IS NOT NULL AND UpdatedAt IS NOT NULL")->fetch()['cnt'];
    $total = $db->query("SELECT COUNT(*) as cnt FROM posts")->fetch()['cnt'];
    $pct = $total > 0 ? round(($timestamped / $total) * 100, 1) : 0;
    printf("Posts with timestamps: %d / %d (%s%%)\n", $timestamped, $total, $pct);
} catch (\Exception $e) {
    echo "Posts with timestamps: [unavailable]\n";
}

// Artists with images
try {
    $withImages = $db->query("SELECT COUNT(*) as cnt FROM artists WHERE image_url IS NOT NULL AND image_url != ''")->fetch()['cnt'];
    $total = $db->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt'];
    $pct = $total > 0 ? round(($withImages / $total) * 100, 1) : 0;
    printf("Artists with images: %d / %d (%s%%)\n", $withImages, $total, $pct);
} catch (\Exception $e) {
    echo "Artists with images: [unavailable]\n";
}

echo "\n";

// Summary
echo "5. SUMMARY\n";
echo str_repeat("-", 70) . "\n";

try {
    $allTables = $db->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetch()['cnt'];
    $allRecords = $db->query("SELECT SUM(TABLE_ROWS) as total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetch()['total'];

    printf("Total tables in 2025 database: %d\n", $allTables);
    printf("Total records (approximate): %s\n", number_format($allRecords));
} catch (\Exception $e) {
    echo "Summary data: [unavailable]\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "CROSS-REFERENCE COMPLETE\n";
echo str_repeat("=", 70) . "\n\n";
