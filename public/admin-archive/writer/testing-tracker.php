<?php
/**
 * Writer Engine - Testing Tracker Dashboard
 * Real-time test progress tracking and management
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Logging\LoggerFactory;

Env::load($root);
$config = new Config();

try {
    $pdo = ConnectionFactory::write($config);
    $logger = LoggerFactory::create($config, 'testing_tracker');
} catch (\Throwable $e) {
    die('Failed to initialize services');
}

$pageTitle = 'Writer Engine - Testing Tracker';
$currentPage = 'writer_testing_tracker';

// Handle test result submission
$message = $messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add_feature') {
            $featureName = $_POST['feature_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $addedBy = $_POST['added_by'] ?? 'Unknown';
            $dateAdded = $_POST['date_added'] ?? date('Y-m-d');
            $estimatedTests = (int)($_POST['estimated_tests'] ?? 1);

            if (empty($featureName)) {
                throw new \Exception('Feature name required');
            }

            $sql = "
                INSERT INTO writer_feature_additions (feature_name, description, added_by, date_added, estimated_test_cases)
                VALUES (:name, :description, :added_by, :date_added, :est_tests)
            ";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':name' => $featureName,
                ':description' => $description,
                ':added_by' => $addedBy,
                ':date_added' => $dateAdded,
                ':est_tests' => $estimatedTests,
            ]);

            if ($success) {
                $logger->info("Feature addition tracked", ['feature' => $featureName, 'added_by' => $addedBy]);
                $message = "Feature '$featureName' added to tracking";
                $messageType = 'success';
                header("Location: ?");
                exit;
            }
        } elseif ($action === 'update_feature') {
            $featureId = (int)($_POST['feature_id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $testCasesCreated = (int)($_POST['test_cases_created'] ?? 0);
            $notes = $_POST['notes'] ?? '';

            $sql = "
                UPDATE writer_feature_additions
                SET test_coverage_status = :status,
                    test_cases_created = :cases_created,
                    notes = :notes
                WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':status' => $status,
                ':cases_created' => $testCasesCreated,
                ':notes' => $notes,
                ':id' => $featureId,
            ]);

            if ($success) {
                $logger->info("Feature coverage updated", ['feature_id' => $featureId, 'status' => $status]);
                $message = 'Feature test coverage updated';
                $messageType = 'success';
                header("Location: ?");
                exit;
            }
        } elseif ($action === 'update_test') {
            $testId = (int)($_POST['test_id'] ?? 0);
            $status = $_POST['status'] ?? 'not_started';
            $result = $_POST['result_notes'] ?? '';
            $testerName = $_POST['tester_name'] ?? 'Unknown';
            $issueFound = isset($_POST['issue_found']) ? 1 : 0;
            $issueTitle = $_POST['issue_title'] ?? '';
            $issueSeverity = $_POST['issue_severity'] ?? null;

            $sql = "
                UPDATE writer_test_cases
                SET status = :status,
                    result_notes = :result_notes,
                    tester_name = :tester_name,
                    issue_found = :issue_found,
                    issue_title = :issue_title,
                    issue_severity = :issue_severity,
                    started_at = CASE WHEN started_at IS NULL AND :status != 'not_started' THEN NOW() ELSE started_at END,
                    completed_at = CASE WHEN :status IN ('passed', 'failed', 'blocked', 'skipped') THEN NOW() ELSE NULL END
                WHERE id = :id
            ";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':status' => $status,
                ':result_notes' => $result,
                ':tester_name' => $testerName,
                ':issue_found' => $issueFound,
                ':issue_title' => $issueTitle,
                ':issue_severity' => $issueFound ? $issueSeverity : null,
                ':id' => $testId,
            ]);

            if ($success) {
                $logger->info("Test updated", ['test_id' => $testId, 'status' => $status]);
                $message = 'Test result updated successfully';
                $messageType = 'success';
            }
        } elseif ($action === 'start_test_run') {
            $runName = $_POST['run_name'] ?? date('Y-m-d H:i');
            $targetDate = $_POST['target_end_date'] ?? null;

            $sql = "INSERT INTO writer_test_runs (run_name, start_date, target_end_date, status) VALUES (:name, CURDATE(), :target, 'in_progress')";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':name' => $runName,
                ':target' => $targetDate,
            ]);

            if ($success) {
                $message = 'Test run started: ' . $runName;
                $messageType = 'success';
                header("Location: ?");
                exit;
            }
        }
    } catch (\Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
        $logger->error("Test update failed", ['error' => $e->getMessage()]);
    }
}

// Get statistics
$statsSql = "
    SELECT
        COUNT(*) as total_tests,
        SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started,
        SUM(CASE WHEN issue_found = 1 THEN 1 ELSE 0 END) as issues_found
    FROM writer_test_cases
";

$statsResult = $pdo->query($statsSql)->fetch(\PDO::FETCH_ASSOC);
$passRate = $statsResult['total_tests'] > 0
    ? round(($statsResult['passed'] / $statsResult['total_tests']) * 100, 1)
    : 0;

// Get suites with counts
$suitesSql = "
    SELECT
        s.id, s.name, s.category, s.assigned_to, s.estimated_hours, s.priority, s.status,
        COUNT(t.id) as test_count,
        SUM(CASE WHEN t.status = 'passed' THEN 1 ELSE 0 END) as tests_passed,
        SUM(CASE WHEN t.status = 'failed' THEN 1 ELSE 0 END) as tests_failed,
        SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END) as tests_blocked
    FROM writer_test_suites s
    LEFT JOIN writer_test_cases t ON s.id = t.suite_id
    GROUP BY s.id
    ORDER BY s.priority DESC, s.name
";

$suites = $pdo->query($suitesSql)->fetchAll(\PDO::FETCH_ASSOC);

// Get test cases for selected suite
$selectedSuiteId = (int)($_GET['suite_id'] ?? 1);
$suiteFilter = "WHERE suite_id = :suite_id";
$params = [':suite_id' => $selectedSuiteId];

if (isset($_GET['status_filter']) && $_GET['status_filter'] !== 'all') {
    $suiteFilter .= " AND status = :status";
    $params[':status'] = $_GET['status_filter'];
}

$testsSql = "
    SELECT id, suite_id, test_id, name, description, status, tester_name, result_notes,
           issue_found, issue_title, issue_severity, priority, started_at, completed_at
    FROM writer_test_cases
    $suiteFilter
    ORDER BY priority DESC, test_id ASC
";

$testsStmt = $pdo->prepare($testsSql);
$testsStmt->execute($params);
$tests = $testsStmt->fetchAll(\PDO::FETCH_ASSOC);

// Get active test run
$runSql = "SELECT * FROM writer_test_runs WHERE status = 'in_progress' ORDER BY created_at DESC LIMIT 1";
$activeRun = $pdo->query($runSql)->fetch(\PDO::FETCH_ASSOC);

// Get feature additions
$featuresSql = "
    SELECT id, feature_name, description, added_by, date_added, estimated_test_cases,
           test_coverage_status, test_cases_created, notes
    FROM writer_feature_additions
    ORDER BY date_added DESC
";
$features = $pdo->query($featuresSql)->fetchAll(\PDO::FETCH_ASSOC);

// Calculate feature coverage statistics
$featuresTotal = count($features);
$featuresComplete = count(array_filter($features, fn($f) => $f['test_coverage_status'] === 'complete'));
$featuresPartial = count(array_filter($features, fn($f) => $f['test_coverage_status'] === 'partial'));
$featuresPending = count(array_filter($features, fn($f) => $f['test_coverage_status'] === 'pending'));
$featuresCoverageReady = $featuresPending === 0 && $featuresTotal > 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .test-row { border-bottom: 1px solid #e9ecef; padding: 15px 0; }
        .test-row:last-child { border-bottom: none; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-not_started { background: #e9ecef; color: #495057; }
        .status-in_progress { background: #cfe2ff; color: #084298; }
        .status-passed { background: #d1e7dd; color: #0a3622; }
        .status-failed { background: #f8d7da; color: #842029; }
        .status-blocked { background: #fff3cd; color: #664d03; }
        .status-skipped { background: #e2e3e5; color: #41464b; }

        .priority-critical { color: #dc3545; font-weight: 600; }
        .priority-high { color: #fd7e14; font-weight: 500; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #6c757d; }

        .progress-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        .stat-card.critical { border-left-color: #dc3545; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.failed { border-left-color: #dc3545; }
        .stat-card.blocked { border-left-color: #ffc107; }

        .stat-number { font-size: 28px; font-weight: bold; color: #007bff; }
        .stat-card.critical .stat-number { color: #dc3545; }
        .stat-card.success .stat-number { color: #28a745; }
        .stat-card.failed .stat-number { color: #dc3545; }
        .stat-card.blocked .stat-number { color: #ffc107; }

        .stat-label { font-size: 0.9rem; color: #6c757d; margin-top: 5px; }

        .suite-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
            cursor: pointer;
            transition: all 0.2s;
        }
        .suite-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .suite-card.active { background: #e7f3ff; border-left-color: #0056b3; }

        .test-modal-content { max-height: 80vh; overflow-y: auto; }
        .progress-bar-container { margin: 15px 0; }
        .run-info { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📋 Writer Engine - Testing Tracker</h1>
            <div>
                <a href="/admin" class="btn btn-secondary">Back to Admin</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Active Test Run Info -->
        <?php if ($activeRun): ?>
            <div class="run-info">
                <strong>🚀 Active Test Run:</strong> <?php echo htmlspecialchars($activeRun['run_name']); ?>
                (Started: <?php echo date('M d, Y', strtotime($activeRun['start_date'])); ?>
                | Target: <?php echo $activeRun['target_end_date'] ? date('M d, Y', strtotime($activeRun['target_end_date'])) : 'No deadline'; ?>)
            </div>
        <?php endif; ?>

        <!-- Overall Statistics -->
        <div class="progress-section">
            <h3 class="mb-3">Overall Progress</h3>

            <div class="row mb-3">
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $statsResult['total_tests']; ?></div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $statsResult['passed']; ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card failed">
                        <div class="stat-number"><?php echo $statsResult['failed']; ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card blocked">
                        <div class="stat-number"><?php echo $statsResult['blocked']; ?></div>
                        <div class="stat-label">Blocked</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card critical">
                        <div class="stat-number"><?php echo $statsResult['issues_found']; ?></div>
                        <div class="stat-label">Issues Found</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div style="background: white; border-radius: 8px; padding: 15px; text-align: center; border-left: 4px solid #28a745;">
                        <div class="stat-number" style="color: #28a745; font-size: 32px;"><?php echo $passRate; ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar-container">
                <div class="progress" style="height: 30px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $passRate; ?>%" aria-valuenow="<?php echo $passRate; ?>" aria-valuemin="0" aria-valuemax="100">
                        <strong><?php echo $statsResult['passed'] . '/' . $statsResult['total_tests']; ?> Passed</strong>
                    </div>
                </div>
            </div>

            <small class="text-muted">
                🎯 Target for deployment: 95%+ pass rate or all critical tests passing
                <span class="<?php echo $passRate >= 95 ? 'text-success' : 'text-warning'; ?>">
                    <?php echo $passRate >= 95 ? '✅ Ready' : '⚠️ ' . (95 - $passRate) . '% to go'; ?>
                </span>
            </small>
        </div>

        <!-- Test Suites Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <h3>Test Suites</h3>
                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($suites as $suite): ?>
                        <?php
                        $suitePassRate = $suite['test_count'] > 0
                            ? round(($suite['tests_passed'] / $suite['test_count']) * 100, 0)
                            : 0;
                        $isActive = $suite['id'] == $selectedSuiteId;
                        ?>
                        <a href="?suite_id=<?php echo $suite['id']; ?>" style="text-decoration: none;">
                            <div class="suite-card <?php echo $isActive ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($suite['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $suite['test_count']; ?> tests
                                            | Assigned: <?php echo htmlspecialchars($suite['assigned_to'] ?? 'Unassigned'); ?>
                                        </small>
                                    </div>
                                    <span class="priority-<?php echo $suite['priority']; ?>">
                                        <?php echo ucfirst($suite['priority']); ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $suitePassRate; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        ✅ <?php echo $suite['tests_passed']; ?>
                                        | ❌ <?php echo $suite['tests_failed']; ?>
                                        | ⏸ <?php echo $suite['tests_blocked']; ?>
                                    </small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Test Cases -->
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Test Cases</h3>
                    <select class="form-select" style="width: auto;" onchange="window.location.href = '?suite_id=<?php echo $selectedSuiteId; ?>&status_filter=' + this.value">
                        <option value="all">All Statuses</option>
                        <option value="not_started" <?php echo $_GET['status_filter'] === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                        <option value="in_progress" <?php echo $_GET['status_filter'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="passed" <?php echo $_GET['status_filter'] === 'passed' ? 'selected' : ''; ?>>Passed</option>
                        <option value="failed" <?php echo $_GET['status_filter'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="blocked" <?php echo $_GET['status_filter'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                </div>

                <div style="background: white; border-radius: 8px; padding: 20px; max-height: 600px; overflow-y: auto;">
                    <?php foreach ($tests as $test): ?>
                        <div class="test-row">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        <span class="badge <?php echo 'bg-' . ($test['priority'] === 'critical' ? 'danger' : ($test['priority'] === 'high' ? 'warning' : 'secondary')); ?>">
                                            <?php echo htmlspecialchars($test['test_id']); ?>
                                        </span>
                                        <?php echo htmlspecialchars($test['name']); ?>
                                    </h6>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($test['description']); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo $test['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $test['status'])); ?>
                                </span>
                            </div>

                            <?php if ($test['tester_name']): ?>
                                <small class="text-muted">👤 <?php echo htmlspecialchars($test['tester_name']); ?></small>
                            <?php endif; ?>

                            <?php if ($test['result_notes']): ?>
                                <div class="bg-light p-2 rounded mt-2" style="font-size: 0.9rem;">
                                    <?php echo nl2br(htmlspecialchars(substr($test['result_notes'], 0, 200))); ?>
                                    <?php if (strlen($test['result_notes']) > 200): ?>...<br><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($test['issue_found']): ?>
                                <div class="alert alert-<?php echo $test['issue_severity'] === 'critical' ? 'danger' : ($test['issue_severity'] === 'high' ? 'warning' : 'info'); ?>" style="font-size: 0.9rem; margin-top: 8px; padding: 8px;">
                                    <strong>Issue:</strong> <?php echo htmlspecialchars($test['issue_title']); ?>
                                    <span class="badge bg-<?php echo $test['issue_severity'] === 'critical' ? 'danger' : ($test['issue_severity'] === 'high' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($test['issue_severity']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#testModal<?php echo $test['id']; ?>">
                                    📝 Update Result
                                </button>
                            </div>
                        </div>

                        <!-- Test Update Modal -->
                        <div class="modal fade" id="testModal<?php echo $test['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content test-modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?php echo htmlspecialchars($test['name']); ?> (<?php echo htmlspecialchars($test['test_id']); ?>)</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" class="modal-body">
                                        <input type="hidden" name="action" value="update_test">
                                        <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Your Name</label>
                                            <input type="text" name="tester_name" class="form-control" placeholder="Your name" value="<?php echo htmlspecialchars($test['tester_name'] ?? ''); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Test Status</label>
                                            <select name="status" class="form-select" required>
                                                <option value="not_started" <?php echo $test['status'] === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                                <option value="in_progress" <?php echo $test['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="passed" <?php echo $test['status'] === 'passed' ? 'selected' : ''; ?>>✅ Passed</option>
                                                <option value="failed" <?php echo $test['status'] === 'failed' ? 'selected' : ''; ?>>❌ Failed</option>
                                                <option value="blocked" <?php echo $test['status'] === 'blocked' ? 'selected' : ''; ?>>⏸ Blocked</option>
                                                <option value="skipped" <?php echo $test['status'] === 'skipped' ? 'selected' : ''; ?>>⊘ Skipped</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Notes & Results</label>
                                            <textarea name="result_notes" class="form-control" rows="4" placeholder="What happened? Pass/Fail details, errors, observations..."><?php echo htmlspecialchars($test['result_notes'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="issue_found" id="issueCheck<?php echo $test['id']; ?>" class="form-check-input" <?php echo $test['issue_found'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="issueCheck<?php echo $test['id']; ?>">
                                                Found an issue
                                            </label>
                                        </div>

                                        <div id="issueSection<?php echo $test['id']; ?>" style="display: <?php echo $test['issue_found'] ? 'block' : 'none'; ?>;">
                                            <div class="mb-3">
                                                <label class="form-label">Issue Title</label>
                                                <input type="text" name="issue_title" class="form-control" placeholder="Brief issue description" value="<?php echo htmlspecialchars($test['issue_title'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Issue Severity</label>
                                                <select name="issue_severity" class="form-select">
                                                    <option value="low" <?php echo ($test['issue_severity'] ?? '') === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
                                                    <option value="medium" <?php echo ($test['issue_severity'] ?? '') === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                                    <option value="high" <?php echo ($test['issue_severity'] ?? '') === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                                                    <option value="critical" <?php echo ($test['issue_severity'] ?? '') === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                                                </select>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" onclick="document.querySelector('#testModal<?php echo $test['id']; ?> form').submit();">Save Result</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($tests)): ?>
                        <div class="alert alert-info">No tests in this category yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Feature Additions Tracking -->
        <div class="progress-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>📦 Feature Additions Tracking</h3>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                    ➕ Add New Feature
                </button>
            </div>

            <!-- Feature Coverage Statistics -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $featuresTotal; ?></div>
                        <div class="stat-label">Total Features</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $featuresComplete; ?></div>
                        <div class="stat-label">Complete Coverage</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card blocked">
                        <div class="stat-number"><?php echo $featuresPartial; ?></div>
                        <div class="stat-label">Partial Coverage</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card critical">
                        <div class="stat-number"><?php echo $featuresPending; ?></div>
                        <div class="stat-label">Pending Tests</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($features)): ?>
                <div style="background: white; border-radius: 8px; padding: 20px;">
                    <?php foreach ($features as $feature): ?>
                        <div class="test-row">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($feature['feature_name']); ?>
                                    </h6>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($feature['description']); ?></p>
                                    <small class="text-muted">
                                        Added by <?php echo htmlspecialchars($feature['added_by']); ?>
                                        on <?php echo date('M d, Y', strtotime($feature['date_added'])); ?>
                                    </small>
                                </div>
                                <span class="status-badge status-<?php echo
                                    $feature['test_coverage_status'] === 'complete' ? 'passed' :
                                    ($feature['test_coverage_status'] === 'partial' ? 'in_progress' : 'failed'); ?>">
                                    <?php echo ucfirst($feature['test_coverage_status']); ?> Coverage
                                </span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        📋 Estimated: <?php echo $feature['estimated_test_cases']; ?> test(s) |
                                        ✅ Created: <?php echo $feature['test_cases_created']; ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#updateFeatureModal<?php echo $feature['id']; ?>">
                                        ✏️ Update Coverage
                                    </button>
                                </div>
                            </div>

                            <?php if ($feature['notes']): ?>
                                <div class="bg-light p-2 rounded mt-2" style="font-size: 0.9rem;">
                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($feature['notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Update Feature Modal -->
                        <div class="modal fade" id="updateFeatureModal<?php echo $feature['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Feature Coverage - <?php echo htmlspecialchars($feature['feature_name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" class="modal-body">
                                        <input type="hidden" name="action" value="update_feature">
                                        <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Feature Name</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($feature['feature_name']); ?>" disabled>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Test Coverage Status</label>
                                            <select name="status" class="form-select" required>
                                                <option value="pending" <?php echo $feature['test_coverage_status'] === 'pending' ? 'selected' : ''; ?>>⏳ Pending - Tests not yet written</option>
                                                <option value="partial" <?php echo $feature['test_coverage_status'] === 'partial' ? 'selected' : ''; ?>>🔄 Partial - Some tests written</option>
                                                <option value="complete" <?php echo $feature['test_coverage_status'] === 'complete' ? 'selected' : ''; ?>>✅ Complete - Full test coverage</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Test Cases Created</label>
                                            <input type="number" name="test_cases_created" class="form-control" value="<?php echo $feature['test_cases_created']; ?>" min="0" placeholder="Number of test cases created for this feature">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Any notes about testing this feature..."><?php echo htmlspecialchars($feature['notes'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="alert alert-info" style="margin: 0;">
                                            <small>
                                                <strong>Progress:</strong> <?php echo $feature['test_cases_created']; ?> / <?php echo $feature['estimated_test_cases']; ?> test cases
                                                (<?php echo $feature['estimated_test_cases'] > 0 ? round(($feature['test_cases_created'] / $feature['estimated_test_cases']) * 100, 0) : 0; ?>%)
                                            </small>
                                        </div>
                                    </form>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" onclick="document.querySelector('#updateFeatureModal<?php echo $feature['id']; ?> form').submit();">Save Changes</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No features tracked yet. Click "➕ Add New Feature" to start.</div>
            <?php endif; ?>
        </div>

        <!-- Add Feature Modal -->
        <div class="modal fade" id="addFeatureModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Feature</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" class="modal-body">
                        <input type="hidden" name="action" value="add_feature">

                        <div class="mb-3">
                            <label class="form-label">Feature Name *</label>
                            <input type="text" name="feature_name" class="form-control" placeholder="e.g., 'Inter-persona comments on articles'" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="What does this feature do?"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Added By</label>
                            <input type="text" name="added_by" class="form-control" placeholder="Your name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Developer'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date Added</label>
                            <input type="date" name="date_added" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estimated Test Cases</label>
                            <input type="number" name="estimated_tests" class="form-control" value="1" min="1" placeholder="How many test cases do you estimate this feature needs?">
                        </div>

                        <div class="alert alert-info" style="margin: 0;">
                            <small>
                                Feature will start with <strong>Pending</strong> coverage status.
                                Update coverage as you write and complete tests.
                            </small>
                        </div>
                    </form>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="document.querySelector('#addFeatureModal form').submit();">Add Feature</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issues Found -->
        <?php
        $issuesSql = "
            SELECT id, test_id, name, issue_title, issue_severity, suite_id, status
            FROM writer_test_cases
            WHERE issue_found = 1
            ORDER BY issue_severity DESC
        ";
        $issues = $pdo->query($issuesSql)->fetchAll(\PDO::FETCH_ASSOC);
        ?>

        <?php if (!empty($issues)): ?>
            <div class="progress-section">
                <h3>Issues Found (<?php echo count($issues); ?>)</h3>

                <div class="row">
                    <?php foreach ($issues as $issue): ?>
                        <div class="col-md-6 mb-3">
                            <div class="alert alert-<?php echo $issue['issue_severity'] === 'critical' ? 'danger' : ($issue['issue_severity'] === 'high' ? 'warning' : 'info'); ?>">
                                <strong><?php echo htmlspecialchars($issue['test_id'] . ': ' . $issue['name']); ?></strong><br>
                                <span class="badge bg-<?php echo $issue['issue_severity'] === 'critical' ? 'danger' : ($issue['issue_severity'] === 'high' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($issue['issue_severity']); ?>
                                </span>
                                <br>
                                <small><?php echo htmlspecialchars($issue['issue_title']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Deployment Readiness -->
        <div class="progress-section">
            <h3>🚀 Deployment Readiness</h3>

            <div class="row">
                <div class="col-md-6">
                    <h5>Checklist</h5>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <?php echo $passRate >= 95 ? '✅' : '❌'; ?>
                            <strong>Pass Rate ≥ 95%</strong>
                            (Currently: <?php echo $passRate; ?>%)
                        </li>
                        <li class="list-group-item">
                            <?php echo $statsResult['failed'] == 0 ? '✅' : '❌'; ?>
                            <strong>All Critical Tests Passing</strong>
                            (Failed: <?php echo $statsResult['failed']; ?>)
                        </li>
                        <li class="list-group-item">
                            <?php echo $statsResult['blocked'] == 0 ? '✅' : '❌'; ?>
                            <strong>No Blocked Tests</strong>
                            (Blocked: <?php echo $statsResult['blocked']; ?>)
                        </li>
                        <li class="list-group-item">
                            <?php echo $statsResult['not_started'] == 0 ? '✅' : '❌'; ?>
                            <strong>All Tests Started/Completed</strong>
                            (Not Started: <?php echo $statsResult['not_started']; ?>)
                        </li>
                        <li class="list-group-item">
                            <?php echo ($featuresCoverageReady || $featuresTotal === 0) ? '✅' : '❌'; ?>
                            <strong>Feature Test Coverage Complete</strong>
                            (<?php echo $featuresComplete; ?>/<?php echo $featuresTotal; ?> complete<?php echo $featuresPending > 0 ? ', ' . $featuresPending . ' pending' : ''; ?>)
                        </li>
                    </ul>
                </div>

                <div class="col-md-6">
                    <h5>Summary</h5>
                    <div style="background: white; border-radius: 8px; padding: 15px;">
                        <p>
                            <strong>Total Tests:</strong> <?php echo $statsResult['total_tests']; ?><br>
                            <strong>Passed:</strong> <span class="text-success"><?php echo $statsResult['passed']; ?></span><br>
                            <strong>Failed:</strong> <span class="text-danger"><?php echo $statsResult['failed']; ?></span><br>
                            <strong>Blocked:</strong> <span class="text-warning"><?php echo $statsResult['blocked']; ?></span><br>
                            <strong>Not Started:</strong> <?php echo $statsResult['not_started']; ?><br>
                            <br>
                            <strong>Issues Found:</strong> <span class="text-info"><?php echo $statsResult['issues_found']; ?></span>
                        </p>

                        <?php
                        $readyForDeployment = $passRate >= 95
                            && $statsResult['not_started'] == 0
                            && ($featuresCoverageReady || $featuresTotal === 0);
                        ?>

                        <div class="alert alert-<?php echo $readyForDeployment ? 'success' : 'warning'; ?>" style="margin: 0;">
                            <?php if ($readyForDeployment): ?>
                                ✅ READY FOR DEPLOYMENT
                                <br><small>All tests passing, no blockers, and all features have complete test coverage.</small>
                            <?php else: ?>
                                ⚠️ NOT YET READY
                                <br><small>
                                    <?php
                                    $blockers = [];
                                    if ($passRate < 95) $blockers[] = "Pass rate below 95% (" . $passRate . "%)";
                                    if ($statsResult['not_started'] > 0) $blockers[] = $statsResult['not_started'] . " tests not started";
                                    if ($featuresPending > 0) $blockers[] = $featuresPending . " feature(s) with pending test coverage";
                                    echo implode("; ", $blockers);
                                    ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle issue section
        document.querySelectorAll('[id^="issueCheck"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const testId = this.id.replace('issueCheck', '');
                const section = document.getElementById('issueSection' + testId);
                section.style.display = this.checked ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
