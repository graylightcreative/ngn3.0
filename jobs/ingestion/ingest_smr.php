<?php
// SMR ingestion worker (dev-focused)
// Usage:
//  - php jobs/ingestion/ingest_smr.php --id=<upload_id> [--dry-run] [--resume]
//  - php jobs/ingestion/ingest_smr.php --all [--max=10] [--dry-run] [--resume]
// Behavior:
//  - For each upload (single id or batch), generate preview if needed and advance status to preview_ready.
//  - Optionally (dev baseline) persist preview fields into DB tables and insert sample rows into smr_ingestions.

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Smr\UploadService;
use NGN\Lib\Smr\MappingService;
use NGN\Lib\DB\ConnectionFactory;

require_once __DIR__ . '/../../../lib/bootstrap.php';

$root = realpath(__DIR__ . '/../../..');
if (!class_exists(Env::class)) { ngn_autoload_diagnostics($root, true); exit(3); }
Env::load($root);
$config = new Config();

function parseArgs(array $argv): array {
    $out = ['id' => null, 'all' => false, 'max' => null, 'dry' => false, 'resume' => false];
    foreach ($argv as $a) {
        if (preg_match('/^--id=(.+)$/', $a, $m)) { $out['id'] = $m[1]; }
        elseif ($a === '--all') { $out['all'] = true; }
        elseif (preg_match('/^--max=(\d+)$/', $a, $m)) { $out['max'] = (int)$m[1]; }
        elseif ($a === '--dry-run' || $a === '--dry') { $out['dry'] = true; }
        elseif ($a === '--resume') { $out['resume'] = true; }
    }
    return $out;
}

$args = parseArgs(array_slice($argv, 1));
if (!$args['id'] && !$args['all']) {
    fwrite(STDERR, "Usage: php jobs/ingestion/ingest_smr.php --id=<upload_id> [--dry-run]\n");
    fwrite(STDERR, "   or: php jobs/ingestion/ingest_smr.php --all [--max=10] [--dry-run]\n");
    exit(1);
}

$svc = new UploadService($config);
$pdo = null;
try { $pdo = ConnectionFactory::write($config); } catch (\Throwable $e) { /* DB optional in dev */ }

function setDbStatus(?\PDO $pdo, string $uploadId, string $status, ?string $error = null, ?int $rowsCount = null): void {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("UPDATE smr_raw_uploads SET Status=:s, Error=:e, RowsCount=COALESCE(:rc, RowsCount), UpdatedAt=NOW() WHERE Id=:id");
        $stmt->execute([':s'=>$status, ':e'=>$error, ':rc'=>$rowsCount, ':id'=>$uploadId]);
    } catch (\Throwable $e) { /* ignore */ }
}

function markDbStarted(?\PDO $pdo, string $uploadId): void {
    if (!$pdo) return;
    try {
        $pdo->prepare("UPDATE smr_raw_uploads SET StartedAt=NOW(), UpdatedAt=NOW() WHERE Id=:id")->execute([':id'=>$uploadId]);
    } catch (\Throwable $e) { /* ignore */ }
}

function markDbCompleted(?\PDO $pdo, string $uploadId): void {
    if (!$pdo) return;
    try {
        $pdo->prepare("UPDATE smr_raw_uploads SET CompletedAt=NOW(), UpdatedAt=NOW() WHERE Id=:id")->execute([':id'=>$uploadId]);
    } catch (\Throwable $e) { /* ignore */ }
}

function updateLedgerStatus(Config $config, string $uploadId, callable $mutator): void {
    $file = rtrim($config->uploadDir(), '/').'/ledger/'.$uploadId.'.json';
    if (!is_file($file)) return;
    $raw = file_get_contents($file);
    $rec = json_decode($raw, true);
    if (!is_array($rec)) return;
    $new = $mutator($rec) ?: $rec;
    $new['updated_at'] = gmdate('c');
    @file_put_contents($file, json_encode($new, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function applyMappingToRows(array $rows, ?array $mapping): array {
    if (!$mapping || empty($rows)) return $rows;
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) { $out[] = $r; continue; }
        $nr = $r;
        // mapping like ['artist'=>'colA','track'=>'colB','spins'=>'colC','date'=>'colD','played_at'=>'colE']
        $mapField = function($field) use ($mapping, $r) {
            $col = $mapping[$field] ?? null;
            if (!$col) return null;
            // allow case-insensitive
            foreach ($r as $k=>$v) { if (strcasecmp((string)$k, (string)$col) === 0) return $v; }
            return $r[$col] ?? null;
        };
        $artist = $mapField('artist');
        $track = $mapField('track');
        $spins = $mapField('spins');
        $date = $mapField('date');
        $playedAt = $mapField('played_at');
        if ($artist !== null) $nr['Artist'] = $nr['ArtistName'] = $artist;
        if ($track !== null) { $nr['Track'] = $track; $nr['TrackTitle'] = $track; }
        if ($spins !== null) $nr['Spins'] = $spins;
        if ($date !== null) $nr['Date'] = $date;
        if ($playedAt !== null) $nr['PlayedAt'] = $playedAt;
        $out[] = $nr;
    }
    return $out;
}

function persistUploadIfDb(?\PDO $pdo, array $rec): void {
    if (!$pdo) return;
    try {
        $sql = "INSERT INTO smr_raw_uploads (Id, StationId, OriginalName, StoredPath, SizeBytes, MimeType, Status, HeaderCandidates, SampleRows, RowsCount, CreatedAt, UpdatedAt)
                VALUES (:Id,:StationId,:OriginalName,:StoredPath,:SizeBytes,:MimeType,:Status,:HeaderCandidates,:SampleRows,:RowsCount, NOW(), NOW())
                ON DUPLICATE KEY UPDATE StationId=VALUES(StationId), OriginalName=VALUES(OriginalName), StoredPath=VALUES(StoredPath), SizeBytes=VALUES(SizeBytes), MimeType=VALUES(MimeType), Status=VALUES(Status), HeaderCandidates=VALUES(HeaderCandidates), SampleRows=VALUES(SampleRows), RowsCount=VALUES(RowsCount), UpdatedAt=NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':Id' => (string)($rec['id'] ?? ''),
            ':StationId' => $rec['station_id'] ?? null,
            ':OriginalName' => (string)($rec['original_name'] ?? ($rec['filename'] ?? '')),
            ':StoredPath' => (string)($rec['path'] ?? ''),
            ':SizeBytes' => (int)($rec['size_bytes'] ?? 0),
            ':MimeType' => (string)($rec['mime'] ?? ''),
            ':Status' => (string)($rec['status'] ?? 'received'),
            ':HeaderCandidates' => json_encode($rec['header_candidates'] ?? null),
            ':SampleRows' => json_encode(($rec['preview_path'] ?? null) && is_file($rec['preview_path']) ? json_decode((string)file_get_contents($rec['preview_path']), true)['sample_rows'] ?? null : ($rec['sample_rows'] ?? null)),
            ':RowsCount' => (int)($rec['rows_count'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        fwrite(STDERR, "[warn] DB persist smr_raw_uploads failed: ".$e->getMessage()."\n");
    }
}

function insertIngestionsIfDb(?\PDO $pdo, array $rec, array $sampleRows): int {
    if (!$pdo || empty($sampleRows)) return 0;
    $ins = $pdo->prepare("INSERT INTO smr_ingestions (UploadId, StationId, RowIndex, ArtistName, TrackTitle, Spins, PlayDate, PlayedAt, Raw, MatchStatus, CreatedAt, UpdatedAt)
                           VALUES (:UploadId,:StationId,:RowIndex,:ArtistName,:TrackTitle,:Spins,:PlayDate,:PlayedAt,:Raw,'unresolved', NOW(), NOW())");
    $count = 0;
    foreach ($sampleRows as $i => $row) {
        $artist = $row['artist'] ?? $row['Artist'] ?? $row['artist_name'] ?? $row['ArtistName'] ?? null;
        $track  = $row['track'] ?? $row['Track'] ?? $row['title'] ?? $row['TrackTitle'] ?? null;
        $spins  = isset($row['spins']) ? (int)$row['spins'] : (isset($row['Spins']) ? (int)$row['Spins'] : null);
        $playDate = $row['date'] ?? $row['Date'] ?? null;
        $playedAt = $row['played_at'] ?? $row['PlayedAt'] ?? null;
        $raw = json_encode($row);
        try {
            $ins->execute([
                ':UploadId' => (string)$rec['id'],
                ':StationId' => $rec['station_id'] ?? null,
                ':RowIndex' => $i + 1,
                ':ArtistName' => $artist,
                ':TrackTitle' => $track,
                ':Spins' => $spins,
                ':PlayDate' => $playDate ? date('Y-m-d', strtotime((string)$playDate)) : null,
                ':PlayedAt' => $playedAt ? date('Y-m-d H:i:s', strtotime((string)$playedAt)) : null,
                ':Raw' => $raw,
            ]);
            $count++;
        } catch (\Throwable $e) {
            fwrite(STDERR, "[warn] insert ingestion row failed: ".$e->getMessage()."\n");
        }
    }
    return $count;
}

function loadSampleRowsFromLedger(array $rec): array {
    // Prefer preview_path JSON if present
    $previewPath = $rec['preview_path'] ?? null;
    if ($previewPath && is_file($previewPath)) {
        $json = json_decode((string)file_get_contents($previewPath), true);
        if (is_array($json) && isset($json['sample_rows']) && is_array($json['sample_rows'])) return $json['sample_rows'];
    }
    $hc = $rec['header_candidates'] ?? null;
    if (is_array($hc) && isset($hc['sample_rows']) && is_array($hc['sample_rows'])) return $hc['sample_rows'];
    return [];
}

/**
 * Full-file processor (CSV baseline). Attempts to parse the uploaded file and insert rows into smr_ingestions.
 * Returns number of rows inserted. Applies station mapping when available.
 */
function processFullFile(Config $config, ?\PDO $pdo, MappingService $mapSvc, array $rec, bool $dry = false, bool $resume = false): int {
    if (!$pdo) return 0;
    $path = $rec['path'] ?? null;
    if (!$path || !is_file($path)) return 0;
    // CSV and XLSX support
    $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    $stationId = $rec['station_id'] ?? null;
    $activeMap = null;
    if ($stationId) {
        try { $activeMap = $mapSvc->getActiveForStation((int)$stationId); } catch (\Throwable $e) { $activeMap = null; }
    }
    $count = 0;
    $rowIdx = 0;
    $sql = "INSERT INTO smr_ingestions (UploadId, StationId, RowIndex, ArtistName, TrackTitle, Spins, PlayDate, PlayedAt, Raw, MatchStatus, CreatedAt, UpdatedAt)
            VALUES (:UploadId,:StationId,:RowIndex,:ArtistName,:TrackTitle,:Spins,:PlayDate,:PlayedAt,:Raw,'unresolved', NOW(), NOW())";
    if ($resume) {
        // No-op on duplicate to allow resume without errors
        $sql .= " ON DUPLICATE KEY UPDATE UpdatedAt = VALUES(UpdatedAt)";
    }
    $ins = $pdo->prepare($sql);

    if ($ext === 'csv') {
        $fp = @fopen($path, 'r');
        if (!$fp) return 0;
        $headers = null;
        while (($cols = fgetcsv($fp)) !== false) {
            if ($headers === null) { $headers = $cols; continue; }
            $rowIdx++;
            $row = [];
            foreach ($cols as $i => $val) {
                $key = isset($headers[$i]) ? (string)$headers[$i] : ('col'.($i+1));
                $row[$key] = $val;
            }
            // Apply mapping if present
            $mappedRows = applyMappingToRows([$row], $activeMap);
            $nr = $mappedRows[0] ?? $row;
            $artist = $nr['artist'] ?? $nr['Artist'] ?? $nr['ArtistName'] ?? null;
            $track  = $nr['track'] ?? $nr['Track'] ?? $nr['TrackTitle'] ?? null;
            $spins  = isset($nr['spins']) ? (int)$nr['spins'] : (isset($nr['Spins']) ? (int)$nr['Spins'] : null);
            $playDate = $nr['date'] ?? $nr['Date'] ?? null;
            $playedAt = $nr['played_at'] ?? $nr['PlayedAt'] ?? null;
            if ($dry) { $count++; continue; }
            try {
                $ins->execute([
                    ':UploadId' => (string)$rec['id'],
                    ':StationId' => $stationId ?? null,
                    ':RowIndex' => $rowIdx,
                    ':ArtistName' => $artist,
                    ':TrackTitle' => $track,
                    ':Spins' => $spins,
                    ':PlayDate' => $playDate ? date('Y-m-d', strtotime((string)$playDate)) : null,
                    ':PlayedAt' => $playedAt ? date('Y-m-d H:i:s', strtotime((string)$playedAt)) : null,
                    ':Raw' => json_encode($nr),
                ]);
                $count++;
            } catch (\Throwable $e) {
                // continue; could log per-row failures
            }
        }
        fclose($fp);
        return $count;
    }

    if ($ext === 'xlsx') {
        try {
            // Prefer streaming read options
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            // First row as headers
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $headers[] = (string)$sheet->getCellByColumnAndRow($col, 1)->getCalculatedValue();
            }
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowIdx++;
                $rowArr = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $key = $headers[$col-1] !== '' ? (string)$headers[$col-1] : ('col'.$col);
                    $rowArr[$key] = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
                }
                $mappedRows = applyMappingToRows([$rowArr], $activeMap);
                $nr = $mappedRows[0] ?? $rowArr;
                $artist = $nr['artist'] ?? $nr['Artist'] ?? $nr['ArtistName'] ?? null;
                $track  = $nr['track'] ?? $nr['Track'] ?? $nr['TrackTitle'] ?? null;
                $spins  = isset($nr['spins']) ? (int)$nr['spins'] : (isset($nr['Spins']) ? (int)$nr['Spins'] : null);
                $playDate = $nr['date'] ?? $nr['Date'] ?? null;
                $playedAt = $nr['played_at'] ?? $nr['PlayedAt'] ?? null;
                if ($dry) { $count++; continue; }
                try {
                    $ins->execute([
                        ':UploadId' => (string)$rec['id'],
                        ':StationId' => $stationId ?? null,
                        ':RowIndex' => $rowIdx,
                        ':ArtistName' => $artist,
                        ':TrackTitle' => $track,
                        ':Spins' => $spins,
                        ':PlayDate' => $playDate ? date('Y-m-d', strtotime((string)$playDate)) : null,
                        ':PlayedAt' => $playedAt ? date('Y-m-d H:i:s', strtotime((string)$playedAt)) : null,
                        ':Raw' => json_encode($nr),
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    // continue; could log per-row failures
                }
            }
            // cleanup
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            return $count;
        } catch (\Throwable $e) {
            // If XLSX parse fails, return what we have (likely 0)
            return $count;
        }
    }

    // Unsupported extension
    return 0;
}

function findPendingLedgerRecords(Config $config, ?int $max): array {
    $dir = rtrim($config->uploadDir(), '/').'/ledger';
    $files = glob($dir.'/*.json') ?: [];
    $out = [];
    foreach ($files as $f) {
        $rec = json_decode((string)file_get_contents($f), true);
        if (!is_array($rec)) continue;
        $st = strtolower((string)($rec['status'] ?? 'received'));
        if (in_array($st, ['received','pending'], true)) { $out[] = $rec; }
        if ($max !== null && count($out) >= $max) break;
    }
    return $out;
}

$processed = 0;

if ($args['id']) {
    try {
        $rec = $svc->generatePreview($args['id']);
        fwrite(STDOUT, "[ok] preview generated for {$args['id']}\n");
        if (!$args['dry']) {
            persistUploadIfDb($pdo, $rec);
            $rows = loadSampleRowsFromLedger($rec);
            if (!empty($rows)) {
                $n = insertIngestionsIfDb($pdo, $rec, $rows);
                fwrite(STDOUT, "[ok] inserted {$n} sample ingestion rows for {$args['id']}\n");
            }
            // Full-file processing (CSV baseline)
            $mapSvc = new MappingService($config);
            setDbStatus($pdo, $rec['id'], 'processing', null, null);
            markDbStarted($pdo, $rec['id']);
            updateLedgerStatus($config, $rec['id'], function(array $r){ $r['status']='processing'; return $r; });
            if (!$args['resume'] && $pdo) {
                try { $del = $pdo->prepare("DELETE FROM smr_ingestions WHERE UploadId = :id"); $del->execute([':id'=>$rec['id']]); } catch (\Throwable $e) { /* ignore */ }
            }
            $total = processFullFile($config, $pdo, $mapSvc, $rec, false, (bool)$args['resume']);
            setDbStatus($pdo, $rec['id'], 'completed', null, $total);
            markDbCompleted($pdo, $rec['id']);
            updateLedgerStatus($config, $rec['id'], function(array $r) use ($total){ $r['status']='completed'; $r['rows_count']=$total; $r['progress']=100; return $r; });
            fwrite(STDOUT, "[ok] full-file processed rows={$total} for {$args['id']}\n");
        } else {
            fwrite(STDOUT, "[dry] would persist upload + sample rows and process full file for {$args['id']}\n");
        }
        $processed++;
    } catch (\Throwable $e) {
        if (isset($args['id'])) { setDbStatus($pdo, (string)$args['id'], 'failed', $e->getMessage(), null); updateLedgerStatus($config, (string)$args['id'], function(array $r) use ($e){ $r['status']='failed'; $r['error']=$e->getMessage(); return $r; }); }
        fwrite(STDERR, "[err] {$args['id']}: ".$e->getMessage()."\n");
    }
} else if ($args['all']) {
    $list = findPendingLedgerRecords($config, $args['max']);
    foreach ($list as $rec0) {
        $id = (string)($rec0['id'] ?? '');
        if ($id === '') continue;
        try {
            $rec = $svc->generatePreview($id);
            fwrite(STDOUT, "[ok] preview generated for {$id}\n");
            if (!$args['dry']) {
                persistUploadIfDb($pdo, $rec);
                $rows = loadSampleRowsFromLedger($rec);
                if (!empty($rows)) {
                    $n = insertIngestionsIfDb($pdo, $rec, $rows);
                    fwrite(STDOUT, "[ok] inserted {$n} sample ingestion rows for {$id}\n");
                }
                // Full-file processing
                $mapSvc = new MappingService($config);
                setDbStatus($pdo, $rec['id'], 'processing', null, null);
                updateLedgerStatus($config, $rec['id'], function(array $r){ $r['status']='processing'; return $r; });
                $total = processFullFile($config, $pdo, $mapSvc, $rec, false);
                setDbStatus($pdo, $rec['id'], 'completed', null, $total);
                updateLedgerStatus($config, $rec['id'], function(array $r) use ($total){ $r['status']='completed'; $r['rows_count']=$total; $r['progress']=100; return $r; });
                fwrite(STDOUT, "[ok] full-file processed rows={$total} for {$id}\n");
            } else {
                fwrite(STDOUT, "[dry] would persist upload + sample rows and process full file for {$id}\n");
            }
            $processed++;
        } catch (\Throwable $e) {
            setDbStatus($pdo, $id, 'failed', $e->getMessage(), null);
            updateLedgerStatus($config, $id, function(array $r) use ($e){ $r['status']='failed'; $r['error']=$e->getMessage(); return $r; });
            fwrite(STDERR, "[err] {$id}: ".$e->getMessage()."\n");
        }
    }
}

fwrite(STDOUT, "done. processed={$processed}\n");
