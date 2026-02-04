<?php
namespace NGN\Lib\Sparks;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Sparks\Exception\InsufficientFundsException;
use PDO;

class SparksService
{
    private PDO $read;
    private PDO $write;

    public function __construct(Config $config)
    {
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }

    public function getBalance(int $userId): int
    {
        $sql = "SELECT SUM(change_sparks) AS balance FROM `ngn_2025`.`user_sparks_ledger` WHERE user_id = :user_id";
        $stmt = $this->read->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['balance'] ?? 0);
    }

    public function add(int $userId, int $amount, string $reason, ?array $metadata = null): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be positive.");
        }

        $sql = "INSERT INTO `ngn_2025`.`user_sparks_ledger` (user_id, change_sparks, reason, metadata) 
                VALUES (:user_id, :change_sparks, :reason, :metadata)";
        
        $stmt = $this->write->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':change_sparks', $amount, PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':metadata', $metadata ? json_encode($metadata) : null);

        return $stmt->execute();
    }

    public function deduct(int $userId, int $amount, string $reason, ?array $metadata = null): int
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount to deduct must be positive.");
        }

        try {
            $this->write->beginTransaction();

            $sql = "SELECT SUM(change_sparks) AS balance FROM `ngn_2025`.`user_sparks_ledger` WHERE user_id = :user_id FOR UPDATE";
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $currentBalance = (int)($stmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

            if ($currentBalance < $amount) {
                throw new InsufficientFundsException("Insufficient sparks balance.");
            }

            $sql = "INSERT INTO `ngn_2025`.`user_sparks_ledger` (user_id, change_sparks, reason, metadata) 
                    VALUES (:user_id, :change_sparks, :reason, :metadata)";
            
            $stmt = $this->write->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':change_sparks', -$amount, PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason);
            $stmt->bindValue(':metadata', $metadata ? json_encode($metadata) : null);
            $stmt->execute();

            $newBalance = $currentBalance - $amount;

            $this->write->commit();

            return $newBalance;
        } catch (\Throwable $e) {
            $this->write->rollBack();
            // Re-throw the original exception to be handled by the caller
            throw $e;
        }
    }
}
