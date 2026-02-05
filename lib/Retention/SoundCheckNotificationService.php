<?php

namespace NGN\Lib\Retention;

use PDO;
use Exception;
use DateTime;

/**
 * Sound Check Notification Service
 *
 * Manages Sound Check events and triggers targeted notifications for artists.
 * Handles preference filtering, iOS-specific payload configuration, and history tracking.
 *
 * Bible Ch. 15: iOS Sound Check notifications for artist engagement
 */
class SoundCheckNotificationService
{
    private PDO $pdo;
    private PushNotificationService $pushService;

    // Configuration
    private const BATCH_WINDOW_MINUTES = 5;
    private const HIGH_PRIORITY = 9;
    private const DEFAULT_PRIORITY = 5;

    public function __construct(PDO $pdo, PushNotificationService $pushService)
    {
        $this->pdo = $pdo;
        $this->pushService = $pushService;
    }

    /**
     * Record a Sound Check event and trigger notification if appropriate
     */
    public function recordEvent(int $artistId, string $status, array $metadata = []): int
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Log the event
            $stmt = $this->pdo->prepare("
                INSERT INTO `ngn_2025`.`sound_check_events` (
                    artist_id, status, metadata, created_at
                ) VALUES (
                    :artist_id, :status, :metadata, NOW()
                )
            ");
            $stmt->execute([
                ':artist_id' => $artistId,
                ':status' => $status,
                ':metadata' => json_encode($metadata)
            ]);
            $eventId = (int)$this->pdo->lastInsertId();

            // 2. Check preferences
            $prefs = $this->getPreferences($artistId);
            
            $shouldNotify = $prefs['notifications_enabled'] && (
                ($status === 'started' && $prefs['notify_on_started']) ||
                ($status === 'completed' && $prefs['notify_on_completed']) ||
                ($status === 'failed' && $prefs['notify_on_failed'])
            );

            if ($shouldNotify) {
                $this->queueNotification($artistId, $eventId, $status, $metadata, $prefs);
            }

            $this->pdo->commit();
            return $eventId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error recording Sound Check event: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get or create notification preferences for an artist
     */
    public function getPreferences(int $artistId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`sound_check_preferences`
            WHERE artist_id = :artist_id
        ");
        $stmt->execute([':artist_id' => $artistId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            // Default preferences
            return [
                'artist_id' => $artistId,
                'notifications_enabled' => 1,
                'notify_on_started' => 0,
                'notify_on_completed' => 1,
                'notify_on_failed' => 1,
                'ios_haptic_enabled' => 1
            ];
        }

        return $prefs;
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(int $artistId, array $updates): bool
    {
        $current = $this->getPreferences($artistId);
        $fields = array_intersect_key($updates, $current);
        
        if (empty($fields)) return false;

        $setParts = [];
        $params = [':artist_id' => $artistId];
        
        foreach ($fields as $key => $value) {
            $setParts[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }

        $sql = "
            INSERT INTO `ngn_2025`.`sound_check_preferences` (" . implode(', ', array_keys($fields)) . ", artist_id)
            VALUES (" . implode(', ', array_map(fn($k) => ":$k", array_keys($fields))) . ", :artist_id)
            ON DUPLICATE KEY UPDATE " . implode(', ', $setParts)
        ;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Queue a notification for later delivery
     */
    private function queueNotification(int $artistId, int $eventId, string $status, array $metadata, array $prefs): void
    {
        $title = "Sound Check " . ucfirst($status);
        $body = $this->generateNotificationBody($status, $metadata);
        
        $priority = ($status === 'failed') ? self::HIGH_PRIORITY : self::DEFAULT_PRIORITY;

        $stmt = $this->pdo->prepare("
            INSERT INTO `ngn_2025`.`sound_check_notifications` (
                artist_id, event_id, notification_title, notification_body,
                status, priority, created_at
            ) VALUES (
                :artist_id, :event_id, :title, :body,
                'queued', :priority, NOW()
            )
        ");

        $stmt->execute([
            ':artist_id' => $artistId,
            ':event_id' => $eventId,
            ':title' => $title,
            ':body' => $body,
            ':priority' => $priority
        ]);
    }

    /**
     * Generate notification body text based on status
     */
    private function generateNotificationBody(string $status, array $metadata): string
    {
        switch ($status) {
            case 'started':
                return "Your Sound Check has begun. We'll notify you when it's complete.";
            case 'completed':
                $score = $metadata['score'] ?? 'N/A';
                return "Sound Check complete! Your track scored {$score}. View the breakdown now.";
            case 'failed':
                $reason = $metadata['reason'] ?? 'unknown error';
                return "Sound Check failed: {$reason}. Please check your audio levels and try again.";
            default:
                return "Sound Check status update: {$status}";
        }
    }

    /**
     * Process queued notifications (Batching)
     * To be called by cron job
     */
    public function processQueue(): int
    {
        // 1. Find recent queued notifications that haven't been processed
        // We implement 5-minute batching logic here
        $stmt = $this->pdo->prepare("
            SELECT * FROM `ngn_2025`.`sound_check_notifications`
            WHERE status = 'queued'
              AND created_at <= DATE_SUB(NOW(), INTERVAL :window MINUTE)
        ");
        $stmt->bindValue(':window', self::BATCH_WINDOW_MINUTES, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($notifications as $note) {
            try {
                $prefs = $this->getPreferences($note['artist_id']);
                
                // Fetch associated user ID for the artist
                $userId = $this->getUserIdForArtist($note['artist_id']);
                
                if (!$userId) {
                    $this->updateNotificationStatus($note['id'], 'failed', 'User not found for artist');
                    continue;
                }

                // Prepare platform-specific data
                $data = [
                    'deep_link' => '/sound-check/' . $note['event_id'],
                    'event_id' => $note['event_id']
                ];

                if ($prefs['ios_haptic_enabled']) {
                    $data['ios_haptic'] = 'heavy';
                }

                // Deliver via push service
                $success = $this->pushService->sendPush(
                    $userId,
                    $note['notification_title'],
                    $note['notification_body'],
                    $data,
                    $note['priority']
                );

                if ($success) {
                    $this->updateNotificationStatus($note['id'], 'sent');
                    $sentCount++;
                } else {
                    $this->updateNotificationStatus($note['id'], 'failed', 'Push service rejected notification');
                }
            } catch (Exception $e) {
                $this->updateNotificationStatus($note['id'], 'failed', $e->getMessage());
            }
        }

        return $sentCount;
    }

    private function getUserIdForArtist(int $artistId): ?int
    {
        // Assuming a mapping exists, adjust table name if different
        $stmt = $this->pdo->prepare("SELECT user_id FROM `ngn_2025`.`artists` WHERE id = :id");
        $stmt->execute([':id' => $artistId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['user_id'] : null;
    }

    private function updateNotificationStatus(int $id, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE `ngn_2025`.`sound_check_notifications`
            SET status = :status,
                sent_at = CASE WHEN :status = 'sent' THEN NOW() ELSE sent_at END,
                error_message = :error
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':error' => $error,
            ':id' => $id
        ]);
    }
}
