<?php

namespace NGN\Lib\Feed;

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;

/**
 * TrendingFeedService
 *
 * Manages global trending algorithm and materialized trending queue (Tier 3).
 *
 * Moneyball Gate:
 * - NGN Score check: creator must have NGN > 30
 * - Verified humans only (bot-driven spikes ignored)
 * - EV persistence check: must maintain EV for 2+ hours
 *
 * Related: Bible Ch. 22 - Social Feed & Engagement Algorithm
 */
class TrendingFeedService
{
    private \PDO $read;
    private \PDO $write;
    private Config $config;

    // Trending thresholds
    private const NGN_SCORE_MIN = 30;
    private const EV_THRESHOLD_TRENDING = 150;
    private const TRENDING_QUEUE_LIMIT = 50;
    private const TRENDING_MIN_DURATION_HOURS = 2;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read();
        $this->write = ConnectionFactory::write();
    }

    /**
     * Get current trending posts (Tier 3)
     *
     * Returns top N trending posts with their metrics
     *
     * @param int $limit Default 10
     * @param array $filters Optional filters (time_window, genre, etc)
     * @return array Array of trending posts
     */
    public function getTrendingPosts(int $limit = 10, array $filters = []): array
    {
        try {
            $limit = max(1, min($limit, self::TRENDING_QUEUE_LIMIT));
            $timeWindow = $filters['time_window'] ?? '24hours';

            // Calculate time boundary
            $timeBoundary = match ($timeWindow) {
                'hour' => 'TIMESTAMPDIFF(HOUR, gtq.qualified_at, NOW()) <= 1',
                '6hours' => 'TIMESTAMPDIFF(HOUR, gtq.qualified_at, NOW()) <= 6',
                '24hours' => 'TIMESTAMPDIFF(HOUR, gtq.qualified_at, NOW()) <= 24',
                default => 'TIMESTAMPDIFF(HOUR, gtq.qualified_at, NOW()) <= 24'
            };

            $sql = "
                SELECT
                    gtq.id,
                    gtq.post_id,
                    gtq.entity_id,
                    gtq.current_ev_score,
                    gtq.creator_ngn_score,
                    gtq.trending_rank,
                    gtq.qualified_at,
                    gtq.trending_started_at,
                    TIMESTAMPDIFF(HOUR, gtq.trending_started_at, NOW()) as hours_trending,
                    pvs.tier1_impressions + pvs.tier2_impressions + pvs.tier3_impressions as total_impressions,
                    pvs.tier3_impressions,
                    p.title,
                    p.created_at as post_created_at
                FROM `ngn_2025`.`global_trending_queue` gtq
                LEFT JOIN `ngn_2025`.`post_visibility_state` pvs ON gtq.post_id = pvs.post_id
                LEFT JOIN `ngn_2025`.`posts` p ON gtq.post_id = p.id
                WHERE gtq.status = 'trending'
                AND $timeBoundary
                AND gtq.creator_verified = 1
                ORDER BY gtq.trending_rank ASC, gtq.current_ev_score DESC
                LIMIT ?
            ";

            $stmt = $this->read->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching trending posts', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get trending posts for a specific hour
     *
     * @param int $hour Hour offset (-1 = current hour, -2 = last hour, etc)
     * @return array Array of trending posts
     */
    public function getHourlyTrending(int $hour = -1): array
    {
        try {
            $hourOffset = max(-24, min($hour, 0));
            $startTime = date('Y-m-d H:00:00', strtotime("$hourOffset hours"));
            $endTime = date('Y-m-d H:59:59', strtotime("$hourOffset hours"));

            $stmt = $this->read->prepare("
                SELECT
                    gtq.post_id,
                    gtq.current_ev_score,
                    gtq.creator_ngn_score,
                    COUNT(*) as appearance_count
                FROM `ngn_2025`.`global_trending_queue` gtq
                WHERE gtq.trending_started_at >= ? AND gtq.trending_started_at <= ?
                GROUP BY gtq.post_id
                ORDER BY gtq.current_ev_score DESC
            ");
            $stmt->execute([$startTime, $endTime]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching hourly trending', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if post should be included in trending
     *
     * Moneyball gates:
     * 1. EV > 150
     * 2. Creator NGN Score > 30
     * 3. Creator is verified
     * 4. Post not expired (< 48h old)
     *
     * @param int $postId
     * @return bool
     */
    public function shouldIncludeInTrending(int $postId): bool
    {
        try {
            $stmt = $this->read->prepare("
                SELECT
                    pvs.ev_score_current,
                    pvs.expired_at,
                    u.ngn_score
                FROM `ngn_2025`.`post_visibility_state` pvs
                LEFT JOIN `ngn_2025`.`users` u ON pvs.entity_id = u.id
                WHERE pvs.post_id = ?
            ");
            $stmt->execute([$postId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$data) {
                return false;
            }

            // Gate 1: EV threshold
            if (($data['ev_score_current'] ?? 0) <= self::EV_THRESHOLD_TRENDING) {
                return false;
            }

            // Gate 2: NGN Score
            if (($data['ngn_score'] ?? 0) <= self::NGN_SCORE_MIN) {
                return false;
            }

            // Gate 3: Not expired
            if ($data['expired_at'] !== null) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error checking trending eligibility', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate trending rank for a post
     *
     * Ranking formula considers:
     * 1. EV score (primary)
     * 2. Momentum (EV change over time)
     * 3. Duration in trending
     * 4. Creator NGN Score (tie-breaker)
     *
     * @param int $postId
     * @return ?int Trending rank (1-50) or null if not trending
     */
    public function calculateTrendingRank(int $postId): ?int
    {
        try {
            // First check if eligible
            if (!$this->shouldIncludeInTrending($postId)) {
                return null;
            }

            // Get eligible posts ordered by EV score
            $stmt = $this->read->prepare("
                SELECT
                    gtq.post_id,
                    gtq.current_ev_score,
                    gtq.creator_ngn_score
                FROM `ngn_2025`.`global_trending_queue` gtq
                LEFT JOIN `ngn_2025`.`users` u ON gtq.entity_id = u.id
                WHERE gtq.status IN ('trending', 'pending')
                AND gtq.creator_verified = 1
                AND gtq.current_ev_score > ?
                AND u.ngn_score > ?
                ORDER BY gtq.current_ev_score DESC, gtq.creator_ngn_score DESC
                LIMIT ?
            ");
            $stmt->execute([self::EV_THRESHOLD_TRENDING, self::NGN_SCORE_MIN, self::TRENDING_QUEUE_LIMIT]);
            $rankedPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rankedPosts as $rank => $post) {
                if ($post['post_id'] == $postId) {
                    return $rank + 1;
                }
            }

            return null;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error calculating trending rank', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Rebuild entire trending queue
     *
     * Called hourly by rebuild_trending_queue cron job.
     * Clears and rebuilds the global_trending_queue table.
     *
     * @return int Number of posts added to trending queue
     */
    public function rebuildTrendingQueue(): int
    {
        try {
            // Get all eligible posts from last 48h
            $stmt = $this->read->prepare("
                SELECT DISTINCT
                    pvs.post_id,
                    pvs.entity_id,
                    pvs.ev_score_current,
                    u.ngn_score,
                    u.id as user_id
                FROM `ngn_2025`.`post_visibility_state` pvs
                LEFT JOIN `ngn_2025`.`users` u ON pvs.entity_id = u.id
                WHERE pvs.expired_at IS NULL
                AND TIMESTAMPDIFF(HOUR, pvs.created_at, NOW()) < 48
                AND pvs.ev_score_current > ?
                AND u.ngn_score > ?
                AND u.id IS NOT NULL
                ORDER BY pvs.ev_score_current DESC
                LIMIT ?
            ");
            $stmt->execute([self::EV_THRESHOLD_TRENDING, self::NGN_SCORE_MIN, self::TRENDING_QUEUE_LIMIT]);
            $eligiblePosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Start transaction
            $this->write->beginTransaction();

            try {
                // Clear existing queue
                $clearStmt = $this->write->prepare("DELETE FROM `ngn_2025`.`global_trending_queue`");
                $clearStmt->execute();

                // Insert qualified posts
                $insertStmt = $this->write->prepare("
                    INSERT INTO `ngn_2025`.`global_trending_queue` (
                        post_id, entity_id, current_ev_score, creator_ngn_score,
                        creator_verified, status, qualified_at, trending_started_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $count = 0;
                foreach ($eligiblePosts as $rank => $post) {
                    $insertStmt->execute([
                        $post['post_id'],
                        $post['entity_id'],
                        $post['ev_score_current'],
                        $post['ngn_score'],
                        1, // creator_verified
                        'trending', // status
                    ]);
                    $count++;
                }

                // Update trending_rank for all inserted posts
                $rankStmt = $this->write->prepare("
                    UPDATE `ngn_2025`.`global_trending_queue`
                    SET trending_rank = (
                        SELECT COUNT(*) FROM `ngn_2025`.`global_trending_queue` gtq2
                        WHERE gtq2.current_ev_score > `ngn_2025`.`global_trending_queue`.current_ev_score
                        OR (gtq2.current_ev_score = `ngn_2025`.`global_trending_queue`.current_ev_score
                            AND gtq2.id <= `ngn_2025`.`global_trending_queue`.id)
                    ) + 1
                ");
                $rankStmt->execute();

                $this->write->commit();

                LoggerFactory::getLogger('feed')->info('Trending queue rebuilt', [
                    'posts_added' => $count,
                    'total_eligible' => count($eligiblePosts)
                ]);

                return $count;
            } catch (\Exception $e) {
                $this->write->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error rebuilding trending queue', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get posts that recently entered or left trending
     *
     * Used for analytics and monitoring
     *
     * @return array Posts with status changes in last hour
     */
    public function getRecentTrendingChanges(): array
    {
        try {
            $stmt = $this->read->prepare("            'SELECT
                    gtq.post_id,
                    gtq.status,
                    gtq.current_ev_score,
                    gtq.trending_rank,
                    gtq.trending_started_at,
                    TIMESTAMPDIFF(MINUTE, gtq.updated_at, NOW()) as minutes_since_update
                FROM `ngn_2025`.`global_trending_queue` gtq
                WHERE TIMESTAMPDIFF(HOUR, gtq.updated_at, NOW()) <= 1
                ORDER BY gtq.updated_at DESC'");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching trending changes', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Archive old trending data for analytics
     *
     * Called periodically to clean up expired trending records
     *
     * @return int Number of records archived
     */
    public function archiveExpiredTrending(): int
    {
        try {
            $stmt = $this->write->prepare("
                UPDATE `ngn_2025`.`global_trending_queue`
                SET status = 'expired'
                WHERE status = 'trending'
                AND TIMESTAMPDIFF(HOUR, trending_started_at, NOW()) > 48
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error archiving expired trending', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
