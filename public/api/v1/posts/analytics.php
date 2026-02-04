<?php

/**
 * Post Analytics Endpoint
 * GET /api/v1/posts/{id}/analytics
 * Returns engagement source breakdown (authenticated vs anonymous)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Analytics\PostAnalyticsService;
use NGN\Lib\API\Response;
use NGN\Lib\API\Auth;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

header('Content-Type: application/json');

try {
    // Extract post ID from URL
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $postIdIndex = array_search('posts', $pathParts);
    if ($postIdIndex === false || !isset($pathParts[$postIdIndex + 1])) {
        Response::error('Invalid post ID', 400);
        exit;
    }

    $postId = (int) $pathParts[$postIdIndex + 1];

    if ($postId <= 0) {
        Response::error('Invalid post ID', 400);
        exit;
    }

    // Verify authentication (creator or admin can view their own analytics)
    $auth = Auth::verify();
    if (!$auth) {
        Response::error('Unauthorized', 401);
        exit;
    }

    $config = Config::getInstance();
    $readConnection = ConnectionFactory::read();

    // Verify post exists and user owns it or is admin
    $stmt = $readConnection->prepare(
        'SELECT id, author_id AS creator_id FROM `ngn_2025`.`posts` WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        Response::error('Post not found', 404);
        exit;
    }

    // Check authorization
    $isAdmin = $auth['role'] === 'admin';
    $isOwner = $post['creator_id'] == $auth['user_id'];

    if (!$isAdmin && !$isOwner) {
        Response::error('Forbidden', 403);
        exit;
    }

    // Get analytics
    $analyticsService = new PostAnalyticsService($config);
    $analytics = $analyticsService->getPostAnalytics($postId);

    if (!$analytics) {
        // Return empty analytics if none exist yet
        $analytics = [
            'post_id' => $postId,
            'total_authenticated_engagement' => 0,
            'total_anonymous_engagement' => 0,
            'authentication_rate' => 0,
            'fraud_suspicion_score' => 0
        ];
    }

    // Get engagement source breakdown
    $breakdown = $analyticsService->getEngagementSourceBreakdown($postId);

    // Get fraud flags if requested
    $fraudFlags = [];
    if (isset($_GET['include_fraud_flags']) && $_GET['include_fraud_flags'] === 'true') {
        $fraudFlags = $analyticsService->getFraudFlags($postId);
    }

    // Get daily analytics if date range provided
    $dailyAnalytics = [];
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $startDate = $_GET['start_date'];
        $endDate = $_GET['end_date'];

        // Validate dates
        if (strtotime($startDate) && strtotime($endDate)) {
            $dailyAnalytics = $analyticsService->getDailyAnalytics($postId, $startDate, $endDate);
        }
    }

    Response::success([
        'post_id' => $postId,
        'analytics' => [
            'total_authenticated_engagement' => (int) $analytics['total_authenticated_engagement'],
            'total_anonymous_engagement' => (int) $analytics['total_anonymous_engagement'],
            'authentication_rate' => (float) $analytics['authentication_rate'],
            'fraud_suspicion_score' => (float) $analytics['fraud_suspicion_score'],
            'last_updated' => $analytics['updated_at'] ?? null
        ],
        'engagement_breakdown' => $breakdown,
        'fraud_flags' => $fraudFlags,
        'daily_analytics' => $dailyAnalytics
    ]);
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
