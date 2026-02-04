<?php
/**
 * Entity Leaderboard History API
 * Endpoint: GET /api/v1/leaderboards/entity?type=artist&id=123&interval=monthly
 *
 * Returns leaderboard history and stats for a specific entity
 * Shows rank progression across periods (daily/weekly/monthly)
 * Includes best rank, average rank, trend data
 *
 * Query Parameters:
 * - type: 'artist' | 'label' (required)
 * - id: Entity ID (required)
 * - interval: 'daily' | 'weekly' | 'monthly' (default: monthly)
 * - periods: Number of historical periods to include (default: 12, max: 52)
 *
 * Response:
 * {
 *   entity: { id, name, slug, image_url },
 *   type: string,
 *   interval: string,
 *   current_rank: int,
 *   current_score: float,
 *   statistics: {
 *     best_rank: int,
 *     worst_rank: int,
 *     average_rank: float,
 *     times_in_top_10: int,
 *     times_in_top_50: int,
 *     total_appearances: int,
 *     total_periods: int
 *   },
 *   history: [
 *     { period_start, rank, score, rank_movement, trend }
 *   ],
 *   generated_at: ISO8601
 * }
 */

require_once dirname(__DIR__, 4) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

header('Content-Type: application/json');
header('Cache-Control: public, max-age=1800'); // Cache for 30 minutes

// Get query parameters
$type = $_GET['type'] ?? null;
$entityId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$interval = $_GET['interval'] ?? 'monthly';
$periods = isset($_GET['periods']) ? (int)$_GET['periods'] : 12;

// Validate required parameters
if (!$type || !$entityId) {
    http_response_code(400);
    echo json_encode(['error' => 'Required parameters: type, id']);
    exit;
}

// Validate optional parameters
$validTypes = ['artist', 'label'];
$validIntervals = ['daily', 'weekly', 'monthly'];

if (!in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type. Must be: ' . implode(', ', $validTypes)]);
    exit;
}

if (!in_array($interval, $validIntervals)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid interval. Must be: ' . implode(', ', $validIntervals)]);
    exit;
}

// Limit periods
$periods = min($periods, 52);
$periods = max($periods, 1);

try {
    $config = new Config();
    $pdoRankings = ConnectionFactory::named($config, 'rankings2025');
    $pdoDev = ConnectionFactory::named($config, 'dev');

    // Get entity details
    $entityTable = '`ngn_2025`.`cdm_' . $type . 's`';
    $entityStmt = $pdoDev->prepare("
        SELECT id, name, slug, image_url FROM $entityTable WHERE id = ? LIMIT 1
    ");
    $entityStmt->execute([$entityId]);
    $entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$entity) {
        http_response_code(404);
        echo json_encode(['error' => 'Entity not found']);
        exit;
    }

    // Get current rank and score
    $currentStmt = $pdoRankings->prepare("
        SELECT rank, score FROM `ngn_rankings_2025`.`ranking_items`
        WHERE entity_type = ? AND entity_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $currentStmt->execute([$type, $entityId]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    // Get historical ranking data
    $historyStmt = $pdoRankings->prepare("
        SELECT
            rw.window_start,
            rw.window_end,
            ri.rank,
            ri.score,
            ri.prev_rank,
            (COALESCE(ri.prev_rank, 999999) - ri.rank) as rank_movement
        FROM `ngn_rankings_2025`.`ranking_items` ri
        INNER JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
        WHERE ri.entity_type = ?
        AND ri.entity_id = ?
        AND rw.interval = ?
        ORDER BY rw.window_start DESC
        LIMIT ?
    ");
    $historyStmt->execute([$type, $entityId, $interval, $periods]);

    $history = [];
    $ranks = [];
    $inTop10 = 0;
    $inTop50 = 0;

    while ($row = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
        $rank = (int)$row['rank'];
        $ranks[] = $rank;

        if ($rank <= 10) $inTop10++;
        if ($rank <= 50) $inTop50++;

        $trend = 'static';
        if ($row['prev_rank'] !== null) {
            $movement = (int)$row['rank_movement'];
            if ($movement > 0) $trend = 'up';
            elseif ($movement < 0) $trend = 'down';
        }

        $history[] = [
            'period_start' => $row['window_start'],
            'period_end' => $row['window_end'],
            'rank' => $rank,
            'score' => (float)$row['score'],
            'rank_movement' => $row['rank_movement'],
            'trend' => $trend,
        ];
    }

    // Calculate statistics
    $statistics = [
        'best_rank' => !empty($ranks) ? min($ranks) : null,
        'worst_rank' => !empty($ranks) ? max($ranks) : null,
        'average_rank' => !empty($ranks) ? round(array_sum($ranks) / count($ranks), 1) : null,
        'times_in_top_10' => $inTop10,
        'times_in_top_50' => $inTop50,
        'total_appearances' => count($ranks),
        'total_periods' => $periods,
    ];

    http_response_code(200);
    echo json_encode([
        'entity' => [
            'id' => (int)$entity['id'],
            'name' => $entity['name'],
            'slug' => $entity['slug'],
            'image_url' => $entity['image_url'],
        ],
        'type' => $type,
        'ranking_interval' => $interval,
        'current_rank' => $current ? (int)$current['rank'] : null,
        'current_score' => $current ? (float)$current['score'] : null,
        'statistics' => $statistics,
        'history' => $history,
        'generated_at' => date('c'),
    ]);

} catch (\Throwable $e) {
    error_log("Entity leaderboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve entity leaderboard data',
        'type' => $type,
        'id' => $entityId,
    ]);
}
