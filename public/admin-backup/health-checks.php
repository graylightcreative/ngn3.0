<?php
/**
 * Health Check Results Dashboard
 *
 * Bible Ch. 12 - System Integrity
 * Shows weekly test results and compliance status
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'health-checks';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get latest health check run
    $stmt = $pdo->query("
        SELECT * FROM health_check_runs
        ORDER BY completed_at DESC
        LIMIT 1
    ");
    $latestRun = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get latest compliance report
    $stmt = $pdo->query("
        SELECT * FROM compliance_reports
        ORDER BY generated_at DESC
        LIMIT 1
    ");
    $latestReport = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get test results for latest run
    $stmt = $pdo->prepare("
        SELECT s.name, s.category, r.status, r.http_status, r.response_time_ms, r.error_message
        FROM health_check_results r
        JOIN health_check_scenarios s ON r.scenario_id = s.id
        WHERE r.run_id = ?
        ORDER BY s.category, s.name
    ");
    if ($latestRun) {
        $stmt->execute([$latestRun['run_id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $results = [];
    }

    // Get active alerts
    $stmt = $pdo->query("
        SELECT * FROM service_status_alerts
        WHERE is_resolved = FALSE
        ORDER BY alert_level DESC, created_at DESC
        LIMIT 10
    ");
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get test history (last 4 weeks)
    $stmt = $pdo->query("
        SELECT * FROM compliance_reports
        ORDER BY report_period_end DESC
        LIMIT 4
    ");
    $reportHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group results by category
    $resultsByCategory = [];
    foreach ($results as $result) {
        $cat = $result['category'];
        if (!isset($resultsByCategory[$cat])) {
            $resultsByCategory[$cat] = ['pass' => 0, 'fail' => 0, 'timeout' => 0, 'skipped' => 0, 'tests' => []];
        }
        $resultsByCategory[$cat][$result['status']]++;
        $resultsByCategory[$cat]['tests'][] = $result;
    }

} catch (PDOException $e) {
    error_log('Health check error: ' . $e->getMessage());
    $latestRun = null;
    $latestReport = null;
    $results = [];
    $activeAlerts = [];
    $reportHistory = [];
    $resultsByCategory = [];
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Health Checks & Compliance</h3>
            <div class="space-x-2">
                <a href="/admin/api-endpoint-tester.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Test Endpoint</a>
                <button onclick="runTests()" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Run Tests Now</button>
            </div>
        </div>

        <!-- Active Alerts -->
        <?php if (!empty($activeAlerts)): ?>
        <div class="mb-6 space-y-2">
            <?php foreach ($activeAlerts as $alert): ?>
            <div class="bg-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-50 dark:bg-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-900 border-l-4 border-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-500 p-4 rounded">
                <p class="font-semibold text-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-900 dark:text-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-100">
                    <?= ucfirst($alert['alert_level']) ?>: <?= htmlspecialchars($alert['service_name']) ?>
                </p>
                <p class="text-sm text-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-800 dark:text-<?= $alert['alert_level'] === 'critical' ? 'red' : ($alert['alert_level'] === 'warning' ? 'yellow' : 'blue') ?>-200 mt-1">
                    <?= htmlspecialchars($alert['message']) ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <?php if ($latestRun): ?>
        <div class="grid grid-cols-6 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-4">
                <p class="text-gray-500 text-xs uppercase">Last Run</p>
                <p class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-1">
                    <?= date('M j, H:i', strtotime($latestRun['completed_at'])) ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-4">
                <p class="text-gray-500 text-xs uppercase">Total Tests</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?= $latestRun['total_tests'] ?></p>
            </div>

            <div class="bg-green-50 dark:bg-green-900 rounded-lg shadow p-4 border-l-4 border-green-500">
                <p class="text-green-700 dark:text-green-300 text-xs uppercase font-semibold">Passed</p>
                <p class="text-3xl font-bold text-green-600 mt-1"><?= $latestRun['tests_passed'] ?></p>
            </div>

            <div class="bg-red-50 dark:bg-red-900 rounded-lg shadow p-4 border-l-4 border-red-500">
                <p class="text-red-700 dark:text-red-300 text-xs uppercase font-semibold">Failed</p>
                <p class="text-3xl font-bold text-red-600 mt-1"><?= $latestRun['tests_failed'] ?></p>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900 rounded-lg shadow p-4 border-l-4 border-yellow-500">
                <p class="text-yellow-700 dark:text-yellow-300 text-xs uppercase font-semibold">Timeout</p>
                <p class="text-3xl font-bold text-yellow-600 mt-1"><?= $latestRun['tests_timeout'] ?></p>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg shadow p-4 border-l-4 border-blue-500">
                <p class="text-blue-700 dark:text-blue-300 text-xs uppercase font-semibold">Pass Rate</p>
                <p class="text-3xl font-bold text-blue-600 mt-1">
                    <?= $latestRun['total_tests'] > 0 ? round(($latestRun['tests_passed'] / $latestRun['total_tests']) * 100, 1) : '0' ?>%
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Latest Compliance Report -->
        <?php if ($latestReport): ?>
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">
                Latest Compliance Report (<?= date('M j', strtotime($latestReport['report_period_start'])) ?> - <?= date('M j, Y', strtotime($latestReport['report_period_end'])) ?>)
            </h4>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <p class="text-gray-500 text-sm">Pass Rate</p>
                    <p class="text-2xl font-bold text-green-600"><?= $latestReport['pass_rate'] ?>%</p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Tests</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= $latestReport['total_tests'] ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Failures</p>
                    <p class="text-2xl font-bold text-red-600"><?= $latestReport['fail_count'] ?></p>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Avg Response</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= $latestReport['avg_response_time_ms'] ?>ms</p>
                </div>
            </div>
            <?php if ($latestReport['critical_failures']): ?>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm font-semibold text-red-600 mb-2">Critical Failures:</p>
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <?php
                    $failures = json_decode($latestReport['critical_failures'], true);
                    foreach ($failures as $fail) {
                        echo '<li>' . htmlspecialchars($fail) . '</li>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Test Results by Category -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <?php foreach (['api', 'oauth', 'database', 'storage', 'cache', 'integrations', 'webhooks'] as $category): ?>
                <?php if (isset($resultsByCategory[$category])): ?>
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 capitalize"><?= $category ?></h4>
                    <div class="flex gap-4 mb-4">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-green-600"><?= $resultsByCategory[$category]['pass'] ?></p>
                            <p class="text-xs text-gray-500 uppercase">Passed</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-red-600"><?= $resultsByCategory[$category]['fail'] ?></p>
                            <p class="text-xs text-gray-500 uppercase">Failed</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-yellow-600"><?= $resultsByCategory[$category]['timeout'] ?></p>
                            <p class="text-xs text-gray-500 uppercase">Timeout</p>
                        </div>
                    </div>

                    <!-- Individual Tests -->
                    <div class="space-y-2">
                        <?php foreach ($resultsByCategory[$category]['tests'] as $test): ?>
                        <div class="flex items-center justify-between text-sm p-2 bg-gray-50 dark:bg-gray-800 rounded">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded-full bg-<?= $test['status'] === 'pass' ? 'green' : ($test['status'] === 'fail' ? 'red' : 'yellow') ?>-500"></span>
                                <span class="text-gray-800 dark:text-gray-200"><?= htmlspecialchars($test['name']) ?></span>
                            </div>
                            <div class="text-right">
                                <?php if ($test['response_time_ms']): ?>
                                <span class="text-gray-500 text-xs"><?= $test['response_time_ms'] ?>ms</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Report History -->
        <?php if (!empty($reportHistory)): ?>
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Compliance Report History</h4>
            <div class="space-y-3">
                <?php foreach ($reportHistory as $report): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">
                            <?= date('M j', strtotime($report['report_period_start'])) ?> - <?= date('M j, Y', strtotime($report['report_period_end'])) ?>
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            <?= $report['total_tests'] ?> tests · <?= $report['fail_count'] ?> failures · Avg <?= $report['avg_response_time_ms'] ?>ms
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold <?= $report['pass_rate'] >= 95 ? 'text-green-600' : 'text-yellow-600' ?>">
                            <?= $report['pass_rate'] ?>%
                        </p>
                        <?php if ($report['pdf_path']): ?>
                        <a href="<?= htmlspecialchars($report['pdf_path']) ?>" class="text-sm text-blue-600 hover:text-blue-700">Download PDF</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function runTests() {
    if (confirm('Run all health checks now? This will take a few minutes.')) {
        fetch('/admin/health-checks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=run_tests'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Tests started! Refresh in a few seconds to see results.');
                setTimeout(() => location.reload(), 3000);
            } else {
                alert('Error: ' + (data.error || 'Failed to start tests'));
            }
        });
    }
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
