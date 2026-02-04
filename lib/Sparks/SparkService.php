<?php

namespace NGN\Lib\Sparks;

use Exception;

class SparkService
{
    private SparkRepository $sparkRepository;

    public function __construct(SparkRepository $sparkRepository)
    {
        $this->sparkRepository = $sparkRepository;
    }

    /**
     * Get the current Spark balance for a user.
     *
     * @param int $userId The ID of the user.
     * @return int The current Spark balance.
     * @throws Exception If the balance cannot be retrieved.
     */
    public function getUserSparkBalance(int $userId): int
    {
        try {
            return $this->sparkRepository->getUserSparkBalance($userId);
        } catch (Exception $e) {
            // Log the exception for debugging purposes
            error_log("Error in SparkService::getUserSparkBalance: " . $e->getMessage());
            throw new Exception("Could not retrieve Spark balance for user ID: " . $userId);
        }
    }

    /**
     * Deduct Sparks from a user's balance.
     *
     * @param int $userId The ID of the user.
     * @param int $amount The amount of Sparks to deduct.
     * @param string $reason The reason for the deduction.
     * @param array $metadata Optional metadata for the transaction.
     * @return bool True on success.
     * @throws InsufficientFundsException If the user has insufficient funds.
     * @throws Exception If the deduction fails for other reasons.
     */
    public function deductSparks(int $userId, int $amount, string $reason, array $metadata = []): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Deduction amount must be positive.");
        }

        $currentBalance = $this->getUserSparkBalance($userId);
        if ($currentBalance < $amount) {
            throw new InsufficientFundsException("Insufficient Sparks. Available: {$currentBalance}, Needed: {$amount}.");
        }

        try {
            $result = $this->sparkRepository->recordSparkTransaction($userId, 'debit', $amount, $reason, $metadata);

            // Award XP for sending sparks (Retention System - Chapter 23)
            if ($reason === 'spark_sent' || $reason === 'tip' && $result) {
                try {
                    $this->awardSparkXP($userId, $amount, 'sent');
                } catch (Exception $e) {
                    error_log("Warning: Failed to award spark XP for sender: " . $e->getMessage());
                    // Don't fail spark deduction if XP awarding fails
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error in SparkService::deductSparks: " . $e->getMessage());
            throw new Exception("Could not deduct Sparks for user ID: " . $userId);
        }
    }

    /**
     * Credit Sparks to a user's balance.
     *
     * @param int $userId The ID of the user.
     * @param int $amount The amount of Sparks to credit.
     * @param string $reason The reason for the credit.
     * @param array $metadata Optional metadata for the transaction.
     * @return bool True on success.
     * @throws Exception If the credit fails.
     */
    public function creditSparks(int $userId, int $amount, string $reason, array $metadata = []): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be positive.");
        }

        try {
            $result = $this->sparkRepository->recordSparkTransaction($userId, 'credit', $amount, $reason, $metadata);

            // Award XP for receiving sparks (Retention System - Chapter 23)
            if ($reason === 'spark_received' || $reason === 'spark_sent_to_you') {
                try {
                    $this->awardSparkXP($userId, $amount, 'received');

                    // Queue Spark ping notification (frequency over volume)
                    $senderName = $metadata['sender_name'] ?? 'A supporter';
                    $this->queueSparkPingNotification($userId, $amount, $senderName);
                } catch (Exception $e) {
                    error_log("Warning: Failed to award spark XP: " . $e->getMessage());
                    // Don't fail spark credit if XP awarding fails
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error in SparkService::creditSparks: " . $e->getMessage());
            throw new Exception("Could not credit Sparks for user ID: " . $userId);
        }
    }

    /**
     * Award XP for spark transactions (Retention System)
     *
     * @param int $userId User ID
     * @param int $sparkAmount Amount of sparks
     * @param string $direction 'sent' or 'received'
     */
    private function awardSparkXP(int $userId, int $sparkAmount, string $direction): void
    {
        try {
            // Get PDO connection from repository
            $reflection = new \ReflectionClass($this->sparkRepository);
            $property = $reflection->getProperty('pdo');
            $property->setAccessible(true);
            $pdo = $property->getValue($this->sparkRepository);

            $retentionService = new \NGN\Lib\Retention\RetentionService($pdo);

            if ($direction === 'received') {
                // 15 XP per spark received
                $retentionService->awardXP($userId, $sparkAmount * 15, 'sparks');
            } else {
                // 1 XP per spark sent
                $retentionService->awardXP($userId, $sparkAmount, 'sparks');
            }
        } catch (Exception $e) {
            error_log("Error awarding spark XP: " . $e->getMessage());
        }
    }

    /**
     * Queue Spark ping notification (frequency over volume)
     *
     * @param int $userId Recipient user ID
     * @param int $sparkAmount Amount of sparks
     * @param string $senderName Name of sender
     */
    private function queueSparkPingNotification(int $userId, int $sparkAmount, string $senderName): void
    {
        try {
            // Get PDO connection from repository
            $reflection = new \ReflectionClass($this->sparkRepository);
            $property = $reflection->getProperty('pdo');
            $property->setAccessible(true);
            $pdo = $property->getValue($this->sparkRepository);

            $pushService = new \NGN\Lib\Retention\PushNotificationService($pdo);
            $pushService->queueSparkPing($userId, $sparkAmount, $senderName);
        } catch (Exception $e) {
            error_log("Error queueing spark ping: " . $e->getMessage());
        }
    }
}