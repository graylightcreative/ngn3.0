<?php

/**
 * Cron Job: Progressive Chart Reveal
 *
 * Executes live chart reveal (100→1)
 * Schedule: * 6-7 * * 1 (Mondays 06:00-07:59 UTC, every minute)
 *
 * Reveals one rank per minute with notifications
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Retention\ChartDropService;
use NGN\Lib\Retention\PushNotificationService;
use NGN\Lib\Http\ConnectionFactory;
use NGN\Lib\Config;

try {
    // Initialize services
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);

    $pushService = new PushNotificationService($pdo);
    $chartDropService = new ChartDropService($pdo, $pushService);

    error_log("=== Progressive Chart Reveal Job Started ===");

    // Check if Chart Drop is active
    if (!$chartDropService->isChartDropActive()) {
        error_log("No active Chart Drop event, checking if we should start one...");

        // Get today's date (Monday should be today)
        $today = new DateTime('now', new DateTimeZone('UTC'));
        $todayStr = $today->format('Y-m-d');

        // Try to get today's chart drop
        try {
            $status = $chartDropService->getChartDropStatus($todayStr);
            if ($status['status'] === 'scheduled') {
                error_log("Starting chart drop reveal for {$todayStr}");
                $chartDropService->startProgressiveReveal($todayStr);
            }
        } catch (Exception $e) {
            error_log("No chart drop event found for today: " . $e->getMessage());
        }
    }

    // Get active chart drop and reveal next rank
    try {
        $latestChart = $chartDropService->getLatestChartDrop();

        if ($latestChart && $latestChart['status'] === 'revealing') {
            $dateStr = $latestChart['event_date'];
            $revealed = $chartDropService->revealNextRank($dateStr);

            if ($revealed) {
                error_log("Revealed rank: {$revealed['rank']} - Artist: {$revealed['artist_name']}");

                // Get updated status
                $status = $chartDropService->getChartDropStatus($dateStr);
                error_log("Chart Drop progress: {$status['reveal_progress_percent']}%");

                if ($status['status'] === 'completed') {
                    error_log("Chart Drop reveal completed!");
                }
            } else {
                error_log("No rank revealed this minute");
            }
        } else {
            error_log("No active chart drop in revealing status");
        }
    } catch (Exception $e) {
        error_log("Error during chart reveal: " . $e->getMessage());
    }

    error_log("=== Progressive Chart Reveal Job Completed ===");

} catch (Exception $e) {
    error_log("Critical error in progressive_chart_reveal job: " . $e->getMessage());
    exit(1);
}
