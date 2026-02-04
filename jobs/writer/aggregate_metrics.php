<?php
/**
 * Aggregate Metrics - Daily Persona Performance Metrics
 * Aggregates daily metrics from generated articles
 * Runs daily at 2 AM UTC
 *
 * Schedule: 0 2 * * * (daily at 2 AM)
 * Command: php /path/to/jobs/writer/aggregate_metrics.php
 */

require_once __DIR__ . '/../../lib/autoload.php';
require_once __DIR__ . '/../../lib/config/config.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$logFile = __DIR__ . '/../../storage/logs/writer_metrics.log';

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    echo $logLine;
}

try {
    logMessage("=== Metrics Aggregation Starting ===", $logFile);

    $config = new Config();
    $read = ConnectionFactory::read($config);

    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Aggregate metrics for each persona
    $sql = "
        SELECT
            wp.id,
            wp.name,
            COUNT(CASE WHEN wa.status = 'published' THEN 1 END) as articles_published,
            COUNT(CASE WHEN wa.status = 'rejected' THEN 1 END) as articles_rejected,
            COUNT(CASE WHEN wa.safety_scan_status = 'flagged' THEN 1 END) as articles_flagged,
            SUM(wa.generation_cost_usd) as total_cost,
            AVG(wa.generation_time_ms) as avg_generation_time,
            SUM(wa.prompt_tokens) as total_prompt_tokens,
            SUM(wa.completion_tokens) as total_completion_tokens
        FROM writer_personas wp
        LEFT JOIN writer_articles wa ON wp.id = wa.persona_id AND DATE(wa.created_at) = :yesterday
        GROUP BY wp.id, wp.name
    ";

    $stmt = $read->prepare($sql);
    $stmt->execute([':yesterday' => $yesterday]);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = $row;

        $rejectionRate = $row['articles_published'] > 0
            ? ($row['articles_rejected'] / ($row['articles_published'] + $row['articles_rejected'])) * 100
            : 0;

        logMessage("{$row['name']}: {$row['articles_published']} published, {$row['articles_rejected']} rejected, " .
                   number_format($rejectionRate, 1) . "% rejection rate", $logFile);

        // Alert if rejection rate exceeds threshold
        if ($rejectionRate > 15) {
            logMessage("⚠️  P1 ALERT - High rejection rate for {$row['name']}: " .
                       number_format($rejectionRate, 1) . "%", $logFile);
        }
    }

    // Check for zero publishes (potential problem)
    $noPubCount = array_filter($results, fn($r) => $r['articles_published'] == 0);
    if (count($noPubCount) > 2) {
        logMessage("⚠️  P0 ALERT - No articles published yesterday by some personas", $logFile);
    }

    logMessage("Metrics aggregation complete for $yesterday", $logFile);
    logMessage("=== Metrics Complete ===\n", $logFile);
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "CRITICAL ERROR in Metrics: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    error_log($errorMessage);
    exit(1);
}
