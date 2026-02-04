<?php

namespace NGN\Lib\Messaging;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use DateTime;

class MessageService
{
    private PDO $pdoPrimary;

    public function __construct(Config $config)
    {
        // Assuming messages are stored in the primary database or a common one.
        // If a separate DB is used, adjust ConnectionFactory::named accordingly.
        $this->pdoPrimary = ConnectionFactory::read($config);
    }

    /**
     * Sends a direct message from one user to another.
     *
     * @param int $senderId The ID of the sender.
     * @param int $receiverId The ID of the recipient.
     * @param string $body The message content.
     * @return bool True on success, false on failure.
     */
    public function sendMessage(int $senderId, int $receiverId, string $body): bool
    {
        if (empty($body)) {
            return false; // Cannot send an empty message
        }

        try {
            $stmt = $this->pdoPrimary->prepare(
                "INSERT INTO `direct_messages` 
                 (`sender_id`, `receiver_id`, `body`, `is_read`, `created_at`)
                 VALUES (:senderId, :receiverId, :body, 0, NOW())"
            );

            return $stmt->execute([
                ':senderId' => $senderId,
                ':receiverId' => $receiverId,
                ':body' => $body,
            ]);

        } catch (\Throwable $e) {
            error_log("MessageService::sendMessage failed from {$senderId} to {$receiverId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all messages in a conversation thread between two users.
     *
     * @param int $user1Id The ID of the first user in the conversation.
     * @param int $user2Id The ID of the second user in the conversation.
     * @return array An array of messages, ordered by creation date.
     */
    public function getThread(int $user1Id, int $user2Id): array
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                "SELECT * FROM `direct_messages`
                 WHERE (`sender_id` = :user1Id AND `receiver_id` = :user2Id)
                 OR (`sender_id` = :user2Id AND `receiver_id` = :user1Id)
                 ORDER BY `created_at` ASC"
            );
            $stmt->execute([
                ':user1Id' => $user1Id,
                ':user2Id' => $user2Id,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log("MessageService::getThread failed for users {$user1Id} and {$user2Id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves the latest message for each unique conversation involving a user (the inbox).
     *
     * @param int $userId The ID of the user whose inbox is being fetched.
     * @return array An array of latest messages per conversation.
     */
    public function getInbox(int $userId): array
    {
        try {
            // This query finds the latest message for each conversation where the user is either sender or receiver.
            // It uses a subquery to get the max ID for each conversation pair (user1, user2).
            $stmt = $this->pdoPrimary->prepare(
                "SELECT dm.* FROM `direct_messages` dm
                 INNER JOIN (
                     SELECT MAX(id) as max_id
                     FROM `direct_messages`
                     WHERE `sender_id` = :userId OR `receiver_id` = :userId
                     GROUP BY LEAST(`sender_id`, `receiver_id`), GREATEST(`sender_id`, `receiver_id`)
                 ) AS last_messages ON dm.id = last_messages.max_id
                 ORDER BY dm.created_at DESC"
            );
            $stmt->execute([':userId' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log("MessageService::getInbox failed for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marks messages in a thread as read for a specific user.
     *
     * @param int $userId The ID of the user for whom messages should be marked as read.
     * @param int $otherUserId The ID of the other participant in the conversation.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function markThreadAsRead(int $userId, int $otherUserId): bool
    {
        try {
            $stmt = $this->pdoPrimary->prepare(
                "UPDATE `direct_messages` 
                 SET `is_read` = 1 
                 WHERE `receiver_id` = :userId AND `sender_id` = :otherUserId AND `is_read` = 0"
            );
            return $stmt->execute([
                ':userId' => $userId,
                ':otherUserId' => $otherUserId
            ]);
        } catch (\Throwable $e) {
            error_log("MessageService::markThreadAsRead failed for user {$userId} with {$otherUserId}: " . $e->getMessage());
            return false;
        }
    }
}
