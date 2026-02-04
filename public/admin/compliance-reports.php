<?php
/**
 * Compliance Report Generator & Archive
 *
 * Bible Ch. 12 - System Integrity and Monitoring
 * Generates and manages weekly compliance reports for auditing
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'compliance-reports';
$message = null;
$error = null;

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $reportStart = $_POST['report_start'] ?? null;
    $reportEnd = $_POST['report_end'] ?? null;

    if (!$reportStart || !$reportEnd) {
        $error = 'Both start and end dates are required';
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Get report data
            $stmt = $pdo->prepare("
                SELECT * FROM compliance_reports
                WHERE report_period_start = ? AND report_period_end = ?
            ");
            $stmt->execute([$reportStart, $reportEnd]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                $error = 'No report found for this period. Run health checks first.';
            } else {
                // Get detailed results for this period
                $stmt = $pdo->prepare("
                    SELECT s.name, s.category, COUNT(*) as run_count,
                           SUM(CASE WHEN r.status = 'pass' THEN 1 ELSE 0 END) as pass_count,
                           SUM(CASE WHEN r.status = 'fail' THEN 1 ELSE 0 END) as fail_count,
                           AVG(r.response_time_ms) as avg_response
                    FROM health_check_results r
                    JOIN health_check_scenarios s ON r.scenario_id = s.id
                    JOIN health_check_runs hr ON r.run_id = hr.run_id
                    WHERE hr.completed_at >= ? AND hr.completed_at < DATE_ADD(?, INTERVAL 1 DAY)
                    GROUP BY s.category, s.name
                    ORDER BY s.category, s.name
                ");
                $stmt->execute([$reportStart, $reportEnd]);
                $testDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $message = 'Report generated successfully. PDF generation is a future enhancement.';
            }
        } catch (Exception $e) {
            $error = 'Error generating report: ' . $e->getMessage();
        }
    }
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all compliance reports
    $stmt = $pdo->query("
        SELECT * FROM compliance_reports
        ORDER BY report_period_end DESC
        LIMIT 12
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Compliance reports error: ' . $e->getMessage());
    $reports = [];
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Compliance Reports</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Weekly system health & audit reports</p>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-100 rounded-lg">
            ✓ <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-100 rounded-lg">
            ✗ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Generate Report Form -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Generate Report</h4>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="generate_report">

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                    <input type="date" name="report_start" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200"
                           value="<?= date('Y-m-d', strtotime('last sunday')) ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                    <input type="date" name="report_end" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-200"
                           value="<?= date('Y-m-d') ?>">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Reports List -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Report Archive</h4>

            <?php if (empty($reports)): ?>
            <p class="text-gray-600 dark:text-gray-400">No reports yet. Run health checks to generate compliance reports.</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($reports as $report): ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-6 gap-4">
                        <!-- Period -->
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Period</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                <?= date('M j', strtotime($report['report_period_start'])) ?> - <?= date('M j, Y', strtotime($report['report_period_end'])) ?>
                            </p>
                        </div>

                        <!-- Pass Rate -->
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Pass Rate</p>
                            <p class="text-2xl font-bold <?= $report['pass_rate'] >= 95 ? 'text-green-600' : ($report['pass_rate'] >= 90 ? 'text-yellow-600' : 'text-red-600') ?>">
                                <?= $report['pass_rate'] ?>%
                            </p>
                        </div>

                        <!-- Tests -->
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Total Tests</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100"><?= $report['total_tests'] ?></p>
                        </div>

                        <!-- Failures -->
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Failures</p>
                            <p class="font-semibold <?= $report['fail_count'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $report['fail_count'] ?>
                            </p>
                        </div>

                        <!-- Avg Response -->
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Avg Response</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-100"><?= $report['avg_response_time_ms'] ?>ms</p>
                        </div>

                        <!-- Actions -->
                        <div class="text-right">
                            <button type="button" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
                                    onclick="viewReport('<?= htmlspecialchars($report['run_id']) ?>')">
                                View Details
                            </button>
                        </div>
                    </div>

                    <!-- Critical Failures -->
                    <?php if ($report['critical_failures']): ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-semibold text-red-600 mb-2">⚠ Critical Failures:</p>
                        <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                            <?php
                            $failures = json_decode($report['critical_failures'], true);
                            foreach ($failures as $fail) {
                                echo '<li>• ' . htmlspecialchars($fail) . '</li>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Report Standards -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-6 rounded">
            <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Compliance Standards</h4>
            <ul class="text-blue-800 dark:text-blue-200 text-sm space-y-1">
                <li>✓ <strong>Pass Rate Target:</strong> ≥ 95% (critical if < 90%)</li>
                <li>✓ <strong>Average Response:</strong> < 200ms is healthy</li>
                <li>✓ <strong>Test Categories:</strong> API, OAuth, Database, Storage, Cache, Integrations, Webhooks</li>
                <li>✓ <strong>Frequency:</strong> Weekly (Sunday 2:00 AM UTC)</li>
                <li>✓ <strong>Retention:</strong> 12 months for audit trail</li>
            </ul>
        </div>
    </div>
</main>

<script>
function viewReport(runId) {
    // TODO: Show detailed report modal
    alert('View report: ' + runId);
}
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
