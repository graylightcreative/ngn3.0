<?php
/**
 * Drafting Processor - Article Generation & Safety Scanning
 * Generates articles from assigned anomalies and scans for defamation
 * Runs every 15 minutes
 *
 * Schedule: Every 15 minutes (cron: \*\/15 \* \* \* \*)
 * Command: php /path/to/jobs/writer/drafting_processor.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\Writer\WriterEngineService;
use NGN\Lib\Writer\SafetyFilterService;

$logFile = __DIR__ . '/../../storage/logs/writer_drafting.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Drafting Processor Starting ===", $logFile);

    $config = new Config();
    $engine = new WriterEngineService($config);
    $safety = new SafetyFilterService($config);

    // Generate pending articles (limit 10)
    logMessage("Generating articles...", $logFile);
    $generateResult = $engine->generatePendingArticles(10);

    logMessage("Article generation:", $logFile);
    logMessage("  - Generated: " . $generateResult['generated'], $logFile);
    logMessage("  - Failed: " . $generateResult['failed'], $logFile);
    logMessage("  - Total cost: $" . number_format($generateResult['cost_total_usd'], 4), $logFile);

    // Alert if generation time excessive
    if ($generateResult['failed'] > 3) {
        logMessage("⚠️  HIGH FAILURE RATE - P1 ALERT: " . $generateResult['failed'] . " generation failures", $logFile);
    }

    // Scan pending articles for safety issues
    logMessage("Scanning articles for safety...", $logFile);
    $scanResult = $safety->scanPendingArticles(50);

    logMessage("Safety scanning:", $logFile);
    logMessage("  - Scanned: " . $scanResult['scanned'], $logFile);
    logMessage("  - Approved: " . $scanResult['approved'], $logFile);
    logMessage("  - Flagged: " . $scanResult['flagged'], $logFile);
    logMessage("  - Rejected: " . $scanResult['rejected'], $logFile);
    logMessage("  - Errors: " . $scanResult['errors'], $logFile);

    // Alert if rejection rate exceeds 10%
    if ($scanResult['scanned'] > 0) {
        $rejectionRate = ($scanResult['rejected'] / $scanResult['scanned']) * 100;
        if ($rejectionRate > 10) {
            logMessage("⚠️  HIGH REJECTION RATE - P1 ALERT: " . number_format($rejectionRate, 1) . "% rejection rate", $logFile);
        }
    }

    logMessage("=== Drafting Processor Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Drafting: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
