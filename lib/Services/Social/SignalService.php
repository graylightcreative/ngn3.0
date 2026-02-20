<?php
namespace NGN\Lib\Services\Social;

/**
 * Signal Service - Event-Driven Triggers
 * Unified fleet node for broadcasting platform events and triggers.
 * Bible Ref: Chapter 50 (Signal Node)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Notifications\PushNotificationService;
use PDO;

class SignalService
{
    private $config;
    private $pdo;
    private $pushService;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
        $this->pushService = new PushNotificationService($config);
    }

    /**
     * Broadcast a platform signal
     * 
     * @param string $type The signal type (e.g., 'artist.breakout', 'revenue.spike')
     * @param array $data Contextual payload for the signal
     * @param int|null $targetUserId Optional target for directed signals
     */
    public function broadcast(string $type, array $data, ?int $targetUserId = null): bool
    {
        // 1. Log the signal in the immutable audit registry
        $this->logSignal($type, $data, $targetUserId);

        // 2. Trigger Push Notifications if applicable
        if ($targetUserId) {
            $this->pushService->sendPush(
                $targetUserId, 
                $data['title'] ?? 'NGN System Alert',
                $data['body'] ?? 'Action required in your dashboard.',
                $data['url'] ?? '/'
            );
        }

        // 3. Optional: Trigger Webhooks for external fleet nodes (e.g., Boardroom Enforcer)
        return true;
    }

    private function logSignal(string $type, array $data, ?int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO fleet_signals (signal_type, payload, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$type, json_encode($data), $userId]);
    }
}
