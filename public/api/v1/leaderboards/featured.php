<?php
/**
 * Featured Leaderboard Entries API
 * Endpoint: GET /api/v1/leaderboards/featured?interval=monthly&limit=12
 *
 * Returns curated featured entries:
 * - Top performers
 * - Biggest climbers
 * - New entries
 * - Editor picks
 * - Trending now
 *
 * Used for homepage/featured sections
 *
 * Query Parameters:
 * - interval: 'daily' | 'weekly' | 'monthly' (default: monthly)
 * - limit: Number of entries, max 50 (default: 12)
 *
 * Response:
 * {
 *   featured_entries: [
 *     {
 *       type: 'artists' | 'labels' | 'genres',
 *       rank: int,
 *       entity: { id, name, slug, image_url, genres },
 *       featured_reason: 'top_performer' | 'biggest_climber' | 'new_entry' | 'trending' | 'editor_pick'
 *     }
 *   ],
 *   total_entries: int,
 *   interval: string,
 *   generated_at: ISO8601
 * }
 */

require_once dirname(__DIR__, 4) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Rankings\LeaderboardCalculator;

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Get query parameters
$interval = $_GET['interval'] ?? 'monthly';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;

// Validate parameters
$validIntervals = ['daily', 'weekly', 'monthly'];

if (!in_array($interval, $validIntervals)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid interval. Must be: ' . implode(', ', $validIntervals)]);
    exit;
}

// Limit max entries
$limit = min($limit, 50);
$limit = max($limit, 1);

try {
    $config = new Config();
    $leaderboardService = new LeaderboardCalculator($config);

    // Get featured entries
    $featured = $leaderboardService->getFeaturedEntries($interval, $limit);

    http_response_code(200);
    echo json_encode([
        'featured_entries' => $featured,
        'total_entries' => count($featured),
        'ranking_interval' => $interval,
        'generated_at' => date('c'),
    ]);

} catch (\Throwable $e) {
    error_log("Featured leaderboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve featured entries',
        'interval' => $interval,
    ]);
}
