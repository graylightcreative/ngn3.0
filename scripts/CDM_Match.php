<?php

/**
 * CDM_Match.php - Link raw artist names to NGN ArtistIDs
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔄 CDM Match - Artist Identity Alignment
";
echo "========================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Identify unmatched records in staging or ingestion logs
// For this implementation, we'll look at smr_records which are pending mapping
$stmt = $pdo->query("
    SELECT DISTINCT artist_name 
    FROM smr_records 
    WHERE status = 'pending_mapping' AND cdm_artist_id IS NULL
");
$unmatched = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($unmatched)) {
    echo "✅ No unmatched artists found in pending records.
";
    exit(0);
}

echo "Found " . count($unmatched) . " unmatched artist names. Matching...
";

$matchedCount = 0;
$ghostCount = 0;

foreach ($unmatched as $row) {
    $rawName = $row['artist_name'];
    
    // A. Direct Match
    $matchStmt = $pdo->prepare("SELECT id FROM artists WHERE name = ? LIMIT 1");
    $matchStmt->execute([$rawName]);
    $artist = $matchStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($artist) {
        $artistId = $artist['id'];
        echo "   🔗 Matched: '$rawName' -> ID $artistId
";
        
        $updateStmt = $pdo->prepare("UPDATE smr_records SET cdm_artist_id = ?, status = 'mapped' WHERE artist_name = ?");
        $updateStmt->execute([$artistId, $rawName]);
        $matchedCount++;
    } else {
        // B. Ghost Profile Creation (Pending Wealth)
        // Only for high-heat (we'll assume all archive artists are high-heat for now)
        echo "   👻 Creating Ghost Profile for: '$rawName'
";
        
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawName));
        $ghostStmt = $pdo->prepare("INSERT INTO artists (name, slug, status) VALUES (?, ?, 'ghost')");
        try {
            $ghostStmt->execute([$rawName, $slug]);
            $newId = $pdo->lastInsertId();
            
            $updateStmt = $pdo->prepare("UPDATE smr_records SET cdm_artist_id = ?, status = 'mapped' WHERE artist_name = ?");
            $updateStmt->execute([$newId, $rawName]);
            $ghostCount++;
        } catch (\PDOException $e) {
            echo "      ⚠️  Failed to create ghost (likely duplicate slug): " . $e->getMessage() . "
";
        }
    }
}

echo "
========================================
";
echo "🏁 Matching Complete
";
echo "   Matched: $matchedCount
";
echo "   Ghosts:  $ghostCount
";
echo "========================================
";
