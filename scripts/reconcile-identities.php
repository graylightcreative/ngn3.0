<?php
/**
 * reconcile-identities.php - System Integrity Recovery
 * 
 * 1. Merges duplicate artist/label identities in rankings.
 * 2. Recovers real label names from SMR archives.
 * 3. Restores consistency to the Charts Moat.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🛰️ NGN System Integrity Recovery (v1.0)
";
echo "=======================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config); // ngn_2025
$rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
$smrPdo = ConnectionFactory::named($config, 'smr2025');

// --- PHASE 1: DEDUPLICATE ARTISTS ---
echo "Deduplicating artists in rankings...
";
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
        // This is a duplicate! Update rankings to use primaryId
        $upd = $rankingsPdo->prepare("UPDATE ranking_items SET entity_id = ? WHERE entity_type = 'artist' AND entity_id = ?");
        $upd->execute([$primaryId, $currentId]);
        $mergedCount += $upd->rowCount();
        
        // Remove the duplicate artist record
        $del = $pdo->prepare("DELETE FROM artists WHERE id = ?");
        $del->execute([$currentId]);
    }
}
echo "   ✓ Merged $mergedCount artist ranking entries.
";

// --- PHASE 2: RECOVER LABEL NAMES ---
echo "
Recovering label identities from SMR archives...
";
$stmt = $pdo->query("SELECT id, name FROM labels WHERE name LIKE 'Ghost Label %'");
$ghostLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recoveredLabels = 0;
foreach ($ghostLabels as $gl) {
    $lid = (int)$gl['id'];
    
    // Find an artist that belongs to this label in rankings
    // We have to look at the recalculate-rankings logic: it used artists.label_id.
    // So we look for artists in ngn_2025 that have this label_id.
    $astmt = $pdo->prepare("SELECT name FROM artists WHERE label_id = ? LIMIT 1");
    $astmt->execute([$lid]);
    $artistName = $astmt->fetchColumn();
    
    if ($artistName) {
        // Now find the label name for this artist in smr_chart
        $lstmt = $smrPdo->prepare("SELECT label FROM smr_chart WHERE artist = ? AND label IS NOT NULL AND label != '' LIMIT 1");
        $lstmt->execute([$artistName]);
        $realLabelName = $lstmt->fetchColumn();
        
        if ($realLabelName && !str_contains($realLabelName, 'Ghost')) {
            echo "   🏷️ [RECOVERED] Label $lid => '$realLabelName' (via '$artistName')
";
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $realLabelName)) . '-' . $lid;
            $upd = $pdo->prepare("UPDATE labels SET name = ?, slug = ? WHERE id = ?");
            $upd->execute([$realLabelName, $slug, $lid]);
            $recoveredLabels++;
        }
    }
}
echo "   ✓ Recovered $recoveredLabels label names.
";

// --- PHASE 3: CLEANUP REMAINING GHOSTS ---
// If we still have ghost labels, try matching them by checking SMR records directly if possible.
// Or just leave them as placeholders if we can't find them.

echo "
🔄 Repressurizing Moat...
";
include __DIR__ . '/repressurize-moat.php';

echo "
🏁 Integrity Recovery Complete.
";
