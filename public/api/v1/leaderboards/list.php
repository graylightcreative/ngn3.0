<?php
/**
 * Public Leaderboards API
 * Endpoint: GET /api/v1/leaderboards/list?type=artists&interval=monthly&category=hip-hop&limit=50
 *
 * Returns ranked list of artists, labels, or genres
 * Supports filtering by category (genre, location, etc)
 * Results are cached for performance
 *
 * Query Parameters:
 * - type: 'artists' | 'labels' | 'genres' (default: artists)
 * - interval: 'daily' | 'weekly' | 'monthly' (default: monthly)
 * - category: Genre slug or category filter (optional)
 * - limit: Number of entries to return, max 500 (default: 100)
 * - include_trending: boolean - include top climbers
 *
 * Response:
 * {
 *   leaderboard_type: string,
 *   ranking_interval: string,
 *   category: string|null,
 *   window: { interval, start, end },
 *   entries: [
 *     { rank, id, name, slug, image_url, score, previous_rank, rank_movement, trend, genres }
 *   ],
 *   total_entries: int,
 *   from_cache: boolean,
 *   generated_at: ISO8601
 * }
 */

require_once dirname(__DIR__, 4) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Rankings\LeaderboardCalculator;

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Get query parameters
$type = $_GET['type'] ?? 'artists';
$interval = $_GET['interval'] ?? 'monthly';
$category = $_GET['category'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$includeTrending = isset($_GET['include_trending']) ? filter_var($_GET['include_trending'], FILTER_VALIDATE_BOOLEAN) : false;

// Validate parameters
$validTypes = ['artists', 'labels', 'genres'];
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

// Limit max entries to prevent abuse
$limit = min($limit, 500);
$limit = max($limit, 1);

try {
    $config = new Config();
    $leaderboardService = new LeaderboardCalculator($config);

    // Get main leaderboard
    $leaderboard = $leaderboardService->getLeaderboard($type, $interval, $category, $limit);

    // Optional: Include trending entries
    if ($includeTrending) {
        $leaderboard['trending'] = $leaderboardService->getTrendingEntries($type, $interval, 10);
    }

    http_response_code(200);
    echo json_encode($leaderboard);

} catch (\Throwable $e) {
    error_log("Leaderboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve leaderboard',
        'type' => $type,
        'interval' => $interval,
    ]);
}
