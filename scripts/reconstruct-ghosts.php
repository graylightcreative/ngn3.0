<?php
/**
 * reconstruct-ghosts.php - Identity Recovery System
 * 
 * Re-creates missing artist/label identities in ngn_2025 by reverse-matching
 * scores/spins from ngn_smr_2025.smr_chart.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🧠 NGN Identity Reconstruction Engine (v1.1)\n";
echo "===========================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
$smrPdo = ConnectionFactory::named($config, 'smr2025');

// 1. Fetch current IDs to avoid joins
echo "Fetching current artist IDs...\n";
$currentArtistIds = $pdo->query("SELECT id FROM artists")->fetchAll(PDO::FETCH_COLUMN);
$currentArtistIds = array_map('intval', $currentArtistIds);

echo "Identifying ghost artists...\n";
$stmt = $rankingsPdo->query("
    SELECT DISTINCT ri.entity_id, ri.score, ri.deltas, rw.window_start
    FROM ranking_items ri
    JOIN ranking_windows rw ON ri.window_id = rw.id
    WHERE ri.entity_type = 'artist'
    ORDER BY rw.window_start DESC
    LIMIT 2000
");
$allRankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ghosts = [];
foreach ($allRankings as $r) {
    if (!in_array((int)$r['entity_id'], $currentArtistIds, true)) {
        $ghosts[] = $r;
    }
}

echo "Found " . count($ghosts) . " artist ghosts to reconstruct.\n";

$reconstructed = 0;
foreach ($ghosts as $g) {
    $id = (int)$g['entity_id'];
    $score = (float)$g['score'];
    $deltas = json_decode($g['deltas'], true);
    $spins = $deltas['spins'] ?? 0;
    
    // Try to find name in SMR Chart
    $matchStmt = $smrPdo->prepare("
        SELECT artist FROM smr_chart 
        WHERE tws = ? 
        AND window_date BETWEEN DATE_SUB(?, INTERVAL 14 DAY) AND DATE_ADD(?, INTERVAL 14 DAY)
        LIMIT 1
    ");
    $matchStmt->execute([$spins, $g['window_start'], $g['window_start']]);
    $name = $matchStmt->fetchColumn();
    
    if ($name) {
        echo "   👻 [MATCH] ID $id => '$name'\n";
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . $id;
        try {
            $ins = $pdo->prepare("INSERT INTO artists (id, name, slug, status) VALUES (?, ?, ?, 'ghost') ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $ins->execute([$id, $name, $slug]);
            $reconstructed++;
            $currentArtistIds[] = $id; // Mark as done
        } catch (\Throwable $e) {
            echo "      [FAIL] " . $e->getMessage() . "\n";
        }
    }
}

// 2. Labels
echo "\nFetching current label IDs...\n";
$currentLabelIds = $pdo->query("SELECT id FROM labels")->fetchAll(PDO::FETCH_COLUMN);
$currentLabelIds = array_map('intval', $currentLabelIds);

echo "Identifying ghost labels...\n";
$stmt = $rankingsPdo->query("SELECT DISTINCT entity_id FROM ranking_items WHERE entity_type = 'label'");
$allLabelRankings = $stmt->fetchAll(PDO::FETCH_COLUMN);

$labelGhosts = [];
foreach ($allLabelRankings as $lid) {
    if (!in_array((int)$lid, $currentLabelIds, true)) {
        $labelGhosts[] = (int)$lid;
    }
}

echo "Found " . count($labelGhosts) . " label ghosts. (Creating placeholders...)\n";

foreach ($labelGhosts as $id) {
    $name = "Ghost Label $id";
    $slug = "ghost-label-$id";
    try {
        $ins = $pdo->prepare("INSERT INTO labels (id, name, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
        $ins->execute([$id, $name, $slug]);
    } catch (\Throwable $e) {}
}

echo "\n🏁 Reconstruction complete. ($reconstructed artists recovered)\n";
echo "🔄 Triggering Moat Repressurization...\n";
include __DIR__ . '/repressurize-moat.php';
