<?php
/**
 * Niko Dispatcher - Story Assignment & Routing
 * Evaluates story value and assigns detected anomalies to personas
 * Runs every 10 minutes
 *
 * Schedule: Every 10 minutes (cron: \*\/10 \* \* \* \*)
 * Command: php /path/to/jobs/writer/niko_dispatcher.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\Writer\NikoService;

$logFile = __DIR__ . '/../../storage/logs/writer_niko.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Niko Dispatcher Starting ===", $logFile);

    $config = new Config();
    $niko = new NikoService($config);

    // Process all unassigned anomalies
    $result = $niko->processAnomalies();

    logMessage("Processing complete:", $logFile);
    logMessage("  - Processed: " . $result['processed'], $logFile);
    logMessage("  - Assigned: " . $result['assigned'], $logFile);
    logMessage("  - Skipped: " . $result['skipped'], $logFile);
    logMessage("  - Errors: " . $result['errors'], $logFile);

    // Alert if errors exceed threshold
    if ($result['errors'] > 5) {
        logMessage("⚠️  HIGH ERROR RATE - P1 ALERT: " . $result['errors'] . " errors", $logFile);
    }

    logMessage("=== Niko Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Niko: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
