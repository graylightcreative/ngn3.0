<?php

/**
 * Cron Job: Check Streaks
 *
 * Checks streak expiry and sends loss aversion warnings
 * Schedule: 0 0,12 * * * (twice daily: midnight, noon)
 *
 * Sends notifications when:
 * - Streak expiring in < 12 hours
 * - Streak has just expired
 * - Grace period activated
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\Retention\RetentionService;
use NGN\Lib\Retention\PushNotificationService;
use NGN\Lib\Http\ConnectionFactory;
use NGN\Lib\Config;

try {
    // Initialize services
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);

    $retentionService = new RetentionService($pdo);
    $pushService = new PushNotificationService($pdo);

    error_log("=== Check Streaks Job Started ===");

    // Get users with active streaks expiring soon
    $stmt = $pdo->prepare("
        SELECT id, user_id, current_streak, next_check_in_deadline
        FROM user_streaks
        WHERE current_streak > 0
          AND next_check_in_deadline > NOW()
          AND next_check_in_deadline <= DATE_ADD(NOW(), INTERVAL 12 HOUR)
    ");
    $stmt->execute();
    $expiringStreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $warningsSent = 0;

    foreach ($expiringStreaks as $streak) {
        try {
            $userId = (int)$streak['user_id'];
            $streakLength = (int)$streak['current_streak'];
            $deadline = new DateTime($streak['next_check_in_deadline']);
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $hoursRemaining = (int)ceil($deadline->diff($now)->h + ($deadline->diff($now)->d * 24));

            // Only send warning if we haven't sent one recently
            $checkInterval = 6; // hours
            if ($hoursRemaining <= 12 && $hoursRemaining > (12 - $checkInterval)) {
                $pushService->sendStreakWarning($userId, $streakLength, $hoursRemaining);
                $warningsSent++;
            }
        } catch (Exception $e) {
            error_log("Error processing streak {$streak['id']}: " . $e->getMessage());
        }
    }

    // Check for expired streaks
    $stmt = $pdo->prepare("
        SELECT id, user_id, current_streak, last_broken_streak_length
        FROM user_streaks
        WHERE current_streak > 0
          AND next_check_in_deadline < NOW()
          AND grace_period_active = 0
    ");
    $stmt->execute();
    $expiredStreaks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $streaksExpired = 0;
    $gracePeriodActivated = 0;

    foreach ($expiredStreaks as $streak) {
        try {
            $userId = (int)$streak['user_id'];
            $streakLength = (int)$streak['current_streak'];

            // Mark streak as broken and activate grace period
            $gracePeriodExpires = new DateTime('now', new DateTimeZone('UTC'));
            $gracePeriodExpires->modify('+48 hours');

            $stmt = $pdo->prepare("
                UPDATE user_streaks
                SET grace_period_active = 1,
                    grace_period_expires_at = :grace_expires,
                    last_broken_at = NOW(),
                    last_broken_streak_length = :broken_length,
                    streak_broken_count = streak_broken_count + 1,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':grace_expires' => $gracePeriodExpires->format('Y-m-d H:i:s'),
                ':broken_length' => $streakLength,
                ':id' => $streak['id']
            ]);

            // Send notification about broken streak
            $pushService->sendPush(
                $userId,
                '😢 Your {' . $streakLength . '}-day streak is broken',
                'But you have 48 hours to recover it. Check in again to restart!',
                ['deep_link' => '/streaks'],
                9
            );

            $streaksExpired++;
            $gracePeriodActivated++;
        } catch (Exception $e) {
            error_log("Error processing expired streak {$streak['id']}: " . $e->getMessage());
        }
    }

    // Check for expired grace periods
    $stmt = $pdo->prepare("
        SELECT id, user_id, last_broken_streak_length
        FROM user_streaks
        WHERE grace_period_active = 1
          AND grace_period_expires_at < NOW()
    ");
    $stmt->execute();
    $expiredGracePeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gracePeriodExpired = 0;

    foreach ($expiredGracePeriods as $period) {
        try {
            $userId = (int)$period['user_id'];

            // Expire grace period
            $stmt = $pdo->prepare("
                UPDATE user_streaks
                SET grace_period_active = 0,
                    current_streak = 0,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $period['id']]);

            // Send notification
            $pushService->sendPush(
                $userId,
                '⏰ Grace period expired',
                'Your streak recovery window has closed. Start a new streak today!',
                ['deep_link' => '/streaks'],
                7
            );

            $gracePeriodExpired++;
        } catch (Exception $e) {
            error_log("Error expiring grace period {$period['id']}: " . $e->getMessage());
        }
    }

    error_log("=== Check Streaks Job Completed ===");
    error_log("Warnings sent: {$warningsSent}");
    error_log("Streaks expired: {$streaksExpired}");
    error_log("Grace periods activated: {$gracePeriodActivated}");
    error_log("Grace periods expired: {$gracePeriodExpired}");

} catch (Exception $e) {
    error_log("Critical error in check_streaks job: " . $e->getMessage());
    exit(1);
}
