<?php

/**
 * recalculate-rankings.php - Regenerate NGN Rankings from SMR History
 * 
 * "From the start of time"
 * Now including Label aggregation, Resume logic, and Audit Logging.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🏆 NGN Ranking Recalculation Engine (v2.2)\n";
echo "=========================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

// 1. Get all historical ingestions chronologically (Grouped by Week/Year)
echo "Fetching historical ingestion logs...\n";
$stmt = $pdo->query("
    SELECT report_year, report_week, GROUP_CONCAT(i.id) as ingestion_ids
    FROM cdm_ingestion_logs l
    JOIN smr_ingestions i ON l.filename = i.filename
    WHERE l.status = 'anchored'
    GROUP BY report_year, report_week
    ORDER BY report_year ASC, report_week ASC
");
$ingestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($ingestions)) {
    echo "❌ No anchored ingestions found. Run SMR Bulk Ingest first.\n";
    exit(1);
}

echo "Found " . count($ingestions) . " historical weeks to process.\n";

$previousArtistRankings = []; 
$previousLabelRankings = [];

$force = in_array('--force', $argv);
$resume = in_array('--resume', $argv);

$limit = null;
$offset = 0;
foreach ($argv as $arg) {
    if (str_contains($arg, '--limit=')) {
        $limit = (int)str_replace('--limit=', '', $arg);
    }
    if (str_contains($arg, '--offset=')) {
        $offset = (int)str_replace('--offset=', '', $arg);
    }
}

// If resuming, we need to preload the state from the last processed window
if ($resume && !$force) {
    echo "Resuming: Preloading state from latest ranking window...\n";
    $stmt = $rankingsPdo->query("SELECT MAX(id) FROM ranking_windows");
    $lastWindowId = $stmt->fetchColumn();
    if ($lastWindowId) {
        // Preload Artists
        $stmt = $rankingsPdo->prepare("SELECT entity_id, `rank` FROM ranking_items WHERE window_id = ? AND entity_type = 'artist'");
        $stmt->execute([$lastWindowId]);
        $previousArtistRankings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Preload Labels
        $stmt = $rankingsPdo->prepare("SELECT entity_id, `rank` FROM ranking_items WHERE window_id = ? AND entity_type = 'label'");
        $stmt->execute([$lastWindowId]);
        $previousLabelRankings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo "   Loaded " . count($previousArtistRankings) . " artist rankings and " . count($previousLabelRankings) . " label rankings.\n";
    }
}

$processedCount = 0;
$skippedCount = 0;
foreach ($ingestions as $ingestion) {
    if ($skippedCount < $offset) {
        $skippedCount++;
        continue;
    }

    if ($limit !== null && $processedCount >= $limit) {
        echo "\n🏁 Limit reached ($limit weeks). Stopping.\n";
        break;
    }
    
    $week = (int)$ingestion['report_week'];
    $year = (int)$ingestion['report_year'];
    $ingestionIds = $ingestion['ingestion_ids'];
    
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $startDate = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');
    
    echo "Processing Week $week-$year ($startDate to $endDate)... ";

    // 2. Create/Get Ranking Window
    $stmt = $rankingsPdo->prepare("SELECT id FROM ranking_windows WHERE `interval` = 'weekly' AND window_start = ?");
    $stmt->execute([$startDate]);
    $windowId = $stmt->fetchColumn();

    if ($windowId && !$force) {
        $checkStmt = $rankingsPdo->prepare("SELECT COUNT(*) FROM ranking_items WHERE window_id = ?");
        $checkStmt->execute([$windowId]);
        if ($checkStmt->fetchColumn() > 0) {
            echo "[SKIP] Updating local state... ";
            
            // Still need to update previousRankings to keep the chain accurate
            $stmt = $rankingsPdo->prepare("SELECT entity_id, `rank` FROM ranking_items WHERE window_id = ? AND entity_type = 'artist'");
            $stmt->execute([$windowId]);
            $previousArtistRankings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stmt = $rankingsPdo->prepare("SELECT entity_id, `rank` FROM ranking_items WHERE window_id = ? AND entity_type = 'label'");
            $stmt->execute([$windowId]);
            $previousLabelRankings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            echo "OK.\n";
            continue;
        }
    }

    if (!$windowId) {
        $stmt = $rankingsPdo->prepare("
            INSERT INTO ranking_windows (`interval`, window_start, window_end)
            VALUES ('weekly', ?, ?)
        ");
        $stmt->execute([$startDate, $endDate]);
        $windowId = $rankingsPdo->lastInsertId();
    } else {
        // Clean existing items for re-run
        $rankingsPdo->prepare("DELETE FROM ranking_items WHERE window_id = ?")->execute([$windowId]);
    }

    // 3. Aggregate Artist Scores (From all ingestions in this week)
    $sql = "
        SELECT 
            cdm_artist_id,
            SUM(spin_count) as total_spins,
            MAX(reach_count) as max_reach,
            SUM(spin_count * (1 + (reach_count * 0.25))) as calculated_score
        FROM `ngn_2025`.`smr_records`
        WHERE ingestion_id IN ($ingestionIds) AND cdm_artist_id IS NOT NULL
        GROUP BY cdm_artist_id
        ORDER BY calculated_score DESC
    ";
    
    $recordStmt = $pdo->prepare($sql);
    $recordStmt->execute();
    $artistResults = $recordStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $artistRank = 1;
    $currentArtistRankings = [];
    $insertStmt = $rankingsPdo->prepare("INSERT INTO ranking_items (window_id, entity_type, entity_id, `rank`, prev_rank, score, deltas) VALUES (?, 'artist', ?, ?, ?, ?, ?)");
    
    // NGN 2.0.3: Audit Service
    $receiptService = new \NGN\Lib\Fairness\FairnessReceipt($pdo);

    foreach ($artistResults as $row) {
        $artistId = $row['cdm_artist_id'];
        $score = $row['calculated_score'];
        $prevRank = $previousArtistRankings[$artistId] ?? null;
        $delta = $prevRank ? ($prevRank - $artistRank) : 0;
        $factors = ['spins' => (int)$row['total_spins'], 'reach' => (int)$row['max_reach']];
        $deltas = json_encode(['rank_change' => $delta, 'spins' => $factors['spins'], 'reach' => $factors['reach']]);

        $insertStmt->execute([$windowId, $artistId, $artistRank, $prevRank, $score, $deltas]);
        
        // Log Fairness Receipt
        try {
            $receipt = $receiptService->generateArtistReceipt($artistId, $windowId, true);
            if (isset($receipt['error'])) {
                fwrite(STDERR, "   ⚠️ Audit Error for Artist $artistId: " . $receipt['error'] . "\n");
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, "   ❌ Audit Fatal for Artist $artistId: " . $e->getMessage() . "\n");
        }

        $currentArtistRankings[$artistId] = $artistRank;
        $artistRank++;
    }
    $previousArtistRankings = $currentArtistRankings;

    // 4. Aggregate Label Scores
    $labelScores = [];
    $stmt = $pdo->prepare("SELECT id, label_id FROM artists WHERE id IN (SELECT cdm_artist_id FROM `ngn_2025`.`smr_records` WHERE ingestion_id IN ($ingestionIds) AND cdm_artist_id IS NOT NULL)");
    $stmt->execute();
    $artistLabelMap = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['label_id']) $artistLabelMap[(int)$row['id']] = (int)$row['label_id'];
    }

    foreach ($artistResults as $row) {
        $aid = (int)$row['cdm_artist_id'];
        if (isset($artistLabelMap[$aid])) {
            $lid = $artistLabelMap[$aid];
            if (!isset($labelScores[$lid])) $labelScores[$lid] = 0;
            $labelScores[$lid] += (float)$row['calculated_score'];
        }
    }

    arsort($labelScores);
    $labelRank = 1;
    $currentLabelRankings = [];
    $insertLabelStmt = $rankingsPdo->prepare("INSERT INTO ranking_items (window_id, entity_type, entity_id, `rank`, prev_rank, score) VALUES (?, 'label', ?, ?, ?, ?)");

    foreach ($labelScores as $labelId => $score) {
        $prevRank = $previousLabelRankings[$labelId] ?? null;
        $insertLabelStmt->execute([$windowId, $labelId, $labelRank, $prevRank, $score]);
        $currentLabelRankings[$labelId] = $labelRank;
        $labelRank++;
    }
    $previousLabelRankings = $currentLabelRankings;
    
    echo "✅ Done (" . count($artistResults) . " A / " . count($labelScores) . " L)\n";
    $processedCount++;
}

echo "\n========================================\n";
echo "🏁 Recalculation Complete.\n";
