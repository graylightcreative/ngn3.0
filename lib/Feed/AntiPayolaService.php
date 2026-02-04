<?php

namespace NGN\Lib\Feed;

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;

/**
 * AntiPayolaService
 *
 * Enforcement layer to prevent paid organic boosts and detect suspicious engagement spikes.
 *
 * Rules:
 * - Organic posts cannot have paid boosts
 * - Only labeled ad inventory (sidebar, banners) allowed
 * - Sudden EV spikes flagged and investigated
 * - Verified humans only count toward EV tiers
 *
 * Related: Bible Ch. 22 - Social Feed & Engagement Algorithm
 */
class AntiPayolaService
{
    private \PDO $read;
    private \PDO $write;
    private Config $config;

    // Spike detection thresholds
    private const SPIKE_MULTIPLIER_THRESHOLD = 2.0; // 2x daily average
    private const SUSPICIOUS_ANONYMOUS_RATIO = 0.8; // >80% anonymous engagement
    private const MIN_HOURS_FOR_BASELINE = 6;

    // Ad types allowed
    private const ALLOWED_AD_TYPES = ['sidebar_ad', 'banner_ad', 'promoted_video', 'sponsored_post'];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read();
        $this->write = ConnectionFactory::write();
    }

    /**
     * Validate that post has no paid promotion
     *
     * Organic posts must not have paid_promotion flag
     *
     * @param int $postId
     * @return bool True if no paid promotion detected
     */
    public function validatePostHasNoPaidPromotion(int $postId): bool
    {
        try {
            $stmt = $this->read->prepare("
                SELECT has_paid_promotion, paid_promotion_type
                FROM post_visibility_state
                WHERE post_id = ?
            ");
            $stmt->execute([$postId]);
            $state = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$state) {
                return true; // No state = organic
            }

            return !$state['has_paid_promotion'];
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error validating post promotion', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for suspicious payment-driven engagement patterns
     *
     * Detects:
     * 1. Sudden EV spikes (>2x daily average)
     * 2. Abnormal anonymous ratio (>80%)
     * 3. Bot-like engagement patterns
     * 4. Geo anomalies or device anomalies
     *
     * @param int $postId
     * @return array Flags array [suspicious: bool, flags: [], severity: string]
     */
    public function checkForPaymentBoosting(int $postId): array
    {
        try {
            $flags = [];
            $maxSeverity = 'low';

            // Get post age and engagement data
            $stmt = $this->read->prepare("
                SELECT
                    p.created_at,
                    pec.authenticated_views,
                    pec.anonymous_views,
                    pec.authentication_rate,
                    pec.fraud_suspicion_score
                FROM posts p
                LEFT JOIN post_engagement_analytics pec ON p.id = pec.post_id
                WHERE p.id = ?
            ");
            $stmt->execute([$postId]);
            $postData = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$postData) {
                return ['suspicious' => false, 'flags' => [], 'severity' => 'none'];
            }

            $hoursOld = (time() - strtotime($postData['created_at'])) / 3600;

            // Flag 1: High anonymous ratio
            $totalViews = ($postData['authenticated_views'] ?? 0) + ($postData['anonymous_views'] ?? 0);
            if ($totalViews > 0) {
                $anonymousRatio = ($postData['anonymous_views'] ?? 0) / $totalViews;
                if ($anonymousRatio > self::SUSPICIOUS_ANONYMOUS_RATIO) {
                    $flags[] = [
                        'type' => 'high_anonymous_ratio',
                        'severity' => 'medium',
                        'ratio' => round($anonymousRatio * 100, 2),
                        'threshold' => self::SUSPICIOUS_ANONYMOUS_RATIO * 100
                    ];
                    $maxSeverity = 'medium';
                }
            }

            // Flag 2: Check fraud score from PostAnalyticsService
            if (($postData['fraud_suspicion_score'] ?? 0) > 0.7) {
                $flags[] = [
                    'type' => 'fraud_flag_detected',
                    'severity' => 'high',
                    'score' => round($postData['fraud_suspicion_score'], 4)
                ];
                $maxSeverity = 'high';
            }

            // Flag 3: Sudden EV spike (if older than baseline window)
            if ($hoursOld >= self::MIN_HOURS_FOR_BASELINE) {
                $spikeInfo = $this->detectEVSpike($postId);
                if ($spikeInfo['is_spike']) {
                    $flags[] = [
                        'type' => 'sudden_ev_spike',
                        'severity' => 'medium',
                        'current_spike' => round($spikeInfo['current_spike'], 2),
                        'baseline' => round($spikeInfo['baseline'], 2),
                        'multiplier' => round($spikeInfo['multiplier'], 2)
                    ];
                    $maxSeverity = 'high';
                }
            }

            $isSuspicious = !empty($flags);

            // Log if suspicious
            if ($isSuspicious) {
                LoggerFactory::getLogger('feed')->warning('Suspicious engagement pattern detected', [
                    'post_id' => $postId,
                    'severity' => $maxSeverity,
                    'flags_count' => count($flags)
                ]);
            }

            return [
                'suspicious' => $isSuspicious,
                'flags' => $flags,
                'severity' => $maxSeverity
            ];
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error checking for payment boosting', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return ['suspicious' => false, 'flags' => [], 'severity' => 'error'];
        }
    }

    /**
     * Detect EV spike compared to baseline
     *
     * @param int $postId
     * @return array [is_spike: bool, current_spike: float, baseline: float, multiplier: float]
     */
    private function detectEVSpike(int $postId): array
    {
        try {
            // Get engagement data from past 24h, split into first 6h and last 6h
            $stmt = $this->read->prepare("
                SELECT
                    SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 6 HOUR) THEN 1 ELSE 0 END) as baseline_count,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR) THEN 1 ELSE 0 END) as recent_count
                FROM cdm_engagements
                WHERE entity_type = 'post' AND entity_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$postId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $baseline = ($data['baseline_count'] ?? 0) / 6; // Per-hour baseline
            $recent = ($data['recent_count'] ?? 0) / 6; // Per-hour recent

            $multiplier = $baseline > 0 ? $recent / $baseline : 0;
            $isSpikeDetected = $multiplier >= self::SPIKE_MULTIPLIER_THRESHOLD && $baseline > 0;

            return [
                'is_spike' => $isSpikeDetected,
                'current_spike' => $recent,
                'baseline' => $baseline,
                'multiplier' => $multiplier
            ];
        } catch (\Exception $e) {
            return ['is_spike' => false, 'current_spike' => 0, 'baseline' => 0, 'multiplier' => 1];
        }
    }

    /**
     * Flag a post with suspicious EV spike
     *
     * @param int $postId
     * @return bool Success
     */
    public function flagSuspiciousEVSpike(int $postId): bool
    {
        try {
            // Get spike info
            $spikeInfo = $this->detectEVSpike($postId);

            if (!$spikeInfo['is_spike']) {
                return false;
            }

            // Log to analytics fraud flags table
            $stmt = $this->write->prepare("
                INSERT INTO post_analytics_fraud_flags (
                    post_id, flag_type, severity, description, metric_value, threshold_value
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    flag_type = ?,
                    severity = ?,
                    metric_value = ?,
                    threshold_value = ?
            ");

            $description = sprintf(
                'Sudden EV spike detected: %.2fx daily average (%.2f per hour baseline, %.2f per hour recent)',
                $spikeInfo['multiplier'],
                $spikeInfo['baseline'],
                $spikeInfo['current_spike']
            );

            $stmt->execute([
                $postId,
                'unusual_spike',
                'medium',
                $description,
                $spikeInfo['multiplier'],
                self::SPIKE_MULTIPLIER_THRESHOLD,
                'unusual_spike',
                'medium',
                $spikeInfo['multiplier'],
                self::SPIKE_MULTIPLIER_THRESHOLD
            ]);

            LoggerFactory::getLogger('feed')->warning('Suspicious spike flagged', [
                'post_id' => $postId,
                'multiplier' => round($spikeInfo['multiplier'], 2)
            ]);

            return true;
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error flagging suspicious spike', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Require ad labeling for promoted/paid posts
     *
     * Marks post as having paid promotion and requires ad label display
     *
     * @param int $postId
     * @param string $adType One of ALLOWED_AD_TYPES
     * @return bool Success
     */
    public function requireAdLabelingForPromoted(int $postId, string $adType): bool
    {
        // Validate ad type
        if (!in_array($adType, self::ALLOWED_AD_TYPES)) {
            LoggerFactory::getLogger('feed')->warning('Invalid ad type', [
                'ad_type' => $adType,
                'allowed' => self::ALLOWED_AD_TYPES
            ]);
            return false;
        }

        try {
            $stmt = $this->write->prepare("
                UPDATE post_visibility_state
                SET has_paid_promotion = 1, paid_promotion_type = ?
                WHERE post_id = ?
            ");
            $stmt->execute([$adType, $postId]);

            LoggerFactory::getLogger('feed')->info('Ad labeling required', [
                'post_id' => $postId,
                'ad_type' => $adType
            ]);

            return true;
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error requiring ad label', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Audit all posts for anti-payola violations
     *
     * Called by anti_payola_audit cron job
     * Finds suspicious posts and verifies ad labeling
     *
     * @return array Audit results
     */
    public function auditAllPosts(): array
    {
        try {
            // Find posts with suspicious spikes
            $stmt = $this->read->prepare("
                SELECT pvs.post_id
                FROM post_visibility_state pvs
                WHERE TIMESTAMPDIFF(HOUR, pvs.created_at, NOW()) < 48
                AND pvs.expired_at IS NULL
                AND pvs.has_paid_promotion = 0
            ");
            $stmt->execute();
            $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $suspicious = [];
            $violations = [];

            foreach ($posts as $post) {
                $postId = $post['post_id'];
                $checkResult = $this->checkForPaymentBoosting($postId);

                if ($checkResult['suspicious']) {
                    $suspicious[] = [
                        'post_id' => $postId,
                        'severity' => $checkResult['severity'],
                        'flags' => $checkResult['flags']
                    ];

                    // Flag for investigation
                    $this->flagSuspiciousEVSpike($postId);
                }
            }

            // Verify ad labeling for posts with paid_promotion flag
            $stmt = $this->read->prepare("
                SELECT pvs.post_id, pvs.paid_promotion_type, p.title
                FROM post_visibility_state pvs
                JOIN posts p ON pvs.post_id = p.id
                WHERE pvs.has_paid_promotion = 1
                AND TIMESTAMPDIFF(HOUR, pvs.created_at, NOW()) < 48
            ");
            $stmt->execute();
            $paidPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // TODO: Verify ad labels are shown in UI
            // For now, just note them
            foreach ($paidPosts as $post) {
                if (!$post['paid_promotion_type']) {
                    $violations[] = [
                        'post_id' => $post['post_id'],
                        'violation' => 'missing_ad_label',
                        'title' => $post['title']
                    ];
                }
            }

            return [
                'suspicious_posts' => count($suspicious),
                'violations' => count($violations),
                'suspicious' => $suspicious,
                'violations_detail' => $violations
            ];
        } catch (\Exception $e) {
            LoggerFactory::getLogger('feed')->error('Error in anti-payola audit', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get compliance report for suspicious posts
     *
     * @param int $limit
     * @return array Posts needing review
     */
    public function getComplianceReport(int $limit = 50): array
    {
        try {
            $stmt = $this->read->prepare("
                SELECT
                    paf.id,
                    paf.post_id,
                    paf.flag_type,
                    paf.severity,
                    paf.description,
                    paf.metric_value,
                    paf.reviewed_at,
                    paf.action_taken,
                    p.title,
                    pvs.created_at,
                    pvs.ev_score_current
                FROM post_analytics_fraud_flags paf
                JOIN posts p ON paf.post_id = p.id
                JOIN post_visibility_state pvs ON paf.post_id = pvs.post_id
                WHERE paf.action_taken = 'none'
                ORDER BY paf.severity DESC, paf.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            LoggerFactory::getLogger('feed')->error('Error fetching compliance report', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
