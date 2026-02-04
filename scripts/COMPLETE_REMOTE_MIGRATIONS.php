<?php
/**
 * COMPLETE_REMOTE_MIGRATIONS.php
 * Complete migration of venues, stations, and rankings from legacy databases
 */

$remoteHost = 'server.starrship1.com';
$pass = 'NextGenNoise!1';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

// Legacy nextgennoise user (has access to nextgennoise and ngn_2025)
$legacyDb = new PDO(
    "mysql:host={$remoteHost};dbname=nextgennoise",
    'nextgennoise',
    $pass,
    $pdoOptions
);

// Legacy NGNRankings user (has access to ngnrankings and ngn_rankings_2025)
$legacyRankingsDb = new PDO(
    "mysql:host={$remoteHost};dbname=ngnrankings",
    'NGNRankings',
    $pass,
    $pdoOptions
);

echo "\n" . str_repeat("=", 80) . "\n";
echo "COMPLETE REMOTE MIGRATIONS: Venues, Stations & Rankings\n";
echo "Server: $remoteHost\n";
echo str_repeat("=", 80) . "\n\n";

// ============================================================================
// VENUES MIGRATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "VENUES MIGRATION\n";
echo str_repeat("-", 80) . "\n\n";

try {
    echo "[INFO] Connecting to ngn_2025 via nextgennoise user...\n";
    
    $stmt = $legacyDb->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.venues");
    $stmt->execute();
    $result = $stmt->fetch();
    $currentVenueCount = $result['cnt'] ?? 0;
    echo "[INFO] Current venues: $currentVenueCount\n\n";

    echo "[STEP 1] Migrating venues from nextgennoise.users (RoleId=11)...\n";
    
    $stmt = $legacyDb->prepare("
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
          CASE WHEN Updated = '0000-00-00 00:00:00' THEN NULL ELSE Updated END as updated_at
        FROM users 
        WHERE RoleId = 11
        ON DUPLICATE KEY UPDATE name = name
    ");
    $stmt->execute();
    
    $stmt = $legacyDb->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.venues");
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
    $stmt = $legacyDb->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.stations");
    $stmt->execute();
    $result = $stmt->fetch();
    $currentStationCount = $result['cnt'] ?? 0;
    echo "[INFO] Current stations: $currentStationCount\n\n";

    echo "[STEP 1] Migrating stations from nextgennoise.users (RoleId=9)...\n";
    
    $stmt = $legacyDb->prepare("
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
          CASE WHEN Updated = '0000-00-00 00:00:00' THEN NULL ELSE Updated END as updated_at
        FROM users 
        WHERE RoleId = 9
        ON DUPLICATE KEY UPDATE name = name
    ");
    $stmt->execute();
    
    $stmt = $legacyDb->prepare("SELECT COUNT(*) as cnt FROM ngn_2025.stations");
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
    echo "[STEP 1] Creating ranking windows from legacy daily data...\n";
    
    // Insert without specifying columns - let DB use defaults
    $stmt = $legacyRankingsDb->prepare("
        INSERT INTO ngn_rankings_2025.ranking_windows (`interval`, window_start, window_end)
        SELECT DISTINCT 
          'daily' as `interval`,
          DATE(Timestamp) as window_start,
          DATE(Timestamp) as window_end
        FROM artistsdaily
        ORDER BY DATE(Timestamp)
        ON DUPLICATE KEY UPDATE `interval` = `interval`
    ");
    $stmt->execute();
    
    $stmt = $legacyRankingsDb->prepare("SELECT COUNT(*) as cnt FROM ngn_rankings_2025.ranking_windows");
    $stmt->execute();
    $result = $stmt->fetch();
    $windowCount = $result['cnt'] ?? 0;
    echo "[OK] Created $windowCount ranking windows\n\n";

    echo "[STEP 2] Migrating artist rankings...\n";
    
    $stmt = $legacyRankingsDb->prepare("DELETE FROM ngn_rankings_2025.ranking_items");
    $stmt->execute();
    
    $stmt = $legacyRankingsDb->prepare("
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
          FROM artistsdaily
        ) ad_latest
        JOIN ngn_rankings_2025.ranking_windows rw 
          ON ad_latest.ranking_date = rw.window_start 
          AND rw.`interval` = 'daily'
        WHERE ad_latest.rn = 1
    ");
    $stmt->execute();
    
    echo "[OK] Artist rankings migrated\n\n";

    echo "[STEP 3] Migrating label rankings...\n";
    
    $stmt = $legacyRankingsDb->prepare("
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
          FROM labelsdaily
        ) ld_latest
        JOIN ngn_rankings_2025.ranking_windows rw 
          ON ld_latest.ranking_date = rw.window_start 
          AND rw.`interval` = 'daily'
        WHERE ld_latest.rn = 1
    ");
    $stmt->execute();
    
    echo "[OK] Label rankings migrated\n\n";

    echo "[STEP 4] Verifying rankings...\n";
    
    $stmt = $legacyRankingsDb->prepare("
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
    
    $stmt = $legacyRankingsDb->prepare("
        SELECT 
          COUNT(DISTINCT window_id) as window_count,
          COUNT(*) as total_items,
          MIN(window_start) as earliest,
          MAX(window_start) as latest
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
    $stmt = $legacyDb->prepare("
        SELECT 
          (SELECT COUNT(*) FROM ngn_2025.venues) as venue_count,
          (SELECT COUNT(*) FROM ngn_2025.stations) as station_count
    ");
    $stmt->execute();
    $counts1 = $stmt->fetch();
    
    $stmt = $legacyRankingsDb->prepare("
        SELECT 
          (SELECT COUNT(*) FROM ngn_rankings_2025.ranking_windows) as ranking_windows,
          (SELECT COUNT(*) FROM ngn_rankings_2025.ranking_items) as ranking_items
    ");
    $stmt->execute();
    $counts2 = $stmt->fetch();
    
    echo "Final Record Counts on $remoteHost:\n";
    echo "  ✓ Venues: {$counts1['venue_count']}\n";
    echo "  ✓ Stations: {$counts1['station_count']}\n";
    echo "  ✓ Ranking Windows: {$counts2['ranking_windows']}\n";
    echo "  ✓ Ranking Items: {$counts2['ranking_items']}\n\n";
    
} catch (Exception $e) {
    echo "[ERROR] Could not fetch final counts: " . $e->getMessage() . "\n";
}

echo str_repeat("=", 80) . "\n\n";
?>
