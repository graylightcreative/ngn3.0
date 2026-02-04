<?php
/**
 * Auto-Hype Publisher - Instant Publishing Pipeline
 * Publishes approved auto-hype articles on schedule
 * Runs every 5 minutes
 *
 * Schedule: Every 5 minutes (cron: \*\/5 \* \* \* \*)
 * Command: php /path/to/jobs/writer/auto_hype_publisher.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\Writer\WriterEngineService;

$logFile = __DIR__ . '/../../storage/logs/writer_publisher.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Auto-Hype Publisher Starting ===", $logFile);

    $config = new Config();
    $engine = new WriterEngineService($config);

    // Publish scheduled articles
    $result = $engine->publishAutoHypeArticles();

    logMessage("Publishing results:", $logFile);
    logMessage("  - Published: " . $result['published'], $logFile);
    logMessage("  - Errors: " . $result['errors'], $logFile);

    if ($result['published'] > 0) {
        logMessage("✅ " . $result['published'] . " articles published", $logFile);
    }

    // Check for publishing backlog
    // This would involve checking scheduled_for timestamps
    logMessage("=== Auto-Hype Publisher Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Publisher: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
