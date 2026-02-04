<?php

namespace NGN\Lib\Analytics;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;
use PDOException;

/**
 * Post Analytics Service
 * Tracks engagement sources (authenticated vs anonymous) for posts
 * Enables creators to understand engagement quality for EQS calculations
 */
class PostAnalyticsService
{
    private PDO $readConnection;
    private PDO $writeConnection;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->readConnection = ConnectionFactory::read();
        $this->writeConnection = ConnectionFactory::write();
    }

    /**
     * Track engagement event with source information
     */
    public function trackEngagementEvent(int $postId, string $engagementType, array $data = []): void
    {
        try {
            $userId = $data['user_id'] ?? null;
            $isAuthenticated = $userId !== null ? 1 : 0;
            $sessionId = $data['session_id'] ?? null;
            $engagementId = $data['engagement_id'] ?? null;
            $engagementValue = $data['engagement_value'] ?? null;
            $userAgent = $data['user_agent'] ?? null;
            $ipHash = $data['ip_hash'] ?? null;
            $referrer = $data['referrer'] ?? null;
            $deviceType = $data['device_type'] ?? 'unknown';

            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`post_engagement_events` (
                    post_id, engagement_id, user_id, session_id, engagement_type,
                    is_authenticated, user_agent, ip_hash, engagement_value,
                    referrer, device_type, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $postId, $engagementId, $userId, $sessionId, $engagementType,
                $isAuthenticated, $userAgent, $ipHash, $engagementValue,
                $referrer, $deviceType
            ]);

            // Update analytics summary
            $this->updatePostAnalytics($postId);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error tracking engagement event', [
                'post_id' => $postId,
                'engagement_type' => $engagementType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update post analytics (called after each engagement)
     */
    public function updatePostAnalytics(int $postId): void
    {
        try {
            // Get engagement counts by source
            $stmt = $this->readConnection->prepare(
                'SELECT
                    engagement_type,
                    is_authenticated,
                    COUNT(*) as count,
                    SUM(COALESCE(engagement_value, 0)) as total_value
                 FROM `ngn_2025`.`post_engagement_events`
                 WHERE post_id = ?
                 GROUP BY engagement_type, is_authenticated'
            );
            $stmt->execute([$postId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Initialize counters
            $counts = [
                'authenticated_views' => 0,
                'anonymous_views' => 0,
                'authenticated_likes' => 0,
                'anonymous_likes' => 0,
                'authenticated_shares' => 0,
                'anonymous_shares' => 0,
                'authenticated_comments' => 0,
                'anonymous_comments' => 0,
                'authenticated_sparks' => 0.0,
                'anonymous_sparks' => 0.0,
            ];

            // Aggregate counts
            foreach ($events as $event) {
                $typeKey = ($event['is_authenticated'] ? 'authenticated_' : 'anonymous_') . $event['engagement_type'];
                if (isset($counts[$typeKey])) {
                    if (in_array($event['engagement_type'], ['views', 'likes', 'shares', 'comments'])) {
                        $counts[$typeKey] = (int) $event['count'];
                    } else {
                        $counts[$typeKey] = (float) $event['total_value'];
                    }
                }
            }

            // Calculate aggregate totals
            $totalAuthenticated = $counts['authenticated_views'] +
                                 $counts['authenticated_likes'] +
                                 $counts['authenticated_shares'] +
                                 $counts['authenticated_comments'] +
                                 (int) $counts['authenticated_sparks'];

            $totalAnonymous = $counts['anonymous_views'] +
                             $counts['anonymous_likes'] +
                             $counts['anonymous_shares'] +
                             $counts['anonymous_comments'] +
                             (int) $counts['anonymous_sparks'];

            $totalEngagement = $totalAuthenticated + $totalAnonymous;
            $authenticationRate = $totalEngagement > 0 ? round(($totalAuthenticated / $totalEngagement) * 100, 2) : 0;

            // Calculate fraud suspicion score
            $fraudScore = $this->calculateFraudScore($postId, $counts, $authenticationRate);

            // Update or insert analytics
            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`post_engagement_analytics` (
                    post_id, authenticated_views, anonymous_views,
                    authenticated_likes, anonymous_likes, authenticated_shares, anonymous_shares,
                    authenticated_comments, anonymous_comments, authenticated_sparks, anonymous_sparks,
                    total_authenticated_engagement, total_anonymous_engagement,
                    authentication_rate, fraud_suspicion_score
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                authenticated_views = VALUES(authenticated_views),
                anonymous_views = VALUES(anonymous_views),
                authenticated_likes = VALUES(authenticated_likes),
                anonymous_likes = VALUES(anonymous_likes),
                authenticated_shares = VALUES(authenticated_shares),
                anonymous_shares = VALUES(anonymous_shares),
                authenticated_comments = VALUES(authenticated_comments),
                anonymous_comments = VALUES(anonymous_comments),
                authenticated_sparks = VALUES(authenticated_sparks),
                anonymous_sparks = VALUES(anonymous_sparks),
                total_authenticated_engagement = VALUES(total_authenticated_engagement),
                total_anonymous_engagement = VALUES(total_anonymous_engagement),
                authentication_rate = VALUES(authentication_rate),
                fraud_suspicion_score = VALUES(fraud_suspicion_score),
                updated_at = NOW()'
            )->execute([
                $postId,
                $counts['authenticated_views'],
                $counts['anonymous_views'],
                $counts['authenticated_likes'],
                $counts['anonymous_likes'],
                $counts['authenticated_shares'],
                $counts['anonymous_shares'],
                $counts['authenticated_comments'],
                $counts['anonymous_comments'],
                $counts['authenticated_sparks'],
                $counts['anonymous_sparks'],
                $totalAuthenticated,
                $totalAnonymous,
                $authenticationRate,
                $fraudScore
            ]);

            // Check fraud flags
            $this->checkAndCreateFraudFlags($postId, $counts, $authenticationRate, $fraudScore);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error updating post analytics', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get post analytics
     */
    public function getPostAnalytics(int $postId): ?array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`post_engagement_analytics` WHERE post_id = ?'
            );
            $stmt->execute([$postId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error getting post analytics', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get daily analytics for date range
     */
    public function getDailyAnalytics(int $postId, string $startDate, string $endDate): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`post_analytics_daily`
                 WHERE post_id = ? AND date_key BETWEEN ? AND ?
                 ORDER BY date_key ASC'
            );
            $stmt->execute([$postId, $startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error getting daily analytics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get fraud flags for post
     */
    public function getFraudFlags(int $postId, string $severity = null): array
    {
        try {
            $sql = 'SELECT * FROM `ngn_2025`.`post_analytics_fraud_flags` WHERE post_id = ?';
            $params = [$postId];

            if ($severity) {
                $sql .= ' AND severity >= ?';
                $params[] = $severity;
            }

            $sql .= ' ORDER BY created_at DESC';

            $stmt = $this->readConnection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error getting fraud flags', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get engagement source breakdown
     */
    public function getEngagementSourceBreakdown(int $postId): array
    {
        try {
            $analytics = $this->getPostAnalytics($postId);

            if (!$analytics) {
                return [
                    'total_authenticated' => 0,
                    'total_anonymous' => 0,
                    'authentication_rate' => 0,
                    'breakdown' => [
                        'views' => ['authenticated' => 0, 'anonymous' => 0],
                        'likes' => ['authenticated' => 0, 'anonymous' => 0],
                        'shares' => ['authenticated' => 0, 'anonymous' => 0],
                        'comments' => ['authenticated' => 0, 'anonymous' => 0],
                        'sparks' => ['authenticated' => 0, 'anonymous' => 0]
                    ]
                ];
            }

            return [
                'total_authenticated' => $analytics['total_authenticated_engagement'],
                'total_anonymous' => $analytics['total_anonymous_engagement'],
                'authentication_rate' => $analytics['authentication_rate'],
                'fraud_suspicion_score' => $analytics['fraud_suspicion_score'],
                'breakdown' => [
                    'views' => [
                        'authenticated' => $analytics['authenticated_views'],
                        'anonymous' => $analytics['anonymous_views']
                    ],
                    'likes' => [
                        'authenticated' => $analytics['authenticated_likes'],
                        'anonymous' => $analytics['anonymous_likes']
                    ],
                    'shares' => [
                        'authenticated' => $analytics['authenticated_shares'],
                        'anonymous' => $analytics['anonymous_shares']
                    ],
                    'comments' => [
                        'authenticated' => $analytics['authenticated_comments'],
                        'anonymous' => $analytics['anonymous_comments']
                    ],
                    'sparks' => [
                        'authenticated' => (float) $analytics['authenticated_sparks'],
                        'anonymous' => (float) $analytics['anonymous_sparks']
                    ]
                ]
            ];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error getting engagement breakdown', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate fraud suspicion score (0-1 scale)
     */
    private function calculateFraudScore(int $postId, array $counts, float $authenticationRate): float
    {
        $score = 0.0;

        // High anonymous ratio indicator
        if ($authenticationRate < 30 && ($counts['anonymous_views'] + $counts['anonymous_likes'] > 10)) {
            $score += 0.25;
        }

        // Check for duplicate patterns
        $stmt = $this->readConnection->prepare(
            'SELECT COUNT(*) as count FROM `ngn_2025`.`post_engagement_events`
             WHERE post_id = ? AND is_duplicate = 1'
        );
        $stmt->execute([$postId]);
        $duplicates = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        if ($duplicates > 0) {
            $totalEvents = $counts['authenticated_views'] + $counts['anonymous_views'] +
                          $counts['authenticated_likes'] + $counts['anonymous_likes'] +
                          $counts['authenticated_shares'] + $counts['anonymous_shares'];
            $duplicateRate = $totalEvents > 0 ? $duplicates / $totalEvents : 0;
            if ($duplicateRate > 0.1) {
                $score += 0.3;
            }
        }

        // Unusual spike detection
        $avgDailyEngagement = $this->getAverageEngagementVelocity($postId);
        $todayEngagement = $this->getTodayEngagement($postId);
        if ($avgDailyEngagement > 0 && $todayEngagement > $avgDailyEngagement * 3) {
            $score += 0.2;
        }

        return min(round($score, 4), 1.0);
    }

    /**
     * Check and create fraud flags if thresholds exceeded
     */
    private function checkAndCreateFraudFlags(int $postId, array $counts, float $authRate, float $fraudScore): void
    {
        try {
            // Flag: High anonymous ratio
            if ($authRate < 20 && ($counts['anonymous_views'] + $counts['anonymous_likes'] > 50)) {
                $this->createFraudFlag($postId, 'high_anonymous_ratio', 'high', 'More than 80% of engagement from anonymous sources', $authRate, 20);
            }

            // Flag: High fraud suspicion score
            if ($fraudScore > 0.7) {
                $this->createFraudFlag($postId, 'bot_pattern', 'critical', 'Multiple suspicious patterns detected', $fraudScore, 0.7);
            }

            // Flag: Unusual spike
            $avgVelocity = $this->getAverageEngagementVelocity($postId);
            $todayEngagement = $this->getTodayEngagement($postId);
            if ($avgVelocity > 0 && $todayEngagement > $avgVelocity * 5) {
                $this->createFraudFlag($postId, 'unusual_spike', 'medium', 'Engagement spike detected (5x average)', $todayEngagement, $avgVelocity);
            }
        } catch (Exception $e) {
            LoggerFactory::getLogger('analytics')->error('Error checking fraud flags', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create fraud flag if not already exists today
     */
    private function createFraudFlag(int $postId, string $type, string $severity, string $description, $metricValue, $thresholdValue): void
    {
        try {
            // Check if flag already exists today
            $stmt = $this->readConnection->prepare(
                'SELECT id FROM `ngn_2025`.`post_analytics_fraud_flags`
                 WHERE post_id = ? AND flag_type = ? AND DATE(created_at) = DATE(NOW())'
            );
            $stmt->execute([$postId, $type]);
            if ($stmt->fetch()) {
                return; // Flag already created today
            }

            // Create new flag
            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`post_analytics_fraud_flags` (
                    post_id, flag_type, severity, description, metric_value, threshold_value
                ) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $postId, $type, $severity, $description, $metricValue, $thresholdValue
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error creating fraud flag', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get average daily engagement velocity
     */
    private function getAverageEngagementVelocity(int $postId): float
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT AVG(authenticated_views + anonymous_views +
                            authenticated_likes + anonymous_likes +
                            authenticated_shares + anonymous_shares +
                            authenticated_comments + anonymous_comments) as avg_engagement
                 FROM `ngn_2025`.`post_analytics_daily`
                 WHERE post_id = ? AND date_key >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
            );
            $stmt->execute([$postId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float) ($result['avg_engagement'] ?? 0);
        } catch (PDOException $e) {
            return 0.0;
        }
    }

    /**
     * Get today's engagement count
     */
    private function getTodayEngagement(int $postId): int
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as count FROM `ngn_2025`.`post_engagement_events`
                 WHERE post_id = ? AND DATE(created_at) = CURDATE()'
            );
            $stmt->execute([$postId]);
            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Detect duplicate engagement (same user/session engaging multiple times)
     */
    public function detectDuplicateEngagements(int $postId, int $maxAgeMinutes = 5): void
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT e1.id, e2.id as duplicate_id
                 FROM `ngn_2025`.`post_engagement_events` e1
                 JOIN `ngn_2025`.`post_engagement_events` e2
                   ON e1.post_id = e2.post_id
                   AND (e1.user_id IS NOT NULL AND e1.user_id = e2.user_id OR e1.session_id = e2.session_id)
                   AND e1.engagement_type = e2.engagement_type
                   AND e1.id < e2.id
                   AND TIMESTAMPDIFF(MINUTE, e1.created_at, e2.created_at) <= ?
                 WHERE e1.post_id = ?
                 LIMIT 1000'
            );
            $stmt->execute([$maxAgeMinutes, $postId]);
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($duplicates as $dup) {
                $this->writeConnection->prepare(
                    'UPDATE `ngn_2025`.`post_engagement_events`
                     SET is_duplicate = 1, duplicate_of_event_id = ?
                     WHERE id = ?'
                )->execute([$dup['id'], $dup['duplicate_id']]);
            }
        } catch (PDOException $e) {
            LoggerFactory::getLogger('analytics')->error('Error detecting duplicates', ['error' => $e->getMessage()]);
        }
    }
}
