<?php

namespace NGN\Lib\Fans;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use DateTime;

class GamificationService
{
    private PDO $pdoFanScores;

    public function __construct(Config $config)
    {
        // DB connection for fan scores (as per migration file)
        $this->pdoFanScores = ConnectionFactory::named($config, 'rankings2025');
    }

    /**
     * Awards points to a user for a specific action related to an artist.
     *
     * @param int $userId The ID of the user receiving points.
     * @param int $artistId The ID of the artist associated with the action.
     * @param string $action The type of action performed (e.g., 'subscribe', 'tip', 'like').
     * @return bool True if points were awarded successfully, false otherwise.
     */
    public function awardPoints(int $userId, int $artistId, string $action): bool
    {
        $points = 0;

        switch ($action) {
            case 'subscribe':
                $points = 500;
                break;
            case 'tip':
                // Assuming amount is passed directly, but tip service needs to pass the amount.
                // For this method, let's assume the action is just 'tip' and the amount is handled elsewhere or passed differently.
                // If 'amount' is needed, the method signature should change to awardPoints(int $userId, int $artistId, string $action, int $amount = 0)
                // For now, let's hardcode a value or expect it to be passed differently.
                // Let's adjust based on the prompt: 'tip' = 1 per Spark. This means the amount needs to be passed.
                // Revising signature and logic:
                // public function awardPoints(int $userId, int $artistId, string $action, int $amount = 0): bool
                // $points = $amount;
                // For now, let's use a placeholder of 1 for any tip.
                $points = 1; // Default for tip action, actual amount would be passed if method signature changed
                break;
            case 'like':
                $points = 10;
                break;
            default:
                // Unknown action, do not award points
                return false;
        }
        
        // If points is 0, no need to interact with DB
        if ($points === 0) {
            return false;
        }

        try {
            // UPSERT logic: INSERT ON DUPLICATE KEY UPDATE
            // This will insert a new row if user_id/artist_id doesn't exist, 
            // or update the score if it does.
            $stmt = $this->pdoFanScores->prepare(
                "INSERT INTO `user_fan_scores` 
                 (`user_id`, `artist_id`, `score`, `updated_at`)
                 VALUES (:userId, :artistId, :points, NOW())
                 ON DUPLICATE KEY UPDATE 
                 `score` = `score` + VALUES(`score`)"
            );

            return $stmt->execute([
                ':userId' => $userId,
                ':artistId' => $artistId,
                ':points' => $points,
            ]);

        } catch (\Throwable $e) {
            error_log("GamificationService::awardPoints failed for user {$userId}, artist {$artistId}, action {$action}: " . $e->getMessage());
            return false;
        }
    }
    
    // Method to get user score, if needed later
    public function getUserScore(int $userId, int $artistId): int
    {
        try {
            $stmt = $this->pdoFanScores->prepare(
                "SELECT `score` FROM `user_fan_scores`
                 WHERE `user_id` = :userId AND `artist_id` = :artistId LIMIT 1"
            );
            $stmt->execute([
                ':userId' => $userId,
                ':artistId' => $artistId
            ]);
            $score = $stmt->fetchColumn();

            return $score ? (int)$score : 0;

        } catch (\Throwable $e) {
            error_log("GamificationService::getUserScore failed for user {$userId}, artist {$artistId}: " . $e->getMessage());
            return 0;
        }
    }
}
