<?php

/**
 * Cron Job: Process Push Notifications
 *
 * Processes notification queue and delivers via FCM
 * Schedule: */5 * * * * (every 5 minutes)
 *
 * Batches Spark pings and sends notifications
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Retention\PushNotificationService;
use NGN\Lib\Http\ConnectionFactory;
use NGN\Lib\Config;

try {
    // Initialize services
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);

    $pushService = new PushNotificationService($pdo, $_ENV['FCM_API_KEY'] ?? '');

    error_log("=== Process Push Notifications Job Started ===");

    // Batch Spark pings first
    $stmt = $pdo->prepare("
        SELECT DISTINCT user_id
        FROM push_notification_queue
        WHERE notification_type = 'spark_ping'
          AND status = 'pending'
          AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        LIMIT 100
    ");
    $stmt->execute();
    $usersWithBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $batchedCount = 0;
    foreach ($usersWithBatches as $user) {
        try {
            if ($pushService->batchSparkPings((int)$user['user_id'])) {
                $batchedCount++;
            }
        } catch (Exception $e) {
            error_log("Error batching spark pings for user {$user['user_id']}: " . $e->getMessage());
        }
    }

    // Get pending notifications for delivery
    $notifications = $pushService->getPendingNotifications(500);

    $sent = 0;
    $failed = 0;

    foreach ($notifications as $notification) {
        try {
            $userId = (int)$notification['user_id'];
            $notificationId = (int)$notification['id'];
            $title = $notification['notification_title'];
            $body = $notification['notification_body'];
            $data = $notification['notification_data'] ? json_decode($notification['notification_data'], true) : [];

            // Get device tokens for user
            $deviceTokens = $pushService->getUserDeviceTokens($userId);

            if (empty($deviceTokens)) {
                // No device tokens, skip but mark as sent (in-app only)
                $pushService->markAsSent($notificationId);
                $sent++;
                continue;
            }

            // Deliver to each device
            $deliverySuccess = false;
            foreach ($deviceTokens as $device) {
                try {
                    if ($pushService->deliverViaFCM($device['fcm_token'], $title, $body, $data)) {
                        $deliverySuccess = true;
                    }
                } catch (Exception $e) {
                    error_log("Error delivering to token {$device['fcm_token']}: " . $e->getMessage());
                }
            }

            if ($deliverySuccess) {
                $pushService->markAsSent($notificationId);
                $sent++;
            } else {
                $pushService->markAsFailed($notificationId, 'FCM delivery failed');
                $failed++;
            }
        } catch (Exception $e) {
            error_log("Error processing notification {$notification['id']}: " . $e->getMessage());
            try {
                $pushService->markAsFailed((int)$notification['id'], $e->getMessage());
            } catch (Exception $innerE) {
                error_log("Could not mark as failed: " . $innerE->getMessage());
            }
            $failed++;
        }
    }

    // Clean up old failed notifications (older than 7 days)
    $stmt = $pdo->prepare("
        DELETE FROM push_notification_queue
        WHERE status = 'failed'
          AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $cleanedUp = $stmt->rowCount();

    error_log("=== Process Push Notifications Job Completed ===");
    error_log("Batches created: {$batchedCount}");
    error_log("Notifications sent: {$sent}");
    error_log("Notifications failed: {$failed}");
    error_log("Old records cleaned up: {$cleanedUp}");

} catch (Exception $e) {
    error_log("Critical error in process_push_notifications job: " . $e->getMessage());
    exit(1);
}
