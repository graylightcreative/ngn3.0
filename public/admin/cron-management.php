<?php
/**
 * Cron Management Dashboard
 *
 * Bible Ch. 5.1 Implementation
 * Displays the Cron Registry with execution status and health checks
 *
 * Features:
 * - View all registered cron jobs with schedules
 * - See last execution status for each job
 * - Monitor execution time and performance
 * - Alert on failures or missed executions
 * - Manual job trigger (for testing)
 * - View execution history
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'cron-management';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all cron jobs with last execution
    $stmt = $pdo->query("
        SELECT
            r.id,
            r.schedule,
            r.job_name,
            r.script_path,
            r.description,
            r.category,
            r.enabled,
            r.timeout_seconds,
            l.id as last_exec_id,
            l.status as last_status,
            l.started_at as last_started,
            l.completed_at as last_completed,
            l.duration_seconds as last_duration,
            l.exit_code,
            l.error_message,
            (SELECT COUNT(*) FROM cron_execution_log WHERE cron_id = r.id) as total_executions,
            (SELECT COUNT(*) FROM cron_execution_log WHERE cron_id = r.id AND status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_failures
        FROM cron_registry r
        LEFT JOIN cron_execution_log l ON r.id = l.cron_id AND l.id = (
            SELECT MAX(id) FROM cron_execution_log WHERE cron_id = r.id
        )
        ORDER BY r.schedule
    ");
    $cronJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate execution health
    $healthStats = [];
    foreach ($cronJobs as $job) {
        $health = 'unknown';
        if ($job['last_status'] === 'success') {
            $health = 'healthy';
        } elseif ($job['last_status'] === 'failed') {
            $health = 'failed';
        } elseif ($job['last_status'] === 'timeout') {
            $health = 'timeout';
        }
        $healthStats[$job['id']] = $health;
    }

    // Get recent alerts
    $stmt = $pdo->query("
        SELECT a.*, r.job_name FROM cron_alerts a
        JOIN cron_registry r ON a.cron_id = r.id
        WHERE a.is_active = TRUE
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Cron management error: ' . $e->getMessage());
    $cronJobs = [];
    $activeAlerts = [];
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Cron Registry</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Bible Ch. 5.1 | The NGN 2.0 Heartbeat</p>
        </div>

        <!-- Active Alerts -->
        <?php if (!empty($activeAlerts)): ?>
        <div class="mb-6 space-y-3">
            <?php foreach ($activeAlerts as $alert): ?>
            <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4 rounded">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-semibold text-yellow-900 dark:text-yellow-100">⚠ <?= htmlspecialchars($alert['job_name']) ?></p>
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <?= ucfirst(str_replace('_', ' ', $alert['alert_type'])) ?> - <?= htmlspecialchars($alert['notes'] ?? '') ?>
                        </p>
                    </div>
                    <button type="button" class="text-yellow-600 hover:text-yellow-800 text-sm" onclick="dismissAlert(<?= $alert['id'] ?>)">Dismiss</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase">Total Jobs</p>
                <p class="text-4xl font-bold text-gray-800 dark:text-gray-100 mt-2"><?= count($cronJobs) ?></p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase">Healthy</p>
                <p class="text-4xl font-bold text-green-600 mt-2">
                    <?= count(array_filter($healthStats, fn($s) => $s === 'healthy')) ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase">Failed</p>
                <p class="text-4xl font-bold text-red-600 mt-2">
                    <?= count(array_filter($healthStats, fn($s) => $s === 'failed')) ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase">Active Alerts</p>
                <p class="text-4xl font-bold text-yellow-600 mt-2"><?= count($activeAlerts) ?></p>
            </div>
        </div>

        <!-- Cron Jobs by Category -->
        <?php
        $categories = [
            'linkage' => 'Data Linking',
            'sync' => 'Synchronization',
            'ingestion' => 'Ingestion',
            'backup' => 'Backup & Recovery',
            'reporting' => 'Reporting',
            'qa' => 'Quality Assurance'
        ];

        foreach ($categories as $catKey => $catName):
            $jobsInCategory = array_filter($cronJobs, fn($j) => $j['category'] === $catKey);
            if (empty($jobsInCategory)) continue;
        ?>

        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">
                <?= $catName ?> (<?= count($jobsInCategory) ?> job<?= count($jobsInCategory) !== 1 ? 's' : '' ?>)
            </h4>

            <div class="space-y-4">
                <?php foreach ($jobsInCategory as $job): ?>
                <div class="border-l-4 border-<?= match($healthStats[$job['id']] ?? 'unknown') {
                    'healthy' => 'green-400',
                    'failed' => 'red-400',
                    'timeout' => 'orange-400',
                    default => 'gray-400'
                } ?> bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="grid grid-cols-12 gap-4">
                        <!-- Job Info -->
                        <div class="col-span-4">
                            <p class="font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($job['job_name']) ?></p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 font-mono mt-1"><?= htmlspecialchars($job['script_path']) ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($job['description'] ?? '') ?></p>
                        </div>

                        <!-- Schedule -->
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500 uppercase">Schedule</p>
                            <p class="font-mono text-sm text-gray-800 dark:text-gray-100 mt-1"><?= htmlspecialchars($job['schedule']) ?></p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                <?= $job['enabled'] ? '✓ Enabled' : '⊘ Disabled' ?>
                            </p>
                        </div>

                        <!-- Last Execution -->
                        <div class="col-span-3">
                            <p class="text-xs text-gray-500 uppercase">Last Execution</p>
                            <?php if ($job['last_started']): ?>
                            <div class="mt-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-<?= match($job['last_status']) {
                                        'success' => 'green-500',
                                        'failed' => 'red-500',
                                        'timeout' => 'orange-500',
                                        'running' => 'blue-500',
                                        default => 'gray-500'
                                    } ?>"></span>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        <?= ucfirst($job['last_status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    <?= date('M j H:i:s', strtotime($job['last_started'])) ?>
                                </p>
                                <?php if ($job['last_duration']): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    Duration: <?= round($job['last_duration'], 2) ?>s
                                    <?php if ($job['timeout_seconds'] && $job['last_duration'] > $job['timeout_seconds']): ?>
                                    <span class="text-red-600">(TIMEOUT)</span>
                                    <?php endif; ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($job['exit_code'] !== 0 && $job['exit_code'] !== null): ?>
                                <p class="text-xs text-red-600 mt-1">Exit Code: <?= $job['exit_code'] ?></p>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Never executed</p>
                            <?php endif; ?>
                        </div>

                        <!-- Stats & Actions -->
                        <div class="col-span-3 text-right">
                            <p class="text-xs text-gray-500 uppercase">Stats</p>
                            <div class="mt-1 space-y-1">
                                <p class="text-sm text-gray-800 dark:text-gray-100">
                                    <strong><?= $job['total_executions'] ?></strong> total runs
                                </p>
                                <?php if ($job['recent_failures'] > 0): ?>
                                <p class="text-sm text-red-600">
                                    <strong><?= $job['recent_failures'] ?></strong> failures (7d)
                                </p>
                                <?php endif; ?>
                            </div>

                            <div class="flex gap-2 mt-3 justify-end">
                                <button type="button" class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600"
                                        onclick="viewHistory(<?= $job['id'] ?>)">History</button>
                                <?php if ($job['enabled']): ?>
                                <button type="button" class="px-2 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600"
                                        onclick="runNow(<?= $job['id'] ?>)">Run Now</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Error Details -->
                    <?php if ($job['error_message']): ?>
                    <div class="mt-3 p-2 bg-red-50 dark:bg-red-900 rounded text-xs text-red-700 dark:text-red-200">
                        <strong>Last Error:</strong> <?= htmlspecialchars(substr($job['error_message'], 0, 200)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Bible Reference -->
        <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-6 rounded mb-6">
            <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">The NGN 2.0 Heartbeat</h4>
            <p class="text-blue-800 dark:text-blue-200 text-sm mb-3">
                These 7 cron jobs form the backbone of NGN 2.0. They run automatically on the server schedule.
                If any job fails or misses its schedule, data integrity is at risk.
            </p>
            <p class="text-blue-800 dark:text-blue-200 text-sm">
                Critical Priority: Database Backup & QA Gatekeeper. If these fail, contact ops immediately.
            </p>
        </div>
    </div>
</main>

<script>
function viewHistory(cronId) {
    // TODO: Show execution history modal for this cron job
    alert('Show history for cron job: ' + cronId);
}

function runNow(cronId) {
    if (confirm('Manually trigger this cron job now?')) {
        fetch('/admin/cron-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=run_now&cron_id=' + cronId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Job triggered! Check execution log.');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to run job'));
            }
        });
    }
}

function dismissAlert(alertId) {
    if (confirm('Dismiss this alert?')) {
        fetch('/admin/cron-management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=dismiss_alert&alert_id=' + alertId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
