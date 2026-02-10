<?php

/**
 * CDM_Match.php - Link raw artist names and tracks to NGN identities
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔄 CDM Match - Identity & Reach Alignment\n";
echo "========================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Identify unmatched records
$stmt = $pdo->query("
    SELECT id, artist_name, track_title, reach_count 
    FROM smr_records 
    WHERE status = 'pending_mapping'
");
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "✅ No pending records found.\n";
    exit(0);
}

echo "Found " . count($pending) . " records to align.\n";

$matchedCount = 0;
$ghostCount = 0;

foreach ($pending as $row) {
    $recordId = $row['id'];
    $rawArtist = $row['artist_name'];
    $rawTrack = $row['track_title'];
    $reachCount = $row['reach_count'];
    
    // A. Artist Matching
    $matchStmt = $pdo->prepare("SELECT id FROM artists WHERE name = ? LIMIT 1");
    $matchStmt->execute([$rawArtist]);
    $artist = $matchStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($artist) {
        $artistId = $artist['id'];
    } else {
        // Create Ghost Profile
        echo "   👻 Creating Ghost Profile: '$rawArtist'\n";
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawArtist)) . '-' . rand(100, 999);
        $ghostStmt = $pdo->prepare("INSERT INTO artists (name, slug, status) VALUES (?, ?, 'ghost')");
        $ghostStmt->execute([$rawArtist, $slug]);
        $artistId = $pdo->lastInsertId();
        $ghostCount++;
    }

    // B. Calculate Reach Multiplier
    // Logic: Every station provides a 1.25x multiplier to the raw spin heat
    $multiplier = 1.0 + ($reachCount * 0.25);
    
    // C. Update Record
    $updateStmt = $pdo->prepare("
        UPDATE smr_records 
        SET cdm_artist_id = ?, status = 'mapped' 
        WHERE id = ?
    ");
    $updateStmt->execute([$artistId, $recordId]);
    
    $matchedCount++;
}

echo "\n========================================\n";
echo "🏁 Alignment Complete\n";
echo "   Processed: $matchedCount\n";
echo "   New Ghosts: $ghostCount\n";
echo "========================================\n";