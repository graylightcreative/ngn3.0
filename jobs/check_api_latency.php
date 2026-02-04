<?php

/**
 * P95 API Latency Monitor
 *
 * Cron job that runs every minute to check P95 API latency.
 * Fires P1 alerts when latency exceeds 250ms threshold.
 *
 * Bible Ch. 12: Mandatory HIGH alert for API performance monitoring.
 *
 * Schedule: * * * * * (every minute)
 * Command: php /path/to/jobs/check_api_latency.php
 */

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/config/config.php';

use NGN\Lib\Services\MetricsService;
use NGN\Lib\Services\AlertService;

// Constants
const P95_THRESHOLD_MS = 250.0;
const MONITORING_WINDOW_MINUTES = 5;
const ALERT_DEBOUNCE_MINUTES = 15;
const MIN_REQUESTS_FOR_ALERT = 10; // Don't alert if very low traffic

// Log file
$logFile = __DIR__ . '/../storage/logs/latency_monitor.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("Starting P95 latency check...", $logFile);

    // Initialize services
    $config = new \NGN\Lib\Config\Config();
    $pdo = new PDO(
        "mysql:host={$config->get('db.host')};dbname={$config->get('db.name')};charset=utf8mb4",
        $config->get('db.user'),
        $config->get('db.pass'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $metricsService = new MetricsService($pdo);
    $alertService = new AlertService($pdo, [
        'high_priority_email' => $config->get('alerts.high_priority_email'),
        'slack_webhook_url' => $config->get('alerts.slack_webhook_url')
    ]);

    // Get overall latency statistics
    $stats = $metricsService->getLatencyStats(MONITORING_WINDOW_MINUTES);

    logMessage(
        sprintf(
            "Stats (last %d min): Requests=%d, P50=%.2fms, P95=%.2fms, P99=%.2fms, Avg=%.2fms",
            MONITORING_WINDOW_MINUTES,
            $stats['request_count'],
            $stats['p50_ms'] ?? 0,
            $stats['p95_ms'] ?? 0,
            $stats['p99_ms'] ?? 0,
            $stats['avg_ms'] ?? 0
        ),
        $logFile
    );

    // Check if we have enough data to make a determination
    if ($stats['request_count'] < MIN_REQUESTS_FOR_ALERT) {
        logMessage("Not enough requests to evaluate (min: " . MIN_REQUESTS_FOR_ALERT . ")", $logFile);
        exit(0);
    }

    // Check if P95 exceeds threshold
    $p95 = $stats['p95_ms'];
    if ($p95 !== null && $p95 > P95_THRESHOLD_MS) {
        logMessage(
            sprintf("⚠️  P95 THRESHOLD EXCEEDED: %.2fms > %.2fms", $p95, P95_THRESHOLD_MS),
            $logFile
        );

        // Check if we already alerted recently (debounce)
        if ($alertService->wasRecentlyFired('p95_latency', ALERT_DEBOUNCE_MINUTES)) {
            logMessage("Alert already fired in last " . ALERT_DEBOUNCE_MINUTES . " minutes (debounced)", $logFile);
        } else {
            // Fire P1 alert
            $alertMessage = sprintf(
                "API P95 latency exceeded threshold: %.2fms (threshold: %.2fms, window: %d minutes)",
                $p95,
                P95_THRESHOLD_MS,
                MONITORING_WINDOW_MINUTES
            );

            $alertDetails = [
                'p95_ms' => $p95,
                'threshold_ms' => P95_THRESHOLD_MS,
                'p50_ms' => $stats['p50_ms'],
                'p99_ms' => $stats['p99_ms'],
                'avg_ms' => $stats['avg_ms'],
                'request_count' => $stats['request_count'],
                'window_minutes' => MONITORING_WINDOW_MINUTES,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $alertId = $alertService->createAlert(
                'p95_latency',
                'p1',
                $alertMessage,
                $alertDetails,
                true // Send notification
            );

            logMessage("P1 Alert fired (ID: $alertId)", $logFile);

            // Get per-endpoint breakdown to identify slow endpoints
            $breakdown = $metricsService->getEndpointBreakdown(MONITORING_WINDOW_MINUTES, 10);

            logMessage("=== Slowest Endpoints (by P95) ===", $logFile);
            foreach ($breakdown as $endpointStats) {
                if ($endpointStats['p95_ms'] > P95_THRESHOLD_MS) {
                    logMessage(
                        sprintf(
                            "  🔴 %s: P95=%.2fms, Requests=%d",
                            $endpointStats['endpoint'],
                            $endpointStats['p95_ms'],
                            $endpointStats['request_count']
                        ),
                        $logFile
                    );
                } else {
                    logMessage(
                        sprintf(
                            "  🟢 %s: P95=%.2fms, Requests=%d",
                            $endpointStats['endpoint'],
                            $endpointStats['p95_ms'],
                            $endpointStats['request_count']
                        ),
                        $logFile
                    );
                }
            }
        }
    } else {
        logMessage(
            sprintf("✅ P95 within threshold: %.2fms <= %.2fms", $p95 ?? 0, P95_THRESHOLD_MS),
            $logFile
        );
    }

    // Cleanup old metrics (keep 7 days)
    $deletedRows = $metricsService->cleanupOldMetrics(7);
    if ($deletedRows > 0) {
        logMessage("Cleaned up $deletedRows old metric rows", $logFile);
    }

    logMessage("P95 latency check complete\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in latency monitor: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
