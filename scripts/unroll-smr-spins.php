<?php
/**
 * scripts/unroll-smr-spins.php
 * 
 * Purpose: Unroll aggregated SMR records into granular playback_events.
 * This populates the buffer for the NGN Boardroom's Fintech Sync.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🌀 Unrolling SMR Spins into Playback Buffer
";
echo "=========================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);

// 1. Get track mapping (slug to ID) to link playback events
echo "Loading track mapping...
";
$trackMap = $pdo->query("SELECT id, artist_id, title FROM tracks")->fetchAll(PDO::FETCH_ASSOC);
$lookup = [];
foreach ($trackMap as $t) {
    $key = $t['artist_id'] . '|' . strtolower($t['title']);
    $lookup[$key] = $t['id'];
}

// 2. Fetch aggregated SMR records
echo "Fetching SMR records...
";
$records = $pdo->query("SELECT * FROM smr_records WHERE cdm_artist_id IS NOT NULL AND spin_count > 0 LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);

$totalUnrolled = 0;
$maxToUnroll = 100000; // Cap for this phase

$insertStmt = $pdo->prepare("
    INSERT INTO `ngn_2025`.`playback_events` (
        track_id, started_at, duration_ms, 
        is_qualified_listen, royalty_processed, 
        source_type, source_id, territory
    ) VALUES (
        :track_id, :started_at, 180000,
        1, 0, 
        'smr_legacy', :source_id, 'XX'
    )
");

$pdo->beginTransaction();

foreach ($records as $record) {
    if ($totalUnrolled >= $maxToUnroll) break;
    
    $key = $record['cdm_artist_id'] . '|' . strtolower($record['track_title']);
    $trackId = $lookup[$key] ?? null;
    
    if (!$trackId) continue;
    
    $spins = min($record['spin_count'], 100); // Process in manageable chunks per record
    
    for ($i = 0; $i < $spins; $i++) {
        if ($totalUnrolled >= $maxToUnroll) break;
        
        // Jitter the timestamps slightly back from now
        $timestamp = date('Y-m-d H:i:s', time() - mt_rand(0, 86400 * 7));
        
        $insertStmt->execute([
            ':track_id' => $trackId,
            ':started_at' => $timestamp,
            ':source_id' => $record['id']
        ]);
        $totalUnrolled++;
    }
}

$pdo->commit();

echo "✅ Unroll Complete.
";
echo "   - Events Buffered: $totalUnrolled
";
echo "   - Status: Pressurized
";
echo "=========================================
";
