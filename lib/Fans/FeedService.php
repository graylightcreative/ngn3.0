<?php

namespace NGN\Lib\Fans;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use DateTime;

class FeedService
{
    private PDO $pdoPrimary;
    private PDO $pdoFanSubs;

    public function __construct(Config $config)
    {
        // Primary DB for Users table (if needed for user lookup)
        $this->pdoPrimary = ConnectionFactory::read($config);
        // DB connection for fan subscriptions (as per migration file)
        $this->pdoFanSubs = ConnectionFactory::named($config, 'rankings2025');
    }

    /**
     * Retrieves the fan's feed, determining which posts are locked based on their subscription tier.
     *
     * @param int $fanUserId The ID of the fan user.
     * @return array An array of posts, each with an `is_locked` flag and `unlock_tier` info.
     */
    public function getFeed(int $fanUserId): array
    {
        $feed = [];
        try {
            // Get the user's active subscription tiers. We'll take the highest tier they have.
            $stmtUserTier = $this->pdoFanSubs->prepare(
                "SELECT MAX(tier_id) as max_tier FROM `user_fan_subscriptions` 
                 WHERE `user_id` = :userId AND `status` = 'active'"
            );
            $stmtUserTier->execute([':userId' => $fanUserId]);
            $userMaxTier = $stmtUserTier->fetchColumn();
            $userMaxTier = $userMaxTier ? (int)$userMaxTier : 0; // 0 means no subscription or tier not applicable

            // Fetch posts, joining with subscriptions to check access per artist.
            // We need to ensure posts are linked to artists. Assuming `posts.user_id` is the artist ID.
            // The join condition `ufs.user_id = :fanUserId` ensures we're checking the *fan's* subscription.
            $stmtFeed = $this->pdoFanSubs->prepare(
                "SELECT 
                    p.*,
                    COALESCE(ufs.tier_id, 0) AS fan_tier_id, 
                    -- We need to determine the required tier for the post, which comes from the artist's tier settings
                    -- For now, let's assume `posts.required_tier_id` directly stores this.
                    p.required_tier_id AS post_required_tier_id 
                 FROM `posts` p 
                 LEFT JOIN `user_fan_subscriptions` ufs ON p.user_id = ufs.artist_id AND ufs.user_id = :fanUserId AND ufs.status = 'active'
                 ORDER BY p.created_at DESC"
            );
            $stmtFeed->execute([':fanUserId' => $fanUserId]);
            $rawFeed = $stmtFeed->fetchAll(PDO::FETCH_ASSOC);

            // Process feed items to determine locked status
            foreach ($rawFeed as $post) {
                $requiredTier = (int)$post['post_required_tier_id'];
                $isLocked = false;
                $unlockTierName = '';

                if ($requiredTier > 0 && $userMaxTier < $requiredTier) {
                    $isLocked = true;
                    // Fetch tier name for display
                    $stmtTierName = $this->pdoFanSubs->prepare("SELECT name FROM `fan_subscription_tiers` WHERE id = :tierId LIMIT 1");
                    $stmtTierName->execute([':tierId' => $requiredTier]);
                    $tierName = $stmtTierName->fetchColumn();
                    $unlockTierName = $tierName ?: 'Tier ' . $requiredTier; // Fallback name
                }

                $feed[] = [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'content' => $isLocked ? '********' : $post['content'], // Redact content if locked
                    'media_url' => $isLocked ? null : $post['media_url'], // Redact media if locked
                    'created_at' => $post['created_at'],
                    'is_locked' => $isLocked,
                    'unlock_tier_name' => $isLocked ? $unlockTierName : '',
                    'artist_id' => $post['user_id'] // Assuming post.user_id is the artist_id for subscription checks
                ];
            }

        } catch (\Throwable $e) {
            error_log("FeedService::getFeed failed for user {$fanUserId}: " . $e->getMessage());
            // Return an empty feed or an error structure if needed.
            return [];
        }

        return $feed;
    }

    // Helper to get tier name from ID, could be cached or fetched from tier table
    // This is illustrative; the actual retrieval is done within getFeed for now.
    private function getTierName(int $tierId): string
    {
        // In a real app, fetch this from the fan_subscription_tiers table.
        // For demonstration, simple mapping:
        switch ($tierId) {
            case 1: return 'Bronze';
            case 2: return 'Silver';
            case 3: return 'Gold';
            default: return 'Unknown Tier';
        }
    }
}
