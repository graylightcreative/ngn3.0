<?php
namespace NGN\Lib\Services\Notifications;

/**
 * PWA Push Notification Service
 * Handles Web Push subscription management and notification dispatch.
 * Bible Ref: Chapter 51 (App-A-Day Protocol)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class PushNotificationService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Store a new Web Push subscription for a user
     */
    public function saveSubscription(int $userId, array $subscriptionData): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        
        return $stmt->execute([
            $userId,
            $subscriptionData['endpoint'],
            $subscriptionData['keys']['p256dh'],
            $subscriptionData['keys']['auth']
        ]);
    }

    /**
     * Trigger a push notification to a specific user
     */
    public function sendPush(int $userId, string $title, string $body, string $url = '/'): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subs)) {
            return ['status' => 'no_subscriptions'];
        }

        // Real implementation would use Minishlink/WebPush to send payloads
        return [
            'status' => 'queued',
            'sub_count' => count($subs),
            'payload' => [
                'title' => $title,
                'body' => $body,
                'data' => ['url' => $url]
            ]
        ];
    }
}
