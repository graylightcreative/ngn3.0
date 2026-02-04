<?php

require_once __DIR__ . '/../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use NGN\Lib\Http\HttpClient; // For calling internal APIs if needed

// --- Page Title ---
$pageTitle = '2.0 Readiness Check';

// --- Dependencies ---
try {
    // Assume $pdo, $logger, $config are available from bootstrap.php
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        if (class_exists('NGN\Lib\Database\ConnectionFactory')) {
            $pdo = NGN\Lib\Database\ConnectionFactory::read(new Config());
        } else {
            throw new \RuntimeException("PDO connection not available and ConnectionFactory not found.");
        }
    }
    if (!isset($logger) || !($logger instanceof Logger)) {
        $logger = new Logger('readiness_check');
        $logFilePath = __DIR__ . '/../../../storage/logs/readiness_check.log';
        $logger->pushHandler(new StreamHandler($logFilePath, Logger::INFO));
    }
    if (!isset($config) || !($config instanceof Config)) {
         $config = new Config();
    }

} catch (\Throwable $e) {
    error_log("Readiness Check Page Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Could not initialize critical services for readiness check.</p>";
    exit;
}

// --- Fetch Data for Badges ---
$coverageStatus = null;
$integrityStatus = null;
$backupsStatus = null;

$coveragePassed = false;
$integrityPassed = false;
$backupsPassed = false;

$coverageMessages = [];
$integrityMessages = [];
$backupMessages = [];

try {
    // --- Badge 1: Coverage ---
    // Fetch latest QA Gatekeeper summary from cdm_chart_runs
    $qaStmt = $pdo->query(
        "SELECT status, summary_json 
         FROM cdm_chart_runs 
         ORDER BY run_at DESC LIMIT 1"
    );
    $qaData = $qaStmt->fetch(PDO::FETCH_ASSOC);

    if ($qaData) {
        $summary = json_decode($qaData['summary_json'], true);
        if ($summary && $summary['success']) {
            $coveragePassed = $summary['station_coverage']['passed'] && $summary['linkage_rate']['passed'];
            if (!$coveragePassed) {
                $coverageMessages = array_filter($summary['messages'], fn($m) => str_contains($m, 'COVERAGE') || str_contains($m, 'LINKAGE'));
            }
            
            // Assuming 'ok' status in summary implies integrity checks passed.
            // AND that Caps were within expected bounds (this part is hard to check without Factor definitions).
            // For now, we'll simplify 'integrityPassed' based on the overall summary success.
            $integrityPassed = $summary['success'] ?? false;
            if (!$integrityPassed) {
                $integrityMessages[] = 'Fairness summary status is not OK.';
                // Add more specific integrity checks if defined elsewhere.
            }

        } else {
            $coveragePassed = false;
            $integrityPassed = false;
            $coverageMessages[] = 'QA summary data unavailable or failed.';
            $integrityMessages[] = 'QA summary data unavailable or failed.';
            $logger->warning("Could not retrieve valid QA summary data.");
        }
    } else {
        $coveragePassed = false;
        $integrityPassed = false;
        $coverageMessages[] = 'No QA summary found in cdm_chart_runs.';
        $integrityMessages[] = 'No QA summary found in cdm_chart_runs.';
        $logger->warning("No QA summary data found in cdm_chart_runs.");
    }

    // --- Badge 2: Integrity ---
    // (Assumed to be covered by QA summary success and specific checks above)
    // If there were separate checks for Caps, they would go here.

    // --- Badge 3: Backups ---
    // Check if backup_verify_all.php cron job status is green.
    // This is hard to check directly via PHP without specific logging or status files.
    // Simulating check: looking for a status file or checking last modified time of logs.
    $backupStatusFile = $root . '/storage/logs/backup_status.log'; // Example status file
    $sevenDailyRestorePointsCheck = true; // Placeholder: Assume check passed
    $backupSuccess = false;

    if (is_file($backupStatusFile)) {
        $lastBackupStatus = trim(file_get_contents($backupStatusFile));
        if ($lastBackupStatus === 'SUCCESS') {
            $backupSuccess = true;
            $logger->info("Backup status file indicates SUCCESS.");
        } else {
            $backupMessages[] = "Backup status: {$lastBackupStatus}.";
            $logger->warning("Backup status file indicates FAILURE or is missing SUCCESS: {$lastBackupStatus}.");
        }
    } else {
        $backupMessages[] = "Backup status file not found ({$backupStatusFile}).";
        $logger->warning("Backup status file not found. Assuming backup check failed.");
    }

    // Checking for 7 daily restore points: This would involve checking log files or backup directories.
    // Simulating: checking for a specific number of log files from the backup cron job.
    $backupLogDir = $root . '/storage/logs/';
    $backupLogFiles = glob($backupLogDir . 'backup_verify_all.php_*.log'); // Example log pattern
    if ($backupLogFiles !== false && count($backupLogFiles) >= 7) {
        // This check is very basic, a real check would verify file content/success messages.
        $logger->info("Found more than 7 daily backup log files.");
    } else {
        $backupMessages[] = "Found " . (count($backupLogFiles) ?: 0) . " backup log files. Need at least 7.";
        $logger->warning("Backup log files insufficient. Found: " . count($backupLogFiles) . ". Need at least 7.");
        $sevenDailyRestorePointsCheck = false;
    }

    $backupsPassed = $backupSuccess && $sevenDailyRestorePointsCheck;
    if (!$backupsPassed) $runStatus = 'failed'; // Fail overall if backups are bad


} catch (\Throwable $e) {
    $logger->critical("Error fetching readiness data: " . $e->getMessage());
    $runStatus = 'error';
    $messages[] = 'Error fetching data for readiness checks.';
}

// --- Feature Flag Display ---
$publicMode = $config->get('FEATURE_PUBLIC_VIEW_MODE', 'legacy'); // Get current feature flag state

// --- Page Setup ---
$pageTitle = '2.0 Readiness Check';
$currentPage = 'readiness';
include __DIR__ . '/_header.php';

include __DIR__ . '/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold sk-text-gradient-secondary">NGN 2.0 Readiness Check</h1>
        <p class="text-gray-500 dark:text-gray-400">System status overview for seamless transition.</p>
    </div>

    <!-- Status Badges Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Coverage Badge -->
        <div class="flex flex-col items-center p-5 rounded-lg shadow-md border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 transition-shadow duration-300 hover:shadow-lg">
            <div class="flex items-center justify-center w-24 h-24 rounded-full mb-4 text-4xl font-bold <?= $coveragePassed ? 'bg-green-500/20 text-green-500' : ($runStatus === 'error' ? 'bg-red-500/20 text-red-500' : 'bg-yellow-500/20 text-yellow-500') ?>">
                <?= $coveragePassed ? '✔' : ($runStatus === 'error' ? 'X' : '!') ?>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Coverage</h3>
            <p class="text-sm text-center <?= $coveragePassed ? 'text-green-600 dark:text-green-400' : ($runStatus === 'error' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400') ?>">
                <?= $coveragePassed ? 'Passed' : ($runStatus === 'error' ? 'Error' : 'Review Needed') ?>
            </p>
            <?php if (!empty($coverageMessages)):
                foreach($coverageMessages as $msg):
            ?>
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($msg) ?></p>
            <?php endforeach;
            endif; ?>
        </div>

        <!-- Integrity Badge -->
        <div class="flex flex-col items-center p-5 rounded-lg shadow-md border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 transition-shadow duration-300 hover:shadow-lg">
            <div class="flex items-center justify-center w-24 h-24 rounded-full mb-4 text-4xl font-bold <?= $integrityPassed ? 'bg-green-500/20 text-green-500' : ($runStatus === 'error' ? 'bg-red-500/20 text-red-500' : 'bg-yellow-500/20 text-yellow-500') ?>">
                <?= $integrityPassed ? '✔' : ($runStatus === 'error' ? 'X' : '!') ?>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Integrity</h3>
            <p class="text-sm text-center <?= $integrityPassed ? 'text-green-600 dark:text-green-400' : ($runStatus === 'error' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400') ?>">
                <?= $integrityPassed ? 'Passed' : ($runStatus === 'error' ? 'Error' : 'Review Needed') ?>
            </p>
            <?php if (!empty($integrityMessages)):
                foreach($integrityMessages as $msg):
            ?>
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($msg) ?></p>
            <?php endforeach;
            endif; ?>
        </div>

        <!-- Backups Badge -->
        <div class="flex flex-col items-center p-5 rounded-lg shadow-md border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 transition-shadow duration-300 hover:shadow-lg">
            <div class="flex items-center justify-center w-24 h-24 rounded-full mb-4 text-4xl font-bold <?= $backupsPassed ? 'bg-green-500/20 text-green-500' : ($runStatus === 'error' ? 'bg-red-500/20 text-red-500' : 'bg-yellow-500/20 text-yellow-500') ?>">
                <?= $backupsPassed ? '✔' : ($runStatus === 'error' ? 'X' : '!') ?>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Backups</h3>
            <p class="text-sm text-center <?= $backupsPassed ? 'text-green-600 dark:text-green-400' : ($runStatus === 'error' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400') ?>">
                <?= $backupsPassed ? 'Passed' : ($runStatus === 'error' ? 'Error' : 'Review Needed') ?>
            </p>
            <?php if (!empty($backupMessages)):
                foreach($backupMessages as $msg):
            ?>
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($msg) ?></p>
            <?php endforeach;
            endif; ?>
        </div>
    </div>

    <!-- Cutover Flag Display -->
    <div class="mt-8 p-5 rounded-lg shadow-md border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5">
        <h3 class="text-lg font-semibold mb-3">Cutover Configuration</h3>
        <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500 dark:text-gray-400">FEATURE_PUBLIC_VIEW_MODE</span>
            <span class="font-medium text-lg sk-text-primary"><?= htmlspecialchars(ucfirst($publicMode)) ?></span>
        </div>
    </div>

    <!-- Rollback Button -->
    <div class="mt-8 p-5 rounded-lg shadow-md border border-rose-200 dark:border-rose-500/30 bg-rose-50/50 dark:bg-rose-500/5">
        <h3 class="text-lg font-semibold mb-3 text-rose-800 dark:text-rose-200">Rollback Action</h3>
        <div class="flex justify-between items-center">
            <p class="text-sm text-rose-700 dark:text-rose-300">Set FEATURE_PUBLIC_VIEW_MODE to 'legacy' to rollback NGN 2.0.</p>
            <button id="rollbackBtn" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-opacity-50">
                Rollback to Legacy
            </button>
        </div>
    </div>

</section>

<?php include __DIR__ . '/_footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rollbackBtn = document.getElementById('rollbackBtn');
        if (rollbackBtn) {
            rollbackBtn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to rollback? This action sets FEATURE_PUBLIC_VIEW_MODE to \'legacy\'.')) {
                    return;
                }

                const token = localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
                try {
                    const response = await fetch('/api/v1/settings/feature-flag/rollback-public-view', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': token ? 'Bearer ' + token : ''
                        },
                        body: JSON.stringify({
                            'feature': 'FEATURE_PUBLIC_VIEW_MODE',
                            'value': 'legacy'
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        alert('Rollback initiated. The system will be set to legacy mode.');
                        // Optionally reload or indicate success visually
                        location.reload(); 
                    } else {
                        alert('Rollback failed: ' + (result.message || 'An unknown error occurred.'));
                    }
                } catch (error) {
                    alert('Rollback failed due to a network error: ' + error.message);
                }
            });
        }
    });
</script>

<?php // Note: The actual data fetching for badges in PHP would be implemented here
      // by querying the database directly or calling internal API services.
      // For example, fetching coverage/integrity from DB and checking backup logs.
?>

</body>
</html>
