<?php

namespace NGN\Lib\Services\Automation;

use PDO;
use Exception;
use Monolog\Logger;

/**
 * WebhookService
 * 
 * Handles B2B event notifications for labels and distributors.
 * Supports payload signing and delivery logging.
 */
class WebhookService
{
    private PDO $pdo;
    private Logger $logger;

    public function __construct(PDO $pdo, Logger $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Dispatch an event to all active subscribers
     */
    public function dispatch(string $eventType, array $payload, ?int $userId = null): void
    {
        try {
            $query = "SELECT * FROM webhook_subscriptions WHERE event_type = ? AND status = 'active'";
            $params = [$eventType];

            if ($userId) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($subscriptions as $sub) {
                $this->deliver($sub, $eventType, $payload);
            }

        } catch (Exception $e) {
            $this->logger->error('webhook_dispatch_failed', [
                'event' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Deliver payload to a specific target URL
     */
    private function deliver(array $subscription, string $eventType, array $payload): void
    {
        $startTime = microtime(true);
        $targetUrl = $subscription['target_url'];
        $secretKey = $subscription['secret_key'];

        // Generate signature: HMAC-SHA256 of payload
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload, $secretKey);

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-NGN-Event: ' . $eventType,
            'X-NGN-Signature: ' . $signature,
            'User-Agent: NGN-Webhook-Dispatcher/2.1'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $responseBody = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = (int)((microtime(true) - $startTime) * 1000);
        curl_close($ch);

        $status = ($responseCode >= 200 && $responseCode < 300) ? 'success' : 'failed';

        // Log delivery attempt
        $logStmt = $this->pdo->prepare("
            INSERT INTO webhook_delivery_logs (
                subscription_id, event_type, payload, response_code, response_body, duration_ms, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $logStmt->execute([
            $subscription['id'],
            $eventType,
            $jsonPayload,
            $responseCode,
            $responseBody,
            $duration,
            $status
        ]);

        if ($status === 'failed') {
            $this->logger->warning('webhook_delivery_failed', [
                'sub_id' => $subscription['id'],
                'url' => $targetUrl,
                'code' => $responseCode
            ]);
        }
    }

    /**
     * Subscribe a user to an event
     */
    public function subscribe(int $userId, string $url, string $eventType): int
    {
        $secret = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_subscriptions (user_id, target_url, event_type, secret_key)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $url, $eventType, $secret]);
        return (int)$this->pdo->lastInsertId();
    }
}
