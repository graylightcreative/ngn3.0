<?php
/**
 * scripts/repressurize-playback-buffer.php
 * 
 * Purpose: Move 2025 granular spin data into the playback_events buffer.
 * Part of the "Fintech Sync" for the NGN Boardroom.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

echo "🔋 Repressurizing Playback Buffer (2025 Shard)
";
echo "============================================
";

$config = new Config();
$pdo = ConnectionFactory::write($config);
$spinsPdo = ConnectionFactory::named($config, 'spins2025');

// 1. Fetch from station_spins
echo "Fetching granular spins from ngn_spins_2025...
";
$spins = $spinsPdo->query("SELECT * FROM station_spins")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($spins) . " events.
";

$inserted = 0;
$skipped = 0;

// 2. Insert into playback_events buffer
$insertStmt = $pdo->prepare("
    INSERT INTO `ngn_2025`.`playback_events` (
        track_id, started_at, duration_ms, 
        is_qualified_listen, royalty_processed, 
        source_type, source_id, territory
    ) VALUES (
        :track_id, :started_at, :duration,
        1, 0, 
        'station_spin', :source_id, 'XX'
    ) ON DUPLICATE KEY UPDATE id=id
");

foreach ($spins as $spin) {
    // Check if track_id exists in ngn_2025
    if (!$spin['track_id']) {
        $skipped++;
        continue;
    }

    $insertStmt->execute([
        ':track_id' => $spin['track_id'],
        ':started_at' => $spin['played_at'],
        ':duration' => 180000, // Default 3 mins for station spins
        ':source_id' => $spin['id']
    ]);
    $inserted++;
}

echo "✅ Sync Complete.
";
echo "   - Inserted: $inserted
";
echo "   - Skipped: $skipped (missing track_id)
";
echo "============================================
";
