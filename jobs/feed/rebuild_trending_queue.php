<?php

/**
 * Rebuild Trending Queue
 *
 * Rebuilds the global trending queue (Tier 3) based on current EV and NGN scores.
 *
 * Moneyball Gates:
 * - EV > 150
 * - Creator NGN Score > 30
 * - Creator is verified
 * - Post not expired (< 48h old)
 *
 * Limits trending to top 50 posts.
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: 0 * * * * (every hour)
 * Command: php /path/to/jobs/feed/rebuild_trending_queue.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\Feed\TrendingFeedService;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_trending_rebuild.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting trending queue rebuild ===");

    $config = Config::getInstance();
    $trendingService = new TrendingFeedService($config);

    // Rebuild the trending queue
    $postsAdded = $trendingService->rebuildTrendingQueue();

    logMessage(sprintf("Trending queue rebuilt with %d posts", $postsAdded));

    // Get recent changes for analytics
    $recentChanges = $trendingService->getRecentTrendingChanges();

    if (!empty($recentChanges)) {
        logMessage(sprintf("Recent trending changes: %d posts", count($recentChanges)));
        foreach ($recentChanges as $change) {
            logMessage(sprintf(
                "  Post %d: Status=%s, EV=%.2f, Rank=%d",
                $change['post_id'],
                $change['status'],
                $change['current_ev_score'],
                $change['trending_rank'] ?? 'N/A'
            ));
        }
    }

    // Archive old trending data
    $archivedCount = $trendingService->archiveExpiredTrending();
    if ($archivedCount > 0) {
        logMessage(sprintf("Archived %d expired trending posts", $archivedCount));
    }

    // Get top trending
    $topTrending = $trendingService->getTrendingPosts(10);
    if (!empty($topTrending)) {
        logMessage(sprintf("Top %d trending posts:", count($topTrending)));
        foreach ($topTrending as $index => $post) {
            logMessage(sprintf(
                "  #%d Post %d: EV=%.2f, Impressions=%d",
                $index + 1,
                $post['post_id'],
                $post['current_ev_score'],
                $post['total_impressions'] ?? 0
            ));
        }
    }

    logMessage("=== Trending queue rebuild complete ===");

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('Trending rebuild job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
