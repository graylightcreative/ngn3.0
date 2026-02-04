<?php

/**
 * NGN Score Audit Cron Job
 * Batch verifies NGN scores, generates audit reports, and detects anomalies
 * Schedule: Daily at 2 AM
 * Path: /jobs/audit/audit_ngn_scores.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\Rankings\NGNScoreAuditService;
use NGN\Lib\Rankings\ScoreVerificationService;
use NGN\Lib\Logger\LoggerFactory;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

$logger = LoggerFactory::getLogger('audit');
$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$writeConnection = ConnectionFactory::write();

$startTime = time();
$logger->info('Starting NGN Score audit batch job');

try {
    $auditService = new NGNScoreAuditService($config);
    $verificationService = new ScoreVerificationService($config, $auditService);

    // ========== PHASE 1: Batch Verify Scores ==========
    $logger->info('Phase 1: Starting batch score verification');

    // Get unverified scores from previous 24 hours
    $stmt = $readConnection->prepare('
        SELECT h.id, h.artist_id, h.score_value, h.period_start, h.period_end, h.formula_used
        FROM ngn_score_history h
        LEFT JOIN ngn_score_verification v ON h.id = v.history_id AND v.verification_type = "recalculation"
        WHERE h.calculated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND v.id IS NULL
        ORDER BY h.calculated_at DESC
        LIMIT 100
    ');
    $stmt->execute();
    $scoreIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $verificationResults = [
        'total_verified' => 0,
        'passed' => 0,
        'failed' => 0,
        'failures' => []
    ];

    foreach ($scoreIds as $historyId) {
        try {
            $verification = $verificationService->verifyScore($historyId);
            $verificationResults['total_verified']++;

            if ($verification['status'] === 'passed') {
                $verificationResults['passed']++;
            } else {
                $verificationResults['failed']++;
                $verificationResults['failures'][] = [
                    'history_id' => $historyId,
                    'artist_id' => $verification['artist_id'] ?? null,
                    'percent_difference' => $verification['percent_difference'] ?? 0
                ];
            }
        } catch (Exception $e) {
            $logger->error('Error verifying score ' . $historyId, ['error' => $e->getMessage()]);
        }
    }

    $passRate = $verificationResults['total_verified'] > 0
        ? ($verificationResults['passed'] / $verificationResults['total_verified']) * 100
        : 0;

    $logger->info('Phase 1 complete', [
        'total_verified' => $verificationResults['total_verified'],
        'passed' => $verificationResults['passed'],
        'failed' => $verificationResults['failed'],
        'pass_rate' => number_format($passRate, 2) . '%'
    ]);

    // Alert if pass rate is low
    if ($passRate < 80 && $verificationResults['total_verified'] > 0) {
        $logger->warning('ALERT: Low verification pass rate', [
            'pass_rate' => number_format($passRate, 2) . '%',
            'failed_scores' => $verificationResults['failed']
        ]);
    }

    // ========== PHASE 2: Check Data Lineage Anomalies ==========
    $logger->info('Phase 2: Starting data lineage verification');

    $stmt = $readConnection->prepare('
        SELECT DISTINCT history_id FROM ngn_score_lineage
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ');
    $stmt->execute();
    $lineageHistoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $lineageIssues = [
        'total_checked' => 0,
        'issues_found' => 0,
        'modified_records' => 0,
        'deleted_records' => 0
    ];

    foreach (array_chunk($lineageHistoryIds, 50) as $chunk) {
        foreach ($chunk as $historyId) {
            try {
                $lineageCheck = $auditService->verifyLineageIntegrity($historyId);
                $lineageIssues['total_checked']++;

                if (!empty($lineageCheck['issues'])) {
                    $lineageIssues['issues_found']++;

                    foreach ($lineageCheck['issues'] as $issue) {
                        if ($issue['status'] === 'modified') {
                            $lineageIssues['modified_records']++;
                        } elseif ($issue['status'] === 'deleted') {
                            $lineageIssues['deleted_records']++;
                        }
                    }
                }
            } catch (Exception $e) {
                $logger->error('Error checking lineage for history ' . $historyId, ['error' => $e->getMessage()]);
            }
        }
    }

    $logger->info('Phase 2 complete', [
        'total_checked' => $lineageIssues['total_checked'],
        'issues_found' => $lineageIssues['issues_found'],
        'modified_records' => $lineageIssues['modified_records'],
        'deleted_records' => $lineageIssues['deleted_records']
    ]);

    // Alert if suspicious modifications found
    if ($lineageIssues['modified_records'] > 0 || $lineageIssues['deleted_records'] > 0) {
        $logger->warning('ALERT: Data lineage anomalies detected', [
            'modified' => $lineageIssues['modified_records'],
            'deleted' => $lineageIssues['deleted_records']
        ]);
    }

    // ========== PHASE 3: Generate Periodic Audit Report ==========
    $logger->info('Phase 3: Generating periodic audit report');

    // Check if we should generate a weekly report (Sunday)
    if (date('N') == 7) { // Sunday
        $periodStart = date('Y-m-d', strtotime('-7 days'));
        $periodEnd = date('Y-m-d');

        try {
            // Get artists audited in this period
            $stmt = $readConnection->prepare('
                SELECT COUNT(DISTINCT artist_id) as artist_count
                FROM ngn_score_verification
                WHERE completed_at BETWEEN ? AND ?
            ');
            $stmt->execute([$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
            $artistCount = $stmt->fetch(PDO::FETCH_ASSOC)['artist_count'];

            // Get scores verified
            $stmt = $readConnection->prepare('
                SELECT
                    COUNT(*) as total_verified,
                    SUM(CASE WHEN verification_status = "passed" THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN verification_status = "failed" THEN 1 ELSE 0 END) as failed
                FROM ngn_score_verification
                WHERE completed_at BETWEEN ? AND ?
            ');
            $stmt->execute([$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
            $verifyStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get disputes filed
            $stmt = $readConnection->prepare('
                SELECT COUNT(*) as total_disputes
                FROM ngn_score_disputes
                WHERE created_at BETWEEN ? AND ?
            ');
            $stmt->execute([$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
            $disputeCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_disputes'];

            // Calculate overall metrics
            $weeklyPassRate = $verifyStats['total_verified'] > 0
                ? ($verifyStats['passed'] / $verifyStats['total_verified']) * 100
                : 100;

            $findings = [];
            if ($weeklyPassRate < 95) {
                $findings[] = 'Low verification pass rate (' . number_format($weeklyPassRate, 1) . '%). Investigate discrepancies.';
            }
            if ($lineageIssues['modified_records'] > 5) {
                $findings[] = 'Unusual number of modified source records detected (' . $lineageIssues['modified_records'] . '). Review data integrity.';
            }
            if ($disputeCount > 10) {
                $findings[] = 'High number of artist disputes filed this week (' . $disputeCount . '). Consider policy review.';
            }
            if (empty($findings)) {
                $findings[] = 'All audit metrics within normal ranges. System performing well.';
            }

            // Create report record
            $stmt = $writeConnection->prepare('
                INSERT INTO ngn_audit_reports (
                    report_type, scope_type, period_start, period_end,
                    total_artists_audited, scores_verified, discrepancies_found,
                    pass_rate, summary_findings, generated_by, generation_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                'periodic',
                'full_database',
                $periodStart,
                $periodEnd,
                $artistCount,
                $verifyStats['total_verified'],
                $verifyStats['failed'],
                $weeklyPassRate,
                json_encode($findings),
                1, // System user ID
                'audit_ngn_scores_cron'
            ]);

            $logger->info('Weekly audit report generated', [
                'artists_audited' => $artistCount,
                'scores_verified' => $verifyStats['total_verified'],
                'pass_rate' => number_format($weeklyPassRate, 2) . '%',
                'findings' => count($findings)
            ]);
        } catch (Exception $e) {
            $logger->error('Error generating audit report', ['error' => $e->getMessage()]);
        }
    }

    // ========== PHASE 4: Cleanup Old Verification Records ==========
    $logger->info('Phase 4: Starting data cleanup');

    try {
        // Archive verification records older than 90 days
        $stmt = $writeConnection->prepare('
            DELETE FROM ngn_score_verification
            WHERE completed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ');
        $stmt->execute();
        $deletedVerifications = $stmt->rowCount();

        // Archive lineage records older than 365 days (keep for 1 year for compliance)
        $stmt = $writeConnection->prepare('
            DELETE FROM ngn_score_lineage
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
        ');
        $stmt->execute();
        $deletedLineage = $stmt->rowCount();

        $logger->info('Phase 4 complete', [
            'deleted_verifications' => $deletedVerifications,
            'deleted_lineage' => $deletedLineage
        ]);
    } catch (Exception $e) {
        $logger->error('Error during cleanup', ['error' => $e->getMessage()]);
    }

    // ========== FINAL: Summary and Status ==========
    $duration = time() - $startTime;
    $logger->info('NGN Score audit batch job completed successfully', [
        'duration_seconds' => $duration,
        'phase_1_pass_rate' => number_format($passRate, 2) . '%',
        'phase_2_lineage_issues' => $lineageIssues['issues_found'],
        'phase_3_report_generated' => (date('N') == 7 ? 'yes' : 'no'),
        'total_runtime' => number_format($duration, 2) . 's'
    ]);

    // Alert if execution took too long
    if ($duration > 300) { // 5 minutes
        $logger->warning('ALERT: Audit job took longer than expected', [
            'duration' => $duration . 's',
            'threshold' => '300s'
        ]);
    }

} catch (Exception $e) {
    $logger->error('Fatal error in NGN Score audit job', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

exit(0);
