<?php

namespace NGN\Lib\Feed;

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;

/**
 * SeedVisibilityService
 *
 * Manages 5% random non-follower distribution for new posts (seed phase).
 *
 * Seed Visibility Logic:
 * 1. Extract creator's primary genre
 * 2. Fetch users who follow that genre but don't follow creator
 * 3. Select 5% of them randomly (or 50 users minimum)
 * 4. Log in feed_seed_visibility table
 * 5. Track if they engage (seeds → Tier 1 via EV)
 *
 * Related: Bible Ch. 22 - Social Feed & Engagement Algorithm
 */
class SeedVisibilityService
{
    private \PDO $read;
    private \PDO $write;
    private Config $config;

    // Seed distribution constants
    private const MIN_SEED_COUNT = 50; // Minimum users to show seed
    private const SEED_PERCENTAGE = 0.05; // 5% of eligible users
    private const SEED_VISIBILITY_HOURS = 48;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read();
        $this->write = ConnectionFactory::write();
    }

    /**
     * Distribute seed visibility for a new post
     *
     * Selects 5% of users who follow post creator's genre but don't follow creator
     *
     * @param int $postId
     * @param int $targetCount Optional override target count (default: calculated)
     * @return array Distribution summary
     */
    public function distributeSeedVisibility(int $postId, int $targetCount = 0): array
    {
        try {
            // Get post details
            $stmt = $this->read->prepare("
                SELECT
                    id, title, created_by_user_id, created_by_entity_type, created_by_entity_id
                FROM posts
                WHERE id = ?
            ");
            $stmt->execute([$postId]);
            $post = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$post) {
                return ['error' => 'Post not found', 'distributed' => 0];
            }

            // Get creator's primary genre
            $creatorGenre = $this->getCreatorPrimaryGenre($post['created_by_entity_type'], $post['created_by_entity_id']);

            if (!$creatorGenre) {
                LoggerFactory::getLogger('feed')->warning('Creator has no genre', [
                    'post_id' => $postId,
                    'creator_type' => $post['created_by_entity_type'],
                    'creator_id' => $post['created_by_entity_id']
                ]);
                return ['error' => 'Creator has no genre', 'distributed' => 0];
            }

            // Get eligible users (follow genre, don't follow creator)
            $eligibleUsers = $this->selectRandomAudienceByGenre(
                $creatorGenre,
                100000, // Get large pool to sample from
                $post['created_by_entity_id'],
                $post['created_by_entity_type']
            );

            if (empty($eligibleUsers)) {
                LoggerFactory::getLogger('feed')->info('No eligible seed users', [
                    'post_id' => $postId,
                    'genre' => $creatorGenre
                ]);
                return ['error' => 'No eligible users', 'distributed' => 0];
            }

            // Calculate target
            if ($targetCount === 0) {
                $targetCount = max(
                    self::MIN_SEED_COUNT,
                    (int) (count($eligibleUsers) * self::SEED_PERCENTAGE)
                );
            }

            // Randomly select target count
            $selectedUsers = array_rand($eligibleUsers, min($targetCount, count($eligibleUsers)));
            if (!is_array($selectedUsers)) {
                $selectedUsers = [$selectedUsers];
            }

            // Record seed visibility for each selected user
            $distributed = 0;
            foreach ($selectedUsers as $arrayKey) {
                $userId = $eligibleUsers[$arrayKey]['user_id'];
                $userGenre = $eligibleUsers[$arrayKey]['user_genre_affinity'];

                if ($this->recordSeedShow($postId, $userId, 'genre_match', $creatorGenre, $userGenre)) {
                    $distributed++;
                }
            }

            LoggerFactory::getLogger('feed')->info('Seed visibility distributed', [
                'post_id' => $postId,
                'distributed' => $distributed,
                'target' => $targetCount,
                'genre' => $creatorGenre
            ]);

            return [
                'post_id' => $postId,
                'distributed' => $distributed,
                'target' => $targetCount,
                'genre' => $creatorGenre,
                'eligible_pool' => count($eligibleUsers)
            ];
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error distributing seed visibility', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage(), 'distributed' => 0];
        }
    }

    /**
     * Select random audience by genre affinity
     *
     * Fetches users with affinity for a genre who don't follow the creator
     *
     * @param string $genreSlug
     * @param int $count Maximum users to return
     * @param int $excludeUserId Creator user ID to exclude
     * @param string $excludeEntityType Creator entity type for follow check
     * @return array Array of user IDs
     */
    public function selectRandomAudienceByGenre(
        string $genreSlug,
        int $count,
        int $excludeUserId,
        string $excludeEntityType
    ): array {
        try {
            // Get all users with affinity for genre
            $stmt = $this->read->prepare("
                SELECT DISTINCT
                    u.id as user_id,
                    u.email,
                    ufa.genre_slug as user_genre_affinity,
                    ufa.affinity_score
                FROM users u
                JOIN user_genre_affinity ufa ON u.id = ufa.user_id
                WHERE ufa.genre_slug = ?
                AND ufa.affinity_score > 0
                AND u.id != ?
                AND NOT EXISTS (
                    SELECT 1 FROM follows
                    WHERE user_id = u.id
                    AND followable_id = ?
                    AND followable_type = ?
                    AND deleted_at IS NULL
                )
                ORDER BY RAND()
                LIMIT ?
            ");

            $stmt->execute([
                $genreSlug,
                $excludeUserId,
                $excludeUserId,
                $excludeEntityType,
                $count
            ]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error selecting audience by genre', [
                'genre' => $genreSlug,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Record seed impression for a user
     *
     * @param int $postId
     * @param int $userId
     * @param string $reason genre_match|random_sample|emerging_artist
     * @param string $postGenre Creator's primary genre
     * @param string $userGenre User's affinity genre
     * @return bool Success
     */
    private function recordSeedShow(
        int $postId,
        int $userId,
        string $reason,
        string $postGenre,
        string $userGenre
    ): bool {
        try {
            $stmt = $this->write->prepare("
                INSERT INTO feed_seed_visibility (
                    post_id, user_id, user_genre_affinity, post_genre, seed_reason,
                    was_shown, shown_at
                ) VALUES (?, ?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    was_shown = 1,
                    shown_at = NOW()
            ");

            $stmt->execute([$postId, $userId, $userGenre, $postGenre, $reason]);
            return true;
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error recording seed show', [
                'post_id' => $postId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Track engagement from seed visibility
     *
     * Called when a user who was shown seed content engages with post
     *
     * @param int $postId
     * @param int $userId
     * @param string $engagementType like|comment|share|spark
     * @return bool Success
     */
    public function trackSeedEngagement(int $postId, int $userId, string $engagementType): bool
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE feed_seed_visibility
                SET user_engaged = 1, engagement_type = ?, engaged_at = NOW()
                WHERE post_id = ? AND user_id = ?
            ");

            $stmt->execute([$engagementType, $postId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error tracking seed engagement', [
                'post_id' => $postId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate seed success rate for a post
     *
     * Percentage of seed visibility recipients who engaged
     *
     * @param int $postId
     * @return float Percentage (0-100)
     */
    public function calculateSeedSuccessRate(int $postId): float
    {
        try {
            $stmt = $this->read->prepare("
                SELECT
                    COUNT(*) as total_shown,
                    SUM(CASE WHEN user_engaged = 1 THEN 1 ELSE 0 END) as total_engaged
                FROM feed_seed_visibility
                WHERE post_id = ? AND was_shown = 1
            ");

            $stmt->execute([$postId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result || $result['total_shown'] === 0) {
                return 0.0;
            }

            return ($result['total_engaged'] / $result['total_shown']) * 100;
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error calculating seed success rate', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Get seed visibility analytics for a post
     *
     * @param int $postId
     * @return array Seed analytics
     */
    public function getSeedAnalytics(int $postId): array
    {
        try {
            $stmt = $this->read->prepare("
                SELECT
                    COUNT(*) as total_shown,
                    SUM(CASE WHEN user_engaged = 1 THEN 1 ELSE 0 END) as total_engaged,
                    GROUP_CONCAT(DISTINCT seed_reason) as seed_reasons,
                    GROUP_CONCAT(DISTINCT post_genre) as post_genres,
                    MIN(shown_at) as first_shown_at,
                    MAX(engaged_at) as last_engaged_at
                FROM feed_seed_visibility
                WHERE post_id = ? AND was_shown = 1
            ");

            $stmt->execute([$postId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return [];
            }

            return [
                'post_id' => $postId,
                'total_shown' => (int) $result['total_shown'],
                'total_engaged' => (int) $result['total_engaged'],
                'engagement_rate' => $result['total_shown'] > 0
                    ? round(($result['total_engaged'] / $result['total_shown']) * 100, 2)
                    : 0,
                'seed_reasons' => array_filter(explode(',', $result['seed_reasons'] ?? '')),
                'genres' => array_filter(explode(',', $result['post_genres'] ?? '')),
                'first_shown_at' => $result['first_shown_at'],
                'last_engaged_at' => $result['last_engaged_at']
            ];
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching seed analytics', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get seed distribution breakdown for a post
     *
     * Shows which users were selected and if they engaged
     *
     * @param int $postId
     * @param int $limit
     * @return array Array of seed visibility records
     */
    public function getSeedDistributionDetail(int $postId, int $limit = 50): array
    {
        try {
            $stmt = $this->read->prepare("
                SELECT
                    fsv.id,
                    fsv.user_id,
                    fsv.user_genre_affinity,
                    fsv.post_genre,
                    fsv.seed_reason,
                    fsv.user_engaged,
                    fsv.engagement_type,
                    fsv.shown_at,
                    fsv.engaged_at,
                    TIMESTAMPDIFF(HOUR, fsv.shown_at, NOW()) as hours_since_shown
                FROM feed_seed_visibility fsv
                WHERE fsv.post_id = ? AND fsv.was_shown = 1
                ORDER BY fsv.user_engaged DESC, fsv.shown_at DESC
                LIMIT ?
            ");

            $stmt->execute([$postId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching seed distribution detail', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get creator's primary genre
     *
     * Fetches the main genre for an artist, label, station, or venue
     *
     * @param string $entityType
     * @param int $entityId
     * @return ?string Genre slug
     */
    private function getCreatorPrimaryGenre(string $entityType, int $entityId): ?string
    {
        try {
            $table = match ($entityType) {
                'artist' => 'artists',
                'label' => 'labels',
                'station' => 'stations',
                'venue' => 'venues',
                default => null
            };

            if (!$table) {
                return null;
            }

            $stmt = $this->read->prepare("SELECT primary_genre FROM $table WHERE id = ? LIMIT 1");
            $stmt->execute([$entityId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['primary_genre'] ?? null;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching creator genre', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
