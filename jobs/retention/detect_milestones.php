<?php

/**
 * Cron Job: Detect Milestones
 *
 * Detects approaching milestones and sends goal gradient alerts
 * Schedule: 0 */6 * * * (every 6 hours)
 *
 * Checks for:
 * - Badge thresholds (75-99% complete)
 * - Rank milestones
 * - Level progression
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

    error_log("=== Detect Milestones Job Started ===");

    // Get all active users
    $stmt = $pdo->prepare("
        SELECT id FROM `ngn_2025`.`users`
        WHERE status = 'active'
        LIMIT 5000
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $milestonesDetected = 0;
    $alertsSent = 0;

    foreach ($users as $user) {
        try {
            $userId = (int)$user['id'];

            // Detect milestones for user
            $milestones = $retentionService->detectMilestones($userId);

            if (!empty($milestones)) {
                $milestonesDetected += count($milestones);

                foreach ($milestones as $milestone) {
                    try {
                        $distance = $milestone['target'] - $milestone['progress'];

                        // Send milestone alert
                        if ($distance > 0) {
                            $pushService->sendMilestoneAlert(
                                $userId,
                                $milestone['badge_key'],
                                $distance,
                                $milestone['target']
                            );
                            $alertsSent++;
                        }
                    } catch (Exception $e) {
                        error_log("Error sending milestone alert for user {$userId}: " . $e->getMessage());
                    }
                }
            }

            // Check XP progress
            try {
                $xpData = $retentionService->getUserLevel($userId);

                // If at 75%+ progress to next level, send milestone
                if ($xpData['xp_progress_percent'] >= 75) {
                    $xpRemaining = (int)($xpData['xp_to_next_level'] * (1 - $xpData['xp_progress_percent'] / 100));

                    $pushService->sendMilestoneAlert(
                        $userId,
                        'Level ' . ($xpData['current_level'] + 1),
                        $xpRemaining,
                        (int)$xpData['xp_to_next_level']
                    );
                    $alertsSent++;
                }
            } catch (Exception $e) {
                error_log("Error checking XP progress for user {$userId}: " . $e->getMessage());
            }

        } catch (Exception $e) {
            error_log("Error processing milestones for user {$user['id']}: " . $e->getMessage());
        }
    }

    error_log("=== Detect Milestones Job Completed ===");
    error_log("Users processed: " . count($users));
    error_log("Milestones detected: {$milestonesDetected}");
    error_log("Alerts sent: {$alertsSent}");

} catch (Exception $e) {
    error_log("Critical error in detect_milestones job: " . $e->getMessage());
    exit(1);
}
