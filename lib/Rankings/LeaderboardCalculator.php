<?php
namespace NGN\Lib\Rankings;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;

/**
 * LeaderboardCalculator for NGN 2.0
 * Generates monthly leaderboards with caching and genre filtering
 * Feature 7: Simple Leaderboards
 */
class LeaderboardCalculator
{
    private PDO $pdoRankings;
    private PDO $pdoDev;
    private Config $config;

    // Cache TTL in seconds (6 hours - updates daily)
    private const CACHE_TTL = 21600;

    // Default number of top entries to display
    private const DEFAULT_TOP_ENTRIES = 100;

    // Supported leaderboard types
    private const LEADERBOARD_TYPES = ['artists', 'labels', 'genres'];
    private const INTERVALS = ['daily', 'weekly', 'monthly'];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdoRankings = ConnectionFactory::named($config, 'rankings2025');
        $this->pdoDev = ConnectionFactory::named($config, 'dev');
    }

    /**
     * Get leaderboard for specified type and interval
     * Returns cached result if available and not expired
     *
     * @param string $leaderboardType 'artists', 'labels', or 'genres'
     * @param string $rankingInterval 'daily', 'weekly', or 'monthly'
     * @param string|null $category Genre slug or category filter
     * @param int $limit Number of top entries
     * @return array Leaderboard with entries, metadata, and timestamps
     */
    public function getLeaderboard(
        string $leaderboardType,
        string $rankingInterval = 'monthly',
        ?string $category = null,
        int $limit = self::DEFAULT_TOP_ENTRIES
    ): array {
        // Validate inputs
        if (!in_array($leaderboardType, self::LEADERBOARD_TYPES)) {
            throw new \InvalidArgumentException("Invalid leaderboard type: $leaderboardType");
        }

        if (!in_array($rankingInterval, self::INTERVALS)) {
            throw new \InvalidArgumentException("Invalid ranking interval: $rankingInterval");
        }

        // Get current ranking window for interval
        $windowId = $this->getCurrentWindowId($rankingInterval);
        if (!$windowId) {
            return $this->emptyLeaderboard($leaderboardType, $rankingInterval, $category);
        }

        // Check cache first
        $cached = $this->getCachedLeaderboard($leaderboardType, $rankingInterval, $windowId, $category);
        if ($cached) {
            return $cached;
        }

        // Generate fresh leaderboard
        $leaderboard = $this->generateLeaderboard($leaderboardType, $windowId, $rankingInterval, $category, $limit);

        // Cache the result
        $this->cacheLeaderboard($leaderboardType, $rankingInterval, $windowId, $category, $leaderboard);

        // Store snapshot for historical tracking
        $this->storeSnapshot($leaderboardType, $rankingInterval, $windowId, $category, $leaderboard);

        return $leaderboard;
    }

    /**
     * Get top entries for a leaderboard
     *
     * @param string $leaderboardType
     * @param int $windowId
     * @param string $rankingInterval
     * @param string|null $category
     * @param int $limit
     * @return array
     */
    private function generateLeaderboard(
        string $leaderboardType,
        int $windowId,
        string $rankingInterval,
        ?string $category,
        int $limit
    ): array {
        $entityType = rtrim($leaderboardType, 's'); // 'artists' -> 'artist'

        // Query ranking_items for this window
        $query = '
            SELECT
                ri.entity_id,
                ri.rank,
                ri.score,
                ri.prev_rank,
                ri.deltas,
                rw.interval,
                rw.window_start,
                rw.window_end
            FROM `ngn_rankings_2025`.`ranking_items` ri
            INNER JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
            WHERE ri.window_id = :window_id
            AND ri.entity_type = :entity_type
        ';

        $params = [
            ':window_id' => $windowId,
            ':entity_type' => $entityType,
        ];

        // Apply category filter if provided
        if ($category && $entityType !== 'genre') {
            // For genre filtering, we need to check genre_clusters or entity genres
            $query .= ' AND ri.entity_id IN (
                SELECT a.id FROM `ngn_2025`.`cdm_' . $entityType . 's` a
                WHERE JSON_CONTAINS(a.genres_json, JSON_QUOTE(:genre))
            )';
            $params[':genre'] = $category;
        }

        $query .= ' ORDER BY ri.rank ASC LIMIT ' . min($limit, 1000);

        $stmt = $this->pdoRankings->prepare($query);
        $stmt->execute($params);

        $windowRow = null;
        $entries = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$windowRow) {
                $windowRow = $row;
            }

            $entityId = (int)$row['entity_id'];
            $rank = (int)$row['rank'];
            $score = (float)$row['score'];

            // Get entity details
            $entity = $this->getEntityDetails($entityType, $entityId);
            if (!$entity) {
                continue;
            }

            // Calculate rank movement
            $rankMovement = null;
            $trend = 'static';

            if ($row['prev_rank'] !== null) {
                $prevRank = (int)$row['prev_rank'];
                $rankMovement = $prevRank - $rank; // Positive = moved up

                if ($rankMovement > 0) {
                    $trend = 'up';
                } elseif ($rankMovement < 0) {
                    $trend = 'down';
                }
            } else {
                $trend = 'new';
            }

            $entries[] = [
                'rank' => $rank,
                'id' => $entityId,
                'name' => $entity['name'],
                'slug' => $entity['slug'],
                'image_url' => $entity['image_url'],
                'score' => $score,
                'previous_rank' => $row['prev_rank'],
                'rank_movement' => $rankMovement,
                'trend' => $trend,
                'genres' => $entity['genres'] ?? [],
            ];
        }

        return [
            'leaderboard_type' => $leaderboardType,
            'ranking_interval' => $rankingInterval,
            'category' => $category,
            'window' => [
                'interval' => $rankingInterval,
                'start' => $windowRow['window_start'] ?? date('Y-m-d'),
                'end' => $windowRow['window_end'] ?? date('Y-m-d'),
            ],
            'entries' => $entries,
            'total_entries' => count($entries),
            'generated_at' => date('c'),
        ];
    }

    /**
     * Get entity (artist, label, or genre) details for display
     *
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    private function getEntityDetails(string $entityType, int $entityId): ?array
    {
        $table = '`ngn_2025`.`cdm_' . $entityType . 's`';

        $query = "SELECT id, slug, name, image_url, genres_json FROM $table WHERE id = ? LIMIT 1";
        $stmt = $this->pdoDev->prepare($query);
        $stmt->execute([$entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'image_url' => $row['image_url'],
            'genres' => $row['genres_json'] ? json_decode($row['genres_json'], true) : [],
        ];
    }

    /**
     * Get current ranking window ID for interval
     *
     * @param string $interval
     * @return int|null
     */
    private function getCurrentWindowId(string $interval): ?int
    {
        $today = date('Y-m-d');

        switch ($interval) {
            case 'daily':
                $windowStart = $today;
                break;
            case 'weekly':
                $windowStart = date('Y-m-d', strtotime('monday this week'));
                break;
            case 'monthly':
                $windowStart = date('Y-m-01');
                break;
            default:
                return null;
        }

        $query = '
            SELECT id FROM `ngn_rankings_2025`.`ranking_windows`
            WHERE `interval` = :interval
            AND window_start = :start
            ORDER BY id DESC
            LIMIT 1
        ';

        $stmt = $this->pdoRankings->prepare($query);
        $stmt->execute([':interval' => $interval, ':start' => $windowStart]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }

    /**
     * Get cached leaderboard if available and not expired
     *
     * @param string $leaderboardType
     * @param string $rankingInterval
     * @param int $windowId
     * @param string|null $category
     * @return array|null
     */
    private function getCachedLeaderboard(
        string $leaderboardType,
        string $rankingInterval,
        int $windowId,
        ?string $category
    ): ?array {
        $query = '
            SELECT cached_data, cached_at, expires_at
            FROM `ngn_2025`.`leaderboard_cache`
            WHERE leaderboard_type = :type
            AND ranking_interval = :interval
            AND window_id = :window_id
            AND category <=> :category
            AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ';

        $stmt = $this->pdoDev->prepare($query);
        $stmt->execute([
            ':type' => $leaderboardType,
            ':interval' => $rankingInterval,
            ':window_id' => $windowId,
            ':category' => $category,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $cached = json_decode($row['cached_data'], true);
        if (!is_array($cached)) {
            return null;
        }

        // Add cache metadata
        $cached['cached_at'] = $row['cached_at'];
        $cached['from_cache'] = true;

        return $cached;
    }

    /**
     * Cache leaderboard result
     *
     * @param string $leaderboardType
     * @param string $rankingInterval
     * @param int $windowId
     * @param string|null $category
     * @param array $leaderboard
     * @return void
     */
    private function cacheLeaderboard(
        string $leaderboardType,
        string $rankingInterval,
        int $windowId,
        ?string $category,
        array $leaderboard
    ): void {
        $expiresAt = date('Y-m-d H:i:s', time() + self::CACHE_TTL);

        $query = '
            INSERT INTO `ngn_2025`.`leaderboard_cache`
            (leaderboard_type, ranking_interval, window_id, category, cached_data, total_count, entries_count, cached_at, expires_at)
            VALUES (:type, :interval, :window_id, :category, :data, :total, :count, NOW(), :expires_at)
            ON DUPLICATE KEY UPDATE
            cached_data = VALUES(cached_data),
            total_count = VALUES(total_count),
            entries_count = VALUES(entries_count),
            cached_at = NOW(),
            expires_at = VALUES(expires_at)
        ';

        $stmt = $this->pdoDev->prepare($query);
        $stmt->execute([
            ':type' => $leaderboardType,
            ':interval' => $rankingInterval,
            ':window_id' => $windowId,
            ':category' => $category,
            ':data' => json_encode($leaderboard),
            ':total' => $leaderboard['total_entries'] ?? 0,
            ':count' => $leaderboard['total_entries'] ?? 0,
            ':expires_at' => $expiresAt,
        ]);
    }

    /**
     * Store leaderboard snapshot for historical tracking
     *
     * @param string $leaderboardType
     * @param string $rankingInterval
     * @param int $windowId
     * @param string|null $category
     * @param array $leaderboard
     * @return void
     */
    private function storeSnapshot(
        string $leaderboardType,
        string $rankingInterval,
        int $windowId,
        ?string $category,
        array $leaderboard
    ): void {
        $entityType = rtrim($leaderboardType, 's');

        $query = '
            INSERT INTO `ngn_2025`.`leaderboard_snapshots`
            (leaderboard_type, ranking_interval, window_id, category, entity_id, rank, score, previous_rank, rank_movement, trend, snapshot_at)
            VALUES (:type, :interval, :window_id, :category, :entity_id, :rank, :score, :prev_rank, :movement, :trend, NOW())
            ON DUPLICATE KEY UPDATE
            rank = VALUES(rank),
            score = VALUES(score),
            trend = VALUES(trend)
        ';

        $stmt = $this->pdoDev->prepare($query);

        foreach ($leaderboard['entries'] ?? [] as $entry) {
            $stmt->execute([
                ':type' => $leaderboardType,
                ':interval' => $rankingInterval,
                ':window_id' => $windowId,
                ':category' => $category,
                ':entity_id' => $entry['id'],
                ':rank' => $entry['rank'],
                ':score' => $entry['score'],
                ':prev_rank' => $entry['previous_rank'],
                ':movement' => $entry['rank_movement'],
                ':trend' => $entry['trend'],
            ]);
        }
    }

    /**
     * Update cached ranks in entity tables
     * Called periodically to keep current_rank_monthly in sync
     *
     * @param string $leaderboardType
     * @return int Number of entities updated
     */
    public function updateCachedRanks(string $leaderboardType = 'artists'): int
    {
        $entityType = rtrim($leaderboardType, 's');
        $table = '`ngn_2025`.`cdm_' . $entityType . 's`';

        // Get current monthly window
        $windowId = $this->getCurrentWindowId('monthly');
        if (!$windowId) {
            return 0;
        }

        // Update entity rank cache from ranking_items
        $query = "
            UPDATE $table e
            INNER JOIN (
                SELECT
                    ri.entity_id,
                    ri.rank,
                    ri.score,
                    (SELECT prev_rank FROM `ngn_rankings_2025`.`ranking_items`
                     WHERE window_id = :window_id AND entity_type = :entity_type AND entity_id = ri.entity_id LIMIT 1) as prev_rank
                FROM `ngn_rankings_2025`.`ranking_items` ri
                WHERE ri.window_id = :window_id
                AND ri.entity_type = :entity_type
            ) ranked ON e.id = ranked.entity_id
            SET
                e.current_rank_monthly = ranked.rank,
                e.current_score_monthly = ranked.score,
                e.last_rank_monthly = ranked.prev_rank,
                e.rank_updated_at = NOW()
        ";

        $stmt = $this->pdoRankings->prepare($query);
        $stmt->execute([
            ':window_id' => $windowId,
            ':entity_type' => $entityType,
        ]);

        $updatedCount = $stmt->rowCount();

        // Detect rivalries and overtakes (Retention System - Chapter 23)
        if ($updatedCount > 0 && $entityType === 'artist') {
            try {
                $this->detectRivalriesAfterRankUpdate($entityType);
            } catch (Exception $e) {
                error_log("Warning: Rivalry detection failed: " . $e->getMessage());
                // Don't fail rank update if rivalry detection fails
            }
        }

        return $updatedCount;
    }

    /**
     * Get featured leaderboard entries
     * Top climbers, new entries, trending, etc.
     *
     * @param string $rankingInterval
     * @param int $limit
     * @return array
     */
    public function getFeaturedEntries(string $rankingInterval = 'monthly', int $limit = 10): array
    {
        $windowId = $this->getCurrentWindowId($rankingInterval);
        if (!$windowId) {
            return [];
        }

        $query = '
            SELECT
                lf.leaderboard_type,
                lf.entity_id,
                lf.rank,
                lf.featured_reason,
                lf.featured_order
            FROM `ngn_2025`.`leaderboard_featured` lf
            WHERE lf.window_id = :window_id
            AND lf.ranking_interval = :interval
            AND (lf.featured_until IS NULL OR lf.featured_until > NOW())
            ORDER BY lf.featured_order ASC, lf.rank ASC
            LIMIT ' . min($limit, 50)
        ';

        $stmt = $this->pdoDev->prepare($query);
        $stmt->execute([
            ':window_id' => $windowId,
            ':interval' => $rankingInterval,
        ]);

        $featured = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityType = rtrim($row['leaderboard_type'], 's');
            $entity = $this->getEntityDetails($entityType, (int)$row['entity_id']);

            if ($entity) {
                $featured[] = [
                    'type' => $row['leaderboard_type'],
                    'rank' => (int)$row['rank'],
                    'entity' => $entity,
                    'featured_reason' => $row['featured_reason'],
                ];
            }
        }

        return $featured;
    }

    /**
     * Get trending entries (biggest climbers)
     *
     * @param string $leaderboardType
     * @param string $rankingInterval
     * @param int $limit
     * @return array
     */
    public function getTrendingEntries(
        string $leaderboardType = 'artists',
        string $rankingInterval = 'weekly',
        int $limit = 20
    ): array {
        $entityType = rtrim($leaderboardType, 's');
        $windowId = $this->getCurrentWindowId($rankingInterval);

        if (!$windowId) {
            return [];
        }

        $query = '
            SELECT
                ri.entity_id,
                ri.rank,
                ri.score,
                ri.prev_rank,
                (COALESCE(ri.prev_rank, 999999) - ri.rank) as rank_jump
            FROM `ngn_rankings_2025`.`ranking_items` ri
            WHERE ri.window_id = :window_id
            AND ri.entity_type = :entity_type
            AND ri.prev_rank IS NOT NULL
            AND ri.prev_rank > ri.rank
            ORDER BY rank_jump DESC
            LIMIT ' . min($limit, 100)
        ';

        $stmt = $this->pdoRankings->prepare($query);
        $stmt->execute([
            ':window_id' => $windowId,
            ':entity_type' => $entityType,
        ]);

        $trending = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entity = $this->getEntityDetails($entityType, (int)$row['entity_id']);

            if ($entity) {
                $trending[] = [
                    'rank' => (int)$row['rank'],
                    'id' => $entity['id'],
                    'name' => $entity['name'],
                    'slug' => $entity['slug'],
                    'image_url' => $entity['image_url'],
                    'score' => (float)$row['score'],
                    'previous_rank' => (int)$row['prev_rank'],
                    'rank_jump' => (int)$row['rank_jump'],
                ];
            }
        }

        return $trending;
    }

    /**
     * Return empty leaderboard structure
     *
     * @param string $leaderboardType
     * @param string $rankingInterval
     * @param string|null $category
     * @return array
     */
    private function emptyLeaderboard(
        string $leaderboardType,
        string $rankingInterval,
        ?string $category
    ): array {
        return [
            'leaderboard_type' => $leaderboardType,
            'ranking_interval' => $rankingInterval,
            'category' => $category,
            'window' => [
                'interval' => $rankingInterval,
                'start' => date('Y-m-d'),
                'end' => date('Y-m-d'),
            ],
            'entries' => [],
            'total_entries' => 0,
            'message' => 'No rankings available for this period',
            'generated_at' => date('c'),
        ];
    }

    /**
     * Detect rivalries and overtakes after rank updates (Retention System)
     *
     * @param string $entityType Entity type ('artist', 'label', etc.)
     */
    private function detectRivalriesAfterRankUpdate(string $entityType): void
    {
        try {
            // Get PDO connection for retention operations
            $pdoDev = $this->pdoDev;

            $pushService = new \NGN\Lib\Retention\PushNotificationService($pdoDev);
            $rivalryService = new \NGN\Lib\Retention\RivalryDetectionService($pdoDev, $pushService);

            // Get artists with rank changes
            $query = "
                SELECT DISTINCT user_id
                FROM ngn_2025.cdm_artists
                WHERE last_rank_monthly IS NOT NULL
                  AND current_rank_monthly IS NOT NULL
                  AND last_rank_monthly != current_rank_monthly
                LIMIT 500
            ";

            $stmt = $pdoDev->prepare($query);
            $stmt->execute();
            $artistsWithRankChanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($artistsWithRankChanges as $artist) {
                try {
                    $userId = (int)$artist['user_id'];

                    // Check for overtakes in all genres
                    $overtakes = $rivalryService->checkForOvertakes($userId);

                    if (!empty($overtakes)) {
                        error_log("Rivalry overtake detected for user {$userId}: " . count($overtakes) . " overtakes");
                    }
                } catch (Exception $e) {
                    error_log("Error detecting rivalries for user {$artist['user_id']}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Error in detectRivalriesAfterRankUpdate: " . $e->getMessage());
        }
    }
}
