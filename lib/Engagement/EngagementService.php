<?php

namespace NGN\Lib\Engagement;

use PDO;
use Exception;

/**
 * Engagement Service
 *
 * Manages likes, shares, comments, and sparks across all entities
 * Critical for EQS (Engagement Quality Score) calculation
 */
class EngagementService
{
    private PDO $pdo;

    // EQS weights (Bible Ch. 22.4 - Engagement Velocity Formula)
    private const EQS_WEIGHTS = [
        'like' => 1.0,
        'share' => 10.0,  // Bible: Shares×10
        'comment' => 3.0,  // Bible: Comments×3
        'spark' => 15.0  // Bible: Sparks×15 (per spark)
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create an engagement (like, share, comment, spark)
     *
     * @param int $userId User performing the engagement
     * @param string $entityType Entity type (artist, label, post, etc.)
     * @param int $entityId Entity ID
     * @param string $type Engagement type (like, share, comment, spark)
     * @param array $metadata Additional data (comment_text, spark_amount, share_platform)
     * @return array Created engagement
     * @throws Exception
     */
    public function create(int $userId, string $entityType, int $entityId, string $type, array $metadata = []): array
    {
        // Validate engagement type
        $validTypes = ['like', 'share', 'comment', 'spark'];
        if (!in_array($type, $validTypes)) {
            throw new Exception("Invalid engagement type: {$type}");
        }

        // Validate entity type
        $validEntityTypes = ['artist', 'label', 'venue', 'station', 'post', 'video', 'release', 'track', 'show'];
        if (!in_array($entityType, $validEntityTypes)) {
            throw new Exception("Invalid entity type: {$entityType}");
        }

        // Check for duplicate like/spark (prevent double-liking)
        if (in_array($type, ['like', 'spark'])) {
            $existing = $this->get($userId, $entityType, $entityId, $type);
            if ($existing) {
                // Return existing engagement instead of creating duplicate
                return $existing;
            }
        }

        // Extract metadata
        $commentText = $metadata['comment_text'] ?? null;
        $sparkAmount = $metadata['spark_amount'] ?? null;
        $sharePlatform = $metadata['share_platform'] ?? null;

        // Validate spark amount
        if ($type === 'spark') {
            if (!$sparkAmount || $sparkAmount < 1) {
                throw new Exception('Spark amount must be at least 1');
            }
            // TODO: Verify user has enough sparks in balance
        }

        // Validate comment text
        if ($type === 'comment' && empty($commentText)) {
            throw new Exception('Comment text is required');
        }

        // Insert engagement
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.cdm_engagements (
                user_id, entity_type, entity_id, type,
                comment_text, spark_amount, share_platform,
                created_at
            ) VALUES (
                :user_id, :entity_type, :entity_id, :type,
                :comment_text, :spark_amount, :share_platform,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':type' => $type,
            ':comment_text' => $commentText,
            ':spark_amount' => $sparkAmount,
            ':share_platform' => $sharePlatform
        ]);

        $engagementId = $this->pdo->lastInsertId();

        // Recalculate EQS for this entity
        $this->recalculateEQS($entityType, $entityId);

        // Award XP for engagement (Retention System - Chapter 23)
        try {
            $this->awardEngagementXP($userId, $type, $sparkAmount ?? 1);
        } catch (Exception $e) {
            error_log("Warning: Failed to award XP: " . $e->getMessage());
            // Don't fail engagement creation if XP awarding fails
        }

        // Create notification for entity owner
        $this->createNotification($engagementId, $userId, $entityType, $entityId, $type);

        // Update user affinity if entity is an artist (Discovery Engine integration)
        if ($entityType === 'artist') {
            $this->updateAffinityFromEngagement($userId, $entityId, $type, $sparkAmount ?? 1.0);
        }

        // Track analytics if entity is a post (Post Analytics integration)
        if ($entityType === 'post') {
            $this->trackPostAnalytics($userId, $entityId, $engagementId, $type, $sparkAmount ?? null, $metadata);
        }

        return $this->getById($engagementId);
    }

    /**
     * Get engagement by ID
     *
     * @param int $id Engagement ID
     * @return array|null Engagement or null if not found
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                e.*,
                u.display_name as user_name,
                u.email as user_email
            FROM ngn_2025.cdm_engagements e
            LEFT JOIN `ngn_2025`.`users` u ON e.user_id = u.id
            WHERE e.id = :id AND e.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $engagement = $stmt->fetch();

        return $engagement ?: null;
    }

    /**
     * Get engagement by user, entity, and type
     *
     * @param int $userId User ID
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param string $type Engagement type
     * @return array|null Engagement or null if not found
     */
    public function get(int $userId, string $entityType, int $entityId, string $type): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ngn_2025.cdm_engagements
            WHERE user_id = :user_id
              AND entity_type = :entity_type
              AND entity_id = :entity_id
              AND type = :type
              AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':type' => $type
        ]);

        $engagement = $stmt->fetch();
        return $engagement ?: null;
    }

    /**
     * List engagements for an entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param array $filters Filters (type, user_id, limit, offset)
     * @return array List of engagements
     */
    public function list(string $entityType, int $entityId, array $filters = []): array
    {
        $where = ["e.entity_type = :entity_type", "e.entity_id = :entity_id", "e.deleted_at IS NULL"];
        $params = [
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ];

        // Filter by type
        if (!empty($filters['type'])) {
            $where[] = "e.type = :type";
            $params[':type'] = $filters['type'];
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $where[] = "e.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;

        $sql = "
            SELECT
                e.*,
                u.display_name as user_name,
                u.email as user_email
            FROM ngn_2025.cdm_engagements e
            LEFT JOIN `ngn_2025`.`users` u ON e.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        // Bind params
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Delete engagement (soft delete)
     *
     * @param int $id Engagement ID
     * @param int $userId User ID (must be owner of engagement or admin)
     * @return bool Success
     * @throws Exception
     */
    public function delete(int $id, int $userId): bool
    {
        $engagement = $this->getById($id);
        if (!$engagement) {
            throw new Exception('Engagement not found');
        }

        // Check ownership (only owner or admin can delete)
        // TODO: Add admin check
        if ($engagement['user_id'] != $userId) {
            throw new Exception('Not authorized to delete this engagement');
        }

        // Soft delete
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.cdm_engagements
            SET deleted_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);

        // Recalculate EQS
        $this->recalculateEQS($engagement['entity_type'], $engagement['entity_id']);

        return true;
    }

    /**
     * Get engagement counts for an entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Counts by type
     */
    public function getCounts(string $entityType, int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ngn_2025.cdm_engagement_counts
            WHERE entity_type = :entity_type AND entity_id = :entity_id
            LIMIT 1
        ");

        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);

        $counts = $stmt->fetch();

        if (!$counts) {
            // No engagements yet, return zeros
            return [
                'likes_count' => 0,
                'shares_count' => 0,
                'comments_count' => 0,
                'sparks_count' => 0,
                'sparks_total_amount' => 0,
                'eqs_score' => 0.00,
                'last_engagement_at' => null
            ];
        }

        return $counts;
    }

    public function getEngagementVelocity(string $entityType, int $entityId): float
    {
        $eqs = $this->recalculateEQS($entityType, $entityId);
        $creationDate = $this->getEntityCreationDate($entityType, $entityId);

        if (!$creationDate) {
            return 0.0;
        }

        $timeSincePost = time() - strtotime($creationDate);

        if ($timeSincePost <= 0) {
            return $eqs; // Avoid division by zero, return EQS
        }

        // The formula from the Bible (Ch 22.4) is EV = (Likes × 1) + (Comments × 3) + (Shares × 10) + (Sparks × 15) / TimeSincePost
        // This is equivalent to EQS / TimeSincePost
        $ev = $eqs / $timeSincePost;

        return $ev;
    }

    /**
     * Recalculate EQS (Engagement Quality Score) for an entity
     *
     * EQS Formula: Weighted sum of engagement types
     * - Like: 1 point
     * - Share: 3 points
     * - Comment: 2 points
     * - Spark: 5 points per spark
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return float Calculated EQS score
     */
    public function recalculateEQS(string $entityType, int $entityId): float
    {
        $counts = $this->getCounts($entityType, $entityId);

        $eqs = 0.0;
        $eqs += $counts['likes_count'] * self::EQS_WEIGHTS['like'];
        $eqs += $counts['shares_count'] * self::EQS_WEIGHTS['share'];
        $eqs += $counts['comments_count'] * self::EQS_WEIGHTS['comment'];
        $eqs += $counts['sparks_total_amount'] * self::EQS_WEIGHTS['spark'];

        // Update EQS in counts table
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.cdm_engagement_counts
            SET eqs_score = :eqs_score, updated_at = NOW()
            WHERE entity_type = :entity_type AND entity_id = :entity_id
        ");

        $stmt->execute([
            ':eqs_score' => $eqs,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId
        ]);

        return $eqs;
    }

    /**
     * Create notification for entity owner
     *
     * @param int $engagementId Engagement ID
     * @param int $actorUserId User who performed engagement
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param string $engagementType Engagement type
     */
    private function createNotification(int $engagementId, int $actorUserId, string $entityType, int $entityId, string $engagementType): void
    {
        // Get entity owner
        $recipientUserId = $this->getEntityOwner($entityType, $entityId);

        if (!$recipientUserId || $recipientUserId == $actorUserId) {
            // No owner or user engaging with their own content - skip notification
            return;
        }

        // Create notification
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.cdm_engagement_notifications (
                recipient_user_id, engagement_id, actor_user_id,
                entity_type, entity_id, engagement_type,
                created_at
            ) VALUES (
                :recipient_user_id, :engagement_id, :actor_user_id,
                :entity_type, :entity_id, :engagement_type,
                NOW()
            )
        ");

        $stmt->execute([
            ':recipient_user_id' => $recipientUserId,
            ':engagement_id' => $engagementId,
            ':actor_user_id' => $actorUserId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':engagement_type' => $engagementType
        ]);
    }

    /**
     * Get entity owner user ID
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return int|null Owner user ID or null
     */
    private function getEntityOwner(string $entityType, int $entityId): ?int
    {
        // Map entity types to tables and columns
        $tables = [
            'artist' => 'ngn_2025.artists',
            'label' => 'ngn_2025.labels',
            'venue' => 'ngn_2025.venues',
            'station' => 'ngn_2025.stations',
            'post' => 'ngn_2025.cdm_posts',
            'video' => 'ngn_2025.cdm_media',
            'release' => 'ngn_2025.releases',
            'track' => 'ngn_2025.tracks',
            'show' => 'ngn_2025.shows'
        ];

        if (!isset($tables[$entityType])) {
            return null;
        }

        $table = $tables[$entityType];

        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id FROM {$table}
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([':id' => $entityId]);
            $result = $stmt->fetch();

            return $result ? (int)$result['user_id'] : null;
        } catch (\Exception $e) {
            // Table might not exist or user_id column missing - skip notification
            return null;
        }
    }

    private function getEntityCreationDate(string $entityType, int $entityId): ?string
    {
        // Map entity types to tables
        $tables = [
            'artist' => 'ngn_2025.artists',
            'label' => 'ngn_2025.labels',
            'venue' => 'ngn_2025.venues',
            'station' => 'ngn_2025.stations',
            'post' => 'ngn_2025.cdm_posts',
            'video' => 'ngn_2025.cdm_media',
            'release' => 'ngn_2025.releases',
            'track' => 'ngn_2025.tracks',
            'show' => 'ngn_2025.shows'
        ];

        if (!isset($tables[$entityType])) {
            return null;
        }

        $table = $tables[$entityType];

        try {
            $stmt = $this->pdo->prepare("
                SELECT created_at FROM {$table}
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([':id' => $entityId]);
            $result = $stmt->fetch();

            return $result ? $result['created_at'] : null;
        } catch (\Exception $e) {
            // Table might not exist or created_at column missing
            return null;
        }
    }

    /**
     * Check if user has engaged with entity
     *
     * @param int $userId User ID
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param string $type Engagement type
     * @return bool True if user has engaged
     */
    public function hasEngaged(int $userId, string $entityType, int $entityId, string $type): bool
    {
        $engagement = $this->get($userId, $entityType, $entityId, $type);
        return $engagement !== null;
    }

    /**
     * Get user's notifications
     *
     * @param int $userId User ID
     * @param array $filters Filters (is_read, limit, offset)
     * @return array List of notifications
     */
    public function getNotifications(int $userId, array $filters = []): array
    {
        $where = ["n.recipient_user_id = :user_id"];
        $params = [':user_id' => $userId];

        // Filter by read status
        if (isset($filters['is_read'])) {
            $where[] = "n.is_read = :is_read";
            $params[':is_read'] = $filters['is_read'] ? 1 : 0;
        }

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;

        $sql = "
            SELECT
                n.*,
                u.display_name as actor_name,
                u.email as actor_email,
                e.comment_text,
                e.spark_amount,
                e.share_platform
            FROM ngn_2025.cdm_engagement_notifications n
            LEFT JOIN `ngn_2025`.`users` u ON n.actor_user_id = u.id
            LEFT JOIN ngn_2025.cdm_engagements e ON n.engagement_id = e.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark notification as read
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID (must be recipient)
     * @return bool Success
     * @throws Exception
     */
    public function markNotificationRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.cdm_engagement_notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = :id AND recipient_user_id = :user_id
        ");

        $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update user affinity based on engagement (Discovery Engine integration)
     *
     * @param int $userId User ID
     * @param int $artistId Artist ID
     * @param string $engagementType Engagement type (like, share, comment, spark)
     * @param float $value Value/amount for the engagement (spark amount, etc.)
     */
    private function updateAffinityFromEngagement(int $userId, int $artistId, string $engagementType, float $value = 1.0): void
    {
        try {
            $affinityService = new \NGN\Lib\Discovery\AffinityService(\NGN\Config::getInstance());
            $affinityService->updateAffinityFromEngagement($userId, $artistId, $engagementType, $value);
        } catch (Exception $e) {
            // Log error but don't fail engagement creation if affinity update fails
            error_log("Discovery Engine affinity update failed: " . $e->getMessage());
        }
    }

    /**
     * Track post analytics by engagement source (Post Analytics integration)
     */
    private function trackPostAnalytics(int $userId, int $postId, int $engagementId, string $engagementType, ?float $engagementValue, array $metadata): void
    {
        try {
            $analyticsService = new \NGN\Lib\Analytics\PostAnalyticsService(\NGN\Config::getInstance());
            $analyticsService->trackEngagementEvent($postId, $engagementType, [
                'user_id' => $userId,
                'engagement_id' => $engagementId,
                'engagement_value' => $engagementValue,
                'session_id' => $metadata['session_id'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
                'ip_hash' => $metadata['ip_hash'] ?? hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
                'referrer' => $metadata['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? null,
                'device_type' => $metadata['device_type'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log error but don't fail engagement creation if analytics fails
            error_log("Post Analytics tracking failed: " . $e->getMessage());
        }
    }

    /**
     * Award XP for engagement actions (Retention System - Chapter 23)
     */
    private function awardEngagementXP(int $userId, string $engagementType, float $sparkAmount = 1.0): void
    {
        try {
            $retentionService = new \NGN\Lib\Retention\RetentionService($this->pdo);

            $xpMapping = [
                'like' => 10,
                'comment' => 30,
                'share' => 100,
                'spark' => (int)$sparkAmount  // 1 XP per spark sent
            ];

            $xpAmount = $xpMapping[$engagementType] ?? 0;
            $source = $engagementType === 'spark' ? 'sparks' : 'engagement';

            if ($xpAmount > 0) {
                $retentionService->awardXP($userId, $xpAmount, $source);
            }
        } catch (Exception $e) {
            // Log error but don't fail engagement creation if XP awarding fails
            error_log("Retention XP award failed: " . $e->getMessage());
        }
    }
}
