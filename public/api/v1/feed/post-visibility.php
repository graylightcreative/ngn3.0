<?php

/**
 * GET /api/v1/feed/post/{post_id}/visibility
 *
 * Returns visibility state and tier info for a specific post.
 *
 * Shows current tier, visibility decay, EV score, and expansion eligibility.
 *
 * Response: JSON with detailed visibility information
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Feed\SocialFeedAlgorithmService;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\API\Response;

header('Content-Type: application/json');

try {
    // Get post_id from URL path
    $pathInfo = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    preg_match('/\/post\/(\d+)\/visibility/', $pathInfo, $matches);

    if (!isset($matches[1])) {
        Response::error('Post ID required', 400);
        exit;
    }

    $postId = (int) $matches[1];

    $config = Config::getInstance();
    $feedAlgorithmService = new SocialFeedAlgorithmService($config);
    $read = ConnectionFactory::read();

    // Get post visibility state
    $visibilityState = $feedAlgorithmService->getVisibilityState($postId);

    if (!$visibilityState) {
        Response::error('Post not found', 404);
        exit;
    }

    // Get post info
    $postStmt = $read->prepare("
        SELECT id, title, created_at FROM `ngn_2025`.`posts` WHERE id = ?
    ");
    $postStmt->execute([$postId]);
    $postInfo = $postStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$postInfo) {
        Response::error('Post not found', 404);
        exit;
    }

    // Calculate decay percentage
    $hoursOld = (time() - strtotime($postInfo['created_at'])) / 3600;
    $decayPercentage = round((($visibilityState['visibility_score'] ?? 0) / 100) * 100, 1);

    // Check expansion eligibility
    $thresholdInfo = $feedAlgorithmService->checkTierExpansionThresholds($postId);

    // Format response
    Response::success([
        'post_id' => (int) $postId,
        'post_title' => $postInfo['title'],
        'current_tier' => $visibilityState['current_tier'],
        'visibility_score' => round((float) $visibilityState['visibility_score'], 2),
        'visibility_decay_percent' => $decayPercentage,
        'ev_score' => round((float) $visibilityState['ev_score_current'], 2),
        'ev_thresholds' => [
            'tier2_threshold' => round((float) $visibilityState['ev_score_tier2_threshold'], 2),
            'tier3_threshold' => round((float) $visibilityState['ev_score_tier3_threshold'], 2)
        ],
        'expansion_eligibility' => [
            'should_expand_tier2' => $thresholdInfo['should_expand_tier2'] ?? false,
            'should_expand_tier3' => $thresholdInfo['should_expand_tier3'] ?? false
        ],
        'tier_history' => [
            'tier1_expanded_at' => $visibilityState['tier1_expanded_at'],
            'tier2_expanded_at' => $visibilityState['tier2_expanded_at'],
            'tier3_expanded_at' => $visibilityState['tier3_expanded_at']
        ],
        'impressions' => [
            'seed' => (int) $visibilityState['seed_impressions'],
            'tier1' => (int) $visibilityState['tier1_impressions'],
            'tier2' => (int) $visibilityState['tier2_impressions'],
            'tier3' => (int) $visibilityState['tier3_impressions']
        ],
        'post_age' => [
            'created_at' => $postInfo['created_at'],
            'hours_old' => round($hoursOld, 1),
            'will_expire_at' => $visibilityState['expired_at'] ?? date('c', strtotime('+48 hours', strtotime($postInfo['created_at'])))
        ],
        'promotion' => [
            'has_paid_promotion' => (bool) $visibilityState['has_paid_promotion'],
            'paid_promotion_type' => $visibilityState['paid_promotion_type']
        ],
        'generated_at' => date('c')
    ]);
} catch (\Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
