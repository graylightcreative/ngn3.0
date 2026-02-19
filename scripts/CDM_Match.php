<?php

/**
 * CDM_Match.php - Link raw artist names and tracks to NGN identities
 * Updated to also map and link Labels.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔄 CDM Match - Identity & Reach Alignment (v2.0)\n";
echo "==============================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Identify records needing mapping (either artist or label)
// We look for records that are pending OR mapped but might missing label links
$stmt = $pdo->query("
    SELECT id, artist_name, label_name, track_title, reach_count, cdm_artist_id
    FROM smr_records 
    WHERE status != 'imported'
");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    echo "✅ No records needing alignment found.\n";
    exit(0);
}

echo "Found " . count($records) . " records to align.\n";

$matchedCount = 0;
$ghostCount = 0;
$labelCount = 0;

// Performance Hardening: Preload Identity Cache
echo "Preloading Identity Cache...\n";
$artistCache = $pdo->query("SELECT name, id FROM artists")->fetchAll(PDO::FETCH_KEY_PAIR);
$labelCache = $pdo->query("SELECT name, id FROM labels")->fetchAll(PDO::FETCH_KEY_PAIR);

$recordUpdates = [];

foreach ($records as $row) {
    $recordId = $row['id'];
    $rawArtist = trim($row['artist_name']);
    $rawLabel = trim($row['label_name'] ?? '');
    $artistId = $row['cdm_artist_id'];
    
    // A. Artist Matching
    if (!$artistId) {
        if (isset($artistCache[$rawArtist])) {
            $artistId = $artistCache[$rawArtist];
        } else {
            // Create Ghost Profile
            echo "   👻 Creating Ghost Artist: '$rawArtist'\n";
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawArtist)) . '-' . rand(100, 999);
            $ghostStmt = $pdo->prepare("INSERT INTO artists (name, slug, status) VALUES (?, ?, 'ghost')");
            $ghostStmt->execute([$rawArtist, $slug]);
            $artistId = $pdo->lastInsertId();
            $artistCache[$rawArtist] = $artistId;
            $ghostCount++;
        }
    }

    // B. Label Matching & Linking
    $labelId = null;
    if (!empty($rawLabel)) {
        if (isset($labelCache[$rawLabel])) {
            $labelId = $labelCache[$rawLabel];
        } else {
            // Create Ghost Label
            echo "   🏷️ Creating Ghost Label: '$rawLabel'\n";
            $lSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawLabel)) . '-' . rand(100, 999);
            $lGhostStmt = $pdo->prepare("INSERT INTO labels (name, slug) VALUES (?, ?)");
            $lGhostStmt->execute([$rawLabel, $lSlug]);
            $labelId = $pdo->lastInsertId();
            $labelCache[$rawLabel] = $labelId;
            $labelCount++;
        }
        
        // Link Artist to Label if not already linked (best-effort)
        $linkStmt = $pdo->prepare("UPDATE artists SET label_id = ? WHERE id = ? AND label_id IS NULL");
        $linkStmt->execute([$labelId, $artistId]);
    }

    // C. Queue Status Update for Bulk Execution
    $recordUpdates[] = [
        'artist_id' => $artistId,
        'record_id' => $recordId
    ];
    
    $matchedCount++;
}

// D. Execute Bulk Status Updates (Chunks of 500)
echo "Executing Bulk Status Updates...\n";
$chunks = array_chunk($recordUpdates, 500);
foreach ($chunks as $chunk) {
    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("UPDATE smr_records SET cdm_artist_id = ?, status = 'mapped' WHERE id = ?");
    foreach ($chunk as $update) {
        $updateStmt->execute([$update['artist_id'], $update['record_id']]);
    }
    $pdo->commit();
}

echo "\n========================================\n";
echo "🏁 Alignment Complete\n";
echo "   Processed: $matchedCount\n";
echo "   New Artist Ghosts: $ghostCount\n";
echo "   New Label Ghosts: $labelCount\n";
echo "========================================\n";
