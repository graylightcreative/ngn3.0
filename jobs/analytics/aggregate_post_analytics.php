<?php

/**
 * Post Analytics - Aggregate Daily Analytics Cron Job
 * Rolls up event-level data into daily summaries
 * Schedule: Daily at 1 AM
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;

$config = Config::getInstance();
$logger = LoggerFactory::getLogger('analytics');
$readConnection = ConnectionFactory::read();
$writeConnection = ConnectionFactory::write();

$startTime = microtime(true);
$aggregatedDays = 0;
$errors = 0;

try {
    $logger->info('Starting post analytics aggregation');

    // Get yesterday's date (since today may still have incoming events)
    $targetDate = date('Y-m-d', strtotime('-1 day'));

    // Get all posts with events on target date
    $stmt = $readConnection->prepare(
        'SELECT DISTINCT post_id FROM post_engagement_events
         WHERE DATE(created_at) = ?'
    );
    $stmt->execute([$targetDate]);
    $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $logger->info('Found posts to aggregate', ['post_count' => count($postIds), 'date' => $targetDate]);

    foreach ($postIds as $postId) {
        try {
            // Get aggregated stats for this post on this date
            $stmt = $readConnection->prepare(
                'SELECT
                    COUNT(CASE WHEN engagement_type = "view" AND is_authenticated = 1 THEN 1 END) as authenticated_views,
                    COUNT(CASE WHEN engagement_type = "view" AND is_authenticated = 0 THEN 1 END) as anonymous_views,
                    COUNT(CASE WHEN engagement_type = "like" AND is_authenticated = 1 THEN 1 END) as authenticated_likes,
                    COUNT(CASE WHEN engagement_type = "like" AND is_authenticated = 0 THEN 1 END) as anonymous_likes,
                    COUNT(CASE WHEN engagement_type = "share" AND is_authenticated = 1 THEN 1 END) as authenticated_shares,
                    COUNT(CASE WHEN engagement_type = "share" AND is_authenticated = 0 THEN 1 END) as anonymous_shares,
                    COUNT(CASE WHEN engagement_type = "comment" AND is_authenticated = 1 THEN 1 END) as authenticated_comments,
                    COUNT(CASE WHEN engagement_type = "comment" AND is_authenticated = 0 THEN 1 END) as anonymous_comments
                 FROM post_engagement_events
                 WHERE post_id = ? AND DATE(created_at) = ?'
            );
            $stmt->execute([$postId, $targetDate]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalAuthenticated = $stats['authenticated_views'] +
                                 $stats['authenticated_likes'] +
                                 $stats['authenticated_shares'] +
                                 $stats['authenticated_comments'];

            $totalAnonymous = $stats['anonymous_views'] +
                             $stats['anonymous_likes'] +
                             $stats['anonymous_shares'] +
                             $stats['anonymous_comments'];

            $totalEngagement = $totalAuthenticated + $totalAnonymous;
            $authenticationRate = $totalEngagement > 0 ? round(($totalAuthenticated / $totalEngagement) * 100, 2) : 0;

            // Get fraud score from latest analytics
            $stmt = $readConnection->prepare(
                'SELECT fraud_suspicion_score FROM post_engagement_analytics WHERE post_id = ?'
            );
            $stmt->execute([$postId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $fraudScore = (float) ($result['fraud_suspicion_score'] ?? 0);

            // Insert or update daily analytics
            $writeConnection->prepare(
                'INSERT INTO post_analytics_daily (
                    post_id, date_key, authenticated_views, anonymous_views,
                    authenticated_likes, anonymous_likes, authenticated_shares, anonymous_shares,
                    authenticated_comments, anonymous_comments, authentication_rate,
                    fraud_suspicion_score, authenticated_engagements, anonymous_engagements
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                authenticated_views = VALUES(authenticated_views),
                anonymous_views = VALUES(anonymous_views),
                authenticated_likes = VALUES(authenticated_likes),
                anonymous_likes = VALUES(anonymous_likes),
                authenticated_shares = VALUES(authenticated_shares),
                anonymous_shares = VALUES(anonymous_shares),
                authenticated_comments = VALUES(authenticated_comments),
                anonymous_comments = VALUES(anonymous_comments),
                authentication_rate = VALUES(authentication_rate),
                fraud_suspicion_score = VALUES(fraud_suspicion_score),
                authenticated_engagements = VALUES(authenticated_engagements),
                anonymous_engagements = VALUES(anonymous_engagements),
                updated_at = NOW()'
            )->execute([
                $postId, $targetDate,
                $stats['authenticated_views'],
                $stats['anonymous_views'],
                $stats['authenticated_likes'],
                $stats['anonymous_likes'],
                $stats['authenticated_shares'],
                $stats['anonymous_shares'],
                $stats['authenticated_comments'],
                $stats['anonymous_comments'],
                $authenticationRate,
                $fraudScore,
                $totalAuthenticated,
                $totalAnonymous
            ]);

            $aggregatedDays++;
        } catch (Exception $e) {
            $logger->error('Error aggregating post analytics', [
                'post_id' => $postId,
                'date' => $targetDate,
                'error' => $e->getMessage()
            ]);
            $errors++;
        }
    }

    // Clean up old engagement events (keep for 90 days)
    $cutoffDate = date('Y-m-d', strtotime('-90 days'));
    $stmt = $writeConnection->prepare(
        'DELETE FROM post_engagement_events WHERE DATE(created_at) < ?'
    );
    $stmt->execute([$cutoffDate]);
    $deletedEvents = $stmt->rowCount();

    $executionTime = round(microtime(true) - $startTime, 2);

    $logger->info('Post analytics aggregation completed', [
        'execution_time' => $executionTime,
        'aggregated_days' => $aggregatedDays,
        'deleted_old_events' => $deletedEvents,
        'errors' => $errors
    ]);
} catch (Exception $e) {
    $logger->error('Fatal error in post analytics aggregation', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
