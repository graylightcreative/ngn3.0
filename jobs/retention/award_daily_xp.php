<?php

/**
 * Cron Job: Award Daily XP
 *
 * Awards XP for daily activities (listening, engagement)
 * Schedule: 0 * * * * (hourly)
 *
 * Tracks:
 * - Listening time (1 XP per minute)
 * - Engagement actions (likes, comments, shares)
 * - Badge progress
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

    error_log("=== Award Daily XP Job Started ===");

    // Get users active in the last hour
    $stmt = $pdo->prepare("
        SELECT DISTINCT user_id
        FROM cdm_engagements
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        UNION
        SELECT DISTINCT user_id
        FROM analytics_snapshots
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $xpAwarded = 0;
    $levelUps = 0;

    foreach ($activeUsers as $user) {
        $userId = (int)$user['user_id'];

        try {
            // Get user's listening time in last hour (in minutes)
            // TODO: Integrate with analytics to get actual listening time
            $listeningMinutes = 30; // Placeholder

            // Award XP for listening
            if ($listeningMinutes > 0) {
                $retentionService->awardXP($userId, $listeningMinutes, 'listen');
                $xpAwarded += $listeningMinutes;
            }

            // Award XP for engagement actions in last hour
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count, type
                FROM cdm_engagements
                WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY type
            ");
            $stmt->execute([':user_id' => $userId]);
            $engagements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $xpMapping = [
                'like' => 10,
                'comment' => 30,
                'share' => 100,
                'spark' => 1
            ];

            foreach ($engagements as $engagement) {
                $type = $engagement['type'];
                $count = (int)$engagement['count'];
                $xpPerAction = $xpMapping[$type] ?? 0;

                if ($xpPerAction > 0) {
                    $totalXP = $xpPerAction * $count;
                    $retentionService->awardXP($userId, $totalXP, 'engagement');
                    $xpAwarded += $totalXP;
                }
            }

            // Check for level up
            $levelUpData = $retentionService->checkLevelUp($userId);
            if ($levelUpData) {
                $levelUps++;
                // Send level up notification
                $pushService->sendNikoSurprise($userId, 'achievement_unlock');
            }

            // Check for milestone progress
            $milestones = $retentionService->detectMilestones($userId);
            foreach ($milestones as $milestone) {
                if ($milestone['type'] === 'badge' && $milestone['progress'] >= 90) {
                    $pushService->sendMilestoneAlert(
                        $userId,
                        'achievement',
                        (int)(100 - $milestone['progress']),
                        (int)$milestone['target']
                    );
                }
            }
        } catch (Exception $e) {
            error_log("Error awarding XP to user {$userId}: " . $e->getMessage());
        }
    }

    error_log("=== Award Daily XP Job Completed ===");
    error_log("Active users: " . count($activeUsers));
    error_log("Total XP awarded: {$xpAwarded}");
    error_log("Level ups: {$levelUps}");

} catch (Exception $e) {
    error_log("Critical error in award_daily_xp job: " . $e->getMessage());
    exit(1);
}
