<?php
/**
 * Migrate posts data - direct approach
 * Loads legacy data into posts_legacy, then transforms to posts
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\DB\SqlStatementParser;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "=== POSTS MIGRATION ===\n\n";

// Step 1: Create posts_legacy table with legacy schema
echo "Step 1: Creating posts_legacy table with legacy schema...\n";
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

// Step 2: Load legacy data from the SQL file into posts_legacy
echo "Step 2: Loading legacy posts data from SQL file...\n";
$filePath = '/Users/brock/Library/CloudStorage/GoogleDrive-brock@brockstarr.com/Shared drives/Sites/ngn2.0/storage/uploads/legacy backups/032925.sql';
$sql = file_get_contents($filePath);
$statements = SqlStatementParser::parse($sql);

$legacyInserts = 0;
foreach ($statements as $stmt) {
    // Only process INSERT INTO posts statements, change them to posts_legacy
    if (stripos($stmt, 'INSERT INTO `posts`') === 0) {
        // Replace posts with posts_legacy
        $stmt = str_replace('INSERT INTO `posts`', 'INSERT INTO `posts_legacy`', $stmt);
        try {
            $db->exec($stmt);
            $legacyInserts++;
        } catch (\Exception $e) {
            // Continue on errors - some might be duplicates
        }
    }
}
echo "✓ Loaded $legacyInserts legacy posts INSERT statements\n\n";

// Step 3: Count the legacy data
$legacyCount = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy")->fetch()['cnt'];
echo "Step 3: Legacy data count...\n";
echo "✓ $legacyCount records in posts_legacy\n\n";

// Step 4: Transform from posts_legacy to posts
echo "Step 4: Transforming legacy data to new schema...\n";
$transformSql = <<<'SQL'
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
WHERE pl.Title IS NOT NULL
  AND pl.Title != ''
  AND pl.Slug IS NOT NULL
  AND pl.Slug != ''
ON DUPLICATE KEY UPDATE
    Title = VALUES(Title),
    Body = VALUES(Body),
    Status = VALUES(Status),
    PublishedAt = VALUES(PublishedAt),
    UpdatedAt = VALUES(UpdatedAt)
SQL;

try {
    $db->exec($transformSql);
    echo "✓ Transform SQL executed\n\n";
} catch (\Exception $e) {
    echo "✗ Transform failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 5: Verify the migration
echo "Step 5: Verifying migration...\n";
$finalCount = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE CreatedAt IS NOT NULL")->fetch()['cnt'];
echo "✓ Final posts count: $finalCount\n";

if ($finalCount > 0) {
    echo "\n=== SUCCESS ===\n";
    echo "$finalCount posts successfully migrated!\n\n";

    // Show sample
    $result = $db->query("SELECT Id, Title, Status, PublishedAt FROM posts ORDER BY PublishedAt DESC LIMIT 5");
    echo "Sample migrated posts:\n";
    while ($row = $result->fetch()) {
        echo sprintf("  [%d] %s (%s) - %s\n",
            $row['Id'],
            substr($row['Title'], 0, 40),
            $row['Status'],
            $row['PublishedAt']
        );
    }
} else {
    echo "\n=== NO DATA ===\n";
    echo "Warning: No posts found after transformation\n";
}

// Step 6: Cleanup (optional - keep posts_legacy for reference)
echo "\nKeeping posts_legacy table for reference\n";
echo "To clean up later: DROP TABLE posts_legacy;\n";
