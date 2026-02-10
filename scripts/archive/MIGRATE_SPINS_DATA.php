<?php
/**
 * MIGRATE_SPINS_DATA.php
 * Migrates legacy spindata to new station_spins schema
 *
 * Usage: php scripts/MIGRATE_SPINS_DATA.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::write($config);

echo "\n" . str_repeat("=", 80) . "\n";
echo "SPINS DATA MIGRATION: Legacy spindata → station_spins\n";
echo str_repeat("=", 80) . "\n\n";

try {
    // Step 1: Count legacy records
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_spins_2025.spindata");
    $stmt->execute();
    $result = $stmt->fetch();
    $legacyCount = $result['cnt'] ?? 0;
    echo "[INFO] Legacy spindata records to migrate: $legacyCount\n\n";

    if ($legacyCount === 0) {
        echo "[INFO] No legacy spins to migrate.\n";
        exit(0);
    }

    // Step 2: Get all legacy spins
    echo "[STEP 1] Reading legacy spindata...\n";
    $stmt = $db->prepare("
        SELECT
            Id,
            Artist,
            Song,
            Timestamp,
            StationId,
            Approved,
            TWS,
            Program
        FROM ngn_spins_2025.spindata
        ORDER BY Timestamp DESC
    ");
    $stmt->execute();
    $legacySpins = $stmt->fetchAll();

    echo "[OK] Read $legacyCount legacy spin records\n\n";

    // Step 3: Build mapping data
    echo "[STEP 2] Building artist and station mappings...\n";

    // Get all artists for name matching
    $stmt = $db->prepare("SELECT id, name FROM ngn_2025.artists");
    $stmt->execute();
    $artistRows = $stmt->fetchAll();
    $artistsByName = [];
    foreach ($artistRows as $row) {
        $artistsByName[strtolower(trim($row['name']))] = $row['id'];
    }

    // Get all stations (map legacy StationId to ngn_2025 station.id)
    // StationId in spins might refer to legacy station IDs
    // We need to map them to new station IDs
    $stmt = $db->prepare("SELECT id, name FROM ngn_2025.stations");
    $stmt->execute();
    $stationRows = $stmt->fetchAll();
    $stationsById = [];
    foreach ($stationRows as $row) {
        $stationsById[$row['id']] = $row['id']; // ID maps to itself
    }

    echo "[OK] Loaded " . count($artistsByName) . " artists and " . count($stationsById) . " stations\n\n";

    // Step 4: Migrate data
    echo "[STEP 3] Migrating spins to station_spins table...\n";

    $migrated = 0;
    $unmappedArtists = 0;
    $unmappedStations = 0;
    $errors = 0;

    foreach ($legacySpins as $spin) {
        try {
            $artistId = null;
            $stationId = null;

            // Map artist by name matching
            if (!empty($spin['Artist'])) {
                $artistName = strtolower(trim($spin['Artist']));
                if (isset($artistsByName[$artistName])) {
                    $artistId = $artistsByName[$artistName];
                } else {
                    // Try fuzzy matching as fallback
                    foreach ($artistsByName as $name => $id) {
                        if (similar_text($artistName, $name) > strlen($artistName) * 0.85) {
                            $artistId = $id;
                            break;
                        }
                    }
                    if ($artistId === null) {
                        $unmappedArtists++;
                    }
                }
            }

            // Map station
            if (!empty($spin['StationId']) && isset($stationsById[$spin['StationId']])) {
                $stationId = $stationsById[$spin['StationId']];
            } else {
                if (!empty($spin['StationId'])) {
                    $unmappedStations++;
                }
            }

            // Insert into station_spins
            $stmt = $db->prepare("
                INSERT INTO ngn_spins_2025.station_spins
                (station_id, artist_id, played_at, meta)
                VALUES (?, ?, ?, ?)
            ");

            $meta = json_encode([
                'legacy_id' => $spin['Id'],
                'song' => $spin['Song'] ?? null,
                'program' => $spin['Program'] ?? null,
                'approved' => $spin['Approved'] ?? 0,
                'tws' => $spin['TWS'] ?? null
            ]);

            $stmt->execute([
                $stationId,
                $artistId,
                $spin['Timestamp'],
                $meta
            ]);

            $migrated++;

            if ($migrated % 100 === 0) {
                echo "  Processed: $migrated / $legacyCount\r";
            }

        } catch (Exception $e) {
            $errors++;
            if ($errors <= 5) {
                echo "\n  [WARNING] Error migrating spin ID {$spin['Id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n\n[OK] Spins migration complete!\n";
    echo "  Migrated: $migrated\n";
    echo "  Unmapped artists: $unmappedArtists\n";
    echo "  Unmapped stations: $unmappedStations\n";
    echo "  Errors: $errors\n\n";

    // Step 5: Verify results
    echo "[STEP 4] Verifying migrated data...\n";

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ngn_spins_2025.station_spins");
    $stmt->execute();
    $result = $stmt->fetch();
    $newCount = $result['cnt'] ?? 0;

    echo "[OK] New station_spins records: $newCount\n";

    // Get stats
    $stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT artist_id) as artist_count,
            COUNT(DISTINCT station_id) as station_count,
            MIN(played_at) as earliest,
            MAX(played_at) as latest
        FROM ngn_spins_2025.station_spins
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    echo "[INFO] Spins statistics:\n";
    echo "  Unique artists: " . ($stats['artist_count'] ?? 0) . "\n";
    echo "  Unique stations: " . ($stats['station_count'] ?? 0) . "\n";
    echo "  Date range: " . ($stats['earliest'] ?? 'N/A') . " to " . ($stats['latest'] ?? 'N/A') . "\n\n";

    // Get top artists
    $stmt = $db->prepare("
        SELECT artist_id, COUNT(*) as spin_count
        FROM ngn_spins_2025.station_spins
        WHERE artist_id IS NOT NULL
        GROUP BY artist_id
        ORDER BY spin_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topArtists = $stmt->fetchAll();

    if (!empty($topArtists)) {
        echo "[INFO] Top 10 artists by spin count:\n";
        foreach ($topArtists as $i => $row) {
            $stmt2 = $db->prepare("SELECT name FROM ngn_2025.artists WHERE id = ?");
            $stmt2->execute([$row['artist_id']]);
            $artist = $stmt2->fetch();
            echo "  " . ($i + 1) . ". " . ($artist['name'] ?? 'Unknown') . ": {$row['spin_count']} spins\n";
        }
    }

    echo "\n";

} catch (Exception $e) {
    echo "[ERROR] Spins migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo str_repeat("=", 80) . "\n";
echo "SPINS MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n\n";
?>
