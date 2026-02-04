<?php
/**
 * Check if legacy data/files can be cleaned up
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n=== LEGACY DATA CLEANUP ANALYSIS ===\n\n";

// 1. Check staging tables
echo "1. TEMPORARY STAGING TABLES\n";
echo str_repeat("-", 70) . "\n";
try {
    $count = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy")->fetch()['cnt'];
    echo "posts_legacy table: $count records\n";
    echo "  Purpose: Temporary staging table for migration\n";
    echo "  Status: Can be safely dropped\n";
    echo "  Action: DROP TABLE posts_legacy;\n";
} catch (\Exception $e) {
    echo "posts_legacy table: [not found]\n";
}

// 2. Check main data migration status
echo "\n2. MIGRATION COMPLETENESS CHECK\n";
echo str_repeat("-", 70) . "\n";

$postsCount = $db->query("SELECT COUNT(*) as cnt FROM posts")->fetch()['cnt'];
$postsWithTimestamps = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE CreatedAt IS NOT NULL")->fetch()['cnt'];
$postsWithStatus = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE Status IS NOT NULL")->fetch()['cnt'];

printf("Posts in main table: %d\n", $postsCount);
printf("Posts with CreatedAt: %d / %d\n", $postsWithTimestamps, $postsCount);
printf("Posts with Status: %d / %d\n", $postsWithStatus, $postsCount);

if ($postsCount > 0 && $postsWithTimestamps === $postsCount && $postsWithStatus === $postsCount) {
    echo "\n✓ All posts successfully migrated to main database\n";
    echo "  Legacy staging table is no longer needed\n";
}

// 3. Check SQL backup files
echo "\n3. LEGACY SQL BACKUP FILES\n";
echo str_repeat("-", 70) . "\n";

$backupDir = __DIR__ . '/../storage/uploads/legacy backups';
if (is_dir($backupDir)) {
    $files = array_diff(scandir($backupDir), ['.', '..']);
    echo "Location: storage/uploads/legacy\ backups/\n\n";

    $totalSize = 0;
    foreach ($files as $file) {
        if (is_file($backupDir . '/' . $file)) {
            $size = filesize($backupDir . '/' . $file);
            $totalSize += $size;
            $sizeMB = round($size / (1024 * 1024), 1);
            echo "  • $file\n";
            echo "    Size: $sizeMB MB\n";
        }
    }

    echo "\nTotal backup size: " . round($totalSize / (1024 * 1024), 1) . " MB\n";
    echo "\n⚠ These files are still needed for:\n";
    echo "  - Reference/audit trail\n";
    echo "  - Rollback capability (if needed)\n";
    echo "  - Data reconstruction validation\n";
} else {
    echo "Backup directory not found\n";
}

// 4. Check other databases
echo "\n4. SECONDARY DATABASES STATUS\n";
echo str_repeat("-", 70) . "\n";

$secondaryDbs = [
    'ngn_rankings_2025' => 'Rankings',
    'ngn_smr_2025' => 'SMR Charts',
    'ngn_spins_2025' => 'Spins',
];

foreach ($secondaryDbs as $dbName => $displayName) {
    try {
        // Count tables in database
        $result = $db->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
        $tableCount = $result->fetch()['cnt'];

        if ($tableCount > 0) {
            $recordCount = 0;
            $tables = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
            while ($table = $tables->fetch()) {
                $count = $db->query("SELECT COUNT(*) as cnt FROM \`$dbName\`.\`" . $table['TABLE_NAME'] . "\`")->fetch()['cnt'];
                $recordCount += $count;
            }
            printf("  %s: %d tables, %s records ✓\n", $displayName, $tableCount, number_format($recordCount));
        }
    } catch (\Exception $e) {
        printf("  %s: [unavailable]\n", $displayName);
    }
}

// 5. Cleanup recommendations
echo "\n5. CLEANUP RECOMMENDATIONS\n";
echo str_repeat("-", 70) . "\n";

echo "SAFE TO DELETE:\n";
echo "  ✓ posts_legacy table (after confirming migration is complete)\n";
echo "    SQL: DROP TABLE posts_legacy;\n\n";

echo "KEEP FOR NOW:\n";
echo "  ⚠ Legacy SQL backup files (storage/uploads/legacy\ backups/)\n";
echo "    Reason: Audit trail & reference\n";
echo "    Size: ~40 MB\n";
echo "    Action: Archive to cold storage if space is needed\n\n";

echo "KEEP ALWAYS:\n";
echo "  ✓ Secondary databases (rankings, smr, spins)\n";
echo "  ✓ Main 2025 database\n";
echo "  ✓ Writer relationships\n";
echo "  ✓ Image files (lib/images/)\n";

echo "\n" . str_repeat("=", 70) . "\n\n";
