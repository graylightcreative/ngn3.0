<?php

/**
 * Cron Job: Generate Quarterly Governance Report
 *
 * Generates comprehensive governance audit report for board review.
 * Schedule: 0 6 1 1,4,7,10 * (First day of each quarter at 6:00 AM UTC)
 *
 * Bible Reference: Chapter 31 - Quarterly governance audit
 * Generates: Performance metrics, SIR completion rates, director activity summary
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Governance\SirRegistryService;
use NGN\Lib\Governance\DirectorateRoles;

try {
    // Initialize services
    $config = new Config();
    $pdo = ConnectionFactory::read($config);

    if (!$pdo) {
        error_log("[Governance Report] Database connection failed");
        exit(1);
    }

    $roles = new DirectorateRoles();
    $sirService = new SirRegistryService($pdo, $roles);

    error_log("=== Quarterly Governance Report Generator Started ===");

    // Get date info
    $now = new DateTime();
    $quarter = ceil($now->format('n') / 3);
    $year = $now->format('Y');
    $reportDate = $now->format('Y-m-d H:i:s');

    error_log("  Generating Q{$quarter} {$year} Report");

    // Get overall stats
    $overallStats = $sirService->getDashboardStats();

    // Calculate metrics
    $totalSirs = (int)($overallStats['overview']['total'] ?? 0);
    $closedSirs = (int)($overallStats['overview']['closed'] ?? 0);
    $completionRate = $totalSirs > 0 ? round(($closedSirs / $totalSirs) * 100, 2) : 0;

    $openSirs = (int)($overallStats['overview']['open'] ?? 0);
    $inReviewSirs = (int)($overallStats['overview']['in_review'] ?? 0);
    $rantPhaseSirs = (int)($overallStats['overview']['rant_phase'] ?? 0);
    $verifiedSirs = (int)($overallStats['overview']['verified'] ?? 0);
    $overdueSirs = (int)($overallStats['overview']['overdue'] ?? 0);

    // Build report summary
    $reportData = [
        'report_date' => $reportDate,
        'quarter' => "Q{$quarter} {$year}",
        'metrics' => [
            'total_sirs_issued' => $totalSirs,
            'total_sirs_completed' => $closedSirs,
            'completion_rate_percent' => $completionRate,
            'current_status_breakdown' => [
                'open' => $openSirs,
                'in_review' => $inReviewSirs,
                'rant_phase' => $rantPhaseSirs,
                'verified' => $verifiedSirs,
                'closed' => $closedSirs,
            ],
            'overdue_sirs' => $overdueSirs,
        ],
        'director_performance' => [],
    ];

    // Get director-specific stats
    foreach ($roles->getDirectorSlugs() as $directorSlug) {
        $directorStats = $sirService->getDashboardStats($directorSlug);
        $directorName = $roles->getDirectorName($directorSlug);
        $focus = $roles->getDirectorFocus($directorSlug);

        $directorTotal = (int)($directorStats['overview']['total'] ?? 0);
        $directorClosed = (int)($directorStats['overview']['closed'] ?? 0);
        $directorCompletion = $directorTotal > 0 ? round(($directorClosed / $directorTotal) * 100, 2) : 0;

        $reportData['director_performance'][$directorSlug] = [
            'name' => $directorName,
            'focus_area' => $focus,
            'total_assigned' => $directorTotal,
            'completed' => $directorClosed,
            'pending' => $directorTotal - $directorClosed,
            'completion_rate_percent' => $directorCompletion,
            'status_breakdown' => [
                'open' => (int)($directorStats['overview']['open'] ?? 0),
                'in_review' => (int)($directorStats['overview']['in_review'] ?? 0),
                'verified' => (int)($directorStats['overview']['verified'] ?? 0),
            ],
            'avg_days_to_verify' => $directorStats['by_director'][$directorSlug]['avg_days_to_verify'] ?? null,
        ];
    }

    // Store report in database (if we have a reports table)
    try {
        $reportJson = json_encode($reportData);

        $stmt = $pdo->prepare(
            "INSERT INTO ngn_2025.governance_reports (
                report_date, report_type, quarter, year, report_data, created_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                report_data = VALUES(report_data),
                created_at = CURRENT_TIMESTAMP"
        );

        // Check if table exists first
        $checkStmt = $pdo->prepare(
            "SELECT COUNT(*) as count FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'governance_reports'"
        );
        $checkStmt->execute();
        $tableExists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        if ($tableExists) {
            $stmt->execute([
                $reportDate,
                'quarterly_audit',
                "Q{$quarter}",
                $year,
                $reportJson,
            ]);

            error_log("  ✓ Report stored in database");
        } else {
            error_log("  ⊘ governance_reports table not found, skipping database storage");
        }
    } catch (Exception $e) {
        error_log("  ⚠ Could not store report in database: " . $e->getMessage());
    }

    // Log report summary
    error_log("=== Quarterly Report Summary ===");
    error_log("  Period: Q{$quarter} {$year}");
    error_log("  Total SIRs Issued: {$totalSirs}");
    error_log("  Completion Rate: {$completionRate}%");
    error_log("  Status Breakdown:");
    error_log("    - Open: {$openSirs}");
    error_log("    - In Review: {$inReviewSirs}");
    error_log("    - Rant Phase: {$rantPhaseSirs}");
    error_log("    - Verified: {$verifiedSirs}");
    error_log("    - Closed: {$closedSirs}");
    error_log("    - Overdue: {$overdueSirs}");

    error_log("\n=== Director Performance ===");
    foreach ($reportData['director_performance'] as $directorSlug => $stats) {
        error_log("  {$stats['name']}:");
        error_log("    - Total Assigned: {$stats['total_assigned']}");
        error_log("    - Completed: {$stats['completed']}");
        error_log("    - Completion Rate: {$stats['completion_rate_percent']}%");
        if ($stats['avg_days_to_verify'] !== null) {
            error_log("    - Avg Days to Verify: " . round($stats['avg_days_to_verify'], 1) . " days");
        }
    }

    error_log("=== Quarterly Governance Report Complete ===");

    // TODO: Email report to board
    // TODO: Publish to governance dashboard
    // TODO: Archive report PDF

    exit(0);

} catch (Exception $e) {
    error_log("[Governance Report] Fatal error: " . $e->getMessage());
    error_log("[Governance Report] Trace: " . $e->getTraceAsString());
    exit(1);
}
