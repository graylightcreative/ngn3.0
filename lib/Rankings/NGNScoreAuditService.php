<?php

namespace NGN\Lib\Rankings;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use PDO;
use PDOException;

/**
 * NGN Score Audit Service
 * Creates immutable audit trail of all NGN score calculations
 * Enables historical verification and fraud detection
 */
class NGNScoreAuditService
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
     * Record a score calculation to audit trail
     */
    public function recordScoreCalculation(
        int $artistId,
        float $scoreValue,
        string $periodType,
        string $periodStart,
        string $periodEnd,
        array $inputs,
        array $factors,
        array $modifiers,
        string $calculationMethod
    ): int {
        try {
            $formulaUsed = json_encode([
                'method' => $calculationMethod,
                'timestamp' => date('c'),
                'factors' => $factors,
                'modifiers' => $modifiers
            ]);

            $stmt = $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_history` (
                    artist_id, score_value, period_type, period_start, period_end,
                    spins_count, plays_count, views_count, engagements_count, sparks_count, followers_count,
                    spins_factor, plays_factor, views_factor, engagement_factor, sparks_factor, momentum_factor,
                    fraud_rate, reputation_multiplier, final_score,
                    calculation_method, formula_used, data_completeness,
                    calculated_by, calculated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $artistId,
                $scoreValue,
                $periodType,
                $periodStart,
                $periodEnd,
                $inputs['spins_count'] ?? 0,
                $inputs['plays_count'] ?? 0,
                $inputs['views_count'] ?? 0,
                $inputs['engagements_count'] ?? 0,
                $inputs['sparks_count'] ?? 0,
                $inputs['followers_count'] ?? 0,
                $factors['spins_factor'] ?? 0,
                $factors['plays_factor'] ?? 0,
                $factors['views_factor'] ?? 0,
                $factors['engagement_factor'] ?? 0,
                $factors['sparks_factor'] ?? 0,
                $factors['momentum_factor'] ?? 0,
                $modifiers['fraud_rate'] ?? 0,
                $modifiers['reputation_multiplier'] ?? 1.0,
                $modifiers['final_score'] ?? $scoreValue,
                $calculationMethod,
                $formulaUsed,
                $inputs['data_completeness'] ?? 100,
                'cron_job'
            ]);

            return $this->writeConnection->lastInsertId();
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error recording score calculation', [
                'artist_id' => $artistId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get score history for artist
     */
    public function getScoreHistory(int $artistId, string $periodType = null, int $limit = 100): array
    {
        try {
            $sql = 'SELECT * FROM `ngn_2025`.`ngn_score_history` WHERE artist_id = ?';
            $params = [$artistId];

            if ($periodType) {
                $sql .= ' AND period_type = ?';
                $params[] = $periodType;
            }

            $sql .= ' ORDER BY period_start DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $this->readConnection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error getting score history', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create audit trail lineage - track which data fed into score
     */
    public function recordLineage(int $historyId, string $sourceTable, int $sourceId, array $dataSnapshot): void
    {
        try {
            $hashValue = hash('sha256', json_encode($dataSnapshot));

            $stmt = $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_lineage` (
                    history_id, source_table, source_id, record_count, data_snapshot, hash_value
                ) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $historyId,
                $sourceTable,
                $sourceId,
                1,
                json_encode($dataSnapshot),
                $hashValue
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error recording lineage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify if source data has been modified since calculation
     */
    public function verifyLineageIntegrity(int $historyId): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`ngn_score_lineage` WHERE history_id = ?'
            );
            $stmt->execute([$historyId]);
            $lineages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [
                'total_sources' => count($lineages),
                'valid' => 0,
                'modified' => 0,
                'deleted' => 0,
                'suspicious' => 0,
                'issues' => []
            ];

            foreach ($lineages as $lineage) {
                $status = $this->verifySourceData($lineage);
                $results[$status]++;

                if ($status !== 'valid') {
                    $results['issues'][] = [
                        'source_table' => $lineage['source_table'],
                        'source_id' => $lineage['source_id'],
                        'status' => $status,
                        'original_hash' => $lineage['hash_value']
                    ];

                    // Update lineage status
                    $this->writeConnection->prepare(
                                            'UPDATE `ngn_2025`.`ngn_score_lineage` SET validation_status = ? WHERE id = ?'
                                    )->execute([$status, $lineage['id']]);                }
            }

            return $results;
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error verifying lineage', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Verify if source data still exists and matches hash
     */
    private function verifySourceData(array $lineage): string
    {
        try {
            $sourceTable = $lineage['source_table'];
            $sourceId = $lineage['source_id'];
            $originalHash = $lineage['hash_value'];

            // Fetch current data
            $stmt = $this->readConnection->prepare(
                "SELECT * FROM `ngn_2025`.`{$sourceTable}` WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$sourceId]);
            $currentData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentData) {
                return 'deleted';
            }

            $currentHash = hash('sha256', json_encode($currentData));

            if ($currentHash === $originalHash) {
                return 'valid';
            } else {
                return 'modified';
            }
        } catch (PDOException $e) {
            return 'suspicious';
        }
    }

    /**
     * Record correction to score
     */
    public function recordCorrection(
        int $artistId,
        int $historyId,
        string $correctionType,
        string $reason,
        float $originalScore,
        float $correctedScore,
        int $requestedBy,
        int $approvedBy
    ): int {
        try {
            $stmt = $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_corrections` (
                    artist_id, history_id, correction_type, reason,
                    original_score, corrected_score, adjustment_amount,
                    requested_by, approved_by, approved_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $artistId,
                $historyId,
                $correctionType,
                $reason,
                $originalScore,
                $correctedScore,
                $correctedScore - $originalScore,
                $requestedBy,
                $approvedBy
            ]);

            return $this->writeConnection->lastInsertId();
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error recording correction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get corrections for artist
     */
    public function getCorrectionHistory(int $artistId, int $limit = 50): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`ngn_score_corrections` WHERE artist_id = ? ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute([$artistId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error getting correction history', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * File a score dispute
     */
    public function createDispute(
        int $artistId,
        int $historyId,
        string $disputeType,
        string $description,
        ?float $allegedImpact = null
    ): int {
        try {
            $stmt = $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_score_disputes` (
                    artist_id, history_id, dispute_type, description, alleged_impact, severity
                ) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $artistId,
                $historyId,
                $disputeType,
                $description,
                $allegedImpact,
                'medium'
            ]);

            return $this->writeConnection->lastInsertId();
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error creating dispute', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get disputes for artist
     */
    public function getDisputes(int $artistId, string $status = null): array
    {
        try {
            $sql = 'SELECT * FROM `ngn_2025`.`ngn_score_disputes` WHERE artist_id = ?';
            $params = [$artistId];

            if ($status) {
                $sql .= ' AND status = ?';
                $params[] = $status;
            }

            $sql .= ' ORDER BY created_at DESC';

            $stmt = $this->readConnection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error getting disputes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Update dispute status
     */
    public function updateDisputeStatus(int $disputeId, string $status, array $resolution = []): void
    {
        try {
            $resolutionText = isset($resolution['resolution']) ? $resolution['resolution'] : null;
            $actionTaken = isset($resolution['action_taken']) ? $resolution['action_taken'] : null;
            $finalScore = isset($resolution['final_score_after']) ? $resolution['final_score_after'] : null;

            $this->writeConnection->prepare(
                'UPDATE `ngn_2025`.`ngn_score_disputes`
                 SET status = ?, resolution = ?, action_taken = ?, final_score_after = ?, resolved_at = NOW()
                 WHERE id = ?'
            )->execute([
                $status,
                $resolutionText,
                $actionTaken,
                $finalScore,
                $disputeId
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error updating dispute', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate audit report
     */
    public function generateAuditReport(
        string $reportType,
        string $scopeType,
        string $periodStart,
        string $periodEnd,
        ?int $artistId = null,
        int $userId = 1
    ): int {
        try {
            // Get verification data
            $stmt = $this->readConnection->prepare(
                'SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN verification_status = "passed" THEN 1 END) as passed,
                    COUNT(CASE WHEN verification_status = "failed" THEN 1 END) as failed,
                    COUNT(CASE WHEN issues_found IS NOT NULL THEN 1 END) as discrepancies
                 FROM `ngn_2025`.`ngn_score_verification`
                 WHERE DATE(created_at) BETWEEN ? AND ?'
            );
            $params = [$periodStart, $periodEnd];

            if ($artistId) {
                $stmt = $this->readConnection->prepare(
                    'SELECT
                        COUNT(*) as total,
                        COUNT(CASE WHEN verification_status = "passed" THEN 1 END) as passed,
                        COUNT(CASE WHEN verification_status = "failed" THEN 1 END) as failed,
                        COUNT(CASE WHEN issues_found IS NOT NULL THEN 1 END) as discrepancies
                     FROM `ngn_2025`.`ngn_score_verification`
                     WHERE artist_id = ? AND DATE(created_at) BETWEEN ? AND ?'
                );
                $params = [$artistId, $periodStart, $periodEnd];
            }

            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $passRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 2) : 100;

            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`ngn_audit_reports` (
                    report_type, scope_type, artist_id, period_start, period_end,
                    total_artists_audited, scores_verified, discrepancies_found, pass_rate,
                    generated_by, generation_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $reportType,
                $scopeType,
                $artistId,
                $periodStart,
                $periodEnd,
                1,
                $stats['total'],
                $stats['discrepancies'],
                $passRate,
                $userId,
                'service_generated'
            ]);

            return $this->writeConnection->lastInsertId();
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error generating report', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get audit reports
     */
    public function getAuditReports(string $reportType = null, int $limit = 50): array
    {
        try {
            $sql = 'SELECT * FROM `ngn_2025`.`ngn_audit_reports`';
            $params = [];

            if ($reportType) {
                $sql .= ' WHERE report_type = ?';
                $params[] = $reportType;
            }

            $sql .= ' ORDER BY generated_at DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $this->readConnection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error getting reports', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate score integrity metrics
     */
    public function calculateIntegrityMetrics(int $artistId, string $periodStart, string $periodEnd): array
    {
        try {
            // Get historical scores
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as total, COUNT(DISTINCT DATE(period_start)) as unique_dates
                 FROM `ngn_2025`.`ngn_score_history`
                 WHERE artist_id = ? AND period_start BETWEEN ? AND ?'
            );
            $stmt->execute([$artistId, $periodStart, $periodEnd]);
            $scores = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get verifications
            $stmt = $this->readConnection->prepare(
                'SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN verification_status = "passed" THEN 1 END) as passed,
                    COUNT(CASE WHEN issues_found IS NOT NULL THEN 1 END) as issues
                 FROM `ngn_2025`.`ngn_score_verification`
                 WHERE artist_id = ? AND DATE(created_at) BETWEEN ? AND ?'
            );
            $stmt->execute([$artistId, $periodStart, $periodEnd]);
            $verifications = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get corrections
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as total, SUM(ABS(adjustment_amount)) as total_adjustments
                 FROM `ngn_2025`.`ngn_score_corrections`
                 WHERE artist_id = ? AND DATE(created_at) BETWEEN ? AND ?'
            );
            $stmt->execute([$artistId, $periodStart, $periodEnd]);
            $corrections = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get disputes
            $stmt = $this->readConnection->prepare(
                'SELECT COUNT(*) as total, COUNT(CASE WHEN status = "resolved" THEN 1 END) as resolved
                 FROM `ngn_2025`.`ngn_score_disputes`
                 WHERE artist_id = ? AND DATE(created_at) BETWEEN ? AND ?'
            );
            $stmt->execute([$artistId, $periodStart, $periodEnd]);
            $disputes = $stmt->fetch(PDO::FETCH_ASSOC);

            $verificationRate = $verifications['total'] > 0 ? round(($verifications['passed'] / $verifications['total']) * 100, 2) : 0;
            $correctionRate = $scores['total'] > 0 ? round(($corrections['total'] / $scores['total']) * 100, 2) : 0;

            return [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'scores_recorded' => $scores['total'],
                'verifications_run' => $verifications['total'],
                'verification_pass_rate' => $verificationRate,
                'scores_with_issues' => $verifications['issues'],
                'corrections_applied' => $corrections['total'],
                'correction_rate' => $correctionRate,
                'total_adjustment_value' => (float) ($corrections['total_adjustments'] ?? 0),
                'disputes_filed' => $disputes['total'],
                'disputes_resolved' => $disputes['resolved'],
                'integrity_score' => $this->calculateIntegrityScore($verificationRate, $correctionRate, (int) $disputes['total'])
            ];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('audit')->error('Error calculating metrics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate integrity score (0-100)
     */
    private function calculateIntegrityScore(float $verificationRate, float $correctionRate, int $disputes): float
    {
        $score = 100.0;

        // Deduct for low verification rate
        if ($verificationRate < 90) {
            $score -= (90 - $verificationRate) * 0.5;
        }

        // Deduct for high correction rate
        if ($correctionRate > 5) {
            $score -= min($correctionRate * 2, 30);
        }

        // Deduct for disputes
        $score -= $disputes * 5;

        return max(0, round($score, 2));
    }
}
