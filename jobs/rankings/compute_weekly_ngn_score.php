<?php

// This script computes and stores artist rankings for the weekly NGN chart.
// It implements the scoring model S(a,w) = Σ_f [ w_f * T_f(a,w) ]
// Factors, normalization, and capping are applied dynamically.

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use NGN\Lib\DB\ConnectionFactory;

// --- Configuration ---
$logFile = __DIR__ . '/../../storage/logs/compute_rankings_weekly.log';
$factorsJsonPath = __DIR__ . '/../../docs/Factors.json';

// --- Setup Logger ---
try {
    $logger = new Logger('compute_rankings_weekly');
    $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));

    $config = new Config();
    
    // Connections
    $pdoPrimary = ConnectionFactory::read($config);
    $pdoRankings = ConnectionFactory::named($config, 'rankings2025');
    $pdoSpins = ConnectionFactory::named($config, 'spins2025');
    $pdoSmr = ConnectionFactory::named($config, 'smrrankings');

    // Load Weights from Factors.json
    if (!is_file($factorsJsonPath)) {
        throw new \RuntimeException("Factors.json not found at: {$factorsJsonPath}");
    }
    $factorsJson = file_get_contents($factorsJsonPath);
    $factorsConfig = json_decode($factorsJson, true);
    if ($factorsConfig === null) {
        throw new \RuntimeException("Failed to parse Factors.json.");
    }
    $weights = $factorsConfig['weights'] ?? [];

} catch (\Throwable $e) {
    error_log("Ranking calculation job setup error: " . $e->getMessage());
    exit("Ranking calculation job setup failed: " . $e->getMessage());
}

$logger->info("Ranking calculation job started for interval: weekly.");

try {
    // Period: Last full week (Monday to Sunday)
    $periodEnd = (new DateTime('last Sunday'))->format('Y-m-d');
    $periodStart = (new DateTime('last Sunday - 6 days'))->format('Y-m-d');
    $logger->info("Period: $periodStart to $periodEnd");

    // 1. Fetch Active Artists
    $logger->info("Fetching active artists...");
    $artistStmt = $pdoPrimary->prepare("
        SELECT a.id, a.name 
        FROM artists a
        JOIN users u ON a.user_id = u.id
        WHERE u.role_id = 3 AND u.status = 'active'
        LIMIT 1000
    ");
    $artistStmt->execute();
    $artists = $artistStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($artists)) {
        $logger->warning("No active artists found.");
        exit("No active artists found.\n");
    }

    $artistIds = array_map('intval', array_column($artists, 'id'));
    $artistNames = array_column($artists, 'name');
    
    $idPlaceholders = implode(',', array_fill(0, count($artistIds), '?'));
    $namePlaceholders = implode(',', array_fill(0, count($artistNames), '?'));

    // 2. Fetch Metrics
    
    // SMR Scores (by name)
    $logger->info("Fetching SMR scores...");
    $smrStmt = $pdoSmr->prepare("
        SELECT artist, SUM(tws) as score 
        FROM smr_chart 
        WHERE artist IN ($namePlaceholders) AND window_date BETWEEN ? AND ?
        GROUP BY artist
    ");
    $smrStmt->execute(array_merge($artistNames, [$periodStart, $periodEnd]));
    $smrScores = $smrStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Spins (by artist_id)
    $logger->info("Fetching spins...");
    $spinsStmt = $pdoSpins->prepare("
        SELECT artist_id, COUNT(*) as cnt 
        FROM station_spins 
        WHERE artist_id IN ($idPlaceholders) AND played_at BETWEEN ? AND ?
        GROUP BY artist_id
    ");
    $spinsStmt->execute(array_merge($artistIds, [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']));
    $spinsCounts = $spinsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Plays (History tracks by artist_id)
    $logger->info("Fetching plays...");
    $playsStmt = $pdoPrimary->prepare("
        SELECT t.artist_id, COUNT(h.id) as cnt
        FROM history h
        JOIN tracks t ON h.entity_id = t.id
        WHERE h.entity_type = 'track' AND t.artist_id IN ($idPlaceholders) AND h.occurred_at BETWEEN ? AND ?
        GROUP BY t.artist_id
    ");
    $playsStmt->execute(array_merge($artistIds, [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']));
    $playsCounts = $playsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Video Views (entity_id/entity_type)
    $logger->info("Fetching video views...");
    $viewsStmt = $pdoPrimary->prepare("
        SELECT entity_id, SUM(view_count) as cnt
        FROM videos
        WHERE entity_type = 'artist' AND entity_id IN ($idPlaceholders) AND created_at BETWEEN ? AND ?
        GROUP BY entity_id
    ");
    $viewsStmt->execute(array_merge($artistIds, [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']));
    $viewsCounts = $viewsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Posts (entity_id/entity_type)
    $logger->info("Fetching posts...");
    $postsStmt = $pdoPrimary->prepare("
        SELECT entity_id, COUNT(*) as cnt
        FROM posts
        WHERE entity_type = 'artist' AND entity_id IN ($idPlaceholders) AND created_at BETWEEN ? AND ?
        GROUP BY entity_id
    ");
    $postsStmt->execute(array_merge($artistIds, [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']));
    $postsCounts = $postsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // 3. Process and Score
    $logger->info("Calculating scores...");
    $processed = [];
    $metricPools = ['spins' => [], 'plays' => [], 'views' => [], 'posts' => [], 'smr' => []];

    foreach ($artists as $a) {
        $id = (int)$a['id'];
        $name = $a['name'];

        $metrics = [
            'spins' => (float)($spinsCounts[$id] ?? 0),
            'plays' => (float)($playsCounts[$id] ?? 0),
            'views' => (float)($viewsCounts[$id] ?? 0),
            'posts' => (float)($postsCounts[$id] ?? 0),
            'smr' => (float)($smrScores[$name] ?? 0),
        ];

        $normalized = [
            'spins' => $metrics['spins'],
            'plays' => log1p($metrics['plays']),
            'views' => log1p($metrics['views']),
            'posts' => log1p($metrics['posts']),
            'smr' => $metrics['smr'],
        ];

        foreach ($metricPools as $k => &$pool) { $pool[] = $normalized[$k]; }

        $processed[] = [
            'id' => $id,
            'name' => $name,
            'normalized' => $normalized
        ];
    }

    // Caps
    $percentile = function(array $data, float $p): float {
        if (empty($data)) return 0.0;
        sort($data);
        $idx = (int)max(0, floor($p * (count($data) - 1)));
        return (float)$data[$idx];
    };

    $caps = [];
    foreach ($metricPools as $k => $pool) { $caps[$k] = $percentile($pool, 0.98); }
    $caps['smr'] = $percentile($metricPools['smr'], 0.95);

    $finalItems = [];
    foreach ($processed as $p) {
        $score = 0;
        $score += min($p['normalized']['spins'], $caps['spins']) * ($weights['spins'] ?? 0.60);
        $score += min($p['normalized']['plays'], $caps['plays']) * ($weights['plays'] ?? 0.20);
        $score += min($p['normalized']['views'], $caps['views']) * ($weights['views'] ?? 0.05);
        $score += min($p['normalized']['posts'], $caps['posts']) * ($weights['posts'] ?? 0.05);
        $score += min($p['normalized']['smr'], $caps['smr']) * ($weights['smr'] ?? 1.0);

        $finalItems[] = [
            'id' => $p['id'],
            'score' => round($score, 4)
        ];
    }

    // Rank
    usort($finalItems, fn($a, $b) => $b['score'] <=> $a['score']);
    foreach ($finalItems as $idx => &$item) { $item['rank'] = $idx + 1; }
    unset($item);

    // 4. Persist
    $logger->info("Persisting rankings...");
    $pdoRankings->beginTransaction();

    $windowStmt = $pdoRankings->prepare("
        INSERT INTO `ranking_windows` (`interval`, `window_start`, `window_end`)
        VALUES ('weekly', :start, :end)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");
    $windowStmt->execute([':start' => $periodStart, ':end' => $periodEnd]);
    $windowId = (int)$pdoRankings->lastInsertId();

    $prevWindowIdStmt = $pdoRankings->prepare("
        SELECT id FROM `ranking_windows` 
        WHERE `interval` = 'weekly' AND `window_end` = DATE_SUB(:start, INTERVAL 1 DAY) 
        LIMIT 1
    ");
    $prevWindowIdStmt->execute([':start' => $periodStart]);
    $prevWindowId = $prevWindowIdStmt->fetchColumn();
    
    $prevRanks = [];
    if ($prevWindowId) {
        $prevRanks = $pdoRankings->query("SELECT entity_id, `rank` FROM `ranking_items` WHERE `window_id` = $prevWindowId AND `entity_type` = 'artist'")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    $insertStmt = $pdoRankings->prepare("
        INSERT INTO `ranking_items` (`window_id`, `entity_type`, `entity_id`, `rank`, `score`, `prev_rank`)
        VALUES (:wid, 'artist', :eid, :rank, :score, :prev)
        ON DUPLICATE KEY UPDATE `rank` = :rank_u, `score` = :score_u, `prev_rank` = :prev_u
    ");

    foreach ($finalItems as $item) {
        $prev = $prevRanks[$item['id']] ?? null;
        $insertStmt->execute([
            ':wid' => $windowId,
            ':eid' => $item['id'],
            ':rank' => $item['rank'],
            ':score' => $item['score'],
            ':prev' => $prev,
            ':rank_u' => $item['rank'],
            ':score_u' => $item['score'],
            ':prev_u' => $prev
        ]);
    }

    $pdoRankings->commit();
    $logger->info(sprintf("Success. Persisted %d rankings to window %d.", count($finalItems), $windowId));

} catch (\Throwable $e) {
    if (isset($pdoRankings) && $pdoRankings->inTransaction()) { $pdoRankings->rollBack(); }
    $logger->critical("Critical error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
