<?php
/**
 * JC's Kick Ass Rock Show - Spin Ingestion Engine v3
 * Merit-Based Ingestion: Queueing unknown artists.
 */

require_once __DIR__ . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Artists\EntityResolver;

$config = new Config();
$pdoPrimary = ConnectionFactory::read($config);
$pdoSpins = ConnectionFactory::named($config, 'spins2025');
$resolver = new EntityResolver($pdoPrimary);

$stationId = 12; // JC's Kick Ass Rock Show
$dataDir = __DIR__ . '/docs/Incoming Spins Data';
$files = glob($dataDir . '/*.csv');

echo "Starting v3 Merit-Based Ingestion for Station #{$stationId}...\n";

foreach ($files as $file) {
    echo "Processing: " . basename($file) . "\n";
    
    $handle = fopen($file, 'r');
    $headers = fgetcsv($handle);
    $headerMap = array_flip($headers);
    
    $rowCount = 0;
    $spinCount = 0;
    $queuedCount = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        
        $rawArtist = $row[$headerMap['ARTIST']] ?? '';
        $rawTitle = $row[$headerMap['TITLE']] ?? '';
        $datePlayed = $row[$headerMap['DATE_PLAYED']] ?? '';
        
        if (empty($rawArtist) || empty($rawTitle)) continue;

        // 1. Resolve Artists (Handling & and Features)
        $artistIds = $resolver->resolve($rawArtist);
        
        // 2. Formatting
        $timestamp = date('Y-m-d H:i:s', strtotime($datePlayed));

        if (!empty($artistIds)) {
            // Path A: Artist Exists - Direct Link
            foreach ($artistIds as $artistId) {
                $trackStmt = $pdoPrimary->prepare("SELECT id FROM tracks WHERE artist_id = ? AND title LIKE ? LIMIT 1");
                $trackStmt->execute([$artistId, '%' . $rawTitle . '%']);
                $trackId = $trackStmt->fetchColumn() ?: null;

                try {
                    $sql = "INSERT INTO station_spins 
                            (station_id, artist_name, song_title, played_at, artist_id, track_id, created_at) 
                            VALUES (:sid, :aname, :title, :played, :aid, :tid, NOW())";
                    
                    $stmt = $pdoSpins->prepare($sql);
                    $stmt->execute([
                        ':sid' => $stationId,
                        ':aname' => $rawArtist,
                        ':title' => $rawTitle,
                        ':played' => $timestamp,
                        ':aid' => $artistId,
                        ':tid' => $trackId
                    ]);
                    if ($stmt->rowCount() > 0) $spinCount++;
                } catch (\Throwable $e) {}
            }
        } else {
            // Path B: Unknown Artist - Push to Ingestion Queue
            try {
                $sql = "INSERT INTO artist_ingestion_queue (name, first_seen_at, last_seen_at, total_spins, created_at)
                        VALUES (:name, :played, :played, 1, NOW())
                        ON DUPLICATE KEY UPDATE 
                        last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at)),
                        total_spins = total_spins + 1";
                $stmt = $pdoPrimary->prepare($sql);
                $stmt->execute([':name' => $rawArtist, ':played' => $timestamp]);
                $queuedCount++;
            } catch (\Throwable $e) {}
        }
    }
    
    fclose($handle);
    echo "Done. Processed {$rowCount} rows. New Spins: {$spinCount}, Queued Artists: {$queuedCount}\n";
}

echo "Full Ingestion Complete.\n";
