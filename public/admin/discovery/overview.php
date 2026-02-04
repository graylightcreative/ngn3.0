<?php

/**
 * Discovery Engine - Admin Overview Dashboard
 * Displays metrics and performance dashboard
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

// Verify admin access
AdminAuth::requireAdmin();

$readConnection = ConnectionFactory::read();

// Get KPI metrics
try {
    // Active users with recommendations
    $stmt = $readConnection->prepare(
        'SELECT COUNT(DISTINCT user_id) as active_users FROM discovery_recommendations WHERE expires_at > NOW()'
    );
    $stmt->execute();
    $activeUsersWithRecs = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];

    // Digest metrics
    $stmt = $readConnection->prepare(
        'SELECT
            COUNT(*) as total_sent,
            COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as opened,
            COUNT(CASE WHEN clicked_artist_ids IS NOT NULL THEN 1 END) as clicked
         FROM niko_discovery_digests
         WHERE status = "sent" AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    $stmt->execute();
    $digestStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $digestOpenRate = $digestStats['total_sent'] > 0 ? round($digestStats['opened'] / $digestStats['total_sent'] * 100, 1) : 0;

    // Average affinity score
    $stmt = $readConnection->prepare(
        'SELECT AVG(affinity_score) as avg_score FROM user_artist_affinity'
    );
    $stmt->execute();
    $avgAffinityScore = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_score'] ?? 0, 2);

    // Similarity computations (last 24h)
    $stmt = $readConnection->prepare(
        'SELECT COUNT(*) as recent_computations FROM artist_similarity WHERE computed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $stmt->execute();
    $recentComputations = $stmt->fetch(PDO::FETCH_ASSOC)['recent_computations'];

    // Daily recommendation requests (last 7 days)
    $stmt = $readConnection->prepare(
        'SELECT DATE(created_at) as date, COUNT(*) as requests
         FROM discovery_recommendations
         WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC'
    );
    $stmt->execute();
    $dailyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Genre affinity distribution
    $stmt = $readConnection->prepare(
        'SELECT genre_slug, COUNT(DISTINCT user_id) as user_count FROM user_genre_affinity GROUP BY genre_slug ORDER BY user_count DESC LIMIT 10'
    );
    $stmt->execute();
    $genreDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent activity log
    $stmt = $readConnection->prepare(
        'SELECT id, user_id, status, created_at FROM niko_discovery_digests ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Discovery Engine - Overview</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #333; margin-top: 0; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .metric-card h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: 600; text-transform: uppercase; }
        .metric-card .value { font-size: 32px; font-weight: bold; color: #2196F3; margin: 10px 0; }
        .metric-card .subtext { font-size: 12px; color: #999; }
        .charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .chart { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .chart h3 { margin: 0 0 20px 0; color: #333; }
        .chart-placeholder { height: 300px; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status.sent { background: #e8f5e9; color: #2e7d32; }
        .status.failed { background: #ffebee; color: #c62828; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/discovery/overview.php" style="font-weight: bold;">Overview</a>
            <a href="/admin/discovery/recommendations.php">Recommendations</a>
            <a href="/admin/discovery/similarities.php">Similarities</a>
            <a href="/admin/discovery/digests.php">Digests</a>
            <a href="/admin/discovery/affinities.php">Affinities</a>
        </div>

        <h1>Discovery Engine - Overview</h1>

        <?php if (isset($error)): ?>
            <div style="color: red; margin: 20px 0;">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="metrics">
            <div class="metric-card">
                <h3>Active Users</h3>
                <div class="value"><?php echo number_format($activeUsersWithRecs); ?></div>
                <div class="subtext">With cached recommendations</div>
            </div>

            <div class="metric-card">
                <h3>Digest Open Rate</h3>
                <div class="value"><?php echo $digestOpenRate; ?>%</div>
                <div class="subtext">Last 30 days</div>
            </div>

            <div class="metric-card">
                <h3>Avg Affinity Score</h3>
                <div class="value"><?php echo $avgAffinityScore; ?></div>
                <div class="subtext">All user-artist pairs</div>
            </div>

            <div class="metric-card">
                <h3>Similarity Updates</h3>
                <div class="value"><?php echo number_format($recentComputations); ?></div>
                <div class="subtext">Last 24 hours</div>
            </div>
        </div>

        <div class="charts">
            <div class="chart">
                <h3>Daily Recommendation Requests (7 days)</h3>
                <div class="chart-placeholder">
                    Chart: <?php echo count($dailyRequests); ?> days of data
                </div>
                <table style="margin-top: 10px; font-size: 12px;">
                    <?php foreach ($dailyRequests as $day): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($day['date']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($day['requests']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="chart">
                <h3>Top Genres (User Affinity)</h3>
                <div class="chart-placeholder">
                    Genre distribution
                </div>
                <table style="margin-top: 10px; font-size: 12px;">
                    <?php foreach ($genreDistribution as $genre): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($genre['genre_slug']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($genre['user_count']); ?> users</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="chart" style="margin-bottom: 40px;">
            <h3>Recent Activity Log</h3>
            <table>
                <thead>
                    <tr>
                        <th>Digest ID</th>
                        <th>User ID</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td>#<?php echo $activity['id']; ?></td>
                            <td><?php echo $activity['user_id']; ?></td>
                            <td><span class="status <?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span></td>
                            <td><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
