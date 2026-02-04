<?php
/**
 * Scout Anomaly Detection Cron Job
 * Detects music anomalies: chart jumps, engagement spikes, spin surges, genre trends
 * Runs every 5 minutes
 *
 * Schedule: Every 5 minutes (cron: \*\/5 \* \* \* \*)
 * Command: php /path/to/jobs/writer/scout_anomaly_detection.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\Writer\ScoutService;

$logFile = __DIR__ . '/../../storage/logs/writer_scout.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Scout Anomaly Detection Starting ===", $logFile);

    $config = new Config();
    $scout = new ScoutService($config);

    // Run all detection types
    $results = [
        'chart_jumps' => $scout->detectChartJumps(),
        'engagement_spikes' => $scout->detectEngagementSpikes(),
        'spin_surges' => $scout->detectSpinSurges(),
        'genre_trends' => $scout->detectGenreTrends(),
    ];

    $totalAnomalies = array_sum(array_map('count', $results));

    logMessage("Detection completed: $totalAnomalies total anomalies detected", $logFile);
    logMessage("  - Chart jumps: " . count($results['chart_jumps']), $logFile);
    logMessage("  - Engagement spikes: " . count($results['engagement_spikes']), $logFile);
    logMessage("  - Spin surges: " . count($results['spin_surges']), $logFile);
    logMessage("  - Genre trends: " . count($results['genre_trends']), $logFile);

    // Store anomalies in database
    $stored = 0;
    $failed = 0;

    foreach ($results as $detectionType => $anomalies) {
        foreach ($anomalies as $anomaly) {
            try {
                $scout->createAnomaly(
                    $anomaly['detection_type'],
                    $anomaly['severity'],
                    $anomaly['artist_id'],
                    $anomaly['track_id'] ?? null,
                    $anomaly['detected_value'],
                    $anomaly['baseline_value'],
                    $anomaly['magnitude'],
                    $anomaly['genre'] ?? null,
                    $anomaly['city_code'] ?? null
                );
                $stored++;
            } catch (\Throwable $e) {
                logMessage("ERROR storing anomaly: " . $e->getMessage(), $logFile);
                $failed++;
            }
        }
    }

    logMessage("Stored: $stored anomalies | Failed: $failed", $logFile);

    // Alert if surge detected (>50 anomalies in 5 min)
    if ($totalAnomalies > 50) {
        logMessage("⚠️  SURGE DETECTED - P1 ALERT: $totalAnomalies anomalies in 5 minutes", $logFile);
    }

    logMessage("=== Scout Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Scout: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
