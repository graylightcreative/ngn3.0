<?php

/**
 * Decay Visibility Scores
 *
 * Applies time-based decay to post visibility scores over 48-hour window.
 *
 * Decay Formula: visibility_score = 100 × e^(-0.693 × hours_since_post / 24)
 * At 24h: ~50% visibility
 * At 48h: ~25% visibility
 * At 72h+: Marked as expired
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: 0 * * * * (every hour)
 * Command: php /path/to/jobs/feed/decay_visibility_scores.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\Feed\SocialFeedAlgorithmService;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_decay.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting visibility decay calculation ===");

    $config = Config::getInstance();
    $feedAlgorithmService = new SocialFeedAlgorithmService($config);

    // Get posts needing tier evaluation (decay check)
    $posts = $feedAlgorithmService->getPostsNeedingTierEvaluation(500);

    if (empty($posts)) {
        logMessage("No posts found for decay calculation");
        exit(0);
    }

    logMessage(sprintf("Found %d posts for decay calculation", count($posts)));

    $decayedCount = 0;
    $expiredCount = 0;

    foreach ($posts as $post) {
        $postId = $post['post_id'];

        try {
            // Apply decay
            $isExpired = $feedAlgorithmService->decayVisibilityScore($postId);

            $decayedCount++;

            if ($isExpired) {
                $expiredCount++;
                logMessage(sprintf(
                    "Post %d expired (created: %s, hours ago: %.2f)",
                    $postId,
                    $post['created_at'],
                    (time() - strtotime($post['created_at'])) / 3600
                ));
            }
        } catch (\Exception $e) {
            logMessage(sprintf("Post %d: Error applying decay - %s", $postId, $e->getMessage()));
            continue;
        }
    }

    // Expire old posts (>48h)
    $oldExpiredCount = $feedAlgorithmService->expireOldPosts();

    logMessage(sprintf(
        "=== Decay calculation complete: %d decayed, %d newly expired, %d old posts expired ===",
        $decayedCount,
        $expiredCount,
        $oldExpiredCount
    ));

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('Decay calculation job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
