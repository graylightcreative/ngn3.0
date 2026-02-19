<?php
/**
 * scripts/reconstruct-ghost-tracks.php
 * 
 * Purpose: Create track entities in ngn_2025 from unique artist/title pairs in smr_records.
 * Links them to a "Ghost Release" for each artist to satisfy database constraints.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "👻 Reconstructing Ghost Tracks & Releases (2025 Shard)\n";
echo "===================================================\n";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Fetch unique artist/title pairs from smr_records
echo "Analyzing SMR records for unique tracks...\n";
$sql = "
    SELECT DISTINCT artist_name, track_title, cdm_artist_id, isrc
    FROM smr_records 
    WHERE cdm_artist_id IS NOT NULL
";
$pairs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($pairs) . " unique tracks to reconstruct.\n";

$reconstructed = 0;
$linked = 0;
$artistReleaseMap = [];

// 2. Insert into tracks table
$insertTrackStmt = $pdo->prepare("
    INSERT INTO tracks (release_id, artist_id, title, slug, isrc, created_at)
    VALUES (:release_id, :artist_id, :title, :slug, :isrc, NOW())
    ON DUPLICATE KEY UPDATE id=id
");

// 3. Helper to get or create Ghost Release
$getReleaseStmt = $pdo->prepare("SELECT id FROM releases WHERE artist_id = ? AND title = 'SMR Imports' LIMIT 1");
$createReleaseStmt = $pdo->prepare("INSERT INTO releases (artist_id, title, slug, type, created_at) VALUES (?, 'SMR Imports', ?, 'album', NOW())");

foreach ($pairs as $pair) {
    $artistId = $pair['cdm_artist_id'];
    
    // Get/Create release for this artist
    if (!isset($artistReleaseMap[$artistId])) {
        $getReleaseStmt->execute([$artistId]);
        $releaseId = $getReleaseStmt->fetchColumn();
        
        if (!$releaseId) {
            $releaseSlug = "smr-imports-" . $artistId;
            $createReleaseStmt->execute([$artistId, $releaseSlug]);
            $releaseId = $pdo->lastInsertId();
        }
        $artistReleaseMap[$artistId] = $releaseId;
    }
    
    $releaseId = $artistReleaseMap[$artistId];
    $trackSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $pair['track_title'] . '-' . $artistId)));
    
    try {
        $insertTrackStmt->execute([
            ':release_id' => $releaseId,
            ':artist_id' => $artistId,
            ':title' => $pair['track_title'],
            ':slug' => $trackSlug,
            ':isrc' => $pair['isrc'] ?: null
        ]);
        
        if ($insertTrackStmt->rowCount() > 0) {
            $reconstructed++;
        } else {
            $linked++;
        }
    } catch (Exception $e) {
        // Fallback for slug collisions
        $trackSlug .= "-" . bin2hex(random_bytes(2));
        $insertTrackStmt->execute([
            ':release_id' => $releaseId,
            ':artist_id' => $artistId,
            ':title' => $pair['track_title'],
            ':slug' => $trackSlug,
            ':isrc' => $pair['isrc'] ?: null
        ]);
        $reconstructed++;
    }
}

echo "✅ Reconstruction Complete.\n";
echo "   - Reconstructed: $reconstructed\n";
echo "   - Already Existed: $linked\n";
echo "===================================================\n";
