<?php

/**
 * Cron Job: Schedule Chart Drop
 *
 * Schedules Monday 06:00 UTC Chart Drop events
 * Schedule: 0 0 * * 0 (Sundays at midnight UTC)
 *
 * Creates event, sends Sunday teaser, schedules progressive reveal
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

    error_log("=== Schedule Chart Drop Job Started ===");

    // Get next Monday date
    $today = new DateTime('now', new DateTimeZone('UTC'));
    $daysUntilMonday = (1 - $today->format('N') + 7) % 7;
    if ($daysUntilMonday == 0) {
        $daysUntilMonday = 7; // If today is Monday, get next Monday
    }

    $nextMonday = clone $today;
    $nextMonday->modify("+{$daysUntilMonday} days");
    $nextMondayStr = $nextMonday->format('Y-m-d');

    error_log("Scheduling Chart Drop for: {$nextMondayStr}");

    // Schedule Chart Drop event
    try {
        $scheduled = $chartDropService->scheduleChartDrop($nextMondayStr);
        if ($scheduled) {
            error_log("Chart Drop event created for {$nextMondayStr}");
        } else {
            error_log("Chart Drop event already exists for {$nextMondayStr}");
        }
    } catch (Exception $e) {
        error_log("Error scheduling chart drop: " . $e->getMessage());
    }

    // Send Sunday teaser (today)
    try {
        $teasersSent = $chartDropService->sendSundayTease($nextMondayStr);
        error_log("Sunday teaser sent to {$teasersSent} users");
    } catch (Exception $e) {
        error_log("Error sending Sunday teaser: " . $e->getMessage());
    }

    // Get latest chart drop info
    try {
        $latestChart = $chartDropService->getLatestChartDrop();
        if ($latestChart) {
            error_log("Latest Chart Drop: {$latestChart['event_date']} - Status: {$latestChart['status']}");
        }
    } catch (Exception $e) {
        error_log("Error getting latest chart drop: " . $e->getMessage());
    }

    error_log("=== Schedule Chart Drop Job Completed ===");

} catch (Exception $e) {
    error_log("Critical error in schedule_chart_drop job: " . $e->getMessage());
    exit(1);
}
