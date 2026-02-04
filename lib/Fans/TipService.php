<?php

namespace NGN\Lib\Fans;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use DateTime;
// Assuming SparkService and GamificationService are available for injection
// use NGN\Lib\Commerce\SparkService; // Mocked for now
// use NGN\Lib\Fans\GamificationService; // To be injected

// Mocking SparkService for demonstration if it doesn't exist
if (!class_exists('MockSparkService')) {
    class MockSparkService {
        public function charge($userId, $amount, $description) {
            // Simulate a successful charge
            error_log("MockSparkService: Charging user {$userId} {$amount} for {$description}");
            return ['success' => true, 'transaction_id' => uniqid()];
        }
    }
}

class TipService
{
    private PDO $pdoPrimary;
    private PDO $pdoFanSubs; // Assuming tips are logged in the same DB as subscriptions for now
    private MockSparkService $sparkService; // Injected
    private GamificationService $gamificationService; // Injected

    public function __construct(Config $config, MockSparkService $sparkService, GamificationService $gamificationService)
    {
        $this->pdoPrimary = ConnectionFactory::read($config);
        $this->pdoFanSubs = ConnectionFactory::named($config, 'rankings2025'); // Or a specific DB for tips
        $this->sparkService = $sparkService;
        $this->gamificationService = $gamificationService;
    }

    /**
     * Sends a tip from a fan to an artist.
     *
     * @param int $fanUserId The ID of the fan sending the tip.
     * @param int $artistId The ID of the artist receiving the tip.
     * @param int $amount The amount of sparks to tip.
     * @param int|null $postId The ID of the post the tip is related to (optional).
     * @return array{success: bool, message: string} Resulting status.
     */
    public function sendTip(int $fanUserId, int $artistId, int $amount, ?int $postId = null): array
    {
        if ($amount < 1) {
            return ['success' => false, 'message' => 'Tip amount must be at least 1 Spark.'];
        }

        // Simulate charging sparks
        $chargeResult = $this->sparkService->charge($fanUserId, $amount, 'artist_tip');

        if ($chargeResult['success']) {
            try {
                // Log the tip received
                // Assuming artist_tips table exists with user_id, artist_id, amount, post_id, created_at
                $stmtLog = $this->pdoFanSubs->prepare(
                    "INSERT INTO `artist_tips`
                     (`user_id`, `artist_id`, `amount`, `post_id`, `created_at`)
                     VALUES (:userId, :artistId, :amount, :postId, NOW())"
                );
                $stmtLog->execute([
                    ':userId' => $fanUserId,
                    ':artistId' => $artistId,
                    ':amount' => $amount,
                    ':postId' => $postId,
                ]);

                // Award gamification points for the tip
                // The action string for gamification should reflect the source or type of points
                $gamificationSuccess = $this->gamificationService->awardPoints($fanUserId, $artistId, 'tip', $amount);
                if (!$gamificationSuccess) {
                    error_log("Gamification points not awarded for tip from user {$fanUserId} to artist {$artistId}");
                    // Decide if this should prevent tip success message or be a soft failure
                }


                return ['success' => true, 'message' => 'Tip sent successfully!'];

            } catch (\Throwable $e) {
                error_log("TipService::sendTip failed to log tip for user {$fanUserId}, artist {$artistId}: " . $e->getMessage());
                // Rollback spark charge if logging fails? That's complex. For now, assume logging failure is logged and tip is considered sent.
                return ['success' => false, 'message' => 'Tip sent, but an error occurred during logging. Please check your profile.'];
            }
        } else {
            // Spark charge failed (e.g., insufficient funds)
            // The mock SparkService always returns success, but in reality, it would return an error.
            // If SparkService returned ['success' => false, 'message' => 'Insufficient Spark balance.'], we'd return that.
             return ['success' => false, 'message' => $chargeResult['message'] ?? 'Failed to process tip due to insufficient Spark balance.'];
        }
    }

    // Method to get user score, if needed later
    public function getStatus(int $userId): array
    {
        // Placeholder implementation
        return ['user_id' => $userId, 'status' => 'active'];
    }
}
