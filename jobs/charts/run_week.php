<?php
// CLI-only scaffold: create a weekly chart run row for cdm_chart_runs and (optionally) complete it
// Usage examples:
//   php jobs/charts/run_week.php --chart=ngn:artists:weekly --start=2025-11-10 --end=2025-11-16 --dry-run=1
//   php jobs/charts/run_week.php --chart=ngn:artists:weekly --iso-week=2025-W46 --complete-empty=1 --dry-run=0
// Notes:
// - This is a scaffold: it creates a run row referencing formula 'artist.default@v1'.
// - Computing scores and inserting cdm_chart_entries will be implemented in the next step.
// - Logs to storage/logs/etl-charts.log

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

require __DIR__ . '/../../vendor/autoload.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden (CLI only)\n";
    exit(1);
}

function logc(string $msg): void {
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = '[' . date('c') . '] charts.run_week ' . $msg . "\n";
    file_put_contents($dir . '/etl-charts.log', $line, FILE_APPEND);
    fwrite(STDOUT, $line);
}

function isoWeekToRange(string $iso): array {
    // format: YYYY-Www
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $iso, $m)) {
        throw new InvalidArgumentException('Invalid iso-week format, expected YYYY-Www');
    }
    $year = (int)$m[1];
    $week = (int)$m[2];
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $start = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $end = $dto->format('Y-m-d');
    return [$start, $end];
}

$opts = getopt('', [
    'chart::', 'start::', 'end::', 'iso-week::', 'dry-run::', 'complete-empty::'
]);

$chart = $opts['chart'] ?? 'ngn:artists:weekly';
$dryRun = (int)($opts['dry-run'] ?? 1) === 1;
$completeEmpty = (int)($opts['complete-empty'] ?? 0) === 1;

if (!empty($opts['iso-week'])) {
    [$start, $end] = isoWeekToRange($opts['iso-week']);
} else {
    $start = $opts['start'] ?? date('Y-m-d', strtotime('monday last week'));
    $end   = $opts['end']   ?? date('Y-m-d', strtotime('sunday last week'));
}

logc("starting chart={$chart} start={$start} end={$end} dryRun=" . ($dryRun?'1':'0') . ' completeEmpty=' . ($completeEmpty?'1':'0'));

try { $config = new Config(__DIR__ . '/../../'); } catch (Throwable $e) {
    logc('ERROR loading config: ' . $e->getMessage());
    exit(1);
}

try { $pdo = ConnectionFactory::write($config); } catch (Throwable $e) {
    logc('ERROR connecting DB: ' . $e->getMessage());
    exit(1);
}

// Resolve chart definition
$stmt = $pdo->prepare('SELECT id, slug, period FROM cdm_chart_definitions WHERE slug = :slug LIMIT 1');
$stmt->execute([':slug' => $chart]);
$chartDef = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$chartDef) {
    logc('ERROR: chart definition not found: ' . $chart);
    exit(1);
}
if ($chartDef['period'] !== 'weekly') {
    logc('WARN: chart period is not weekly (' . $chartDef['period'] . '), continuing');
}

// Resolve scoring formula version v1 for artists
$stmt = $pdo->prepare("SELECT id FROM cdm_scoring_formulas WHERE slug='artist.default' AND version=1 LIMIT 1");
$stmt->execute();
$formulaId = (int)($stmt->fetchColumn() ?: 0);
if ($formulaId <= 0) {
    logc('ERROR: scoring formula artist.default@v1 not found. Did 011_scoring_seed_from_env.sql run?');
    exit(1);
}

// Compute current weights checksum (simplified placeholder)
$stmt = $pdo->prepare('SELECT CONCAT_WS(":", `key`, value) as kv FROM cdm_scoring_weights WHERE formula_id = :fid ORDER BY `key`');
$stmt->execute([':fid' => $formulaId]);
$kv = array_map(fn($r) => $r['kv'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
$weightsChecksum = hash('sha256', implode('|', $kv));

if ($dryRun) {
    logc('dry-run: would insert cdm_chart_runs row with checksum=' . $weightsChecksum);
    exit(0);
}

// Insert run row (pending)
$ins = $pdo->prepare('INSERT INTO cdm_chart_runs (chart_id, period_start, period_end, formula_id, weights_checksum, status) VALUES (:cid, :ps, :pe, :fid, :wcs, "pending")');
$ins->execute([
    ':cid' => (int)$chartDef['id'],
    ':ps' => $start,
    ':pe' => $end,
    ':fid' => $formulaId,
    ':wcs' => $weightsChecksum,
]);
$runId = (int)$pdo->lastInsertId();
logc('created run id=' . $runId);

if ($completeEmpty) {
    $upd = $pdo->prepare('UPDATE cdm_chart_runs SET status="completed", updated_at=NOW() WHERE id=:id');
    $upd->execute([':id' => $runId]);
    logc('marked run completed (no entries inserted yet)');
}

logc('done');
