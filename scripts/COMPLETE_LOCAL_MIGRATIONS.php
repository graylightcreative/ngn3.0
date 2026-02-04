<?php
/**
 * COMPLETE_LOCAL_MIGRATIONS.php
 * Complete migration of venues, stations, and rankings from legacy databases
 * 
 * Handles all three migrations in one script by connecting directly to remote databases
 * Usage: php scripts/COMPLETE_LOCAL_MIGRATIONS.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "COMPLETE LOCAL MIGRATIONS: Venues, Stations & Rankings\n";
echo str_repeat("=", 80) . "\n\n";

// ============================================================================
// VENUES MIGRATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "VENUES MIGRATION\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check current venues
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.venues");
    $stmt->execute();
    $result = $stmt->fetch();
    $currentVenueCount = $result['cnt'] ?? 0;
    echo "[INFO] Current venues: $currentVenueCount\n\n";

    // Migrate venues from legacy
    echo "[STEP 1] Migrating venues from nextgennoise.users (RoleId=11)...\n";
    
    $stmt = $db->prepare("
        INSERT INTO ngn_2025.venues (slug, user_id, name, city, region, bio, image_url, created_at, updated_at)
        SELECT 
          Slug,
          Id as user_id,
          Title as name,
          Address as city,
          NULL as region,
          Body as bio,
          Image as image_url,
          Created as created_at,
          Updated as updated_at
        FROM nextgennoise.users 
        WHERE RoleId = 11
        ON DUPLICATE KEY UPDATE name = name
    ");
    $stmt->execute();
    
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.venues");
    $stmt->execute();
    $result = $stmt->fetch();
    $venueCount = $result['cnt'] ?? 0;
    echo "[OK] Venues migrated: $venueCount total\n\n";

} catch (Exception $e) {
    echo "[ERROR] Venues migration failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// STATIONS MIGRATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "STATIONS MIGRATION\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check current stations
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.stations");
    $stmt->execute();
    $result = $stmt->fetch();
    $currentStationCount = $result['cnt'] ?? 0;
    echo "[INFO] Current stations: $currentStationCount\n\n";

    // Migrate stations from legacy
    echo "[STEP 1] Migrating stations from nextgennoise.users (RoleId=9)...\n";
    
    $stmt = $db->prepare("
        INSERT INTO ngn_2025.stations (slug, name, call_sign, region, format, bio, image_url, created_at, updated_at)
        SELECT 
          Slug,
          Title as name,
          NULL as call_sign,
          NULL as region,
          NULL as format,
          Body as bio,
          Image as image_url,
          Created as created_at,
          Updated as updated_at
        FROM nextgennoise.users 
        WHERE RoleId = 9
        ON DUPLICATE KEY UPDATE name = name
    ");
    $stmt->execute();
    
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.stations");
    $stmt->execute();
    $result = $stmt->fetch();
    $stationCount = $result['cnt'] ?? 0;
    echo "[OK] Stations migrated: $stationCount total\n\n";

} catch (Exception $e) {
    echo "[ERROR] Stations migration failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// RANKINGS MIGRATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "RANKINGS MIGRATION\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Step 1: Create ranking windows
    echo "[STEP 1] Creating ranking windows from legacy daily data...\n";
    
    $stmt = $db->prepare("
        INSERT INTO ngn_rankings_2025.ranking_windows (week_start, chart_slug)
        SELECT DISTINCT 
          DATE(Timestamp) as week_start,
          'ngn:daily' as chart_slug
        FROM ngnrankings.artistsdaily
        ORDER BY DATE(Timestamp)
        ON DUPLICATE KEY UPDATE chart_slug = chart_slug
    ");
    $stmt->execute();
    
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_rankings_2025.ranking_windows");
    $stmt->execute();
    $result = $stmt->fetch();
    $windowCount = $result['cnt'] ?? 0;
    echo "[OK] Created $windowCount ranking windows\n\n";

    // Step 2: Migrate artist rankings
    echo "[STEP 2] Migrating artist rankings...\n";
    
    // First, clear existing items to avoid duplicates
    $stmt = $db->prepare("TRUNCATE ngn_rankings_2025.ranking_items");
    $stmt->execute();
    
    $stmt = $db->prepare("
        INSERT INTO ngn_rankings_2025.ranking_items (window_id, entity_type, entity_id, `rank`, score)
        SELECT 
          rw.id,
          'artist',
          ad_latest.ArtistId,
          ROW_NUMBER() OVER (PARTITION BY rw.id ORDER BY ad_latest.Score DESC),
          ad_latest.Score
        FROM (
          SELECT 
            ArtistId,
            Score,
            Timestamp,
            DATE(Timestamp) as ranking_date,
            ROW_NUMBER() OVER (PARTITION BY ArtistId, DATE(Timestamp) ORDER BY Timestamp DESC) as rn
          FROM ngnrankings.artistsdaily
        ) ad_latest
        JOIN ngn_rankings_2025.ranking_windows rw 
          ON ad_latest.ranking_date = rw.week_start
        WHERE ad_latest.rn = 1
    ");
    $stmt->execute();
    
    echo "[OK] Artist rankings migrated\n\n";

    // Step 3: Migrate label rankings
    echo "[STEP 3] Migrating label rankings...\n";
    
    $stmt = $db->prepare("
        INSERT INTO ngn_rankings_2025.ranking_items (window_id, entity_type, entity_id, `rank`, score)
        SELECT 
          rw.id,
          'label',
          ld_latest.LabelId,
          ROW_NUMBER() OVER (PARTITION BY rw.id ORDER BY ld_latest.Score DESC),
          ld_latest.Score
        FROM (
          SELECT 
            LabelId,
            Score,
            Timestamp,
            DATE(Timestamp) as ranking_date,
            ROW_NUMBER() OVER (PARTITION BY LabelId, DATE(Timestamp) ORDER BY Timestamp DESC) as rn
          FROM ngnrankings.labelsdaily
        ) ld_latest
        JOIN ngn_rankings_2025.ranking_windows rw 
          ON ld_latest.ranking_date = rw.week_start
        WHERE ld_latest.rn = 1
    ");
    $stmt->execute();
    
    echo "[OK] Label rankings migrated\n\n";

    // Step 4: Verify rankings
    echo "[STEP 4] Verifying rankings...\n";
    
    $stmt = $db->prepare("
        SELECT 
          entity_type,
          COUNT(*) as item_count,
          MIN(score) as min_score,
          MAX(score) as max_score
        FROM ngn_rankings_2025.ranking_items
        GROUP BY entity_type
    ");
    $stmt->execute();
    $rankingStats = $stmt->fetchAll();
    
    foreach ($rankingStats as $row) {
        echo "[INFO] {$row['entity_type']}:\n";
        echo "  Total items: {$row['item_count']}\n";
        echo "  Score range: {$row['min_score']} - {$row['max_score']}\n";
    }
    
    echo "\n";
    
    $stmt = $db->prepare("
        SELECT 
          COUNT(DISTINCT window_id) as window_count,
          COUNT(*) as total_items,
          MIN(week_start) as earliest,
          MAX(week_start) as latest
        FROM ngn_rankings_2025.ranking_windows w
        JOIN ngn_rankings_2025.ranking_items i ON w.id = i.window_id
    ");
    $stmt->execute();
    $summary = $stmt->fetch();
    
    echo "[INFO] Rankings summary:\n";
    echo "  Windows with data: {$summary['window_count']}\n";
    echo "  Total ranking items: {$summary['total_items']}\n";
    echo "  Date range: {$summary['earliest']} to {$summary['latest']}\n\n";

} catch (Exception $e) {
    echo "[ERROR] Rankings migration failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo str_repeat("=", 80) . "\n";
echo "MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $stmt = $db->prepare("
        SELECT 
          (SELECT COUNT(*) FROM ngn_2025.venues) as venue_count,
          (SELECT COUNT(*) FROM ngn_2025.stations) as station_count,
          (SELECT COUNT(*) FROM ngn_rankings_2025.ranking_windows) as ranking_windows,
          (SELECT COUNT(*) FROM ngn_rankings_2025.ranking_items) as ranking_items
    ");
    $stmt->execute();
    $finalCounts = $stmt->fetch();
    
    echo "Final Record Counts:\n";
    echo "  ✓ Venues: {$finalCounts['venue_count']}\n";
    echo "  ✓ Stations: {$finalCounts['station_count']}\n";
    echo "  ✓ Ranking Windows: {$finalCounts['ranking_windows']}\n";
    echo "  ✓ Ranking Items: {$finalCounts['ranking_items']}\n\n";
    
} catch (Exception $e) {
    echo "[ERROR] Could not fetch final counts: " . $e->getMessage() . "\n";
}

echo str_repeat("=", 80) . "\n\n";
?>
