<?php
/**
 * reconcile-identities.php - System Integrity Recovery
 * 
 * 1. Merges duplicate artist/label identities in rankings.
 * 2. Recovers real label names from SMR archives and Legacy DB.
 * 3. Restores consistency to the Charts Moat.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🛰️ NGN System Integrity Recovery (v1.2)\n";
echo "=======================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
$smrPdo = ConnectionFactory::named($config, 'smr2025');

// Legacy PDO
try {
    $legacyPdo = new PDO('mysql:host=server.starrship1.com;dbname=nextgennoise', 'root', 'Starr!1');
} catch (\Throwable $e) {
    echo "⚠️  Could not connect to legacy DB. Fallback only to SMR.\n";
    $legacyPdo = null;
}

// --- PHASE 1: DEDUPLICATE ARTISTS ---
echo "Deduplicating artists in rankings...\n";
$stmt = $pdo->query("SELECT id, name FROM artists ORDER BY id ASC");
$allArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nameToPrimaryId = [];
foreach ($allArtists as $a) {
    $name = strtolower(trim($a['name']));
    if (!isset($nameToPrimaryId[$name])) {
        $nameToPrimaryId[$name] = (int)$a['id'];
    }
}

$mergedCount = 0;
foreach ($allArtists as $a) {
    $name = strtolower(trim($a['name']));
    $primaryId = $nameToPrimaryId[$name];
    $currentId = (int)$a['id'];
    
    if ($currentId !== $primaryId) {
        $stmt = $rankingsPdo->prepare("SELECT * FROM ranking_items WHERE entity_type = 'artist' AND entity_id = ?");
        $stmt->execute([$currentId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $wid = $item['window_id'];
            $check = $rankingsPdo->prepare("SELECT score FROM ranking_items WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
            $check->execute([$wid, $primaryId]);
            $existingScore = $check->fetchColumn();
            
            if ($existingScore !== false) {
                $newScore = (float)$existingScore + (float)$item['score'];
                $upd = $rankingsPdo->prepare("UPDATE ranking_items SET score = ? WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
                $upd->execute([$newScore, $wid, $primaryId]);
                $del = $rankingsPdo->prepare("DELETE FROM ranking_items WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
                $del->execute([$wid, $currentId]);
            } else {
                $upd = $rankingsPdo->prepare("UPDATE ranking_items SET entity_id = ? WHERE window_id = ? AND entity_type = 'artist' AND entity_id = ?");
                $upd->execute([$primaryId, $wid, $currentId]);
            }
            $mergedCount++;
        }
        $del = $pdo->prepare("DELETE FROM artists WHERE id = ?");
        $del->execute([$currentId]);
    }
}
echo "   ✓ Merged $mergedCount artist ranking entries.\n";

// --- PHASE 2: RECOVER LABEL NAMES ---
echo "\nRecovering label identities...\n";
$stmt = $pdo->query("SELECT id, name FROM labels WHERE name LIKE 'Ghost Label %'");
$ghostLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recoveredLabels = 0;
foreach ($ghostLabels as $gl) {
    $lid = (int)$gl['id'];
    $realLabelName = null;

    // A. Check Legacy DB
    if ($legacyPdo) {
        $lstmt = $legacyPdo->prepare("SELECT Title FROM users WHERE Id = ? LIMIT 1");
        $lstmt->execute([$lid]);
        $realLabelName = $lstmt->fetchColumn();
    }

    // B. Check SMR Fallback (via artists)
    if (!$realLabelName) {
        $astmt = $pdo->prepare("SELECT name FROM artists WHERE label_id = ? LIMIT 1");
        $astmt->execute([$lid]);
        $artistName = $astmt->fetchColumn();
        if ($artistName) {
            $lstmt = $smrPdo->prepare("SELECT label FROM smr_chart WHERE artist = ? AND label IS NOT NULL AND label != '' LIMIT 1");
            $lstmt->execute([$artistName]);
            $realLabelName = $lstmt->fetchColumn();
        }
    }
    
    if ($realLabelName && !str_contains($realLabelName, 'Ghost')) {
        echo "   🏷️ [RECOVERED] Label $lid => '$realLabelName'\n";
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realLabelName)) . '-' . $lid;
        $upd = $pdo->prepare("UPDATE labels SET name = ?, slug = ? WHERE id = ?");
        $upd->execute([$realLabelName, $slug, $lid]);
        $recoveredLabels++;
    }
}
echo "   ✓ Recovered $recoveredLabels label names.\n";

echo "\n🔄 Repressurizing Moat...\n";
include __DIR__ . '/repressurize-moat.php';

echo "\n🏁 Integrity Recovery Complete.\n";
