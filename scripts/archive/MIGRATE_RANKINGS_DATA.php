<?php
/**
 * MIGRATE_RANKINGS_DATA.php
 * Migrates legacy daily rankings data to ngn_rankings_2025 database
 *
 * Usage: php scripts/MIGRATE_RANKINGS_DATA.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "RANKINGS DATA MIGRATION: Legacy Daily Rankings → ngn_rankings_2025\n";
echo str_repeat("=", 80) . "\n\n";

try {
    // Step 1: Count legacy records
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngnrankings.artistsdaily");
    $stmt->execute();
    $result = $stmt->fetch();
    $artistsCount = $result['cnt'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngnrankings.labelsdaily");
    $stmt->execute();
    $result = $stmt->fetch();
    $labelsCount = $result['cnt'] ?? 0;
    
    echo "[INFO] Legacy data to migrate:\n";
    echo "  artistsdaily: $artistsCount records\n";
    echo "  labelsdaily: $labelsCount records\n\n";

    // Step 2: Create ranking windows from legacy data
    echo "[STEP 1] Creating ranking windows from daily legacy data...\n";
    
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

    // Step 3: Migrate artist rankings (using latest timestamp per artist per day)
    echo "[STEP 2] Migrating artist rankings...\n";
    
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
        ON DUPLICATE KEY UPDATE score = score
    ");
    $stmt->execute();
    
    echo "[OK] Artist rankings migrated\n\n";

    // Step 4: Migrate label rankings
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
        ON DUPLICATE KEY UPDATE score = score
    ");
    $stmt->execute();
    
    echo "[OK] Label rankings migrated\n\n";

    // Step 5: Verify results
    echo "[STEP 4] Verifying migrated rankings...\n";
    
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
    $stats = $stmt->fetchAll();
    
    foreach ($stats as $row) {
        echo "[INFO] {$row['entity_type']}:\n";
        echo "  Total items: {$row['item_count']}\n";
        echo "  Score range: {$row['min_score']} - {$row['max_score']}\n";
    }
    
    echo "\n";
    
    // Get overall summary
    $stmt = $db->prepare("
        SELECT 
          COUNT(DISTINCT window_id) as window_count,
          COUNT(*) as total_items
        FROM ngn_rankings_2025.ranking_items
    ");
    $stmt->execute();
    $summary = $stmt->fetch();
    
    echo "[INFO] Rankings summary:\n";
    echo "  Windows populated: {$summary['window_count']}\n";
    echo "  Total ranking items: {$summary['total_items']}\n";
    echo "  Date range: 2024-12-24 to 2025-03-26\n\n";
    
} catch (Exception $e) {
    echo "[ERROR] Rankings migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo str_repeat("=", 80) . "\n";
echo "RANKINGS MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n\n";
?>
