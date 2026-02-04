<?php
/**
 * Trending Leaderboard API
 * Endpoint: GET /api/v1/leaderboards/trending?type=artists&interval=weekly&limit=20
 *
 * Returns biggest climbers and trending entries for a given interval
 * Shows rank movement and climb velocity
 *
 * Query Parameters:
 * - type: 'artists' | 'labels' (default: artists)
 * - interval: 'daily' | 'weekly' | 'monthly' (default: weekly)
 * - limit: Number of entries, max 100 (default: 20)
 *
 * Response:
 * {
 *   type: string,
 *   interval: string,
 *   trending_entries: [
 *     {
 *       rank: int,
 *       id: int,
 *       name: string,
 *       slug: string,
 *       image_url: string,
 *       score: float,
 *       previous_rank: int,
 *       rank_jump: int (positive = climbed that many spots)
 *     }
 *   ],
 *   generated_at: ISO8601
 * }
 */

require_once dirname(__DIR__, 4) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Rankings\LeaderboardCalculator;

header('Content-Type: application/json');
header('Cache-Control: public, max-age=1800'); // Cache for 30 minutes

// Get query parameters
$type = $_GET['type'] ?? 'artists';
$interval = $_GET['interval'] ?? 'weekly';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

// Validate parameters
$validTypes = ['artists', 'labels'];
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

// Limit max entries
$limit = min($limit, 100);
$limit = max($limit, 1);

try {
    $config = new Config();
    $leaderboardService = new LeaderboardCalculator($config);

    // Get trending entries
    $trending = $leaderboardService->getTrendingEntries($type, $interval, $limit);

    http_response_code(200);
    echo json_encode([
        'type' => $type,
        'ranking_interval' => $interval,
        'trending_entries' => $trending,
        'total_entries' => count($trending),
        'generated_at' => date('c'),
    ]);

} catch (\Throwable $e) {
    error_log("Trending leaderboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve trending data',
        'type' => $type,
        'interval' => $interval,
    ]);
}
