<?php

/**
 * GET /api/v1/feed/trending
 *
 * Returns global trending posts (Tier 3).
 *
 * Moneyball-gated: Only posts with EV > 150 and creator NGN > 30
 *
 * Query Parameters:
 * - limit (int, default: 10) - Top N trending
 * - time_window (string, default: '24hours') - hour|6hours|24hours
 *
 * Response: JSON array of trending posts with metrics
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Feed\TrendingFeedService;
use NGN\Lib\API\Response;

header('Content-Type: application/json');

try {
    // Parameter validation
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 50)) : 10;
    $timeWindow = $_GET['time_window'] ?? '24hours';

    // Validate time window
    if (!in_array($timeWindow, ['hour', '6hours', '24hours'])) {
        $timeWindow = '24hours';
    }

    $config = Config::getInstance();
    $trendingService = new TrendingFeedService($config);

    // Get trending posts
    $trendingPosts = $trendingService->getTrendingPosts($limit, [
        'time_window' => $timeWindow
    ]);

    // Format response
    $formattedTrending = [];
    foreach ($trendingPosts as $post) {
        $formattedTrending[] = [
            'rank' => (int) $post['trending_rank'] ?? 0,
            'post_id' => (int) $post['post_id'],
            'title' => $post['title'] ?? 'Untitled',
            'creator_ngn_score' => round((float) $post['creator_ngn_score'], 2),
            'current_ev' => round((float) $post['current_ev_score'], 2),
            'trending_since' => $post['trending_started_at'],
            'hours_trending' => (int) $post['hours_trending'] ?? 0,
            'impressions' => [
                'total' => (int) $post['total_impressions'] ?? 0,
                'tier3' => (int) $post['tier3_impressions'] ?? 0
            ],
            'post_created_at' => $post['post_created_at']
        ];
    }

    Response::success([
        'trending' => $formattedTrending,
        'total_posts' => count($formattedTrending),
        'time_window' => $timeWindow,
        'generated_at' => date('c'),
        'gates' => [
            'ev_threshold' => 150,
            'ngn_score_threshold' => 30,
            'verified_only' => true
        ]
    ]);
} catch (\Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
