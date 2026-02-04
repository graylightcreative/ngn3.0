<?php

/**
 * GET /api/v1/feed/post/{post_id}/seed-visibility
 *
 * Returns seed visibility distribution and engagement analytics for a post.
 *
 * Shows which users were selected for seed distribution and if they engaged.
 *
 * Response: JSON with seed analytics
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Feed\SeedVisibilityService;
use NGN\Lib\API\Response;

header('Content-Type: application/json');

try {
    // Get post_id from URL path
    $pathInfo = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    preg_match('/\/post\/(\d+)\/seed-visibility/', $pathInfo, $matches);

    if (!isset($matches[1])) {
        Response::error('Post ID required', 400);
        exit;
    }

    $postId = (int) $matches[1];

    // Optional limit parameter
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 50;

    $config = Config::getInstance();
    $seedVisibilityService = new SeedVisibilityService($config);

    // Get seed analytics
    $analytics = $seedVisibilityService->getSeedAnalytics($postId);

    if (empty($analytics)) {
        Response::error('No seed visibility data for this post', 404);
        exit;
    }

    // Get distribution detail
    $distribution = $seedVisibilityService->getSeedDistributionDetail($postId, $limit);

    // Format distribution
    $formattedDistribution = [];
    foreach ($distribution as $record) {
        $formattedDistribution[] = [
            'user_id' => (int) $record['user_id'],
            'seed_reason' => $record['seed_reason'],
            'user_genre' => $record['user_genre_affinity'],
            'post_genre' => $record['post_genre'],
            'engaged' => (bool) $record['user_engaged'],
            'engagement_type' => $record['engagement_type'],
            'shown_at' => $record['shown_at'],
            'engaged_at' => $record['engaged_at'],
            'hours_since_shown' => (int) $record['hours_since_shown']
        ];
    }

    // Calculate quality score (0-100)
    $qualityScore = 0;
    if ($analytics['total_shown'] > 0) {
        // Quality based on engagement rate and distribution
        $engagementRatio = $analytics['total_engaged'] / $analytics['total_shown'];
        // Assume 10-15% engagement is excellent for seed
        $qualityScore = min(100, ($engagementRatio / 0.15) * 100);
    }

    Response::success([
        'post_id' => (int) $postId,
        'seed_distribution' => [
            'total_shown' => (int) $analytics['total_shown'],
            'total_engaged' => (int) $analytics['total_engaged'],
            'engagement_rate' => round((float) $analytics['engagement_rate'], 2),
            'quality_score' => round($qualityScore, 1),
            'first_shown_at' => $analytics['first_shown_at'],
            'last_engaged_at' => $analytics['last_engaged_at'],
            'genres' => $analytics['genres'],
            'seed_reasons' => $analytics['seed_reasons']
        ],
        'engagement_breakdown' => $formattedDistribution,
        'predictions' => [
            'predicted_tier2_expansion' => ($analytics['engagement_rate'] ?? 0) > 10
        ],
        'generated_at' => date('c')
    ]);
} catch (\Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
