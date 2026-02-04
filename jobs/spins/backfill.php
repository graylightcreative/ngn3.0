<?php
// CLI-only ETL scaffold: Backfill legacy spins into CDM cdm_spins
// Usage:
//   php jobs/spins/backfill.php --since=2025-01-01 --until=2025-01-31 --batch=1000 --dry-run=1 --source-conn=ngnspins --source-table=spins
// Notes:
// - Safe defaults: dry-run=1. Set --dry-run=0 to write.
// - Logs to storage/logs/etl-spins.log

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

require __DIR__ . '/../../vendor/autoload.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden (CLI only)\n";
    exit(1);
}

function log_line(string $msg): void {
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = '[' . date('c') . '] spins.backfill ' . $msg . "\n";
    file_put_contents($dir . '/etl-spins.log', $line, FILE_APPEND);
    fwrite(STDOUT, $line);
}

// Parse args
$opts = getopt('', [
    'since::', 'until::', 'batch::', 'dry-run::', 'source-conn::', 'source-table::'
]);

$since = $opts['since'] ?? date('Y-m-d', strtotime('-7 days'));
$until = $opts['until'] ?? date('Y-m-d');
$batch = (int)($opts['batch'] ?? 1000);
$dryRun = (int)($opts['dry-run'] ?? 1) === 1;
$sourceConn = $opts['source-conn'] ?? 'ngnspins';
$sourceTable = $opts['source-table'] ?? 'spins';

log_line("starting since={$since} until={$until} batch={$batch} dryRun=" . ($dryRun ? '1' : '0') . " source={$sourceConn}.{$sourceTable}");

try {
    $config = new Config(__DIR__ . '/../../');
} catch (\Throwable $e) {
    log_line('ERROR loading config: ' . $e->getMessage());
    exit(1);
}

// Destination (primary) write connection
try {
    $dst = ConnectionFactory::write($config);
} catch (\Throwable $e) {
    log_line('ERROR connecting to primary DB: ' . $e->getMessage());
    exit(1);
}

// Source named connection (legacy spins)
try {
    $src = ConnectionFactory::named($config, $sourceConn);
} catch (\Throwable $e) {
    log_line("ERROR connecting to source connection '{$sourceConn}': " . $e->getMessage());
    exit(1);
}

// Heuristic: guess source schema columns; allow partial mapping
// Expected minimal columns: station_id, artist_id, track_title, occurred_at
// Fallback mappings can be adjusted later.

// Count rows in window
try {
    $cntStmt = $src->prepare("SELECT COUNT(*) AS c FROM `{$sourceTable}` WHERE occurred_at >= :since AND occurred_at < DATE_ADD(:until, INTERVAL 1 DAY)");
    $cntStmt->execute([':since' => $since, ':until' => $until]);
    $total = (int)($cntStmt->fetchColumn() ?: 0);
} catch (\Throwable $e) {
    log_line('ERROR counting source rows: ' . $e->getMessage());
    exit(1);
}

log_line("source rows in window: {$total}");
if ($total === 0) {
    log_line('nothing to do');
    exit(0);
}

$offset = 0;
$inserted = 0;

while ($offset < $total) {
    try {
        $sel = $src->prepare("SELECT * FROM `{$sourceTable}` WHERE occurred_at >= :since AND occurred_at < DATE_ADD(:until, INTERVAL 1 DAY) ORDER BY occurred_at ASC LIMIT :lim OFFSET :off");
        $sel->bindValue(':since', $since);
        $sel->bindValue(':until', $until);
        $sel->bindValue(':lim', $batch, PDO::PARAM_INT);
        $sel->bindValue(':off', $offset, PDO::PARAM_INT);
        $sel->execute();
    } catch (\Throwable $e) {
        log_line('ERROR selecting batch: ' . $e->getMessage());
        exit(1);
    }

    $rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) break;

    foreach ($rows as $r) {
        // Map legacy row to CDM fields (best-effort; adjust mappings as legacy schema becomes precise)
        $stationId = $r['station_id'] ?? ($r['StationId'] ?? null);
        $artistId  = $r['artist_id'] ?? ($r['ArtistId'] ?? null);
        $title     = $r['track_title'] ?? ($r['TrackTitle'] ?? ($r['title'] ?? null));
        $occurred  = $r['occurred_at'] ?? ($r['OccurredAt'] ?? ($r['play_time'] ?? null));
        $legacyId  = $r['id'] ?? ($r['Id'] ?? null);
        $sourceTag = $sourceConn;

        if (!$stationId || !$artistId || !$occurred) {
            log_line('WARN skipping row with missing required fields: ' . json_encode(['legacy_id'=>$legacyId]));
            continue;
        }

        if ($dryRun) {
            $inserted++;
            continue;
        }

        try {
            // No upsert key defined yet; for idempotency we can rely on (legacy_id) when present
            if ($legacyId) {
                $stmt = $dst->prepare(
                    'INSERT INTO cdm_spins (legacy_id, station_id, artist_id, track_title, occurred_at, source)\n'
                  . 'VALUES (:legacy_id, :station_id, :artist_id, :track_title, :occurred_at, :source)\n'
                  . 'ON DUPLICATE KEY UPDATE track_title=VALUES(track_title), source=VALUES(source), updated_at=NOW()'
                );
                $stmt->execute([
                    ':legacy_id' => $legacyId,
                    ':station_id' => $stationId,
                    ':artist_id' => $artistId,
                    ':track_title' => $title,
                    ':occurred_at' => $occurred,
                    ':source' => $sourceTag,
                ]);
            } else {
                $stmt = $dst->prepare(
                    'INSERT INTO cdm_spins (station_id, artist_id, track_title, occurred_at, source)\n'
                  . 'VALUES (:station_id, :artist_id, :track_title, :occurred_at, :source)'
                );
                $stmt->execute([
                    ':station_id' => $stationId,
                    ':artist_id' => $artistId,
                    ':track_title' => $title,
                    ':occurred_at' => $occurred,
                    ':source' => $sourceTag,
                ]);
            }
            $inserted++;
        } catch (\Throwable $e) {
            log_line('ERROR insert spin: ' . $e->getMessage());
        }
    }

    $offset += count($rows);
    log_line("progress offset={$offset}/{$total} inserted={$inserted}");
}

log_line('done. inserted=' . $inserted . ' dryRun=' . ($dryRun ? '1':'0'));
