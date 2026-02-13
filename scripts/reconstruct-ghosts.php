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

echo "🧠 NGN Identity Reconstruction Engine (v1.2)\n";
echo "===========================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
$smrPdo = ConnectionFactory::named($config, 'smr2025');

// 1. Fetch current IDs
echo "Fetching current artist IDs...\n";
$currentArtistIds = $pdo->query("SELECT id FROM artists")->fetchAll(PDO::FETCH_COLUMN);
$currentArtistIds = array_map('intval', $currentArtistIds);

echo "Identifying ghost artists...\n";
// Focus on specific recent windows if needed, or all
$stmt = $rankingsPdo->query("
    SELECT DISTINCT ri.entity_id, ri.score, ri.deltas, rw.window_start
    FROM ranking_items ri
    JOIN ranking_windows rw ON ri.window_id = rw.id
    WHERE ri.entity_type = 'artist'
    AND rw.id >= 300
");
$allRankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ghosts = [];
foreach ($allRankings as $r) {
    $eid = (int)$r['entity_id'];
    if (!in_array($eid, $currentArtistIds, true)) {
        if (!isset($ghosts[$eid])) {
            $ghosts[$eid] = $r;
        }
    }
}

echo "Found " . count($ghosts) . " unique artist ghosts to reconstruct.\n";

$reconstructed = 0;
foreach ($ghosts as $id => $g) {
    $score = (float)$g['score'];
    $deltas = json_decode((string)$g['deltas'], true);
    $spins = $deltas['spins'] ?? 0;
    
    if ($spins <= 0) {
        echo "   ❔ [SKIP] ID $id has no spins data in deltas.\n";
        continue;
    }

    // Try to find name in SMR Chart
    $matchStmt = $smrPdo->prepare("
        SELECT artist FROM smr_chart 
        WHERE tws = ? 
        AND window_date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_ADD(?, INTERVAL 30 DAY)
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
        } catch (\Throwable $e) {
            echo "      [FAIL] " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❔ [NONE] ID $id (Spins: $spins, Date: {$g['window_start']})\n";
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
