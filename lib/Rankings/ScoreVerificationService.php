<?php

namespace NGN\Lib\Rankings;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;
use PDOException;

/**
 * Score Verification Service
 * Recalculates NGN scores from raw data
 * Enables verification of historical calculations
 */
class ScoreVerificationService
{
    private PDO $readConnection;
    private PDO $writeConnection;
    private Config $config;
    private NGNScoreAuditService $auditService;

    public function __construct(Config $config, ?NGNScoreAuditService $auditService = null)
    {
        $this->config = $config;
        $this->readConnection = ConnectionFactory::read();
        $this->writeConnection = ConnectionFactory::write();
        $this->auditService = $auditService ?? new NGNScoreAuditService($config);
    }

    /**
     * Verify a historical score by recalculating from raw data
     */
    public function verifyScore(int $historyId): array
    {
        try {
            // Get original calculation
            $stmt = $this->readConnection->prepare('SELECT * FROM `ngn_2025`.`ngn_score_history` WHERE id = ?');
            $stmt->execute([$historyId]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                return ['status' => 'error', 'message' => 'Score not found'];
            }

            $artistId = (int) $original['artist_id'];
            $periodStart = $original['period_start'];
            $periodEnd = $original['period_end'];

            // Recalculate from raw data
            $recalculated = $this->recalculateScore(
                $artistId,
                $periodStart,
                $periodEnd,
                json_decode($original['formula_used'], true)
            );

            // Compare results
            $scoreMatch = abs((float) $original['score_value'] - $recalculated['score']) < 0.01;
            $percentDiff = $original['score_value'] > 0
                ? (abs((float) $original['score_value'] - $recalculated['score']) / (float) $original['score_value']) * 100
                : 0;

            // Verify data lineage
            $lineageVerification = $this->auditService->verifyLineageIntegrity($historyId);

            // Create verification record
            $status = $scoreMatch && $lineageVerification['valid'] === $lineageVerification['total_sources']
                ? 'passed'
                : 'failed';

            $verificationId = $this->recordVerification(
                $artistId,
                $historyId,
                'recalculation',
                $status,
                (float) $original['score_value'],
                $recalculated['score'],
                $lineageVerification
            );

            return [
                'status' => $status,
                'verification_id' => $verificationId,
                'original_score' => (float) $original['score_value'],
                'recalculated_score' => round($recalculated['score'], 4),
                'score_match' => $scoreMatch,
                'percent_difference' => round($percentDiff, 4),
                'lineage_valid' => $lineageVerification['valid'] === $lineageVerification['total_sources'],
                'lineage_issues' => $lineageVerification['issues'],
                'data_completeness' => $recalculated['data_completeness'],
                'calculation_method' => $original['calculation_method']
            ];
        } catch (Exception $e) {
            LoggerFactory::getLogger('audit')->error('Error verifying score', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Recalculate score from raw data
     */
    public function recalculateScore(int $artistId, string $periodStart, string $periodEnd, ?array $formula = null): array
    {
        try {
            // Fetch raw data for period
            $rawData = $this->fetchRawData($artistId, $periodStart, $periodEnd);

            // Apply formula (default: equal weights)
            if (!$formula) {
                $formula = [
                    'method' => 'v1.0',
                    'factors' => [
                        'spins' => 0.25,
                        'plays' => 0.25,
                        'engagements' => 0.25,
                        'sparks' => 0.15,
                        'momentum' => 0.10
                    ]
                ];
            }

            // Calculate factors
            $factors = $this->calculateFactors($rawData, $formula['factors'] ?? []);

            // Normalize to 0-100
            $baseScore = $this->normalizeScore($factors);

            // Apply modifiers (fraud, reputation)
            $modifiers = $this->calculateModifiers($artistId, $rawData);
            $finalScore = $baseScore * $modifiers['reputation_multiplier'];
            $finalScore = max(0, min(100, $finalScore));

            return [
                'score' => round($finalScore, 4),
                'base_score' => round($baseScore, 4),
                'factors' => $factors,
                'modifiers' => $modifiers,
                'raw_data' => $rawData,
                'data_completeness' => $this->calculateDataCompleteness($rawData),
                'formula_used' => $formula
            ];
        } catch (Exception $e) {
            LoggerFactory::getLogger('audit')->error('Error recalculating score', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Fetch all raw data for calculation period
     */
    private function fetchRawData(int $artistId, string $periodStart, string $periodEnd): array
    {
        try {
            $data = [
                'spins' => 0,
                'plays' => 0,
                'views' => 0,
                'engagements' => [],
                'sparks' => 0,
                'followers' => 0,
                'data_sources' => []
            ];

            // Fetch spins (if table exists)
            try {
                $pdoSpins = ConnectionFactory::named($this->config, 'spins2025'); // Use dedicated spins connection
                $stmt = $pdoSpins->prepare(
                    'SELECT COUNT(*) as count FROM `ngn_spins_2025`.`station_spins` WHERE artist_id = ? AND played_at BETWEEN ? AND ?'
                );
                $stmt->execute([$artistId, $periodStart, $periodEnd]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['spins'] = (int) ($result['count'] ?? 0);
                $data['data_sources'][] = 'spins';
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Spins table query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            // Fetch plays
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT COUNT(*) as count FROM `ngn_2025`.`playback_events` WHERE artist_id = ? AND played_at BETWEEN ? AND ?'
                );
                $stmt->execute([$artistId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['plays'] = (int) ($result['count'] ?? 0);
                $data['data_sources'][] = 'plays';
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Play events table query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            // Fetch views (from posts/videos)
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT COALESCE(SUM(view_count), 0) as total FROM `ngn_2025`.`videos` WHERE artist_id = ? AND published_at BETWEEN ? AND ?'
                );
                $stmt->execute([$artistId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['views'] = (int) ($result['total'] ?? 0);
                $data['data_sources'][] = 'video_views';
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Video views query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            // Fetch engagements (likes, shares, comments)
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT engagement_type AS type, COUNT(*) as count FROM `ngn_2025`.`cdm_engagements`
                     WHERE artist_id = ? AND timestamp BETWEEN ? AND ? AND deleted_at IS NULL
                     GROUP BY engagement_type'
                );
                $stmt->execute([$artistId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
                $engagements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($engagements as $eng) {
                    $data['engagements'][$eng['type']] = (int) $eng['count'];
                }
                $data['data_sources'][] = 'engagements';
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Engagements table query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            // Fetch sparks
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT COALESCE(SUM(engagement_value), 0) as total FROM `ngn_2025`.`cdm_engagements`
                     WHERE artist_id = ? AND engagement_type = "spark" AND timestamp BETWEEN ? AND ? AND deleted_at IS NULL'
                );
                $stmt->execute([$artistId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['sparks'] = (float) ($result['total'] ?? 0);
                $data['data_sources'][] = 'sparks';
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Sparks query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            // Fetch follower count (snapshot at end of period)
            try {
                $stmt = $this->readConnection->prepare(
                    'SELECT COUNT(*) as follower_count FROM `ngn_2025`.`follows` WHERE artist_id = ? AND followed_at <= ?'
                );
                $stmt->execute([$artistId, $periodEnd . ' 23:59:59']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $data['followers'] = (int) ($result['follower_count'] ?? 0);
            } catch (PDOException $e) {
                LoggerFactory::getLogger('audit')->warning('Followers table query failed (might not exist or be empty)', ['error' => $e->getMessage()]);
            }

            return $data;
        } catch (Exception $e) {
            LoggerFactory::getLogger('audit')->error('Error fetching raw data', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Calculate score factors from raw data
     */
    private function calculateFactors(array $rawData, array $weights): array
    {
        // Normalize raw data to 0-100 scale
        $spinsScore = min($rawData['spins'] * 0.5, 100);
        $playsScore = min($rawData['plays'] * 0.1, 100);
        $viewsScore = min($rawData['views'] * 0.05, 100);
        $engagementScore = min((array_sum($rawData['engagements']) ?? 0) * 2, 100);
        $sparksScore = min($rawData['sparks'] * 0.5, 100);
        $momentumScore = $this->calculateMomentum($rawData);

        return [
            'spins_factor' => $spinsScore * ($weights['spins'] ?? 0.25),
            'plays_factor' => $playsScore * ($weights['plays'] ?? 0.25),
            'views_factor' => $viewsScore * ($weights['views'] ?? 0.15),
            'engagement_factor' => $engagementScore * ($weights['engagements'] ?? 0.25),
            'sparks_factor' => $sparksScore * ($weights['sparks'] ?? 0.10),
            'momentum_factor' => $momentumScore * ($weights['momentum'] ?? 0.10)
        ];
    }

    /**
     * Calculate momentum (trend) factor
     */
    private function calculateMomentum(array $rawData): float
    {
        $current = array_sum($rawData['engagements']) + $rawData['sparks'];

        // Compare to previous period (simplified)
        // In production, would fetch previous period data
        return min($current > 50 ? (($current - 50) / 50) * 100 : 50, 100);
    }

    /**
     * Normalize combined factors to 0-100 score
     */
    private function normalizeScore(array $factors): float
    {
        $sum = array_sum($factors);
        return min(max($sum, 0), 100);
    }

    /**
     * Calculate modifiers (fraud rate, reputation multiplier)
     */
    private function calculateModifiers(int $artistId, array $rawData): array
    {
        // Get artist's trust metrics
        $stmt = $this->readConnection->prepare(
            'SELECT reputation_score, fraud_flags FROM `ngn_2025`.`artists` WHERE id = ?'
        );
        $stmt->execute([$artistId]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);

        $fraudRate = 0.0;
        $reputationMultiplier = 1.0;

        if ($artist) {
            $reputationScore = (float) ($artist['reputation_score'] ?? 100);
            $reputationMultiplier = 0.8 + ($reputationScore / 500); // Maps 0-100 to 0.8-1.2
            $reputationMultiplier = min(1.2, max(0.8, $reputationMultiplier));
        }

        // Check for suspicious patterns
        if (isset($rawData['engagements']) && count($rawData['engagements']) > 0) {
            $engagementTotal = array_sum($rawData['engagements']);
            $likesRatio = ($rawData['engagements']['like'] ?? 0) / max($engagementTotal, 1);

            // High like ratio with low other engagement = suspicious
            if ($likesRatio > 0.95) {
                $fraudRate = 0.15;
            }
        }

        return [
            'fraud_rate' => round($fraudRate, 4),
            'reputation_multiplier' => round($reputationMultiplier, 2),
            'final_score' => 0 // Will be calculated by caller
        ];
    }

    /**
     * Calculate data completeness percentage
     */
    private function calculateDataCompleteness(array $rawData): float
    {
        $sourcesFound = count($rawData['data_sources']);
        $sourceTarget = 6; // spins, plays, views, engagements, sparks, followers

        $completeness = min(($sourcesFound / $sourceTarget) * 100, 100);

        // Also check if we have non-zero values
        $hasData = ($rawData['spins'] > 0 || $rawData['plays'] > 0 || $rawData['views'] > 0 ||
                   array_sum($rawData['engagements']) > 0 || $rawData['sparks'] > 0);

        return $hasData ? round($completeness, 2) : 0.0;
    }

    /**
     * Record verification results
     */
    private function recordVerification(
        int $artistId,
        int $historyId,
        string $type,
        string $status,
        float $originalScore,
        float $recalculatedScore,
        array $lineageIssues
    ): int {
        try {
            $scoreDiff = abs($originalScore - $recalculatedScore);
            $percentDiff = $originalScore > 0 ? ($scoreDiff / $originalScore) * 100 : 0;

            $issues = [];
            if (!empty($lineageIssues['issues'])) {
                foreach ($lineageIssues['issues'] as $issue) {
                    $issues[] = [
                        'type' => 'data_integrity',
                        'severity' => $issue['status'] === 'modified' ? 'high' : 'medium',
                        'description' => "Data modified: {$issue['source_table']}.{$issue['source_id']}"
                    ];
                }
            }

            $stmt = $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_verification` (
                    artist_id, history_id, verification_type, verification_status,
                    original_score, recalculated_score, score_difference, percent_difference,
                    issues_found
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            return $this->writeConnection->lastInsertId();
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error recording verification', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Run bulk verification on scores
     */
    public function runBulkVerification(string $periodStart, string $periodEnd, int $limit = 100): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT DISTINCT artist_id FROM `ngn_2025`.`ngn_score_history`
                 WHERE period_start BETWEEN ? AND ?
                 LIMIT ?'
            );
            $stmt->execute([$periodStart, $periodEnd, $limit]);
            $artistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $results = [
                'total_verified' => 0,
                'passed' => 0,
                'failed' => 0,
                'issues' => []
            ];

            foreach ($artistIds as $artistId) {
                $stmt = $this->readConnection->prepare(
                    'SELECT id FROM `ngn_2025`.`ngn_score_history` WHERE artist_id = ? AND period_start BETWEEN ? AND ? LIMIT 1'
                );
                $stmt->execute([$artistId, $periodStart, $periodEnd]);
                $history = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($history) {
                    $verification = $this->verifyScore((int) $history['id']);
                    $results['total_verified']++;

                    if ($verification['status'] === 'passed') {
                        $results['passed']++;
                    } else {
                        $results['failed']++;
                        $results['issues'][] = [
                            'artist_id' => $artistId,
                            'verification_id' => $verification['verification_id'],
                            'percent_difference' => $verification['percent_difference']
                        ];
                    }
                }
            }

            return $results;
        } catch (Exception $e) {
            LoggerFactory::getLogger('audit')->error('Error running bulk verification', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
