<?php

/**
 * Calculate Post EV and Check Tier Expansion
 *
 * Recalculates engagement velocity for posts and checks tier expansion thresholds.
 * Performs tier transitions (seed → tier1 → tier2 → tier3) based on EV scores.
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: */15 * * * * (every 15 minutes)
 * Command: php /path/to/jobs/feed/calculate_post_ev.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\Feed\SocialFeedAlgorithmService;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_ev_calculation.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting EV calculation and tier expansion check ===");

    $config = Config::getInstance();
    $feedAlgorithmService = new SocialFeedAlgorithmService($config);

    // Get posts needing tier evaluation
    $posts = $feedAlgorithmService->getPostsNeedingTierEvaluation(100);

    if (empty($posts)) {
        logMessage("No posts needing tier evaluation");
        exit(0);
    }

    logMessage(sprintf("Found %d posts needing evaluation", count($posts)));

    $tier2Expansions = 0;
    $tier3Expansions = 0;

    foreach ($posts as $post) {
        $postId = $post['post_id'];

        try {
            // Check tier expansion thresholds
            $thresholdInfo = $feedAlgorithmService->checkTierExpansionThresholds($postId);

            if (isset($thresholdInfo['error'])) {
                logMessage(sprintf("Post %d: Error - %s", $postId, $thresholdInfo['error']));
                continue;
            }

            // Expand to Tier 2 if eligible
            if ($thresholdInfo['should_expand_tier2']) {
                $feedAlgorithmService->expandPostToTier2($postId);
                logMessage(sprintf(
                    "Post %d expanded to Tier 2 (EV: %.2f, threshold: %.2f)",
                    $postId,
                    $thresholdInfo['ev_score'],
                    $thresholdInfo['tier2_threshold']
                ));
                $tier2Expansions++;
            }

            // Expand to Tier 3 if eligible
            if ($thresholdInfo['should_expand_tier3']) {
                $feedAlgorithmService->expandPostToTier3($postId);
                logMessage(sprintf(
                    "Post %d expanded to Tier 3 (EV: %.2f, threshold: %.2f)",
                    $postId,
                    $thresholdInfo['ev_score'],
                    $thresholdInfo['tier3_threshold']
                ));
                $tier3Expansions++;
            }
        } catch (\Exception $e) {
            logMessage(sprintf("Post %d: Exception - %s", $postId, $e->getMessage()));
            continue;
        }
    }

    logMessage(sprintf(
        "=== EV calculation complete: %d Tier 2 expansions, %d Tier 3 expansions ===",
        $tier2Expansions,
        $tier3Expansions
    ));

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('EV calculation job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
