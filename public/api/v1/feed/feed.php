<?php

/**
 * GET /api/v1/feed
 *
 * Returns personalized multi-tier social feed for authenticated user.
 *
 * Blends Tier 1 (50%), Tier 2 (30%), and Tier 3 (20%) content.
 * Applies visibility decay and excludes expired posts.
 *
 * Query Parameters:
 * - limit (int, default: 20) - Items per page
 * - offset (int, default: 0) - Pagination offset
 * - tier (string, optional) - Filter: all|tier1|tier2|tier3
 * - sort (string, default: 'engagement') - Sort: engagement|recent|trending
 *
 * Response: JSON array of posts with visibility metadata
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Feed\SocialFeedAlgorithmService;
use NGN\Lib\Feed\TrendingFeedService;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;

header('Content-Type: application/json');

try {
    // Authentication check
    $auth = Auth::verify();
    if (!$auth) {
        Response::error('Unauthorized', 401);
        exit;
    }

    $userId = $auth['user_id'];

    // Parameter validation
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 50)) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $tier = $_GET['tier'] ?? 'all';
    $sort = $_GET['sort'] ?? 'engagement';

    // Validate tier parameter
    if (!in_array($tier, ['all', 'tier1', 'tier2', 'tier3'])) {
        $tier = 'all';
    }

    // Validate sort parameter
    if (!in_array($sort, ['engagement', 'recent', 'trending'])) {
        $sort = 'engagement';
    }

    $config = Config::getInstance();
    $read = ConnectionFactory::read();

    // Build query based on tier filter
    $tierCondition = 'pvs.current_tier IN ("tier1", "tier2", "tier3")';
    if ($tier !== 'all') {
        $tierCondition = "pvs.current_tier = '" . $read->quote($tier, \PDO::PARAM_STR) . "'";
    }

    // Build sort clause
    $orderClause = match ($sort) {
        'recent' => 'p.created_at DESC',
        'trending' => 'pvs.ev_score_current DESC, p.created_at DESC',
        default => 'pvs.visibility_score DESC, pvs.ev_score_current DESC'
    };

    // Query posts with visibility info
    $stmt = $read->prepare("
        SELECT
            p.id,
            p.title,
            p.description,
            p.created_at,
            p.post_type,
            pvs.current_tier,
            pvs.visibility_score,
            pvs.ev_score_current,
            pvs.seed_impressions,
            pvs.tier1_impressions,
            pvs.tier2_impressions,
            pvs.tier3_impressions,
            ec.likes_count,
            ec.comments_count,
            ec.shares_count,
            ec.sparks_count,
            ec.sparks_total_amount,
            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_since_post
        FROM `ngn_2025`.`posts` p
        JOIN `ngn_2025`.`post_visibility_state` pvs ON p.id = pvs.post_id
        LEFT JOIN `ngn_2025`.`cdm_engagement_counts` ec ON p.id = ec.entity_id AND ec.entity_type = 'post'
        WHERE $tierCondition
        AND pvs.expired_at IS NULL
        AND p.deleted_at IS NULL
        ORDER BY $orderClause
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$limit, $offset]);
    $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countStmt = $read->prepare("
        SELECT COUNT(*) as total
        FROM `ngn_2025`.`posts` p
        JOIN `ngn_2025`.`post_visibility_state` pvs ON p.id = pvs.post_id
        WHERE $tierCondition
        AND pvs.expired_at IS NULL
        AND p.deleted_at IS NULL
    ");
    $countStmt->execute();
    $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
    $total = (int) $countResult['total'];

    // Format response
    $formattedPosts = [];
    foreach ($posts as $post) {
        $formattedPosts[] = [
            'id' => (int) $post['id'],
            'title' => $post['title'],
            'description' => $post['description'],
            'tier' => $post['current_tier'],
            'visibility_score' => round((float) $post['visibility_score'], 2),
            'ev_score' => round((float) $post['ev_score_current'], 2),
            'engagement_counts' => [
                'likes' => (int) $post['likes_count'],
                'comments' => (int) $post['comments_count'],
                'shares' => (int) $post['shares_count'],
                'sparks' => round((float) $post['sparks_count'], 2),
                'sparks_total' => round((float) $post['sparks_total_amount'], 2)
            ],
            'impressions' => [
                'seed' => (int) $post['seed_impressions'],
                'tier1' => (int) $post['tier1_impressions'],
                'tier2' => (int) $post['tier2_impressions'],
                'tier3' => (int) $post['tier3_impressions']
            ],
            'posted_at' => $post['created_at'],
            'hours_since_post' => (int) $post['hours_since_post']
        ];
    }

    // Calculate tier distribution
    $distributionStmt = $read->prepare("
        SELECT
            pvs.current_tier,
            COUNT(*) as count
        FROM `ngn_2025`.`post_visibility_state` pvs
        WHERE pvs.expired_at IS NULL
        GROUP BY pvs.current_tier
    ");
    $distributionStmt->execute();
    $distributionData = $distributionStmt->fetchAll(\PDO::FETCH_ASSOC);

    $distribution = ['tier1' => 0, 'tier2' => 0, 'tier3' => 0];
    foreach ($distributionData as $row) {
        if (isset($distribution[$row['current_tier']])) {
            $distribution[$row['current_tier']] = (int) $row['count'];
        }
    }

    Response::success([
        'posts' => $formattedPosts,
        'pagination' => [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $total
        ],
        'tier_distribution' => $distribution,
        'generated_at' => date('c')
    ]);
} catch (\Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
