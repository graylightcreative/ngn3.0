<?php

namespace NGN\Lib\Fans;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use DateTime;

class SubscriptionService
{
    private PDO $pdoWrite;
    private PDO $pdoFanSubs;

    public function __construct(Config $config)
    {
        $this->pdoWrite = ConnectionFactory::write($config);
        $this->pdoFanSubs = ConnectionFactory::write($config);
    }

    /**
     * Subscribes a user to a specific fan subscription tier for a given artist.
     *
     * @param int $userId The ID of the user subscribing.
     * @param int $artistId The ID of the artist the user is subscribing to.
     * @param int $tierId The ID of the subscription tier.
     * @return array{success: bool, message: string} Resulting status.
     */
    public function subscribe(int $userId, int $artistId, int $tierId): array
    {
        try {
            // Check if a subscription for this user and artist already exists and is active.
            $stmtCheck = $this->pdoFanSubs->prepare(
                "SELECT id, status, tier_id FROM `ngn_2025`.`user_fan_subscriptions`
                 WHERE `user_id` = :userId AND `artist_id` = :artistId LIMIT 1"
            );
            $stmtCheck->execute([
                ':userId' => $userId,
                ':artistId' => $artistId
            ]);
            $existingSubscription = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingSubscription) {
                if ($existingSubscription['status'] === 'active') {
                    // If already active, check if tier is higher, or just inform.
                    // For now, we'll just indicate it's already active.
                    // A more advanced feature might allow upgrades.
                    if ($existingSubscription['tier_id'] == $tierId) {
                        return ['success' => false, 'message' => 'You are already subscribed to this tier for this artist.'];
                    } else {
                        // Handle tier update or inform user.
                        // If we wanted to allow upgrades, we'd update tier_id and start_date here.
                         return ['success' => false, 'message' => 'You have an active subscription. To change tiers, please unsubscribe first or contact support.'];
                    }
                } elseif ($existingSubscription['status'] === 'cancelled' || $existingSubscription['status'] === 'expired') {
                    // If previously cancelled/expired, we can reactivate/update it.
                    $stmtUpdate = $this->pdoFanSubs->prepare(
                        "UPDATE `ngn_2025`.`user_fan_subscriptions`
                         SET `status` = 'active', `tier_id` = :tierId, `start_date` = NOW(), `end_date` = NULL, `stripe_subscription_id` = NULL
                         WHERE `id` = :subscriptionId"
                    );
                    $success = $stmtUpdate->execute([
                        ':tierId' => $tierId,
                        ':subscriptionId' => $existingSubscription['id']
                    ]);
                    return ['success' => $success, 'message' => $success ? 'Your subscription has been reactivated.' : 'Failed to reactivate subscription.'];
                }
            }

            // Insert new subscription
            // NOTE: Stripe integration for payment and subscription ID is not included in this basic method.
            // `stripe_subscription_id` is set to NULL, and `end_date` is NULL for active subscriptions.
            $stmtInsert = $this->pdoFanSubs->prepare(
                "INSERT INTO `ngn_2025`.`user_fan_subscriptions` 
                 (`user_id`, `artist_id`, `tier_id`, `status`, `start_date`, `end_date`, `stripe_subscription_id`)
                 VALUES (:userId, :artistId, :tierId, 'active', NOW(), NULL, NULL)"
            );

            $success = $stmtInsert->execute([
                ':userId' => $userId,
                ':artistId' => $artistId,
                ':tierId' => $tierId,
            ]);

            return ['success' => $success, 'message' => $success ? 'Successfully subscribed!' : 'Failed to subscribe.'];

        } catch (\Throwable $e) {
            error_log("SubscriptionService::subscribe failed for user {$userId}, artist {$artistId}, tier {$tierId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during subscription. Please try again later.'];
        }
    }

    /**
     * Unsubscribes a user from a specific artist's fan subscription.
     *
     * @param int $userId The ID of the user to unsubscribe.
     * @param int $artistId The ID of the artist the user is unsubscribing from.
     * @return array{success: bool, message: string} Resulting status.
     */
    public function unsubscribe(int $userId, int $artistId): array
    {
        try {
            // Find an active subscription for the user and artist, and mark it as cancelled.
            $stmtUpdate = $this->pdoFanSubs->prepare(
                "UPDATE `ngn_2025`.`user_fan_subscriptions` 
                 SET `status` = 'cancelled', `end_date` = NOW() 
                 WHERE `user_id` = :userId AND `artist_id` = :artistId AND `status` = 'active'"
            );

            // Execute and check if any row was affected
            $affectedRows = $stmtUpdate->execute([
                ':userId' => $userId,
                ':artistId' => $artistId
            ]);

            if ($affectedRows > 0) {
                return ['success' => true, 'message' => 'Successfully unsubscribed.'];
            } else {
                return ['success' => false, 'message' => 'You are not currently subscribed to this artist.'];
            }

        } catch (\Throwable $e) {
            error_log("SubscriptionService::unsubscribe failed for user {$userId}, artist {$artistId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during unsubscription. Please try again later.'];
        }
    }

    /**
     * Checks if a user has access to content requiring a minimum subscription tier for a specific artist.
     *
     * @param int $userId The ID of the user.
     * @param int $artistId The ID of the artist.
     * @param int $requiredTierId The minimum tier ID required for access.
     * @return bool True if the user has sufficient access, false otherwise.
     */
    public function checkAccess(int $userId, int $artistId, int $requiredTierId): bool
    {
        try {
            $stmt = $this->pdoFanSubs->prepare(
                "SELECT `tier_id` FROM `ngn_2025`.`user_fan_subscriptions`
                 WHERE `user_id` = :userId AND `artist_id` = :artistId AND `status` = 'active'"
            );
            $stmt->execute([
                ':userId' => $userId,
                ':artistId' => $artistId
            ]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription && $subscription['tier_id'] >= $requiredTierId) {
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            error_log("SubscriptionService::checkAccess failed for user {$userId}, artist {$artistId}, required tier {$requiredTierId}: " . $e->getMessage());
            return false; // Fail safe: deny access on error
        }
    }

    /**
     * Gets the current subscription status for a user and artist.
     *
     * @param int $userId The ID of the user.
     * @param int $artistId The ID of the artist.
     * @return array{tier_id: int|null, status: string, message: string} Subscription details.
     */
    public function getStatus(int $userId, int $artistId): array
    {
        try {
            $stmt = $this->pdoFanSubs->prepare(
                "SELECT `tier_id`, `status` FROM `ngn_2025`.`user_fan_subscriptions`
                 WHERE `user_id` = :userId AND `artist_id` = :artistId LIMIT 1"
            );
            $stmt->execute([
                ':userId' => $userId,
                ':artistId' => $artistId
            ]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription) {
                return [
                    'tier_id' => (int)$subscription['tier_id'],
                    'status' => $subscription['status'],
                    'message' => 'Subscription found.'
                ];
            } else {
                return [
                    'tier_id' => null,
                    'status' => 'not_subscribed',
                    'message' => 'No active subscription found.'
                ];
            }
        } catch (\Throwable $e) {
            error_log("SubscriptionService::getStatus failed for user {$userId}, artist {$artistId}: " . $e->getMessage());
            return ['tier_id' => null, 'status' => 'error', 'message' => 'An error occurred while fetching subscription status.'];
        }
    }
}