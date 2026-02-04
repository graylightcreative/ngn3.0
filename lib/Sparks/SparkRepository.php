<?php

namespace NGN\Lib\Sparks;

use PDO;
use Exception;

class SparkRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
        $stmt = $this->pdo->prepare("
            SELECT SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) AS balance
            FROM ngn_2025.user_sparks_ledger
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['balance'] ?? 0);
    }

    /**
     * Record a Spark transaction (credit or debit).
     *
     * @param int $userId The ID of the user.
     * @param string $transactionType 'credit' or 'debit'.
     * @param int $amount The amount of Sparks.
     * @param string $reason The reason for the transaction.
     * @param array $metadata Optional metadata for the transaction.
     * @return bool True on success, false on failure.
     * @throws Exception If the transaction fails.
     */
    public function recordSparkTransaction(
        int $userId,
        string $transactionType,
        int $amount,
        string $reason,
        array $metadata = []
    ): bool {
        if (!in_array($transactionType, ['credit', 'debit'])) {
            throw new Exception("Invalid transaction type. Must be 'credit' or 'debit'.");
        }

        if ($amount <= 0) {
            throw new Exception("Amount must be a positive integer.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.user_sparks_ledger (user_id, transaction_type, amount, reason, metadata, created_at)
            VALUES (:user_id, :transaction_type, :amount, :reason, :metadata, NOW())
        ");

        return $stmt->execute([
            ':user_id' => $userId,
            ':transaction_type' => $transactionType,
            ':amount' => $amount,
            ':reason' => $reason,
            ':metadata' => json_encode($metadata)
        ]);
    }
}
