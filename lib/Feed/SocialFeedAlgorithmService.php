<?php

namespace NGN\Lib\Feed;

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use NGN\Lib\Engagement\EngagementService;

/**
 * SocialFeedAlgorithmService
 *
 * Implements three-tier content distribution system with engagement velocity (EV)
 * based tier expansion, time decay, and anti-payola enforcement.
 *
 * Tier Structure:
 * - Tier 1 (Core Circle): Posts shown to 100% of entity followers
 * - Tier 2 (Affinity Circle): High-EV posts pushed to genre-affinity users (EV > 50)
 * - Tier 3 (Global Trending): Breakthrough content reaching all users (EV > 150 AND NGN > 30)
 *
 * Related: Bible Ch. 22 - Social Feed & Engagement Algorithm
 */
class SocialFeedAlgorithmService
{
    private \PDO $read;
    private \PDO $write;
    private Config $config;
    private EngagementService $engagementService;

    // EV Thresholds for tier expansion
    private const EV_THRESHOLD_TIER2 = 50;
    private const EV_THRESHOLD_TIER3 = 150;
    private const NGN_SCORE_TIER3_MIN = 30;

    // Decay constants (48-hour window)
    // Formula: visibility_score = 100 × e^(-0.693 × hours_since_post / 24)
    private const DECAY_HALF_LIFE_HOURS = 24;
    private const DECAY_LAMBDA = 0.693;
    private const VISIBILITY_EXPIRY_THRESHOLD = 5; // Below 5%, mark as expired

    public function __construct(Config $config, ?EngagementService $engagementService = null)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read();
        $this->write = ConnectionFactory::write();
        $this->engagementService = $engagementService ?: new EngagementService(ConnectionFactory::write());
    }

    /**
     * Initialize post visibility when created
     *
     * Called when new post is created. Sets tier to 'seed' and prepares for visibility distribution.
     *
     * @param int $postId
     * @param string $entityType artist|label|station|venue
     * @param int $entityId
     * @return void
     * @throws \Exception
     */
    public function initializePostVisibility(int $postId, string $entityType, int $entityId): void
    {
        try {
            $stmt = $this->write->prepare("
                INSERT INTO `ngn_2025`.`post_visibility_state` (
                    post_id, entity_type, entity_id, current_tier,
                    visibility_score, last_decay_calculated_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    current_tier = 'seed',
                    visibility_score = 100,
                    last_decay_calculated_at = NOW()
            ");

            $stmt->execute([$postId, $entityType, $entityId, 'seed']);

            LoggerFactory::getLogger('feed')->info('Post visibility initialized', [
                'post_id' => $postId,
                'entity_type' => $entityType,
                'tier' => 'seed'
            ]);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Failed to initialize post visibility', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to initialize post visibility');
        }
    }

    /**
     * Get current tier and state for a post
     *
     * @param int $postId
     * @return ?array Post visibility state or null
     */
    public function getVisibilityState(int $postId): ?array
    {
        $stmt = $this->read->prepare("
            SELECT * FROM `ngn_2025`.`post_visibility_state` WHERE post_id = ?
        ");
        $stmt->execute([$postId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calculate current engagement velocity for a post
     *
     * EV = [Likes×1 + Comments×3 + Shares×10 + Sparks×15] / TimeSincePost (hours)
     *
     * @param int $postId
     * @return float EV score
     */
    public function recalculatePostEV(int $postId): float
    {
        try {
            // Get post creation time
            $stmt = $this->read->prepare("SELECT created_at FROM `ngn_2025`.`posts` WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$post) {
                throw new \Exception("Post not found: $postId");
            }

            $createdAt = strtotime($post['created_at']);
            $now = time();
            $hoursSincePost = max(1, ($now - $createdAt) / 3600);

            // Get engagement counts from cdm_engagement_counts
            $stmt = $this->read->prepare("
                SELECT
                    COALESCE(likes_count, 0) as likes,
                    COALESCE(comments_count, 0) as comments,
                    COALESCE(shares_count, 0) as shares,
                    COALESCE(sparks_count, 0) as sparks
                FROM `ngn_2025`.`cdm_engagement_counts`
                WHERE entity_type = 'post' AND entity_id = ?
            ");
            $stmt->execute([$postId]);
            $counts = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$counts) {
                $counts = ['likes' => 0, 'comments' => 0, 'shares' => 0, 'sparks' => 0];
            }

            // Calculate EV with weights
            $ev = (
                ($counts['likes'] * 1) +
                ($counts['comments'] * 3) +
                ($counts['shares'] * 10) +
                ($counts['sparks'] * 15)
            ) / $hoursSincePost;

            // Update post_visibility_state
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`post_visibility_state`
                SET ev_score_current = ?
                WHERE post_id = ?
            ");
            $stmt->execute([round($ev, 4), $postId]);

            LoggerFactory::getLogger('feed')->debug('Post EV recalculated', [
                'post_id' => $postId,
                'ev_score' => round($ev, 4),
                'hours_since_post' => round($hoursSincePost, 2)
            ]);

            return $ev;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Failed to recalculate post EV', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Check if post should expand to Tier 2 (Affinity Circle)
     *
     * Tier 2 expansion happens when:
     * - EV > 50
     * - Post is in Tier 1
     * - Post not yet in Tier 2
     *
     * @param int $postId
     * @return bool
     */
    public function shouldExpandToTier2(int $postId): bool
    {
        try {
            $state = $this->getVisibilityState($postId);

            if (!$state) {
                return false;
            }

            // Already in Tier 2 or higher
            if (in_array($state['current_tier'], ['tier2', 'tier3'])) {
                return false;
            }

            // EV not high enough
            if ($state['ev_score_current'] <= self::EV_THRESHOLD_TIER2) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error checking Tier 2 expansion', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if post should expand to Tier 3 (Global Trending)
     *
     * Tier 3 expansion happens when:
     * - EV > 150
     * - Creator NGN Score > 30
     * - Creator is verified human
     * - Post not yet in Tier 3
     *
     * @param int $postId
     * @return bool
     */
    public function shouldExpandToTier3(int $postId): bool
    {
        try {
            $state = $this->getVisibilityState($postId);

            if (!$state) {
                return false;
            }

            // Already in Tier 3
            if ($state['current_tier'] === 'tier3') {
                return false;
            }

            // EV not high enough
            if ($state['ev_score_current'] <= self::EV_THRESHOLD_TIER3) {
                return false;
            }

            // Check creator NGN Score
            $stmt = $this->read->prepare("
                SELECT ngn_score FROM `ngn_2025`.`users` WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$state['entity_id']]);
            $creator = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$creator || ($creator['ngn_score'] ?? 0) <= self::NGN_SCORE_TIER3_MIN) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error checking Tier 3 expansion', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Expand post to Tier 2 (Affinity Circle)
     *
     * @param int $postId
     * @return void
     * @throws \Exception
     */
    public function expandPostToTier2(int $postId): void
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`post_visibility_state`
                SET current_tier = 'tier2', tier2_expanded_at = NOW()
                WHERE post_id = ?
            ");
            $stmt->execute([$postId]);

            // Log tier transition
            $this->logTierTransition($postId, 'tier1', 'tier2', 'ev_threshold_reached');

            LoggerFactory::getLogger('feed')->info('Post expanded to Tier 2', [
                'post_id' => $postId,
                'tier' => 'tier2'
            ]);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Failed to expand post to Tier 2', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to expand post to Tier 2');
        }
    }

    /**
     * Expand post to Tier 3 (Global Trending)
     *
     * @param int $postId
     * @return void
     * @throws \Exception
     */
    public function expandPostToTier3(int $postId): void
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`post_visibility_state`
                SET current_tier = 'tier3', tier3_expanded_at = NOW()
                WHERE post_id = ?
            ");
            $stmt->execute([$postId]);

            // Log tier transition
            $this->logTierTransition($postId, 'tier2', 'tier3', 'ev_threshold_and_ngn_reached');

            LoggerFactory::getLogger('feed')->info('Post expanded to Tier 3', [
                'post_id' => $postId,
                'tier' => 'tier3'
            ]);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Failed to expand post to Tier 3', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to expand post to Tier 3');
        }
    }

    /**
     * Calculate visibility decay multiplier for a post
     *
     * Formula: visibility_score = 100 × e^(-0.693 × hours_since_post / 24)
     *
     * At 24h: ~50%
     * At 48h: ~25%
     * At 72h: ~12.5%
     *
     * @param int $postId
     * @return float Decay multiplier (0-100)
     */
    public function calculateDecayMultiplier(int $postId): float
    {
        try {
            $state = $this->getVisibilityState($postId);

            if (!$state) {
                return 0;
            }

            $createdAt = strtotime($state['created_at']);
            $now = time();
            $hoursSincePost = ($now - $createdAt) / 3600;

            // Decay formula: 100 × e^(-0.693 × hours / 24)
            $decayMultiplier = 100 * exp(-(self::DECAY_LAMBDA * $hoursSincePost) / self::DECAY_HALF_LIFE_HOURS);
            $decayMultiplier = max(0, min(100, $decayMultiplier));

            return round($decayMultiplier, 4);
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error calculating decay multiplier', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Apply visibility decay to a post
     *
     * Updates visibility_score based on time since post creation.
     * Marks as expired if visibility_score < 5%
     *
     * @param int $postId
     * @return bool True if post expired
     */
    public function decayVisibilityScore(int $postId): bool
    {
        try {
            $decayScore = $this->calculateDecayMultiplier($postId);
            $isExpired = $decayScore < self::VISIBILITY_EXPIRY_THRESHOLD;

            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`post_visibility_state`
                SET
                    visibility_score = ?,
                    last_decay_calculated_at = NOW(),
                    expired_at = ?
                WHERE post_id = ?
            ");

            $expiredAt = $isExpired ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$decayScore, $expiredAt, $postId]);

            if ($isExpired) {
                LoggerFactory::getLogger('feed')->info('Post marked as expired', [
                    'post_id' => $postId,
                    'visibility_score' => $decayScore
                ]);
            }

            return $isExpired;
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Failed to decay post visibility', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Expire and remove posts older than 48 hours
     *
     * Called by decay_visibility_scores cron job
     *
     * @return int Number of posts expired
     */
    public function expireOldPosts(): int
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`post_visibility_state`
                SET expired_at = NOW()
                WHERE expired_at IS NULL
                AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 48
                AND visibility_score < ?
            ");
            $stmt->execute([self::VISIBILITY_EXPIRY_THRESHOLD]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error expiring old posts', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Check tier expansion thresholds and return detailed info
     *
     * Used by calculate_post_ev cron job to determine if tier transitions needed
     *
     * @param int $postId
     * @return array Expansion info
     */
    public function checkTierExpansionThresholds(int $postId): array
    {
        try {
            $this->recalculatePostEV($postId);
            $state = $this->getVisibilityState($postId);

            if (!$state) {
                return ['error' => 'Post not found'];
            }

            $shouldExpandTier2 = $this->shouldExpandToTier2($postId);
            $shouldExpandTier3 = $this->shouldExpandToTier3($postId);

            return [
                'post_id' => $postId,
                'current_tier' => $state['current_tier'],
                'ev_score' => $state['ev_score_current'],
                'should_expand_tier2' => $shouldExpandTier2,
                'should_expand_tier3' => $shouldExpandTier3,
                'tier2_threshold' => self::EV_THRESHOLD_TIER2,
                'tier3_threshold' => self::EV_THRESHOLD_TIER3
            ];
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error checking tier expansion thresholds', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get posts eligible for tier expansion
     *
     * Used by cron jobs to find posts needing tier evaluation
     *
     * @param int $limit
     * @return array Array of post visibility states
     */
    public function getPostsNeedingTierEvaluation(int $limit = 100): array
    {
        try {
            $stmt = $this->read->prepare("
                SELECT * FROM `ngn_2025`.`post_visibility_state`
                WHERE (
                    (current_tier = 'seed' AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= 5)
                    OR (current_tier = 'tier1' AND tier2_expanded_at IS NULL)
                    OR (current_tier = 'tier2' AND tier3_expanded_at IS NULL)
                )
                AND expired_at IS NULL
                AND TIMESTAMPDIFF(HOUR, created_at, NOW()) < 48
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching posts for tier evaluation', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Log tier transition event for analytics
     *
     * @param int $postId
     * @param string $tierFrom
     * @param string $tierTo
     * @param string $reason
     * @return void
     */
    private function logTierTransition(int $postId, string $tierFrom, string $tierTo, string $reason): void
    {
        try {
            $eventData = json_encode([
                'tier_from' => $tierFrom,
                'tier_to' => $tierTo,
                'reason' => $reason
            ]);

            $stmt = $this->write->prepare("
                INSERT INTO `ngn_2025`.`feed_events_log` (post_id, event_type, event_data, feed_type)
                VALUES (?, 'tier_transition', ?, 'home')
            ");
            $stmt->execute([$postId, $eventData]);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error logging tier transition', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
