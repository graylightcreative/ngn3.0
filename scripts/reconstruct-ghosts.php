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

echo "🧠 NGN Identity Reconstruction Engine (v1.3)\n";
echo "===========================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
$smrPdo = ConnectionFactory::named($config, 'smr2025');

// 1. Recover Artist Names from Rankings
echo "Identifying ghost artists...\n";
$currentArtistIds = $pdo->query("SELECT id FROM artists")->fetchAll(PDO::FETCH_COLUMN);
$currentArtistIds = array_map('intval', $currentArtistIds);

$stmt = $rankingsPdo->query("
    SELECT DISTINCT ri.entity_id, ri.deltas, rw.window_start
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
        if (!isset($ghosts[$eid])) { $ghosts[$eid] = $r; }
    }
}

echo "Found " . count($ghosts) . " unique artist ghosts to reconstruct.\n";

$reconstructed = 0;
foreach ($ghosts as $id => $g) {
    $deltas = json_decode((string)$g['deltas'], true);
    $spins = $deltas['spins'] ?? 0;
    if ($spins <= 0) continue;

    $matchStmt = $smrPdo->prepare("SELECT artist FROM smr_chart WHERE tws = ? AND window_date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_ADD(?, INTERVAL 30 DAY) LIMIT 1");
    $matchStmt->execute([$spins, $g['window_start'], $g['window_start']]);
    $name = $matchStmt->fetchColumn();
    
    if ($name) {
        echo "   👻 [MATCH] ID $id => '$name'\n";
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . $id;
        try {
            $ins = $pdo->prepare("INSERT INTO artists (id, name, slug, status) VALUES (?, ?, ?, 'ghost') ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $ins->execute([$id, $name, $slug]);
            $reconstructed++;
        } catch (\Throwable $e) {}
    }
}

// 2. Recover Label Links for ALL Artists
echo "\nRecovering label associations for all artists...\n";
$stmt = $pdo->query("SELECT id, name FROM artists WHERE label_id IS NULL");
$unlinkedArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($unlinkedArtists) . " unlinked artists.\n";

foreach ($unlinkedArtists as $ua) {
    $aid = (int)$ua['id'];
    $name = $ua['name'];
    
    $lstmt = $smrPdo->prepare("SELECT label FROM smr_chart WHERE artist = ? AND label IS NOT NULL AND label != '' LIMIT 1");
    $lstmt->execute([$name]);
    $labelName = $lstmt->fetchColumn();
    
    if ($labelName) {
        // Find or create label
        $lidStmt = $pdo->prepare("SELECT id FROM labels WHERE name = ? LIMIT 1");
        $lidStmt->execute([$labelName]);
        $lid = $lidStmt->fetchColumn();
        
        if (!$lid) {
            echo "   🏷️ [NEW LABEL] '$labelName' (for '$name')\n";
            $lslug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $labelName)) . '-' . rand(100, 999);
            $lins = $pdo->prepare("INSERT INTO labels (name, slug) VALUES (?, ?)");
            $lins->execute([$labelName, $lslug]);
            $lid = $pdo->lastInsertId();
        }
        
        if ($lid) {
            $upd = $pdo->prepare("UPDATE artists SET label_id = ? WHERE id = ?");
            $upd->execute([$lid, $aid]);
        }
    }
}

echo "\n🏁 Reconstruction complete.\n";
echo "🔄 Triggering Moat Repressurization...\n";
include __DIR__ . '/repressurize-moat.php';
