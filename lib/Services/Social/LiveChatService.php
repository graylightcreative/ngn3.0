<?php
namespace NGN\Lib\Services\Social;

/**
 * Live Chat Service (Messenger Node Integration)
 * Low-latency event chat infrastructure.
 * Bible Ref: Chapter 14 (Messenger)
 */

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

class LiveChatService
{
    private $config;
    private $pdo;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::read($config);
    }

    /**
     * Post a message to an event's live chat
     */
    public function sendMessage(string $eventId, int $userId, string $message): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO event_chat_messages (event_id, user_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $eventId,
            $userId,
            strip_tags($message)
        ]);
    }

    /**
     * Retrieve recent chat messages for an event
     */
    public function getMessages(string $eventId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.display_name, u.avatar_url 
            FROM event_chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.event_id = ?
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$eventId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
