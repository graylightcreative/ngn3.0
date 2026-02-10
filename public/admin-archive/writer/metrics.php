<?php
/**
 * Writer Engine - Performance Metrics Dashboard
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$config = new Config();
$pdo = ConnectionFactory::read($config);

$pageTitle = 'Writer Engine - Metrics Dashboard';

// Fetch daily metrics
$sql = "
    SELECT
        DATE(metric_date) as date,
        SUM(articles_generated) as generated,
        SUM(articles_published) as published,
        SUM(articles_rejected) as rejected,
        SUM(total_cost_usd) as cost,
        AVG(safety_rejection_rate) as avg_safety_rate
    FROM writer_generation_metrics
    WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(metric_date)
    ORDER BY date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$summarySQL = "
    SELECT
        SUM(articles_published) as total_published,
        SUM(total_cost_usd) as total_cost,
        AVG(safety_rejection_rate) as avg_rejection_rate
    FROM writer_generation_metrics
    WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
";

$stmt = $pdo->prepare($summarySQL);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid p-4">
        <h1>📊 Writer Engine Metrics</h1>
        <a href="/admin" class="btn btn-secondary mb-3">Back to Admin</a>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #007bff;">
                        <?php echo $summary['total_published'] ?? 0; ?>
                    </div>
                    <small class="text-muted">Published (7 days)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #28a745;">
                        $<?php echo number_format($summary['total_cost'] ?? 0, 2); ?>
                    </div>
                    <small class="text-muted">LLM Cost (7 days)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 28px; font-weight: bold; color: #fd7e14;">
                        <?php echo number_format(($summary['avg_rejection_rate'] ?? 0) * 100, 1); ?>%
                    </div>
                    <small class="text-muted">Avg Safety Rejection</small>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <h3>Daily Metrics (Last 30 Days)</h3>
        <div style="background: white; border-radius: 8px; overflow: hidden;">
            <table class="table table-sm mb-0">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th>Date</th>
                        <th>Generated</th>
                        <th>Published</th>
                        <th>Rejected</th>
                        <th>Cost</th>
                        <th>Safety Rejection %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metrics as $metric): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($metric['date'])); ?></td>
                            <td><?php echo $metric['generated'] ?? 0; ?></td>
                            <td><?php echo $metric['published'] ?? 0; ?></td>
                            <td><?php echo $metric['rejected'] ?? 0; ?></td>
                            <td>$<?php echo number_format($metric['cost'] ?? 0, 2); ?></td>
                            <td><?php echo number_format(($metric['avg_safety_rate'] ?? 0) * 100, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
