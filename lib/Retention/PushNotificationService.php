<?php

namespace NGN\Lib\Retention;

use PDO;
use Exception;
use DateTime;

/**
 * Push Notification Service
 *
 * Manages Firebase Cloud Messaging (FCM) push notifications
 * Handles batching, scheduling, and delivery of retention notifications
 */
class PushNotificationService
{
    private PDO $pdo;
    private string $fcmApiKey;

    // Batching configuration
    private const SPARK_PING_BATCH_WINDOW = 900; // 15 minutes in seconds
    private const MAX_BATCH_AGGREGATION = 10;

    public function __construct(PDO $pdo, string $fcmApiKey = '')
    {
        $this->pdo = $pdo;
        $this->fcmApiKey = $fcmApiKey ?: $_ENV['FCM_API_KEY'] ?? '';
    }

    /**
     * Register device token for push notifications
     *
     * @param int $userId User ID
     * @param string $fcmToken Firebase Cloud Messaging token
     * @param string $platform Platform (ios, android, web)
     * @return bool Success
     * @throws Exception
     */
    public function registerDeviceToken(int $userId, string $fcmToken, string $platform): bool
    {
        try {
            $validPlatforms = ['ios', 'android', 'web'];
            if (!in_array($platform, $validPlatforms)) {
                throw new Exception("Invalid platform: {$platform}");
            }

            // Check if token already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM `ngn_2025`.`push_device_tokens`
                WHERE user_id = :user_id AND fcm_token = :token
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $fcmToken
            ]);

            if ($stmt->fetch()) {
                // Update existing token
                $stmt = $this->pdo->prepare("
                    UPDATE `ngn_2025`.`push_device_tokens`
                    SET platform = :platform,
                        is_active = 1,
                        last_verified_at = NOW(),
                        updated_at = NOW()
                    WHERE user_id = :user_id AND fcm_token = :token
                ");
            } else {
                // Insert new token
                $stmt = $this->pdo->prepare("
                    INSERT INTO `ngn_2025`.`push_device_tokens` (
                        user_id, fcm_token, platform,
                        is_active, last_verified_at,
                        created_at, updated_at
                    ) VALUES (
                        :user_id, :token, :platform,
                        1, NOW(), NOW(), NOW()
                    )
                ");
            }

            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $fcmToken,
                ':platform' => $platform
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error registering device token: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send push notification to user
     *
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data (deep links, etc.)
     * @param int $priority Priority level (1-10)
     * @return bool Success
     * @throws Exception
     */
    public function sendPush(int $userId, string $title, string $body, array $data = [], int $priority = 5): bool
    {
        try {
            // Queue notification for delivery
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, notification_data, status,
                    priority, created_at
                ) VALUES (
                    :user_id, :type, :title,
                    :body, :data, 'pending',
                    :priority, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':type' => 'niko_surprise',
                ':title' => $title,
                ':body' => $body,
                ':data' => !empty($data) ? json_encode($data) : null,
                ':priority' => $priority
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error queuing push notification: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notifications in batch
     *
     * @param array $userIds User IDs
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @return array Results for each user
     * @throws Exception
     */
    public function sendBatchPush(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [];

        try {
            foreach ($userIds as $userId) {
                try {
                    $this->sendPush((int)$userId, $title, $body, $data);
                    $results[$userId] = ['success' => true];
                } catch (Exception $e) {
                    $results[$userId] = ['success' => false, 'error' => $e->getMessage()];
                }
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error in batch push: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Queue a Spark ping notification
     *
     * @param int $userId Recipient user ID
     * @param float $sparkAmount Amount of sparks
     * @param string $senderName Name of sender
     * @return bool Success
     * @throws Exception
     */
    public function queueSparkPing(int $userId, float $sparkAmount, string $senderName): bool
    {
        try {
            $batchKey = "spark_ping_{$userId}";

            // Check if we have recent spark pings to batch
            $stmt = $this->pdo->prepare("
                SELECT id, batched_count FROM `ngn_2025`.`push_notification_queue`
                WHERE user_id = :user_id
                  AND notification_type = 'spark_ping'
                  AND batch_key = :batch_key
                  AND status = 'pending'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':batch_key' => $batchKey
            ]);
            $existingBatch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingBatch) {
                // Update existing batch
                $newCount = ((int)$existingBatch['batched_count']) + 1;
                $stmt = $this->pdo->prepare("
                    UPDATE `ngn_2025`.`push_notification_queue`
                    SET batched_count = :count,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':count' => $newCount,
                    ':id' => $existingBatch['id']
                ]);
            } else {
                // Create new batch entry
                $stmt = $this->pdo->prepare("
                    INSERT INTO `ngn_2025`.`push_notification_queue` (
                        user_id, notification_type, notification_title,
                        notification_body, batch_key, batched_count,
                        status, priority, created_at
                    ) VALUES (
                        :user_id, 'spark_ping',
                        'You received sparks!',
                        :body, :batch_key, 1,
                        'pending', 8, NOW()
                    )
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':body' => "You received {$sparkAmount} sparks from {$senderName}",
                    ':batch_key' => $batchKey
                ]);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error queuing spark ping: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Batch spark pings for a user
     *
     * Aggregates recent spark pings and updates notification text
     *
     * @param int $userId User ID
     * @return bool Success
     * @throws Exception
     */
    public function batchSparkPings(int $userId): bool
    {
        try {
            $batchKey = "spark_ping_{$userId}";

            // Get all pending spark pings for this user
            $stmt = $this->pdo->prepare("
                SELECT id, batched_count, notification_body
                FROM `ngn_2025`.`push_notification_queue`
                WHERE user_id = :user_id
                  AND notification_type = 'spark_ping'
                  AND batch_key = :batch_key
                  AND status = 'pending'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':batch_key' => $batchKey
            ]);
            $pings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pings)) {
                return false;
            }

            // Calculate total sparks and aggregated count
            $totalCount = array_sum(array_map(fn($p) => (int)$p['batched_count'], $pings));

            // Keep first notification, update with aggregated count
            $firstId = $pings[0]['id'];
            $aggregatedBody = "You received sparks from {$totalCount} supporters";

            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`push_notification_queue`
                SET notification_body = :body,
                    batched_count = :count,
                    status = 'batched'
                WHERE id = :id
            ");
            $stmt->execute([
                ':body' => $aggregatedBody,
                ':count' => $totalCount,
                ':id' => $firstId
            ]);

            // Mark other pings as batched
            if (count($pings) > 1) {
                $otherIds = array_slice(array_map(fn($p) => $p['id'], $pings), 1);
                $placeholders = implode(',', array_fill(0, count($otherIds), '?'));

                $stmt = $this->pdo->prepare("
                    UPDATE `ngn_2025`.`push_notification_queue`
                    SET status = 'batched'
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($otherIds);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error batching spark pings: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send rivalry alert notification
     *
     * @param int $userId User ID (who is being passed)
     * @param int $rivalUserId Rival user ID (who passed them)
     * @param string $genreSlug Genre they compete in
     * @return bool Success
     * @throws Exception
     */
    public function sendRivalryAlert(int $userId, int $rivalUserId, string $genreSlug): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT display_name FROM `ngn_2025`.`users` WHERE id = :user_id
            ");
            $stmt->execute([':user_id' => $rivalUserId]);
            $rival = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rival) {
                return false;
            }

            $title = "You've been passed!";
            $body = "{$rival['display_name']} just jumped you in the {$genreSlug} chart. Time to drop a post?";

            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, notification_data, status,
                    priority, created_at
                ) VALUES (
                    :user_id, 'rivalry_alert', :title,
                    :body, :data, 'pending',
                    7, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':body' => $body,
                ':data' => json_encode(['rival_id' => $rivalUserId, 'genre' => $genreSlug])
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sending rivalry alert: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send milestone alert notification
     *
     * @param int $userId User ID
     * @param string $milestoneType Type of milestone (badge, rank, etc.)
     * @param int $current Current value
     * @param int $target Target value
     * @return bool Success
     * @throws Exception
     */
    public function sendMilestoneAlert(int $userId, string $milestoneType, int $current, int $target): bool
    {
        try {
            $distance = $target - $current;
            $title = "You're almost there!";
            $body = "{$distance} {$milestoneType} points to your next milestone";

            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, status, priority, created_at
                ) VALUES (
                    :user_id, 'milestone', :title,
                    :body, 'pending', 6, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':body' => $body
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sending milestone alert: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Niko surprise notification
     *
     * Variable reward trigger for psychological engagement
     *
     * @param int $userId User ID
     * @param string $triggerType Type of trigger
     * @return bool Success
     * @throws Exception
     */
    public function sendNikoSurprise(int $userId, string $triggerType): bool
    {
        try {
            // Variable reward messages
            $surprises = [
                'streak_milestone' => ['title' => '🔥 Streak Goal Reached!', 'body' => 'Keep the flame alive - check back tomorrow!'],
                'random_bonus' => ['title' => '✨ Bonus Sparks!', 'body' => 'You earned extra sparks for your engagement'],
                'achievement_unlock' => ['title' => '🏆 Achievement Unlocked!', 'body' => 'You\'ve reached a new milestone'],
                'rank_jump' => ['title' => '📈 Chart Movement!', 'body' => 'You\'ve moved up in your genre ranking'],
            ];

            $surprise = $surprises[$triggerType] ?? [
                'title' => '🎉 Congratulations!',
                'body' => 'Something amazing just happened - check it out!'
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, status, priority, created_at
                ) VALUES (
                    :user_id, 'niko_surprise', :title,
                    :body, 'pending', 9, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $surprise['title'],
                ':body' => $surprise['body']
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sending Niko surprise: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Chart Drop notification
     *
     * @param int $userId User ID
     * @param int $userRank User's current rank
     * @return bool Success
     * @throws Exception
     */
    public function sendChartDropAlert(int $userId, int $userRank): bool
    {
        try {
            $title = "🎵 Chart Drop - Rank #{$userRank}";
            $body = "Check where you stand in this week's chart reveal";

            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, notification_data, status,
                    priority, created_at
                ) VALUES (
                    :user_id, 'chart_drop', :title,
                    :body, :data, 'pending',
                    8, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':body' => $body,
                ':data' => json_encode(['rank' => $userRank, 'deep_link' => '/chart-drop'])
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sending chart drop alert: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send streak warning notification
     *
     * Loss aversion hook - triggers before streak expires
     *
     * @param int $userId User ID
     * @param int $streakLength Current streak length
     * @param int $hoursRemaining Hours until streak breaks
     * @return bool Success
     * @throws Exception
     */
    public function sendStreakWarning(int $userId, int $streakLength, int $hoursRemaining): bool
    {
        try {
            $title = "🔥 Don't break your {$streakLength}-day streak!";
            $body = "Check in in the next {$hoursRemaining} hours to keep it alive";

            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`push_notification_queue` (
                    user_id, notification_type, notification_title,
                    notification_body, status, priority, created_at
                ) VALUES (
                    :user_id, 'streak_warning', :title,
                    :body, 'pending', 9, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':body' => $body
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error sending streak warning: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get pending notifications for delivery
     *
     * @param int $limit Number of notifications to fetch
     * @return array Pending notifications
     * @throws Exception
     */
    public function getPendingNotifications(int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, user_id, notification_type, notification_title,
                       notification_body, notification_data, priority,
                       batched_count
                FROM `ngn_2025`.`push_notification_queue`
                WHERE status IN ('pending', 'batched')
                  AND (scheduled_for IS NULL OR scheduled_for <= NOW())
                ORDER BY priority DESC, created_at ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting pending notifications: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark notification as sent
     *
     * @param int $notificationId Notification ID
     * @return bool Success
     * @throws Exception
     */
    public function markAsSent(int $notificationId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`push_notification_queue`
                SET status = 'sent',
                    sent_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $notificationId]);
            return true;
        } catch (Exception $e) {
            error_log("Error marking notification as sent: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark notification as failed
     *
     * @param int $notificationId Notification ID
     * @param string $error Error message
     * @return bool Success
     * @throws Exception
     */
    public function markAsFailed(int $notificationId, string $error): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE `ngn_2025`.`push_notification_queue`
                SET status = 'failed',
                    delivery_error = :error
                WHERE id = :id
            ");
            $stmt->execute([
                ':error' => $error,
                ':id' => $notificationId
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error marking notification as failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get device tokens for user
     *
     * @param int $userId User ID
     * @return array Device tokens
     * @throws Exception
     */
    public function getUserDeviceTokens(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT fcm_token, platform FROM `ngn_2025`.`push_device_tokens`
                WHERE user_id = :user_id AND is_active = 1
            ");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error getting device tokens: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actually send notification via FCM (placeholder for external API)
     *
     * @param string $fcmToken FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @return bool Success
     */
    public function deliverViaFCM(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        // TODO: Implement FCM API call using $this->fcmApiKey
        // This would use the Firebase Cloud Messaging HTTP API
        // For now, this is a placeholder
        error_log("FCM delivery placeholder - would send to token: {$fcmToken}");
        return true;
    }
}
