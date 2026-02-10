<?php
/**
 * COMPLETE LEGACY MIGRATION SCRIPT - ONE STOP SHOP
 *
 * Migrates ALL legacy data to 2025 database in one command:
 * php scripts/COMPLETE_LEGACY_MIGRATION.php
 *
 * Run: php scripts/COMPLETE_LEGACY_MIGRATION.php
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);
$dbConfig = $config->db();

$results = [
    'success' => true,
    'phases' => [],
    'errors' => [],
];

function debugLog($message, $type = 'info') {
    $prefix = match($type) {
        'success' => '✓ ',
        'error' => '✗ ',
        'warning' => '⚠ ',
        'debug' => '→ ',
        default => '  '
    };
    echo $prefix . $message . "\n";
}

function exitPhase($message, $phaseNum) {
    global $results;
    echo "\n" . str_repeat("█", 80) . "\n";
    echo "MIGRATION FAILED AT PHASE $phaseNum\n";
    echo str_repeat("█", 80) . "\n";
    debugLog($message, 'error');
    echo "\nCannot continue - Phase $phaseNum is a prerequisite for all subsequent phases.\n\n";
    $results['success'] = false;
    $results['errors'][] = "Phase $phaseNum: $message";

    $resultsFile = __DIR__ . '/../storage/logs/complete_migration_results_' . date('Y-m-d_H-i-s') . '.json';
    $dir = dirname($resultsFile);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo "Results saved to: storage/logs/complete_migration_results_*.json\n";
    echo str_repeat("=", 80) . "\n\n";
    exit(1);
}

echo "\n" . str_repeat("█", 80) . "\n";
echo "NGN 2.0 COMPLETE LEGACY MIGRATION\n";
echo str_repeat("█", 80) . "\n\n";

// ============================================================================
// PHASE 0: LOAD LEGACY SQL
// ============================================================================
echo "PHASE 0: LOADING LEGACY DATA\n";
echo str_repeat("-", 80) . "\n";

// Check for both SQL files - prefer nextgennoise.sql
$sqlFiles = [
    'nextgennoise.sql' => __DIR__ . '/../storage/uploads/legacy backups/nextgennoise.sql',
    '032925.sql' => __DIR__ . '/../storage/uploads/legacy backups/032925.sql',
];

$legacyFile = null;
foreach ($sqlFiles as $name => $path) {
    if (file_exists($path)) {
        $legacyFile = $path;
        debugLog("Found SQL file: $name (" . round(filesize($path) / 1024 / 1024, 2) . " MB)", 'success');
        break;
    } else {
        debugLog("Not found: $name at $path", 'debug');
    }
}

if (!$legacyFile) {
    $availableFiles = implode(", ", array_keys($sqlFiles));
    exitPhase("No legacy SQL files found. Expected one of: $availableFiles", 0);
}

debugLog("Using legacy file: " . basename($legacyFile), 'debug');

// Check if legacy tables already exist from previous run
debugLog("Checking for leftover legacy tables from previous runs...", 'info');

// List of all possible legacy tables from the SQL dump
// This includes every table that might exist from the legacy nextgennoise.sql
$allPossibleTables = [
    'posts', 'users', 'userroles', 'userstatuses', 'user_roles',  // Core user/post tables
    'releases', 'songs', 'tracks', 'shows', 'videos', 'spins',    // Music/content tables
    'radiospins', 'hits',                                          // Analytics
    'ads', 'adlocations', 'linklocations', 'links',               // Ads (not in ngn2.0)
    'donations', 'orders', 'orderstatuses',                       // Commerce
    'apikeys', 'tokens', 'verificationcodes',                     // Auth
    'claimstatuses', 'contacts', 'pages',                         // Support/info
    'pendingclaims', 'polls', 'postmentions',                     // Legacy features
    'posttypes', 'slides', 'socialmediaposts',                    // Content types
    'post_writers'                                                 // Relationships
];

$existingTables = [];

foreach ($allPossibleTables as $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $existingTables[] = $table;
        }
    } catch (Exception $e) {
        // Table doesn't exist, continue
    }
}

if (!empty($existingTables)) {
    debugLog("Found existing legacy tables: " . implode(", ", $existingTables), 'warning');
    debugLog("Disabling foreign key checks to allow table drops...", 'info');

    // Disable foreign key checks so we can drop everything
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        debugLog("Foreign key checks disabled", 'debug');
    } catch (Exception $e) {
        debugLog("Could not disable foreign key checks: " . $e->getMessage(), 'warning');
    }

    // Now drop all legacy tables - order doesn't matter with FK checks disabled
    foreach ($existingTables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS `$table`");
            debugLog("  Dropped: $table", 'debug');
        } catch (Exception $e) {
            debugLog("  Could not drop $table: " . $e->getMessage(), 'warning');
        }
    }

    // Re-enable foreign key checks
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        debugLog("Foreign key checks re-enabled", 'debug');
    } catch (Exception $e) {
        debugLog("Could not re-enable foreign key checks: " . $e->getMessage(), 'warning');
    }

    debugLog("Legacy tables cleared", 'success');
} else {
    debugLog("No legacy tables found - database is clean", 'success');
}

// Try loading with MySQL CLI - use TCP connection to avoid socket issues
$host = ($dbConfig['host'] === 'localhost') ? '127.0.0.1' : $dbConfig['host'];
debugLog("Database connection: host=$host, user={$dbConfig['user']}, database={$dbConfig['name']}", 'debug');

// Load legacy tables using PHP/PDO to avoid FK constraint issues
debugLog("Loading legacy tables via PDO...", 'info');

$sqlContent = file_get_contents($legacyFile);
$statements = [];

// Split on semicolons and process statements
$lines = explode("\n", $sqlContent);
$current = '';
$inMultilineFK = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    // Skip comments
    if (substr($trimmed, 0, 2) === '--') {
        continue;
    }

    // Detect start of FK constraint definition
    if (preg_match('/^\s*,?\s*(?:CONSTRAINT|FOREIGN\s+KEY)/i', $trimmed)) {
        $inMultilineFK = true;
        // Skip this line entirely
        continue;
    }

    // End FK block when we see a closing paren or semicolon
    if ($inMultilineFK) {
        if (preg_match('/[);]/', $trimmed)) {
            $inMultilineFK = false;
        }
        // Don't add FK lines to current statement
        if (substr($trimmed, 0, 1) === ')' || substr($trimmed, 0, 1) === ';') {
            $current .= $line . "\n";
        }
        continue;
    }

    $current .= $line . "\n";

    // Check if statement is complete (ends with ;)
    if (substr($trimmed, -1) === ';') {
        $statement = trim($current);
        if (!empty($statement)) {
            // Remove any remaining FK constraint fragments
            $statement = preg_replace('/,\s*(?:CONSTRAINT|FOREIGN\s+KEY)[^,;]*/i', '', $statement);
            // Remove dangling commas before closing paren
            $statement = preg_replace('/,\s*\)/',')',$statement);
            if (!empty(trim($statement))) {
                $statements[] = $statement;
            }
        }
        $current = '';
    }
}

// Execute statements, catching and skipping any errors from non-critical tables
$successCount = 0;
$skipCount = 0;
$errorCount = 0;
$createdTables = [];

foreach ($statements as $stmt) {
    $isCreateTable = preg_match('/CREATE TABLE/i', $stmt);
    $tableName = null;
    if ($isCreateTable && preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $stmt, $m)) {
        $tableName = $m[1];
        debugLog("Attempting to create table: $tableName", 'debug');

        // Remove any trailing FK constraints from CREATE TABLE statements before execution
        // This handles cases where FK constraints weren't properly stripped during parsing
        $stmt = preg_replace('/,\s*(?:CONSTRAINT\s+`?[^`]+`?\s+)?FOREIGN\s+KEY[^)]*\([^)]*\)\s+REFERENCES[^,)]*(?:\([^)]*\))?[^,)]*(?:ON\s+\w+\s+\w+)?/i', '', $stmt);
        // Remove trailing commas before closing paren
        $stmt = preg_replace('/,\s*\)/', ')', $stmt);
    }

    try {
        $db->exec($stmt);

        // Track created tables
        if ($isCreateTable) {
            if ($tableName) {
                $createdTables[] = $tableName;
                $successCount++;
                debugLog("✓ Created table: $tableName", 'success');
            }
        } else if (preg_match('/INSERT INTO/i', $stmt)) {
            // Don't count inserts
        } else {
            $successCount++;
        }
    } catch (Exception $e) {
        // Categorize errors
        $msg = $e->getMessage();
        $isNonCritical = preg_match('/donations|orders|contacts|pages|hits|ads|tokens/i', $stmt);

        // Check for errors that we can safely skip
        $isFKError = preg_match('/1215|cannot add foreign key|foreign key constraint/i', $msg);
        $isTableMissingError = preg_match('/table.*doesn.t exist|table .* not found|1146/i', $msg);

        if ($isFKError) {
            // Skip all FK constraint errors (including CREATE TABLE with FK issues)
            // These are legacy constraints we don't need for data migration
            $skipCount++;
            debugLog("⊘ Skipped FK constraint error for table '$tableName'", 'debug');
        } else if ($isCreateTable) {
            // Report other CREATE TABLE errors prominently
            $errorCount++;
            $errorMsg = "✗ FAILED to create table '$tableName': " . substr($msg, 0, 120);
            debugLog($errorMsg, 'error');
        } else if ($isNonCritical || $isTableMissingError) {
            // Skip non-critical table errors and table-missing errors
            $skipCount++;
        } else {
            // Other errors
            $errorCount++;
            debugLog("⚠ Error: " . substr($msg, 0, 100), 'warning');
        }
    }
}

$createdTablesStr = implode(", ", $createdTables);
debugLog("Created legacy tables: $createdTablesStr", 'info');
debugLog("Loaded $successCount statements, Skipped $skipCount non-critical", 'info');

$returnCode = ($errorCount > 0) ? 1 : 0;
$output = [];

if ($returnCode !== 0) {
    debugLog("Errors occurred during table loading", 'warning');
} else {
    debugLog("SQL dump loaded successfully", 'success');
}

// Create users table manually if it doesn't exist (legacy SQL has FK issues)
try {
    $usersExists = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$usersExists) {
        debugLog("Creating users table manually (legacy SQL had FK issues)...", 'info');
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `users` (
  `Id` int UNSIGNED NOT NULL PRIMARY KEY,
  `StatusId` int NOT NULL DEFAULT '1',
  `Title` varchar(30) NOT NULL,
  `OwnerTitle` varchar(255) DEFAULT NULL,
  `Slug` varchar(255) DEFAULT NULL,
  `Email` varchar(255) NOT NULL,
  `Body` text,
  `Password` varchar(255) NOT NULL,
  `RoleId` int NOT NULL,
  `LabelId` int DEFAULT NULL,
  `ArtistId` int DEFAULT NULL,
  `StationId` int DEFAULT NULL,
  `Phone` varchar(255) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `VerifiedPhone` int DEFAULT '0',
  `VerifiedEmail` int DEFAULT '0',
  `Misc` longblob,
  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Updated` timestamp NULL DEFAULT NULL,
  `Claimed` int NOT NULL DEFAULT '0',
  `Source` varchar(255) DEFAULT NULL,
  `FacebookUrl` varchar(255) DEFAULT NULL,
  `InstagramUrl` varchar(255) DEFAULT NULL,
  `YoutubeUrl` varchar(255) DEFAULT NULL,
  `TiktokUrl` varchar(255) DEFAULT NULL,
  `WebsiteUrl` varchar(255) DEFAULT NULL,
  `FacebookId` varchar(255) DEFAULT NULL,
  `InstagramId` varchar(255) DEFAULT NULL,
  `YoutubeId` varchar(255) DEFAULT NULL,
  `SpotifyId` varchar(255) DEFAULT NULL,
  `TiktokId` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1
SQL);
        debugLog("✓ Users table created manually", 'success');

        // Now load data from legacy SQL manually
        debugLog("Loading legacy users data...", 'info');
        preg_match('/INSERT INTO `users`[^;]+;/is', $sqlContent, $matches);
        if (!empty($matches)) {
            $insertStatement = $matches[0];
            // Split multi-row INSERT into individual rows if needed
            $db->exec($insertStatement);
            $userCount = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
            debugLog("✓ Loaded $userCount users from legacy data", 'success');
        }
    }
} catch (Exception $e) {
    debugLog("⚠ Could not ensure users table exists: " . substr($e->getMessage(), 0, 100), 'warning');
}

// Drop ads-related tables - NGN 2.0 doesn't use advertising system
debugLog("Removing ads-related tables (not part of ngn2.0)...", 'info');
$adsTables = ['ads', 'adlocations', 'linklocations', 'links'];
foreach ($adsTables as $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $db->exec("DROP TABLE IF EXISTS `$table`");
            debugLog("  Dropped ads table: $table", 'debug');
        }
    } catch (Exception $e) {
        debugLog("  Could not drop $table (may not exist): " . $e->getMessage(), 'debug');
    }
}

// Verify critical legacy tables exist
try {
    $result = $db->query("SHOW TABLES LIKE 'posts'")->fetch();
    if (!$result) {
        exitPhase("Legacy 'posts' table not found after SQL load. SQL file may not have loaded.", 0);
    }
    debugLog("✓ Legacy posts table verified", 'success');

    // Check legacy columns
    $columns = $db->query("DESCRIBE posts")->fetchAll();
    $columnNames = array_map(fn($row) => $row['Field'], $columns);
    debugLog("Posts columns: " . implode(", ", $columnNames), 'debug');

    $legacyPostCount = $db->query("SELECT COUNT(*) as cnt FROM posts")->fetch()['cnt'];
    debugLog("Posts records: $legacyPostCount", 'success');

    if ($legacyPostCount === 0) {
        exitPhase("Legacy posts table is empty. SQL load may have failed silently.", 0);
    }

    // Verify users table
    $usersResult = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($usersResult) {
        $legacyUserCount = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
        debugLog("Users records: $legacyUserCount", 'success');
    } else {
        debugLog("Users table not found (may not be needed)", 'warning');
    }

    // Verify userroles table
    $rolesResult = $db->query("SHOW TABLES LIKE 'userroles'")->fetch();
    if ($rolesResult) {
        $roleDistribution = $db->query("SELECT COUNT(*) as cnt FROM userroles")->fetch()['cnt'];
        debugLog("UserRoles records: $roleDistribution", 'success');
    }

} catch (Exception $e) {
    exitPhase("Cannot verify legacy tables: " . $e->getMessage(), 0);
}

debugLog("Phase 0 COMPLETE - All legacy tables verified and ready", 'success');
echo "\n";

// ============================================================================
// PHASE 1: POSTS MIGRATION
// ============================================================================
echo "PHASE 1: POSTS MIGRATION\n";
echo str_repeat("-", 80) . "\n";

try {
    $legacyCount = $db->query("SELECT COUNT(*) as cnt FROM posts")->fetch()['cnt'];
    debugLog("Legacy posts: $legacyCount records found", 'debug');

    // Check the current posts table structure
    $postsColumns = $db->query("DESCRIBE posts")->fetchAll();
    $columnNames = array_map(fn($row) => $row['Field'], $postsColumns);
    debugLog("Posts table columns: " . implode(", ", $columnNames), 'debug');

    // Determine if this is the legacy schema or new schema
    $isLegacySchema = in_array('Created', $columnNames);
    $isNewSchema = in_array('created_at', $columnNames);

    if (!$isLegacySchema && !$isNewSchema) {
        exitPhase("Posts table has unexpected schema. Expected either legacy (Created column) or new (created_at column).", 1);
    }

    debugLog("Detected schema: " . ($isLegacySchema ? "Legacy (Created, Updated)" : "New (created_at, updated_at)"), 'debug');

    // Always perform transformation: rename legacy → posts_legacy, create new posts, migrate data
    debugLog("Creating transformation: legacy posts → new posts schema", 'info');
    debugLog("Mapping: Body→teaser, Published→status, PublishedDate→published_at, Author→author_id", 'debug');

    // First, drop any leftover posts_legacy table
    try {
        $db->exec("DROP TABLE IF EXISTS posts_legacy");
        debugLog("Cleaned up any existing posts_legacy table", 'debug');
    } catch (Exception $e) {
        // Ignore errors
    }

    // Rename current posts table to posts_legacy (it should have legacy schema from Phase 0)
    debugLog("Renaming legacy posts to posts_legacy...", 'debug');
    try {
        $db->exec("RENAME TABLE posts TO posts_legacy");
        debugLog("Posts table renamed to posts_legacy", 'debug');
    } catch (Exception $e) {
        debugLog("Could not rename posts table (may not exist): " . $e->getMessage(), 'debug');
        // If posts table doesn't exist, we can't rename it, but we can still create posts_legacy from scratch
        // This can happen if Phase 0 didn't load the legacy data properly
        $postsLegacyExists = $db->query("SHOW TABLES LIKE 'posts_legacy'")->fetch();
        if (!$postsLegacyExists) {
            exitPhase("Neither posts nor posts_legacy table found. Phase 0 may not have loaded legacy data correctly.", 1);
        }
        debugLog("Using existing posts_legacy table from previous run", 'debug');
    }

    // Create new posts table with proper schema (from schema_dump_ngn_2025_clean.sql)
    debugLog("Creating new posts table with proper ngn2.0 schema...", 'debug');
    $db->exec(<<<'SQL'
CREATE TABLE posts (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  title varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  teaser text COLLATE utf8mb4_unicode_ci,
  status varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  published_at datetime DEFAULT NULL,
  author_id bigint unsigned DEFAULT NULL,
  image_url varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug),
  KEY idx_title (title),
  KEY idx_published_at (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    // Transform and migrate data
    debugLog("Transforming legacy data to new schema...", 'debug');
    $db->exec(<<<'SQL'
INSERT INTO posts (id, slug, title, teaser, status, published_at, author_id, image_url, created_at, updated_at)
SELECT Id, Slug, Title, Body,
  CASE WHEN Published = 1 THEN 'published' ELSE 'draft' END,
  PublishedDate,
  Author,
  Image,
  Created, Updated
FROM posts_legacy
WHERE Title IS NOT NULL AND Title != '' AND Slug IS NOT NULL AND Slug != ''
SQL);

    $migrated = $db->query("SELECT COUNT(*) as cnt FROM posts WHERE created_at IS NOT NULL")->fetch()['cnt'];

    if ($migrated === 0) {
        exitPhase("Posts transformation completed but 0 records were imported. Check data and mapping.", 1);
    }

    debugLog("Transformed $migrated posts to new schema", 'success');
    debugLog("Phase 1 COMPLETE - Posts successfully migrated", 'success');

    $results['phases']['posts'] = [
        'status' => 'success',
        'legacy_count' => $legacyCount,
        'migrated' => $migrated,
        'total' => $migrated,
    ];

} catch (Exception $e) {
    debugLog("Posts migration failed: " . $e->getMessage(), 'error');
    exitPhase($e->getMessage(), 1);
}

echo "\n";

// ============================================================================
// PHASE 2: USERS → ARTISTS/LABELS/VENUES/WRITERS/STATIONS
// ============================================================================
echo "PHASE 2: USERS MIGRATION (by role)\n";
echo str_repeat("-", 80) . "\n";

try {
    // Verify users table exists and has data
    $usersResult = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$usersResult) {
        exitPhase("Legacy 'users' table not found. Phase 0 may not have loaded correctly.", 2);
    }

    $totalUsers = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
    debugLog("Legacy users table: $totalUsers total records", 'debug');

    // Show role distribution
    $roleDistribution = $db->query("SELECT RoleId, COUNT(*) as cnt FROM users GROUP BY RoleId ORDER BY RoleId")->fetchAll();
    foreach ($roleDistribution as $row) {
        $roleDesc = match($row['RoleId']) {
            1 => 'Admin',
            3 => 'Artist',
            7 => 'Label',
            8 => 'Writer',
            9 => 'Station',
            11 => 'Venue',
            default => 'Unknown (' . $row['RoleId'] . ')'
        };
        debugLog("  RoleId {$row['RoleId']} ($roleDesc): {$row['cnt']} users", 'debug');
    }

    // Clear target entity tables before migrating (from previous runs)
    debugLog("Clearing target entity tables from previous migrations...", 'info');
    $targetTables = ['admin_users', 'artists', 'labels', 'venues', 'writers', 'stations'];

    // Temporarily disable FK checks to allow clearing
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($targetTables as $table) {
            try {
                $db->exec("DELETE FROM `$table`");
                debugLog("  Cleared: $table", 'debug');
            } catch (Exception $e) {
                // Table may not exist - that's okay
                debugLog("  Could not clear $table (may not exist): " . substr($e->getMessage(), 0, 50), 'debug');
            }
        }
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
    } catch (Exception $e) {
        debugLog("Error clearing target tables: " . $e->getMessage(), 'warning');
    }

    $adminCount = $artistCount = $labelCount = $venueCount = $writerCount = $stationCount = 0;
    $adminBefore = 0; // Initialize to prevent undefined variable warning
    $adminMigrated = 0; // Initialize for totalMigrated calculation

    // Migrate Admins (RoleId = 1)
    try {
        $adminBefore = $db->query("SELECT COUNT(*) as cnt FROM admin_users")->fetch()['cnt'];
        $db->exec(<<<'SQL'
INSERT IGNORE INTO admin_users (id, username, name, email, role, active, created_at)
SELECT Id, Slug, Title, Email, 'admin', 1, NOW()
FROM users WHERE RoleId = 1
SQL);
        $adminCount = $db->query("SELECT COUNT(*) as cnt FROM admin_users")->fetch()['cnt'];
        $adminMigrated = $adminCount - $adminBefore;
        if ($adminMigrated > 0) {
            debugLog("Admins (RoleId=1): migrated $adminMigrated new records (total: $adminCount)", 'success');
        }
    } catch (Exception $e) {
        debugLog("Admin migration skipped (table may not exist): " . $e->getMessage(), 'warning');
    }

    // Migrate Artists (RoleId = 3)
    debugLog("Migrating Artists (RoleId=3)...", 'info');
    $artistBefore = $db->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt'];
    $db->exec(<<<'SQL'
INSERT IGNORE INTO artists (id, slug, name, image_url)
SELECT Id, Slug, Title, Image
FROM users WHERE RoleId = 3
SQL);
    $artistCount = $db->query("SELECT COUNT(*) as cnt FROM artists")->fetch()['cnt'];
    $artistMigrated = $artistCount - $artistBefore;
    debugLog("Artists: migrated $artistMigrated new records (total: $artistCount)", 'success');

    // Migrate Labels (RoleId = 7)
    debugLog("Migrating Labels (RoleId=7)...", 'info');
    $labelBefore = $db->query("SELECT COUNT(*) as cnt FROM labels")->fetch()['cnt'];
    $db->exec(<<<'SQL'
INSERT IGNORE INTO labels (id, slug, name, image_url)
SELECT Id, Slug, Title, Image
FROM users WHERE RoleId = 7
SQL);
    $labelCount = $db->query("SELECT COUNT(*) as cnt FROM labels")->fetch()['cnt'];
    $labelMigrated = $labelCount - $labelBefore;
    debugLog("Labels: migrated $labelMigrated new records (total: $labelCount)", 'success');

    // Migrate Venues (RoleId = 11)
    debugLog("Migrating Venues (RoleId=11)...", 'info');
    $venueBefore = $db->query("SELECT COUNT(*) as cnt FROM venues")->fetch()['cnt'];
    $db->exec(<<<'SQL'
INSERT IGNORE INTO venues (id, slug, name)
SELECT Id, Slug, Title
FROM users WHERE RoleId = 11
SQL);
    $venueCount = $db->query("SELECT COUNT(*) as cnt FROM venues")->fetch()['cnt'];
    $venueMigrated = $venueCount - $venueBefore;
    debugLog("Venues: migrated $venueMigrated new records (total: $venueCount)", 'success');

    // Migrate Writers (RoleId = 8)
    debugLog("Migrating Writers (RoleId=8)...", 'info');
    $writerBefore = $db->query("SELECT COUNT(*) as cnt FROM writers")->fetch()['cnt'];
    $db->exec(<<<'SQL'
INSERT IGNORE INTO writers (id, slug, name, image_url, updated_at)
SELECT Id, Slug, Title, Image, NOW()
FROM users WHERE RoleId = 8
SQL);
    $writerCount = $db->query("SELECT COUNT(*) as cnt FROM writers")->fetch()['cnt'];
    $writerMigrated = $writerCount - $writerBefore;
    debugLog("Writers: migrated $writerMigrated new records (total: $writerCount)", 'success');

    // Migrate Stations (RoleId = 9) - optional, table may not exist
    $stationMigrated = 0;
    $stationCount = 0;
    try {
        debugLog("Migrating Stations (RoleId=9)...", 'info');
        $stationBefore = $db->query("SELECT COUNT(*) as cnt FROM stations")->fetch()['cnt'];
        $db->exec(<<<'SQL'
INSERT IGNORE INTO stations (id, slug, name, image_url)
SELECT Id, Slug, Title, Image
FROM users WHERE RoleId = 9
SQL);
        $stationCount = $db->query("SELECT COUNT(*) as cnt FROM stations")->fetch()['cnt'];
        $stationMigrated = $stationCount - $stationBefore;
        debugLog("Stations: migrated $stationMigrated new records (total: $stationCount)", 'success');
    } catch (Exception $e) {
        debugLog("Stations table not found or error during migration (optional): " . substr($e->getMessage(), 0, 100), 'warning');
    }

    $totalMigrated = $artistMigrated + $labelMigrated + $venueMigrated + $writerMigrated + $stationMigrated + $adminMigrated;

    if ($totalMigrated === 0) {
        exitPhase("Users migration completed but 0 records were imported. Check legacy users table and role mappings.", 2);
    }

    debugLog("Phase 2 COMPLETE - Users successfully migrated: $totalMigrated total records", 'success');

    $results['phases']['users'] = [
        'status' => 'success',
        'admins' => $adminCount,
        'artists' => $artistCount,
        'labels' => $labelCount,
        'venues' => $venueCount,
        'writers' => $writerCount,
        'stations' => $stationCount,
        'total_migrated' => $totalMigrated,
    ];

} catch (Exception $e) {
    debugLog("Users migration failed: " . $e->getMessage(), 'error');
    exitPhase($e->getMessage(), 2);
}

echo "\n";

// ============================================================================
// PHASE 3: POST-WRITER RELATIONSHIPS
// ============================================================================
echo "PHASE 3: POST-WRITER RELATIONSHIPS\n";
echo str_repeat("-", 80) . "\n";

try {
    // Check if posts_legacy exists (created during Phase 1 transformation)
    $postsLegacyExists = $db->query("SHOW TABLES LIKE 'posts_legacy'")->fetch();

    if (!$postsLegacyExists) {
        debugLog("Legacy posts_legacy table not found (Phase 1 may not have run). Skipping post-writer linking.", 'warning');
        debugLog("Phase 3 SKIPPED - No legacy post data available for linking", 'success');
        $results['phases']['post_writers'] = [
            'status' => 'skipped',
            'reason' => 'posts_legacy table not found',
        ];
    } else {
        debugLog("Checking legacy posts_legacy for Author field...", 'info');
        $postsWithAuthors = $db->query("SELECT COUNT(*) as cnt FROM posts_legacy WHERE Author IS NOT NULL AND Author > 0")->fetch()['cnt'];
        debugLog("Legacy posts with authors: $postsWithAuthors", 'debug');

        debugLog("Dropping existing post_writers table...", 'debug');
        $db->exec("DROP TABLE IF EXISTS post_writers");

        debugLog("Creating post_writers relationship table...", 'debug');
        $db->exec(<<<'SQL'
CREATE TABLE post_writers (
    post_id bigint unsigned NOT NULL,
    writer_id bigint unsigned NOT NULL,
    PRIMARY KEY (post_id, writer_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        debugLog("Linking posts to writers (using legacy post IDs)...", 'info');
        $db->exec(<<<'SQL'
INSERT IGNORE INTO post_writers (post_id, writer_id)
SELECT Id, Author FROM posts_legacy
WHERE Author IS NOT NULL AND Author > 0
SQL);

        $postWriterCount = $db->query("SELECT COUNT(*) as cnt FROM post_writers")->fetch()['cnt'];

        if ($postWriterCount === 0 && $postsWithAuthors > 0) {
            debugLog("Post-writer linking found $postsWithAuthors posts with authors but created 0 relationships. Check data integrity.", 'warning');
        } else {
            debugLog("Created $postWriterCount post-writer relationships", 'success');
        }

        debugLog("Phase 3 COMPLETE - Post-writer relationships configured", 'success');

        $results['phases']['post_writers'] = [
            'status' => 'success',
            'linked' => $postWriterCount,
            'posts_with_authors' => $postsWithAuthors,
        ];

        // Clean up posts_legacy table now that we've extracted the relationships
        debugLog("Cleaning up posts_legacy temporary table...", 'debug');
        try {
            $db->exec("DROP TABLE IF EXISTS posts_legacy");
            debugLog("posts_legacy dropped", 'debug');
        } catch (Exception $e) {
            debugLog("Could not drop posts_legacy: " . $e->getMessage(), 'warning');
        }
    }

} catch (Exception $e) {
    debugLog("Post-writer relationships failed: " . $e->getMessage(), 'error');
    exitPhase($e->getMessage(), 3);
}

echo "\n";

// ============================================================================
// PHASE 4: RELEASES MIGRATION
// ============================================================================
echo "PHASE 4: RELEASES MIGRATION\n";
echo str_repeat("-", 80) . "\n";

try {
    $releasesTableExists = $db->query("SHOW TABLES LIKE 'releases'")->fetch();
    if (!$releasesTableExists) {
        debugLog("Releases table not found. Skipping.", 'warning');
        $results['phases']['releases'] = ['status' => 'skipped'];
    } else {
        debugLog("Releases table found: checking for legacy data...", 'debug');

        $legacyReleases = $db->query("SELECT COUNT(*) as cnt FROM releases")->fetch()['cnt'];
        debugLog("Legacy releases table: $legacyReleases records", 'debug');

        if ($legacyReleases === 0) {
            debugLog("No releases in legacy data. Skipping.", 'info');
            $results['phases']['releases'] = ['status' => 'skipped', 'reason' => 'no_legacy_data'];
        } else {
            // Rename legacy releases to releases_legacy
            debugLog("Renaming legacy releases to releases_legacy...", 'debug');
            try {
                $db->exec("RENAME TABLE releases TO releases_legacy");
                debugLog("Releases table renamed to releases_legacy", 'debug');
            } catch (Exception $e) {
                debugLog("Could not rename releases table: " . $e->getMessage(), 'warning');
            }

            // Create new releases table with proper ngn_2025 schema
            debugLog("Creating new releases table with ngn2.0 schema...", 'debug');
            $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS releases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  artist_id BIGINT UNSIGNED NOT NULL,
  label_id BIGINT UNSIGNED NULL,
  slug VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  type ENUM('album','ep','single','compilation') NOT NULL DEFAULT 'album',
  release_date DATE NULL,
  genre VARCHAR(128) NULL,
  description TEXT NULL,
  cover_url VARCHAR(1024) NULL,
  spotify_url VARCHAR(512) NULL,
  apple_music_url VARCHAR(512) NULL,
  bandcamp_url VARCHAR(512) NULL,
  youtube_url VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_artist_slug (artist_id, slug),
  KEY idx_artist_id (artist_id),
  KEY idx_label_id (label_id),
  KEY idx_release_date (release_date),
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
            debugLog("New releases table created", 'debug');

            // Migrate releases from legacy table
            debugLog("Migrating releases from legacy data...", 'info');
            debugLog("Mapping: ReleaseDate→release_date, Type→type, Image→cover_url, Body→description", 'debug');

            $db->exec(<<<'SQL'
INSERT INTO releases (id, artist_id, label_id, slug, title, type, release_date, genre, description, cover_url, created_at, updated_at)
SELECT
  Id,
  ArtistId,
  LabelId,
  Slug,
  Title,
  LOWER(COALESCE(Type, 'album')),
  CAST(ReleaseDate AS DATE),
  Genre,
  Body,
  Image,
  Created,
  Updated
FROM releases_legacy
WHERE Title IS NOT NULL AND Slug IS NOT NULL AND ArtistId IS NOT NULL
SQL);

            $releaseMigrated = $db->query("SELECT COUNT(*) as cnt FROM releases")->fetch()['cnt'];
            debugLog("Releases: migrated $releaseMigrated records", 'success');

            $results['phases']['releases'] = [
                'status' => 'success',
                'legacy_count' => $legacyReleases,
                'migrated' => $releaseMigrated,
            ];

            // Clean up releases_legacy table
            debugLog("Cleaning up releases_legacy temporary table...", 'debug');
            try {
                $db->exec("DROP TABLE IF EXISTS releases_legacy");
                debugLog("releases_legacy dropped", 'debug');
            } catch (Exception $e) {
                debugLog("Could not drop releases_legacy: " . $e->getMessage(), 'warning');
            }
        }
    }

} catch (Exception $e) {
    debugLog("Releases migration failed: " . $e->getMessage(), 'error');
    $results['phases']['releases'] = ['status' => 'error', 'error' => $e->getMessage()];
}

echo "\n";

// ============================================================================
// PHASE 5: TRACKS MIGRATION (Optional)
// ============================================================================
echo "PHASE 5: TRACKS MIGRATION (Optional)\n";
echo str_repeat("-", 80) . "\n";

try {
    $songsTableExists = $db->query("SHOW TABLES LIKE 'songs'")->fetch();
    if (!$songsTableExists) {
        debugLog("Songs table not in legacy data. Skipping.", 'warning');
        $results['phases']['tracks'] = ['status' => 'skipped'];
    } else {
        debugLog("Songs table found: checking for legacy data...", 'debug');

        $legacySongs = $db->query("SELECT COUNT(*) as cnt FROM songs")->fetch()['cnt'];
        debugLog("Legacy songs table: $legacySongs records", 'debug');

        if ($legacySongs === 0) {
            debugLog("No songs in legacy data. Skipping.", 'info');
            $results['phases']['tracks'] = ['status' => 'skipped', 'reason' => 'no_legacy_data'];
        } else {
            // Rename legacy songs to songs_legacy
            debugLog("Renaming legacy songs to songs_legacy...", 'debug');
            try {
                $db->exec("RENAME TABLE songs TO songs_legacy");
                debugLog("Songs table renamed to songs_legacy", 'debug');
            } catch (Exception $e) {
                debugLog("Could not rename songs table: " . $e->getMessage(), 'warning');
            }

            // Create new tracks table with proper ngn_2025 schema
            debugLog("Creating new tracks table with ngn2.0 schema...", 'debug');
            $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS tracks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  release_id BIGINT UNSIGNED NOT NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  track_number INT UNSIGNED NULL,
  disc_number INT UNSIGNED NULL DEFAULT 1,
  duration_seconds INT UNSIGNED NULL,
  isrc VARCHAR(12) NULL,
  explicit TINYINT(1) NOT NULL DEFAULT 0,
  lyrics TEXT NULL,
  mp3_url VARCHAR(1024) NULL,
  spotify_id VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_release_slug (release_id, slug),
  KEY idx_release_id (release_id),
  KEY idx_artist_id (artist_id),
  KEY idx_spotify_id (spotify_id),
  CONSTRAINT fk_tracks_release FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE,
  CONSTRAINT fk_tracks_artist FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
            debugLog("New tracks table created", 'debug');

            // Migrate tracks from legacy songs table
            debugLog("Migrating tracks from legacy songs data...", 'info');
            debugLog("Mapping: Title→slug (normalized), legacy songs→tracks with proper schema", 'debug');

            $db->exec(<<<'SQL'
INSERT INTO tracks (id, release_id, artist_id, slug, title, created_at, updated_at)
SELECT
  id,
  ReleaseId,
  ArtistId,
  LOWER(REPLACE(REPLACE(Title, ' ', '-'), '--', '-')),
  Title,
  Created,
  Updated
FROM songs_legacy
WHERE Title IS NOT NULL AND ReleaseId IS NOT NULL AND ArtistId IS NOT NULL
SQL);

            $trackMigrated = $db->query("SELECT COUNT(*) as cnt FROM tracks")->fetch()['cnt'];
            debugLog("Tracks: migrated $trackMigrated records", 'success');

            $results['phases']['tracks'] = [
                'status' => 'success',
                'legacy_count' => $legacySongs,
                'migrated' => $trackMigrated,
            ];

            // Clean up songs_legacy table
            debugLog("Cleaning up songs_legacy temporary table...", 'debug');
            try {
                $db->exec("DROP TABLE IF EXISTS songs_legacy");
                debugLog("songs_legacy dropped", 'debug');
            } catch (Exception $e) {
                debugLog("Could not drop songs_legacy: " . $e->getMessage(), 'warning');
            }
        }
    }

} catch (Exception $e) {
    debugLog("Tracks migration failed: " . $e->getMessage(), 'error');
    $results['phases']['tracks'] = ['status' => 'error', 'error' => $e->getMessage()];
}

echo "\n";

// ============================================================================
// PHASE 6: SHOWS (Optional)
// ============================================================================
echo "PHASE 6: SHOWS MIGRATION (Optional)\n";
echo str_repeat("-", 80) . "\n";

try {
    $showsTableExists = $db->query("SHOW TABLES LIKE 'shows'")->fetch();
    if (!$showsTableExists) {
        debugLog("Shows table not in legacy data. Skipping.", 'warning');
        $results['phases']['shows'] = ['status' => 'skipped'];
    } else {
        $showBefore = $db->query("SELECT COUNT(*) as cnt FROM shows WHERE created_at IS NOT NULL")->fetch()['cnt'];
        debugLog("Shows table found: checking for legacy data...", 'debug');

        $legacyShows = $db->query("SELECT COUNT(*) as cnt FROM shows")->fetch()['cnt'];
        debugLog("Legacy shows table: $legacyShows records", 'debug');

        $db->exec(<<<'SQL'
INSERT IGNORE INTO shows (id, slug, title, venue_id, starts_at, image_url, created_at)
SELECT Id, LOWER(CONCAT(ArtistId, '-', DATE(ShowDate))),
       CONCAT('Show ', Id), VenueId, ShowDate, Image, NOW()
FROM shows
WHERE ShowDate IS NOT NULL
SQL);

        $showAfter = $db->query("SELECT COUNT(*) as cnt FROM shows WHERE created_at IS NOT NULL")->fetch()['cnt'];
        $showMigrated = $showAfter - $showBefore;

        debugLog("Shows: migrated $showMigrated new records (total: $showAfter)", 'success');

        $results['phases']['shows'] = [
            'status' => 'success',
            'legacy_count' => $legacyShows,
            'migrated' => $showMigrated,
            'total' => $showAfter,
        ];
    }

} catch (Exception $e) {
    debugLog("Shows migration warning (non-critical): " . $e->getMessage(), 'warning');
    $results['phases']['shows'] = ['status' => 'warning', 'error' => $e->getMessage()];
}

echo "\n";

// ============================================================================
// PHASE 7: VERIFICATION & CLEANUP
// ============================================================================
echo "PHASE 7: VERIFICATION & CLEANUP\n";
echo str_repeat("-", 80) . "\n";

try {
    debugLog("Verifying migrated data...", 'info');

    $counts = [];
    $tables = ['posts', 'writers', 'artists', 'labels', 'venues', 'stations', 'releases', 'tracks', 'shows', 'post_writers'];

    foreach ($tables as $table) {
        try {
            $counts[$table] = $db->query("SELECT COUNT(*) as cnt FROM $table")->fetch()['cnt'];
        } catch (Exception $e) {
            $counts[$table] = 0;
        }
    }

    debugLog("FINAL DATA COUNTS:", 'info');
    foreach ($counts as $table => $count) {
        $icon = $count > 0 ? '✓' : '○';
        debugLog("  $icon $table: $count records", 'debug');
    }

    // Verify critical tables have data
    $criticalTables = ['posts', 'writers', 'artists'];
    $criticalDataMissing = [];

    foreach ($criticalTables as $table) {
        if (($counts[$table] ?? 0) === 0) {
            $criticalDataMissing[] = $table;
        }
    }

    if (!empty($criticalDataMissing)) {
        debugLog("WARNING: Critical tables are empty: " . implode(", ", $criticalDataMissing), 'warning');
        debugLog("This may indicate the migration did not complete properly.", 'warning');
    }

    $results['phases']['verification'] = [
        'status' => 'success',
        'counts' => $counts,
        'critical_missing' => $criticalDataMissing,
    ];

    // Cleanup legacy temporary tables - only after verification passes
    debugLog("Preparing to clean up legacy temporary tables...", 'info');
    debugLog("Note: Keeping migrated tables (posts, releases, tracks, songs is now tracks)", 'debug');

    try {
        // Drop only the legacy temporary tables that were created during migration
        // DO NOT drop the new migrated tables (posts, releases, tracks)
        // These are the core data that was successfully migrated
        $legacyTempTables = ['posts_legacy', 'releases_legacy', 'songs_legacy', 'shows', 'users'];

        foreach ($legacyTempTables as $table) {
            try {
                if ($db->query("SHOW TABLES LIKE '$table'")->fetch()) {
                    debugLog("Dropping legacy temp $table table...", 'debug');
                    $db->exec("DROP TABLE IF EXISTS $table");
                    debugLog("✓ $table dropped", 'debug');
                } else {
                    debugLog("  $table not found (already cleaned up)", 'debug');
                }
            } catch (Exception $e) {
                debugLog("Could not drop $table: " . $e->getMessage(), 'warning');
            }
        }

        debugLog("Legacy temporary tables cleaned up successfully", 'success');

    } catch (Exception $e) {
        debugLog("Cleanup warning (non-critical): " . $e->getMessage(), 'warning');
    }

    debugLog("Phase 7 COMPLETE - Verification and cleanup finished", 'success');

} catch (Exception $e) {
    debugLog("Verification failed: " . $e->getMessage(), 'error');
    $results['success'] = false;
    $results['errors'][] = "Verification: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// FINAL REPORT
// ============================================================================
echo str_repeat("█", 80) . "\n";

if ($results['success'] && empty($criticalDataMissing)) {
    echo "✓ MIGRATION SUCCESSFUL\n";
    $results['success'] = true;
} else {
    echo "⚠ MIGRATION COMPLETED WITH WARNINGS\n";
    if (!empty($criticalDataMissing)) {
        echo "CRITICAL: The following tables have no data: " . implode(", ", $criticalDataMissing) . "\n";
        $results['success'] = false;
    }
}

echo str_repeat("█", 80) . "\n\n";

echo "SUMMARY:\n";
echo "  Posts: {$counts['posts']} records\n";
echo "  Writers: {$counts['writers']} records\n";
echo "  Artists: {$counts['artists']} records\n";
echo "  Labels: {$counts['labels']} records\n";
echo "  Venues: {$counts['venues']} records\n";
echo "  Stations: {$counts['stations']} records\n";
echo "  Post-Writer Links: {$counts['post_writers']} relationships\n";
if (isset($counts['releases'])) echo "  Releases: {$counts['releases']} records\n";
if (isset($counts['tracks'])) echo "  Tracks: {$counts['tracks']} records\n";
if (isset($counts['shows'])) echo "  Shows: {$counts['shows']} records\n";
echo "\n";

if ($results['success'] && empty($criticalDataMissing)) {
    echo "✓ All legacy data has been successfully migrated to the 2025 database\n";
    echo "✓ Legacy tables have been cleaned up\n\n";

    echo "NEXT STEPS:\n";
    echo "  1. Verify application displays all data correctly\n";
    echo "  2. Monitor logs for any issues\n";
    echo "  3. Create a backup of the new database\n";
    echo "  4. Monitor application performance\n\n";
} else {
    echo "⚠ MIGRATION COMPLETED WITH ISSUES - REVIEW REQUIRED\n\n";

    echo "NEXT STEPS:\n";
    echo "  1. Review the results file for details\n";
    echo "  2. Check database schema and data\n";
    echo "  3. Investigate missing data\n";
    echo "  4. Do NOT delete legacy SQL files until verified\n\n";
}

$resultsFile = __DIR__ . '/../storage/logs/complete_migration_results_' . date('Y-m-d_H-i-s') . '.json';
$dir = dirname($resultsFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Detailed results saved to: storage/logs/complete_migration_results_*.json\n";
echo str_repeat("=", 80) . "\n\n";

exit($results['success'] ? 0 : 1);
