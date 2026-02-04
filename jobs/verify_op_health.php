<?php

// This script serves as the NGN 2.0 Operational Verification Script (NGN_V2_OP_VERIFY).
// It checks critical metrics post-launch to ensure system stability.
// Intended to be run as a cron job or on-demand script.

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// --- Configuration ---
$logFile = __DIR__ . '/../storage/logs/op_verify_health.log';
$chartSlugToVerify = 'ngn:artists:weekly';
$backupWorld = 'primary'; // Assuming 'primary' is the world for daily backups
$backupLogFilePattern = __DIR__ . '/../storage/logs/backup_verify_all.php_*.log'; // Pattern to find backup log files

// --- Thresholds ---
$coverageTarget = 0.98; // Minimum required station coverage (98%)
$linkageTarget = 0.95;  // Minimum required linkage rate (95%)

// --- Setup Logger ---
try {
    $logger = new Logger('op_verify_health');
    $logger->pushHandler(new StreamHandler($logFile, Logger::INFO));

    // Assume $pdo and $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Operational Verification Script setup error: " . $e->getMessage());
    exit("Operational Verification Script setup failed.");
}

$logger->info('Operational Verification Script started.');

// --- Main Verification Logic ---
$overallStatus = 'passed'; // Default status
$results = [
    'chart_completeness' => ['status' => 'pending', 'message' => 'Not checked'],
    'policy_adherence' => ['status' => 'pending', 'message' => 'Not checked'],
    'backup_verification' => ['status' => 'pending', 'message' => 'Not checked'],
];

try {
    // --- 1. Chart Completeness Check ---
    $chartStmt = $pdo->prepare(
        "SELECT status 
         FROM cdm_chart_runs 
         WHERE chart_slug = :chart_slug AND status = 'completed' 
         ORDER BY run_at DESC LIMIT 1"
    );
    $chartStmt->execute([':chart_slug' => $chartSlugToVerify]);
    $latestCompletedRun = $chartStmt->fetch(PDO::FETCH_ASSOC);

    if ($latestCompletedRun) {
        $results['chart_completeness'] = ['status' => 'passed', 'message' => "Chart '{$chartSlugToVerify}' completed successfully."];
        $logger->info("Chart completeness check passed for {$chartSlugToVerify}.");
    } else {
        $overallStatus = 'failed';
        $results['chart_completeness'] = ['status' => 'failed', 'message' => "Chart '{$chartSlugToVerify}' has not completed successfully."];
        $logger->error("Chart completeness check failed for {$chartSlugToVerify}.");
    }

    // --- 2. Policy Adherence Check ---
    // Check latest cdm_chart_runs record for populated checksums
    $latestRunStmt = $pdo->query(
        "SELECT weights_checksum, inputs_checksum, summary_json 
         FROM cdm_chart_runs 
         ORDER BY run_at DESC LIMIT 1"
    );
    $latestRun = $latestRunStmt ? $latestRunStmt->fetch(PDO::FETCH_ASSOC) : null;

    $policyAdherencePassed = false;
    $policyMessages = [];

    if ($latestRun) {
        $checksumsPopulated = !empty($latestRun['weights_checksum']) && !empty($latestRun['inputs_checksum']);
        // Also check if the overall summary indicates success based on QA Gatekeeper logic
        $qaSummary = json_decode($latestRun['summary_json'] ?? '{}', true);
        $qaPassed = $qaSummary['success'] ?? false;
        
        if ($checksumsPopulated && $qaPassed) {
            $policyAdherencePassed = true;
            $results['policy_adherence'] = ['status' => 'passed', 'message' => 'Policy adherence checks passed.'];
            $logger->info('Policy adherence checks passed.');
        } else {
            $overallStatus = 'failed'; // Fail if policy adherence fails
            $messages = [];
            if (!$checksumsPopulated) {
                $messages[] = 'Checksums (weights/inputs) are missing in the latest run.';
                $logger->error('Policy Adherence Failure: Checksums missing.');
            }
            if (!$qaPassed) {
                $messages[] = 'Fairness summary indicates failure in QA Gatekeeper.';
                $logger->error('Policy Adherence Failure: QA Gatekeeper failed.');
            }
            $results['policy_adherence'] = ['status' => 'failed', 'message' => implode(' ', $messages)];
        }
    } else {
        $overallStatus = 'failed'; // Fail if no chart runs recorded for policy check
        $results['policy_adherence'] = ['status' => 'failed', 'message' => 'No recent chart run data found for policy adherence check.'];
        $logger->warning('Policy adherence check failed: No recent chart run data.');
    }

    // --- 3. Backup Verification Check ---
    // Check if the last 7 daily backup verification jobs were successful.
    // We'll look for log files that indicate success.
    $backupLogDir = __DIR__ . '/../storage/logs/';
    $backupLogPattern = 'backup_verify_all.php_*.log'; // Pattern used by the backup job
    $backupLogFiles = glob($backupLogDir . $backupLogPattern);

    $recentBackups = [];
    $successfulBackupsCount = 0;
    $requiredRecentBackups = 7;

    if ($backupLogFiles) {
        // Sort files by modification time, newest first
        rsort($backupLogFiles);
        
        $recentBackups = array_slice($backupLogFiles, 0, $requiredRecentBackups);

        foreach ($recentBackups as $logFile) {
            $content = file_get_contents($logFile);
            // Check for a success indicator in the log file content. Adjust string if log format differs.
            // Assuming success is indicated by 'SUCCESS' or similar phrase.
            if ($content !== false && str_contains($content, 'OK')) {
                $successfulBackupsCount++;
            }
        }
    }

    if ($successfulBackupsCount >= $requiredRecentBackups) {
        $backupsPassed = true;
        $results['backup_verification'] = ['status' => 'passed', 'message' => "Backup verification OK: {$successfulBackupsCount}/{$requiredRecentBackups} daily backups verified."];
        $logger->info("Backup verification passed: {$successfulBackupsCount}/{$requiredRecentBackups} recent backups verified.");
    } else {
        $overallStatus = 'failed'; // Fail overall if backups are insufficient
        $results['backup_verification'] = ['status' => 'failed', 'message' => "Backup verification failed. Only {$successfulBackupsCount}/{$requiredRecentBackups} recent daily backups verified."];
        $logger->error("Backup verification failed. Found {$successfulBackupsCount} successful recent backups, need {$requiredRecentBackups}.");
    }

    // --- Final Output ---
    $output = [
        'script_run_at' => date('Y-m-d H:i:s'),
        'overall_status' => $overallStatus,
        'checks' => [
            'chart_completeness' => $results['chart_completeness'],
            'policy_adherence' => $results['policy_adherence'],
            'backup_verification' => $results['backup_verification'],
        ]
    ];

    // Print JSON output suitable for script execution output.
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);

    $logger->info("Operational Verification Script finished. Overall status: {$overallStatus}.");

    // Exit with a non-zero code if any critical check failed
    exit($overallStatus === 'failed' ? 1 : 0);

} catch (\Throwable $e) {
    $logger->critical("Operational Verification Script encountered a critical error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An internal error occurred during verification.',
        'error_details' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit(1); // Indicate failure
}

?>
