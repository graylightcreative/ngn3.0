<?php
/**
 * VALIDATE_SPINS_RANKINGS.php
 * Validates spins and rankings data completeness
 *
 * Usage: php scripts/VALIDATE_SPINS_RANKINGS.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "VALIDATING SPINS AND RANKINGS DATA\n";
echo str_repeat("=", 80) . "\n\n";

// ============================================================================
// SPINS VALIDATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "SPINS DATA VALIDATION (ngn_spins_2025)\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check legacy spindata table
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_spins_2025.spindata");
    $stmt->execute();
    $result = $stmt->fetch();
    $legacySpinCount = $result['cnt'] ?? 0;
    echo "[INFO] Legacy spindata records: $legacySpinCount\n";

    // Check new station_spins table
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_spins_2025.station_spins");
    $stmt->execute();
    $result = $stmt->fetch();
    $newSpinCount = $result['cnt'] ?? 0;
    echo "[INFO] New station_spins records: $newSpinCount\n\n";

    // Get spins by station
    $stmt = $db->prepare("
        SELECT station_id, COUNT(*) as spin_count
        FROM ngn_spins_2025.station_spins
        WHERE station_id IS NOT NULL
        GROUP BY station_id
        ORDER BY spin_count DESC
    ");
    $stmt->execute();
    $stationSpins = $stmt->fetchAll();

    if (!empty($stationSpins)) {
        echo "[INFO] Spins by station:\n";
        foreach ($stationSpins as $row) {
            echo "  Station ID {$row['station_id']}: {$row['spin_count']} spins\n";
        }
    } else {
        echo "[WARNING] No station IDs linked in station_spins records\n";
    }

    echo "\n";

    // Get spins by artist
    $stmt = $db->prepare("
        SELECT artist_id, COUNT(*) as spin_count
        FROM ngn_spins_2025.station_spins
        WHERE artist_id IS NOT NULL
        GROUP BY artist_id
        ORDER BY spin_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $artistSpins = $stmt->fetchAll();

    if (!empty($artistSpins)) {
        echo "[INFO] Top 10 artists by spin count:\n";
        foreach ($artistSpins as $row) {
            echo "  Artist ID {$row['artist_id']}: {$row['spin_count']} spins\n";
        }
    }

    echo "\n";

    // Date range
    $stmt = $db->prepare("
        SELECT MIN(played_at) as earliest, MAX(played_at) as latest
        FROM ngn_spins_2025.station_spins
    ");
    $stmt->execute();
    $dateRange = $stmt->fetch();

    echo "[INFO] Spins date range:\n";
    echo "  Earliest: " . ($dateRange['earliest'] ?? 'N/A') . "\n";
    echo "  Latest: " . ($dateRange['latest'] ?? 'N/A') . "\n\n";

} catch (Exception $e) {
    echo "[ERROR] Spins validation failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// RANKINGS VALIDATION
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "RANKINGS DATA VALIDATION (ngn_rankings_2025)\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check ranking_windows
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_rankings_2025.ranking_windows");
    $stmt->execute();
    $result = $stmt->fetch();
    $windowCount = $result['cnt'] ?? 0;
    echo "[INFO] Ranking windows: $windowCount\n";

    // Get windows by interval
    $stmt = $db->prepare("
        SELECT interval, COUNT(*) as window_count
        FROM ngn_rankings_2025.ranking_windows
        GROUP BY interval
    ");
    $stmt->execute();
    $windows = $stmt->fetchAll();

    if (!empty($windows)) {
        echo "[INFO] Windows by interval:\n";
        foreach ($windows as $row) {
            echo "  {$row['interval']}: {$row['window_count']} windows\n";
        }
    }

    echo "\n";

    // Check ranking_items
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_rankings_2025.ranking_items");
    $stmt->execute();
    $result = $stmt->fetch();
    $itemCount = $result['cnt'] ?? 0;
    echo "[INFO] Ranking items: $itemCount\n";

    // Get items by entity type
    $stmt = $db->prepare("
        SELECT entity_type, COUNT(*) as item_count
        FROM ngn_rankings_2025.ranking_items
        GROUP BY entity_type
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();

    if (!empty($items)) {
        echo "[INFO] Items by entity type:\n";
        foreach ($items as $row) {
            echo "  {$row['entity_type']}: {$row['item_count']} items\n";
        }
    }

    echo "\n";

    // Get date range of rankings
    $stmt = $db->prepare("
        SELECT MIN(rw.window_start) as earliest, MAX(rw.window_end) as latest
        FROM ngn_rankings_2025.ranking_windows rw
    ");
    $stmt->execute();
    $rankingDateRange = $stmt->fetch();

    echo "[INFO] Rankings date range:\n";
    echo "  Earliest window: " . ($rankingDateRange['earliest'] ?? 'N/A') . "\n";
    echo "  Latest window: " . ($rankingDateRange['latest'] ?? 'N/A') . "\n\n";

    // Check for completeness
    echo "[ANALYSIS] Ranking completeness:\n";

    // For each window, check if we have rankings
    $stmt = $db->prepare("
        SELECT rw.interval, rw.window_start, COUNT(ri.entity_id) as item_count
        FROM ngn_rankings_2025.ranking_windows rw
        LEFT JOIN ngn_rankings_2025.ranking_items ri ON rw.id = ri.window_id
        GROUP BY rw.id, rw.interval, rw.window_start
        ORDER BY rw.window_start DESC
        LIMIT 10
    ");
    $stmt->execute();
    $completeness = $stmt->fetchAll();

    if (!empty($completeness)) {
        echo "  Latest 10 windows:\n";
        foreach ($completeness as $row) {
            $status = $row['item_count'] > 0 ? '✓' : '✗';
            echo "    $status {$row['window_start']} ({$row['interval']}) - {$row['item_count']} items\n";
        }
    }

} catch (Exception $e) {
    echo "[ERROR] Rankings validation failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// SMR DATA VALIDATION
// ============================================================================
echo "\n" . str_repeat("-", 80) . "\n";
echo "SMR DATA VALIDATION (ngn_smr_2025)\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check legacy chartdata
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_smr_2025.chartdata");
    $stmt->execute();
    $result = $stmt->fetch();
    $legacyChartCount = $result['cnt'] ?? 0;
    echo "[INFO] Legacy chartdata records: $legacyChartCount\n";

    // Check new smr_chart
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_smr_2025.smr_chart");
    $stmt->execute();
    $result = $stmt->fetch();
    $newChartCount = $result['cnt'] ?? 0;
    echo "[INFO] New smr_chart records: $newChartCount\n\n";

    // Check artist mapping
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN artist_id IS NOT NULL THEN 1 ELSE 0 END) as mapped,
            SUM(CASE WHEN artist_id IS NULL THEN 1 ELSE 0 END) as unmapped
        FROM ngn_smr_2025.smr_chart
    ");
    $stmt->execute();
    $mapping = $stmt->fetch();

    echo "[INFO] Artist mapping in SMR:\n";
    echo "  Total records: " . $mapping['total'] . "\n";
    echo "  Mapped to ngn_2025.artists: " . $mapping['mapped'] . "\n";
    echo "  Unmapped (NULL artist_id): " . $mapping['unmapped'] . "\n";

    if ($mapping['total'] > 0) {
        $mappingPercent = ($mapping['mapped'] / $mapping['total']) * 100;
        echo "  Mapping rate: " . number_format($mappingPercent, 1) . "%\n";
    }

    echo "\n";

    // Date range
    $stmt = $db->prepare("
        SELECT MIN(window_date) as earliest, MAX(window_date) as latest
        FROM ngn_smr_2025.smr_chart
    ");
    $stmt->execute();
    $smrDateRange = $stmt->fetch();

    echo "[INFO] SMR chart date range:\n";
    echo "  Earliest: " . ($smrDateRange['earliest'] ?? 'N/A') . "\n";
    echo "  Latest: " . ($smrDateRange['latest'] ?? 'N/A') . "\n\n";

} catch (Exception $e) {
    echo "[ERROR] SMR validation failed: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// CROSS-DATABASE INTEGRITY
// ============================================================================
echo str_repeat("-", 80) . "\n";
echo "CROSS-DATABASE INTEGRITY CHECK\n";
echo str_repeat("-", 80) . "\n\n";

try {
    // Check if artists referenced in spins exist in ngn_2025
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ss.artist_id) as artist_refs
        FROM ngn_spins_2025.station_spins ss
        WHERE ss.artist_id IS NOT NULL
        AND EXISTS (SELECT 1 FROM ngn_2025.artists a WHERE a.id = ss.artist_id)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $linkedArtists = $result['artist_refs'] ?? 0;

    echo "[INFO] Spins with valid artist references: $linkedArtists\n";

    // Check if stations referenced in spins exist in ngn_2025
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ss.station_id) as station_refs
        FROM ngn_spins_2025.station_spins ss
        WHERE ss.station_id IS NOT NULL
        AND EXISTS (SELECT 1 FROM ngn_2025.stations s WHERE s.id = ss.station_id)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $linkedStations = $result['station_refs'] ?? 0;

    echo "[INFO] Spins with valid station references: $linkedStations\n";

    // Check SMR artists
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT sc.artist_id) as artist_refs
        FROM ngn_smr_2025.smr_chart sc
        WHERE sc.artist_id IS NOT NULL
        AND EXISTS (SELECT 1 FROM ngn_2025.artists a WHERE a.id = sc.artist_id)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $smrLinkedArtists = $result['artist_refs'] ?? 0;

    echo "[INFO] SMR records with valid artist references: $smrLinkedArtists\n\n";

} catch (Exception $e) {
    echo "[ERROR] Cross-database integrity check failed: " . $e->getMessage() . "\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "VALIDATION COMPLETE\n";
echo str_repeat("=", 80) . "\n\n";
?>
