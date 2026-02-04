<?php

/**
 * Anti-Payola Audit
 *
 * Audits feed for suspicious EV spikes and enforces anti-payola rules.
 *
 * Checks:
 * 1. Detects posts with sudden EV spikes (> 2x daily average)
 * 2. Flags suspicious engagement patterns
 * 3. Verifies paid promotion labeling
 * 4. Checks for bot-like engagement behavior
 * 5. Investigates high anonymous engagement ratios
 *
 * Bible Ch. 22: Social Feed & Engagement Algorithm
 *
 * Schedule: 0 1 * * * (daily at 1 AM)
 * Command: php /path/to/jobs/feed/anti_payola_audit.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Config;
use NGN\Lib\Feed\AntiPayolaService;
use NGN\Lib\Logger\LoggerFactory;

// Log file
$logFile = __DIR__ . '/../../storage/logs/feed_anti_payola_audit.log';

function logMessage(string $message): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Starting anti-payola audit ===");

    $config = Config::getInstance();
    $antiPayolaService = new AntiPayolaService($config);

    // Run comprehensive audit
    $auditResult = $antiPayolaService->auditAllPosts();

    if (isset($auditResult['error'])) {
        logMessage("ERROR: " . $auditResult['error']);
        exit(1);
    }

    logMessage(sprintf("Audit complete:"));
    logMessage(sprintf("  Suspicious posts: %d", $auditResult['suspicious_posts']));
    logMessage(sprintf("  Violations: %d", $auditResult['violations']));

    // Report suspicious posts
    if ($auditResult['suspicious_posts'] > 0) {
        logMessage(sprintf("\n%d suspicious posts detected:", $auditResult['suspicious_posts']));

        foreach ($auditResult['suspicious'] as $suspicious) {
            logMessage(sprintf(
                "  Post %d: Severity=%s, Flags=%d",
                $suspicious['post_id'],
                $suspicious['severity'],
                count($suspicious['flags'])
            ));

            foreach ($suspicious['flags'] as $flag) {
                logMessage(sprintf(
                    "    - %s (severity: %s)",
                    $flag['type'],
                    $flag['severity']
                ));

                if (isset($flag['ratio'])) {
                    logMessage(sprintf("      Ratio: %.2f%% (threshold: %.2f%%)", $flag['ratio'], $flag['threshold']));
                }
                if (isset($flag['multiplier'])) {
                    logMessage(sprintf("      Multiplier: %.2fx", $flag['multiplier']));
                }
            }
        }
    }

    // Report violations
    if ($auditResult['violations'] > 0) {
        logMessage(sprintf("\n%d violations detected:", $auditResult['violations']));

        foreach ($auditResult['violations_detail'] as $violation) {
            logMessage(sprintf(
                "  Post %d: %s (Title: %s)",
                $violation['post_id'],
                $violation['violation'],
                $violation['title']
            ));
        }
    }

    // Get compliance report for manual review
    $complianceReport = $antiPayolaService->getComplianceReport(20);

    if (!empty($complianceReport)) {
        logMessage(sprintf("\nCompliance report: %d posts needing review", count($complianceReport)));

        foreach ($complianceReport as $index => $report) {
            if ($index >= 5) break; // Show top 5

            logMessage(sprintf(
                "  [%d] Post %d: %s (Severity: %s, Action: %s)",
                $index + 1,
                $report['post_id'],
                $report['flag_type'],
                $report['severity'],
                $report['action_taken']
            ));
        }
    }

    logMessage("=== Anti-payola audit complete ===");

    exit(0);
} catch (\Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    LoggerFactory::getLogger('feed')->error('Anti-payola audit job failed', [
        'error' => $e->getMessage()
    ]);
    exit(1);
}
