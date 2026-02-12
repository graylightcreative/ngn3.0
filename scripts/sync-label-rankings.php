<?php

/**
 * sync-label-rankings.php - Backfill Label rankings for ALL windows
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🏢 Label Ranking Global Aggregator\n";
echo "===============================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');

// 1. Get all Artist windows
$stmt = $rankingsPdo->query("SELECT * FROM ranking_windows WHERE `interval` = 'weekly' ORDER BY window_start ASC");
$windows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($windows) . " windows to check.\n";

// 2. Fetch Label Roster IDs once
echo "Mapping labels to artists...\n";
$stmt = $pdo->query("SELECT id, label_id FROM artists WHERE label_id IS NOT NULL");
$artistLabels = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $artistLabels[(int)$row['id']] = (int)$row['label_id'];
}

foreach ($windows as $window) {
    $windowId = $window['id'];
    echo "Processing Window #$windowId ({$window['window_start']})... ";

    // Aggregate Scores
    $stmt = $rankingsPdo->prepare("SELECT entity_id, score FROM ranking_items WHERE window_id = ? AND entity_type = 'artist'");
    $stmt->execute([$windowId]);
    $artistRankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($artistRankings)) {
        echo "No artist data. Skip.\n";
        continue;
    }

    $labelScores = [];
    foreach ($artistRankings as $ar) {
        $aid = (int)$ar['entity_id'];
        $score = (float)$ar['score'];
        
        if (isset($artistLabels[$aid])) {
            $lid = $artistLabels[$aid];
            if (!isset($labelScores[$lid])) $labelScores[$lid] = 0;
            $labelScores[$lid] += $score;
        }
    }

    if (empty($labelScores)) {
        echo "No label matches. Skip.\n";
        continue;
    }

    arsort($labelScores);

    // Save Label Rankings
    $rankingsPdo->prepare("DELETE FROM ranking_items WHERE window_id = ? AND entity_type = 'label'")->execute([$windowId]);

    $ins = $rankingsPdo->prepare("
        INSERT INTO ranking_items (window_id, entity_type, entity_id, `rank`, score)
        VALUES (?, 'label', ?, ?, ?)
    ");

    $rank = 1;
    foreach ($labelScores as $lid => $score) {
        $ins->execute([$windowId, $lid, $rank, $score]);
        $rank++;
    }

    echo "✅ Generated " . count($labelScores) . " labels.\n";
}

echo "\n🏁 Synchronization Complete.\n";
