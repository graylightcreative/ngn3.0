<?php
/**
 * Complete Legacy Data Migration
 *
 * Migrates all legacy data from legacy SQL dumps to new CDM schema.
 * Handles multi-database setup with proper schema transformations.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);
$dbConfig = $config->db();

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "NGN 2.0 LEGACY DATA MIGRATION\n";
echo str_repeat("=", 60) . "\n\n";

// Step 1: Create posts_legacy table
echo "Step 1: Creating posts_legacy staging table...\n";
$db->exec("DROP TABLE IF EXISTS posts_legacy");
$createLegacy = <<<'SQL'
CREATE TABLE posts_legacy (
  Id int unsigned PRIMARY KEY,
  Title varchar(255),
  Slug varchar(150),
  Body mediumtext,
  Tags text,
  Summary text,
  TypeId int,
  Published tinyint,
  Featured tinyint,
  Image varchar(255),
  Created datetime,
  Updated datetime,
  Author varchar(255),
  PublishedDate datetime,
  IsUser tinyint
)
SQL;
$db->exec($createLegacy);
echo "✓ posts_legacy table created\n\n";

// Step 2: Load legacy posts data via mysql command
echo "Step 2: Loading legacy posts data from SQL dump...\n";

// Create temp SQL file with only posts INSERTs
$tempFile = '/tmp/load_posts_legacy_' . time() . '.sql';
$sqlContent = "TRUNCATE TABLE posts_legacy;\n";

$originalFile = '/Users/brock/Library/CloudStorage/GoogleDrive-brock@brockstarr.com/Shared drives/Sites/ngn2.0/storage/uploads/legacy backups/032925.sql';
if (!file_exists($originalFile)) {
    echo "✗ Legacy SQL file not found: $originalFile\n";
    exit(1);
}

$rawSql = file_get_contents($originalFile);
$lines = explode("\n", $rawSql);
$currentInsert = '';
$insertCount = 0;

foreach ($lines as $line) {
    if (stripos($line, 'INSERT INTO `posts`') === 0) {
        $currentInsert = str_replace('INSERT INTO `posts`', 'INSERT INTO `posts_legacy`', $line);
    } elseif (!empty($currentInsert)) {
        $currentInsert .= "\n" . $line;
        if (substr(trim($line), -1) === ';') {
            $sqlContent .= "\n" . $currentInsert;
            $insertCount++;
            $currentInsert = '';
        }
    }
}

file_put_contents($tempFile, $sqlContent);

// Execute via mysql
$cmd = sprintf(
    "mysql -h %s -u %s -p%s %s < %s 2>&1",
    $dbConfig['host'],
    $dbConfig['user'],
    $dbConfig['pass'],
    $dbConfig['name'],
    $tempFile
);

exec($cmd, $output, $return);
if ($return === 0) {
    $legacyCount = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy")->fetch()['cnt'];
    echo "✓ Loaded $legacyCount records from $insertCount INSERT statements\n\n";
} else {
    echo "✗ Failed to load legacy data\n";
    foreach ($output as $line) {
        if (trim($line)) echo "  " . $line . "\n";
    }
    unlink($tempFile);
    exit(1);
}

// Step 3: Transform legacy schema to new CDM schema
echo "Step 3: Transforming legacy schema to CDM...\n";

$transformSql = <<<'XSQL'
INSERT INTO posts (
    Id, Slug, Title, Body, Status, PublishedAt, CreatedAt, UpdatedAt, engagement_source_tracking
)
SELECT
    pl.Id,
    pl.Slug,
    pl.Title,
    pl.Body,
    CASE WHEN pl.Published = 1 THEN 'published' ELSE 'draft' END,
    IFNULL(pl.PublishedDate, pl.Created),
    pl.Created,
    pl.Updated,
    1
FROM posts_legacy pl
WHERE pl.Title IS NOT NULL AND pl.Title != '' AND pl.Slug IS NOT NULL AND pl.Slug != ''
ON DUPLICATE KEY UPDATE
    Title = VALUES(Title),
    Body = VALUES(Body),
    Status = VALUES(Status),
    PublishedAt = VALUES(PublishedAt),
    UpdatedAt = VALUES(UpdatedAt)
XSQL;

try {
    $db->exec($transformSql);
    $finalCount = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE CreatedAt IS NOT NULL")->fetch()['cnt'];
    echo "✓ Transformed $finalCount posts to new schema\n\n";
} catch (\Exception $e) {
    echo "✗ Transformation failed: " . $e->getMessage() . "\n";
    unlink($tempFile);
    exit(1);
}

// Step 4: Verify results
echo "Step 4: Verifying migration...\n";
$statuses = $db->query("SELECT Status, COUNT(*) as cnt FROM posts GROUP BY Status");
$statusBreakdown = [];
while ($row = $statuses->fetch()) {
    $statusBreakdown[$row['Status']] = $row['cnt'];
}

foreach ($statusBreakdown as $status => $count) {
    echo "  • $status: $count posts\n";
}

// Get date range
$dateRange = $db->query("SELECT MIN(PublishedAt) as earliest, MAX(PublishedAt) as latest FROM posts WHERE PublishedAt IS NOT NULL")->fetch();
if ($dateRange['earliest']) {
    echo "  • Date range: {$dateRange['earliest']} to {$dateRange['latest']}\n";
}

echo "\n";

// Step 5: Show sample data
echo "Step 5: Sample migrated posts...\n";
$result = $db->query("SELECT Id, Title, Status FROM posts ORDER BY PublishedAt DESC LIMIT 10");
while ($row = $result->fetch()) {
    $title = strlen($row['Title']) > 50 ? substr($row['Title'], 0, 47) . '...' : $row['Title'];
    printf("  [%-3d] %-50s [%s]\n", $row['Id'], $title, $row['Status']);
}

echo "\n";

// Cleanup
unlink($tempFile);

echo str_repeat("=", 60) . "\n";
echo "MIGRATION COMPLETE\n";
echo str_repeat("=", 60) . "\n";
echo "\nNote: posts_legacy table retained for verification\n";
echo "To remove: DROP TABLE posts_legacy;\n\n";
