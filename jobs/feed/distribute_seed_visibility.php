<?php

/**
 * Distribute Seed Visibility
 *
 * Distributes 5% random non-follower exposure for new posts (seed phase).
 *
 * Logic:
 * 1. Find posts in seed phase (created in last 30 minutes)
 * 2. Get creator's primary genre
 * 3. Select 5% of users who follow genre but don't follow creator
 * 4. Record in feed_seed_visibility table
 * 5. Track impressions for seed success rate
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: */30 * * * * (every 30 minutes)
 * Command: php /path/to/jobs/feed/distribute_seed_visibility.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\Feed\SeedVisibilityService;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_seed_distribution.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting seed visibility distribution ===");

    $config = Config::getInstance();
    $seedVisibilityService = new SeedVisibilityService($config);
    $readConn = ConnectionFactory::read();

    // Get posts in seed phase (created in last 30 min, not yet seed-distributed)
    $stmt = $readConn->prepare("
        SELECT
            p.id as post_id,
            pvs.post_id as pvs_post_id,
            p.created_at
        FROM `ngn_2025`.`posts` p
        LEFT JOIN `ngn_2025`.`post_visibility_state` pvs ON p.id = pvs.post_id
        LEFT JOIN `ngn_2025`.`feed_seed_visibility` fsv ON p.id = fsv.post_id
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND (pvs.current_tier = 'seed' OR pvs.current_tier IS NULL)
        AND fsv.id IS NULL
        LIMIT 50
    ");
    $stmt->execute();
    $seedPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($seedPosts)) {
        logMessage("No posts in seed phase found");
        exit(0);
    }

    logMessage(sprintf("Found %d posts in seed phase", count($seedPosts)));

    $distributedCount = 0;
    $totalDistributed = 0;
    $failedCount = 0;

    foreach ($seedPosts as $post) {
        $postId = $post['post_id'];

        try {
            // Distribute seed visibility
            $result = $seedVisibilityService->distributeSeedVisibility($postId);

            if (isset($result['error'])) {
                logMessage(sprintf("Post %d: %s", $postId, $result['error']));
                $failedCount++;
                continue;
            }

            $distributed = $result['distributed'] ?? 0;

            if ($distributed > 0) {
                logMessage(sprintf(
                    "Post %d: Distributed to %d users (target: %d, genre: %s)",
                    $postId,
                    $distributed,
                    $result['target'],
                    $result['genre']
                ));
                $distributedCount++;
                $totalDistributed += $distributed;
            } else {
                logMessage(sprintf("Post %d: No users distributed", $postId));
                $failedCount++;
            }
        } catch (\Exception $e) {
            logMessage(sprintf("Post %d: Exception - %s", $postId, $e->getMessage()));
            $failedCount++;
            continue;
        }
    }

    logMessage(sprintf(
        "=== Seed distribution complete: %d posts distributed to %d users, %d failed ===",
        $distributedCount,
        $totalDistributed,
        $failedCount
    ));

    // Alert if distribution too low
    if ($totalDistributed < 30) {
        logMessage("WARNING: Seed distribution low (< 30 users)");
    }

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('Seed distribution job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
