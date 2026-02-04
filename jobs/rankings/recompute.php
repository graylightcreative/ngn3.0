<?php
// Rankings recompute job (dev baseline)
// Usage examples:
//  - php jobs/rankings/recompute.php --interval=daily
//  - php jobs/rankings/recompute.php --interval=weekly --start=2025-10-01 --end=2025-10-07
//  - php jobs/rankings/recompute.php --interval=monthly --dry-run
// Computes artist rankings from station_spins by counting spins within the window.
// Writes into rankings (and optional ranking_factors) idempotently per interval window.

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;
use NGN\Lib\DB\ConnectionFactory;

require_once __DIR__.'/../../../lib/bootstrap.php';

$root = realpath(__DIR__.'/../../..');
if (!class_exists(Env::class)) { ngn_autoload_diagnostics($root, true); exit(3); }
Env::load($root);
$config = new Config();
$logger = LoggerFactory::create($config, 'rankings');

function parseArgs(array $argv): array {
    $out = ['interval' => 'daily', 'start' => null, 'end' => null, 'dry' => false];
    foreach ($argv as $a) {
        if (preg_match('/^--interval=(daily|weekly|monthly)$/', $a, $m)) { $out['interval'] = $m[1]; }
        elseif (preg_match('/^--start=(\d{4}-\d{2}-\d{2})$/', $a, $m)) { $out['start'] = $m[1]; }
        elseif (preg_match('/^--end=(\d{4}-\d{2}-\d{2})$/', $a, $m)) { $out['end'] = $m[1]; }
        elseif ($a === '--dry' || $a === '--dry-run') { $out['dry'] = true; }
    }
    return $out;
}

$args = parseArgs(array_slice($argv, 1));
$interval = $args['interval'] ?? 'daily';
if (!in_array($interval, ['daily','weekly','monthly'], true)) {
    fwrite(STDERR, "Interval must be daily|weekly|monthly\n");
    exit(1);
}

// Compute window if not provided
$today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
if (!$args['start'] || !$args['end']) {
    if ($interval === 'daily') {
        $start = $today->sub(new DateInterval('P1D'));
        $end = $today; // exclusive end
    } elseif ($interval === 'weekly') {
        // last 7 complete days
        $start = $today->sub(new DateInterval('P7D'));
        $end = $today;
    } else {
        // last 30 complete days (simple baseline)
        $start = $today->sub(new DateInterval('P30D'));
        $end = $today;
    }
} else {
    $start = new DateTimeImmutable($args['start'].' 00:00:00', new DateTimeZone('UTC'));
    $end = new DateTimeImmutable($args['end'].' 00:00:00', new DateTimeZone('UTC'));
}

$periodStart = $start->format('Y-m-d');
$periodEnd = $end->format('Y-m-d');

$logger->info('rankings_recompute_start', ['interval' => $interval, 'start' => $periodStart, 'end' => $periodEnd, 'dry' => $args['dry']]);

// Connect to DB
try {
    $pdo = ConnectionFactory::write($config);
} catch (\Throwable $e) {
    fwrite(STDERR, "DB connection error: ".$e->getMessage()."\n");
    exit(2);
}

// Aggregate spins by artist
$sql = "SELECT ArtistName AS name, COUNT(*) AS spins
        FROM station_spins
        WHERE PlayedAt >= :start AND PlayedAt < :end
        GROUP BY ArtistName
        HAVING name IS NOT NULL AND name <> ''
        ORDER BY spins DESC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':start' => $start->format('Y-m-d H:i:s'), ':end' => $end->format('Y-m-d H:i:s')]);
$rows = $stmt->fetchAll();

// Build ranking rows
$rankRows = [];
$rank = 0;
foreach ($rows as $r) {
    $rank++;
    $rankRows[] = [
        'Interval' => $interval,
        'Resource' => 'artists',
        'EntityId' => null,
        'Name' => (string)$r['name'],
        'PeriodStart' => $periodStart,
        'PeriodEnd' => $periodEnd,
        'RankNum' => $rank,
        'Score' => (float)$r['spins'],
        'Delta' => 0,
        'Meta' => null,
    ];
}

// Compute deltas vs previous period (simple: compare ranks by name)
function previousWindow(string $interval, DateTimeImmutable $start): array {
    if ($interval === 'daily') return ['start' => $start->sub(new DateInterval('P1D')), 'end' => $start];
    if ($interval === 'weekly') return ['start' => $start->sub(new DateInterval('P7D')), 'end' => $start];
    return ['start' => $start->sub(new DateInterval('P30D')), 'end' => $start];
}
$prev = previousWindow($interval, $start);
$prevStart = $prev['start']->format('Y-m-d');
$prevEnd = $prev['end']->format('Y-m-d');
$prevRanks = [];
try {
    $pr = $pdo->prepare("SELECT Name, RankNum FROM rankings WHERE Interval = :i AND Resource = 'artists' AND PeriodStart = :ps AND PeriodEnd = :pe");
    $pr->execute([':i' => $interval, ':ps' => $prevStart, ':pe' => $prevEnd]);
    foreach ($pr->fetchAll() as $row) { $prevRanks[$row['Name']] = (int)$row['RankNum']; }
} catch (\Throwable $e) { /* first run likely empty */ }
foreach ($rankRows as &$rr) {
    $name = $rr['Name'];
    if (isset($prevRanks[$name])) {
        $rr['Delta'] = (int)($prevRanks[$name] - $rr['RankNum']);
    }
}
unset($rr);

if ($args['dry']) {
    fwrite(STDOUT, json_encode(['interval' => $interval, 'start' => $periodStart, 'end' => $periodEnd, 'count' => count($rankRows)], JSON_PRETTY_PRINT)."\n");
    exit(0);
}

// Idempotent replace for window
$pdo->beginTransaction();
try {
    $del = $pdo->prepare("DELETE FROM rankings WHERE Interval = :i AND Resource = 'artists' AND PeriodStart = :ps AND PeriodEnd = :pe");
    $del->execute([':i' => $interval, ':ps' => $periodStart, ':pe' => $periodEnd]);
    $ins = $pdo->prepare("INSERT INTO rankings (Interval, Resource, EntityId, Name, PeriodStart, PeriodEnd, RankNum, Score, Delta, Meta, CreatedAt, UpdatedAt)
                          VALUES (:Interval, :Resource, :EntityId, :Name, :PeriodStart, :PeriodEnd, :RankNum, :Score, :Delta, :Meta, NOW(), NOW())");
    foreach ($rankRows as $rr) {
        $ins->execute([
            ':Interval' => $rr['Interval'],
            ':Resource' => $rr['Resource'],
            ':EntityId' => $rr['EntityId'],
            ':Name' => $rr['Name'],
            ':PeriodStart' => $rr['PeriodStart'],
            ':PeriodEnd' => $rr['PeriodEnd'],
            ':RankNum' => $rr['RankNum'],
            ':Score' => $rr['Score'],
            ':Delta' => $rr['Delta'],
            ':Meta' => $rr['Meta'],
        ]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Failed to write rankings: ".$e->getMessage()."\n");
    exit(3);
}

$logger->info('rankings_recompute_end', ['interval' => $interval, 'start' => $periodStart, 'end' => $periodEnd, 'count' => count($rankRows)]);

fwrite(STDOUT, "ok: interval={$interval} start={$periodStart} end={$periodEnd} count=".count($rankRows)."\n");
