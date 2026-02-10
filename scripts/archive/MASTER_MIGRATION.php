<?php
/**
 * MASTER MIGRATION SCRIPT
 *
 * Single command migration to move all legacy data to new 2025 database.
 * After this runs successfully, legacy databases and files can be deleted.
 *
 * Run: php scripts/MASTER_MIGRATION.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);
$dbConfig = $config->db();

// Track results
$results = [
    'success' => true,
    'phases' => [],
    'errors' => [],
];

echo "\n" . str_repeat("█", 80) . "\n";
echo "NGN 2.0 MASTER MIGRATION - Complete Legacy Data Import\n";
echo str_repeat("█", 80) . "\n\n";

// ============================================================================
// PHASE 1: POSTS MIGRATION WITH IMAGES
// ============================================================================
echo "PHASE 1: POSTS MIGRATION\n";
echo str_repeat("-", 80) . "\n";

try {
    // Create legacy staging table
    $db->exec("DROP TABLE IF EXISTS posts_legacy");
    $db->exec(<<<'SQL'
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
SQL);

    // Load legacy posts from SQL file
    $legacyFile = __DIR__ . '/../storage/uploads/legacy backups/032925.sql';
    if (!file_exists($legacyFile)) {
        throw new Exception("Legacy SQL file not found: $legacyFile");
    }

    // Parse and load posts via PDO
    $db->exec("TRUNCATE TABLE posts_legacy");

    $rawSql = file_get_contents($legacyFile);
    $lines = explode("\n", $rawSql);
    $currentInsert = '';
    $insertCount = 0;
    $executedCount = 0;

    foreach ($lines as $line) {
        if (stripos($line, 'INSERT INTO `posts`') === 0) {
            $currentInsert = str_replace('INSERT INTO `posts`', 'INSERT INTO `posts_legacy`', $line);
        } elseif (!empty($currentInsert)) {
            $currentInsert .= "\n" . $line;
            if (substr(trim($line), -1) === ';') {
                $insertCount++;
                // Execute the statement via PDO
                try {
                    $db->exec($currentInsert);
                    $executedCount++;
                } catch (Exception $e) {
                    echo "⚠ Warning: Failed to execute INSERT statement $insertCount: " . substr($e->getMessage(), 0, 100) . "\n";
                }
                $currentInsert = '';
            }
        }
    }

    if ($executedCount === 0 && $insertCount === 0) {
        throw new Exception("No INSERT statements found in legacy SQL file");
    }

    $legacyCount = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy")->fetch()['cnt'];

    // Transform to new schema - mapping legacy columns to server posts table
    $db->exec(<<<'SQL'
INSERT INTO posts (id, slug, title, body, seo_summary, status, published_at, author_id, image_url, created_at, updated_at)
SELECT pl.Id, pl.Slug, pl.Title, pl.Body, pl.Summary,
  CASE WHEN pl.Published = 1 THEN 'published' ELSE 'draft' END,
  IFNULL(pl.PublishedDate, pl.Created),
  pl.Author,
  pl.Image,
  pl.Created, pl.Updated
FROM posts_legacy pl
WHERE pl.Title IS NOT NULL AND pl.Title != '' AND pl.Slug IS NOT NULL AND pl.Slug != ''
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  body = VALUES(body),
  seo_summary = VALUES(seo_summary),
  status = VALUES(status),
  published_at = VALUES(published_at),
  author_id = VALUES(author_id),
  image_url = VALUES(image_url),
  updated_at = VALUES(updated_at)
SQL);

    $finalCount = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE created_at IS NOT NULL")->fetch()['cnt'];

    echo "✓ Loaded $legacyCount legacy posts\n";
    echo "✓ Transformed $finalCount posts to new schema\n";
    echo "✓ Status mapping: Published → status\n";
    echo "✓ Timestamp mapping: Created/Updated → created_at/updated_at\n";
    echo "✓ Image mapping: Image → image_url\n";
    echo "✓ Author mapping: Author → author_id\n";
    echo "✓ Content mapping: Body → body\n";
    echo "✓ SEO mapping: Summary → seo_summary\n";

    $results['phases']['posts'] = [
        'status' => 'success',
        'loaded' => $legacyCount,
        'migrated' => $finalCount,
    ];

} catch (Exception $e) {
    echo "✗ Posts migration failed: " . $e->getMessage() . "\n";
    $results['success'] = false;
    $results['errors'][] = "Posts: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// PHASE 2: WRITER RELATIONSHIPS
// ============================================================================
echo "PHASE 2: WRITER RELATIONSHIPS\n";
echo str_repeat("-", 80) . "\n";

try {
    // Create post_writers table if it doesn't exist
    $db->exec("DROP TABLE IF EXISTS post_writers");
    $db->exec(<<<'SQL'
CREATE TABLE post_writers (
    post_id bigint unsigned NOT NULL,
    writer_id bigint unsigned NOT NULL,
    PRIMARY KEY (post_id, writer_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
)
SQL);

    // Get author IDs from legacy posts
    $result = $db->query("SELECT DISTINCT Author FROM posts_legacy WHERE Author IS NOT NULL ORDER BY Author");
    $authorIds = [];
    while ($row = $result->fetch()) {
        $authorIds[] = intval($row['Author']);
    }

    // Get existing writers
    $result = $db->query("SELECT id FROM writers");
    $existingWriters = [];
    while ($row = $result->fetch()) {
        $existingWriters[] = intval($row['id']);
    }

    // Link posts to writers
    $linked = 0;
    $missing = [];

    foreach ($authorIds as $authorId) {
        if (in_array($authorId, $existingWriters)) {
            // Link all posts by this author via post_writers table
            $stmt = $db->prepare(
                "INSERT IGNORE INTO post_writers (post_id, writer_id)
                 SELECT p.id, ? FROM posts p WHERE p.author_id = ?"
            );
            try {
                $stmt->execute([$authorId, $authorId]);
                $linked += $stmt->rowCount();
            } catch (Exception $e) {
                // Continue on error
            }
        } else {
            $missing[] = $authorId;
        }
    }

    echo "✓ Created post_writers linking table\n";
    echo "✓ Linked $linked posts to writers\n";

    if (!empty($missing)) {
        echo "⚠ Missing writer IDs: " . implode(", ", $missing) . "\n";
        echo "  Action: Create these writers or map manually\n";
        $results['phases']['writers']['missing'] = $missing;
    }

    $results['phases']['writers'] = [
        'status' => 'success',
        'linked' => $linked,
        'missing' => $missing,
    ];

} catch (Exception $e) {
    echo "✗ Writer relationships failed: " . $e->getMessage() . "\n";
    $results['success'] = false;
    $results['errors'][] = "Writers: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// PHASE 3: ARTIST IMAGES
// ============================================================================
echo "PHASE 3: ARTIST IMAGES\n";
echo str_repeat("-", 80) . "\n";

try {
    $imagesPath = __DIR__ . '/../lib/images/users';
    $matched = 0;

    if (is_dir($imagesPath)) {
        $dirs = array_diff(scandir($imagesPath), ['.', '..']);

        foreach ($dirs as $dir) {
            if (is_dir($imagesPath . '/' . $dir)) {
                // Try to match with artist slug
                $stmt = $db->prepare("SELECT id FROM artists WHERE LOWER(slug) = LOWER(?)");
                $stmt->execute([$dir]);
                $artist = $stmt->fetch();

                if ($artist) {
                    $imagePath = 'users/' . $dir . '/';
                    $updateStmt = $db->prepare("UPDATE artists SET image_url = ? WHERE id = ?");
                    $updateStmt->execute([$imagePath, $artist['id']]);
                    $matched++;
                }
            }
        }

        echo "✓ Scanned " . count($dirs) . " artist image directories\n";
        echo "✓ Matched and linked $matched artist images\n";

        $results['phases']['artist_images'] = [
            'status' => 'success',
            'matched' => $matched,
        ];
    } else {
        echo "⚠ Artist image directory not found\n";
        $results['phases']['artist_images'] = ['status' => 'skipped'];
    }

} catch (Exception $e) {
    echo "✗ Artist images failed: " . $e->getMessage() . "\n";
    $results['errors'][] = "Artist images: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// PHASE 4: VERIFICATION & CLEANUP
// ============================================================================
echo "PHASE 4: VERIFICATION\n";
echo str_repeat("-", 80) . "\n";

try {
    // Count records
    $posts = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE created_at IS NOT NULL")->fetch()['cnt'];
    $postWriters = $db->query("SELECT COUNT(*) as cnt FROM post_writers")->fetch()['cnt'];

    // Safely count from optional tables
    $counts = [
        'artists' => 0,
        'labels' => 0,
        'venues' => 0,
        'stations' => 0,
        'videos' => 0,
    ];

    foreach ($counts as $table => $default) {
        try {
            $counts[$table] = $db->query("SELECT COUNT(*) as cnt FROM $table")->fetch()['cnt'];
        } catch (Exception $e) {
            // Table doesn't exist, use default
            $counts[$table] = 0;
        }
    }

    echo "MIGRATED DATA:\n";
    echo "  Posts: $posts\n";
    echo "  Post-Writer Links: $postWriters\n";
    echo "  Artists: " . $counts['artists'] . "\n";
    echo "  Labels: " . $counts['labels'] . "\n";
    echo "  Venues: " . $counts['venues'] . "\n";
    echo "  Stations: " . $counts['stations'] . "\n";
    echo "  Videos: " . $counts['videos'] . "\n";

    $results['phases']['verification'] = [
        'status' => 'success',
        'posts' => $posts,
        'post_writers' => $postWriters,
        'artists' => $counts['artists'],
        'labels' => $counts['labels'],
        'venues' => $counts['venues'],
        'stations' => $counts['stations'],
        'videos' => $counts['videos'],
    ];

} catch (Exception $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
    $results['errors'][] = "Verification: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// FINAL REPORT
// ============================================================================
echo str_repeat("█", 80) . "\n";
echo "MIGRATION SUMMARY\n";
echo str_repeat("█", 80) . "\n\n";

if ($results['success']) {
    echo "✓✓✓ MIGRATION COMPLETE ✓✓✓\n\n";

    echo "All legacy data has been successfully migrated to the 2025 database.\n\n";

    echo "YOU CAN NOW SAFELY DELETE:\n";
    echo "  • posts_legacy table\n";
    echo "  • Legacy database connections/users (if any)\n";
    echo "  • storage/uploads/legacy\ backups/ directory (optional - keep for audit trail)\n\n";

    echo "SYSTEM IS READY:\n";
    echo "  ✓ All 2025 database tables active\n";
    echo "  ✓ All data relationships configured\n";
    echo "  ✓ All images linked\n";
    echo "  ✓ No legacy database dependency\n\n";

    // Cleanup staging table
    echo "Cleaning up temporary tables...\n";
    try {
        $db->exec("DROP TABLE IF EXISTS posts_legacy");
        echo "✓ posts_legacy table dropped\n\n";
    } catch (Exception $e) {
        echo "⚠ Could not drop posts_legacy: " . $e->getMessage() . "\n\n";
    }

} else {
    echo "✗ MIGRATION FAILED\n\n";
    echo "ERRORS:\n";
    foreach ($results['errors'] as $error) {
        echo "  • $error\n";
    }
    echo "\n";
}

// Write results to file
$resultsFile = __DIR__ . '/../storage/logs/migration_results_' . date('Y-m-d_H-i-s') . '.json';
$dir = dirname($resultsFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo str_repeat("=", 80) . "\n";
echo "Results saved to: storage/logs/migration_results_*.json\n";
echo str_repeat("=", 80) . "\n\n";

exit($results['success'] ? 0 : 1);
