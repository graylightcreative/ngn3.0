<?php
/**
 * Weekly Health Check Runner
 *
 * Bible Ch. 12 - System Integrity and Monitoring
 * Runs weekly compliance tests on all API endpoints and integrations
 * Results stored for reporting and alerting
 *
 * Cron: 0 2 * * 0 (Every Sunday at 2 AM UTC)
 */

require_once __DIR__ . '/../../lib/bootstrap.php';

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Health Check Error [$errno]: $errstr in $errfile:$errline");
    return true;
});

$startTime = microtime(true);
$runId = uniqid('hc_');
$results = [];
$errors = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all enabled weekly tests
    $stmt = $pdo->query("
        SELECT id, endpoint_method, endpoint_path, expected_status, timeout_seconds, request_headers, request_body
        FROM health_check_scenarios
        WHERE is_enabled = TRUE AND run_weekly = TRUE
    ");
    $scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalTests = count($scenarios);
    $passedTests = 0;
    $failedTests = 0;
    $timeoutTests = 0;
    $totalResponseTime = 0;

    // Run each test
    foreach ($scenarios as $scenario) {
        $testStart = microtime(true);
        $status = 'fail';
        $httpStatus = null;
        $errorMessage = null;
        $responseTime = 0;

        try {
            // Build URL
            $url = $scenario['endpoint_path'];
            if (strpos($url, 'http') !== 0) {
                $url = $_ENV['APP_URL'] . $url;
            }

            // Parse headers
            $headers = [];
            if ($scenario['request_headers']) {
                $headerArray = json_decode($scenario['request_headers'], true) ?: [];
                foreach ($headerArray as $key => $val) {
                    $headers[] = "$key: $val";
                }
            }

            // Set up cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $scenario['endpoint_method']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $scenario['timeout_seconds']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, !empty($headers) ? $headers : []);

            // Add body if POST/PUT
            if (in_array($scenario['endpoint_method'], ['POST', 'PUT', 'PATCH']) && $scenario['request_body']) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $scenario['request_body']);
            }

            $response = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $responseTime = round((microtime(true) - $testStart) * 1000, 2);

            if ($curlError) {
                $errorMessage = "cURL Error: $curlError";
                $status = 'fail';
                $failedTests++;
            } elseif ($httpStatus === null) {
                $errorMessage = 'No response status';
                $status = 'timeout';
                $timeoutTests++;
            } elseif ($scenario['expected_status'] && $httpStatus != $scenario['expected_status']) {
                $errorMessage = "Expected {$scenario['expected_status']}, got $httpStatus";
                $status = 'fail';
                $failedTests++;
            } else {
                $status = 'pass';
                $passedTests++;
            }

            // Record result
            $stmt = $pdo->prepare("
                INSERT INTO health_check_results
                    (scenario_id, run_id, status, http_status, response_time_ms, error_message, test_timestamp)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $scenario['id'],
                $runId,
                $status,
                $httpStatus,
                $responseTime,
                $errorMessage
            ]);

            $totalResponseTime += $responseTime;

            // Log result
            error_log("[Health Check] {$scenario['id']} - {$status} ({$responseTime}ms)");

        } catch (Exception $e) {
            $status = 'fail';
            $errorMessage = $e->getMessage();
            $failedTests++;

            $stmt = $pdo->prepare("
                INSERT INTO health_check_results
                    (scenario_id, run_id, status, error_message, test_timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $scenario['id'],
                $runId,
                'fail',
                $errorMessage
            ]);

            error_log("[Health Check Error] {$scenario['id']}: $errorMessage");
        }
    }

    // Calculate duration
    $duration = round(microtime(true) - $startTime, 2);
    $avgResponseTime = $totalTests > 0 ? round($totalResponseTime / $totalTests, 2) : 0;

    // Store run summary
    $stmt = $pdo->prepare("
        INSERT INTO health_check_runs
            (run_id, run_type, total_tests, tests_passed, tests_failed, tests_timeout,
             tests_skipped, avg_response_time_ms, duration_seconds, started_at, completed_at)
        VALUES (?, 'scheduled', ?, ?, ?, ?, 0, ?, ?, DATE_SUB(NOW(), INTERVAL ? SECOND), NOW())
    ");
    $stmt->execute([
        $runId,
        $totalTests,
        $passedTests,
        $failedTests,
        $timeoutTests,
        $avgResponseTime,
        $duration,
        $duration
    ]);

    // Generate compliance report for this week
    $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
    $weekStart = date('Y-m-d', strtotime('last sunday'));
    $weekEnd = date('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO compliance_reports
            (run_id, report_period_start, report_period_end, total_tests, pass_rate,
             fail_count, timeout_count, avg_response_time_ms, generated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            total_tests = VALUES(total_tests),
            pass_rate = VALUES(pass_rate),
            fail_count = VALUES(fail_count),
            avg_response_time_ms = VALUES(avg_response_time_ms)
    ");
    $stmt->execute([
        $runId,
        $weekStart,
        $weekEnd,
        $totalTests,
        $passRate,
        $failedTests,
        $timeoutTests,
        $avgResponseTime
    ]);

    // Create alerts for failures
    if ($failedTests > 0) {
        // Get failed scenarios
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.name FROM health_check_results r
            JOIN health_check_scenarios s ON r.scenario_id = s.id
            WHERE r.run_id = ? AND r.status IN ('fail', 'timeout')
        ");
        $stmt->execute([$runId]);
        $failedScenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($failedScenarios as $failed) {
            $stmt = $pdo->prepare("
                INSERT INTO service_status_alerts
                    (scenario_id, service_name, alert_level, message)
                SELECT id, name, CASE
                    WHEN (SELECT COUNT(*) FROM health_check_results WHERE scenario_id = ? AND status IN ('fail','timeout') AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)) > 5 THEN 'critical'
                    ELSE 'warning'
                    END,
                    CONCAT('Test failed: ', name)
                FROM health_check_scenarios WHERE id = ?
                ON DUPLICATE KEY UPDATE is_resolved = FALSE
            ");
            $stmt->execute([$failed['id'], $failed['id']]);
        }
    }

    echo json_encode([
        'success' => true,
        'run_id' => $runId,
        'total_tests' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'timeout' => $timeoutTests,
        'pass_rate' => $passRate,
        'duration_seconds' => $duration,
        'avg_response_time_ms' => $avgResponseTime
    ]);

    error_log("[Health Check Complete] $passedTests/$totalTests passed ($passRate%) in {$duration}s");

} catch (Exception $e) {
    error_log("[Health Check Fatal Error]: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit(1);
}
