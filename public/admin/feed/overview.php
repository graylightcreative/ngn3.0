<?php

/**
 * Admin Dashboard: Social Feed Algorithm Overview
 *
 * KPI cards: Posts in each tier, Avg EV score, Trending posts count, Seed engagement rate
 * Charts: Hourly tier transitions, EV distribution histogram, Tier 3 trending velocity
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Feed\SocialFeedAlgorithmService;
use NGN\Lib\Feed\TrendingFeedService;

// Verify admin auth
if (!isset($_SESSION['admin_user_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$config = Config::getInstance();
$read = ConnectionFactory::read();
$feedAlgorithm = new SocialFeedAlgorithmService($config);
$trending = new TrendingFeedService($config);

// Fetch KPIs
$kpiStmt = $read->prepare("
    SELECT
        COUNT(*) as total_posts,
        SUM(CASE WHEN current_tier = 'tier1' THEN 1 ELSE 0 END) as tier1_count,
        SUM(CASE WHEN current_tier = 'tier2' THEN 1 ELSE 0 END) as tier2_count,
        SUM(CASE WHEN current_tier = 'tier3' THEN 1 ELSE 0 END) as tier3_count,
        SUM(CASE WHEN current_tier = 'seed' THEN 1 ELSE 0 END) as seed_count,
        SUM(CASE WHEN expired_at IS NOT NULL THEN 1 ELSE 0 END) as expired_count,
        AVG(ev_score_current) as avg_ev,
        MAX(ev_score_current) as max_ev,
        MIN(ev_score_current) as min_ev
    FROM post_visibility_state
    WHERE TIMESTAMPDIFF(HOUR, created_at, NOW()) < 48
");
$kpiStmt->execute();
$kpi = $kpiStmt->fetch(\PDO::FETCH_ASSOC);

// Trending count
$trendingStmt = $read->prepare("
    SELECT COUNT(*) as trending_count FROM global_trending_queue WHERE status = 'trending'
");
$trendingStmt->execute();
$trendingData = $trendingStmt->fetch(\PDO::FETCH_ASSOC);

// Seed engagement rates (last 20 posts)
$seedStmt = $read->prepare("
    SELECT
        AVG(CASE WHEN fsv.user_engaged = 1 THEN 1 ELSE 0 END) * 100 as avg_seed_engagement
    FROM feed_seed_visibility fsv
    WHERE fsv.shown_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$seedStmt->execute();
$seedData = $seedStmt->fetch(\PDO::FETCH_ASSOC);

// Tier transitions (last hour)
$transitionsStmt = $read->prepare("
    SELECT
        event_data,
        COUNT(*) as transition_count
    FROM feed_events_log
    WHERE event_type = 'tier_transition'
    AND occurred_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY JSON_EXTRACT(event_data, '$.tier_to')
");
$transitionsStmt->execute();
$transitions = $transitionsStmt->fetchAll(\PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed Algorithm - Admin Overview</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Social Feed Algorithm Overview</h1>
            <p class="generated-at">Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </header>

        <!-- KPI Cards -->
        <section class="kpi-section">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <h3>Active Posts</h3>
                    <div class="kpi-value"><?php echo (int) $kpi['total_posts']; ?></div>
                    <p class="kpi-subtitle">Posts in last 48h</p>
                </div>

                <div class="kpi-card">
                    <h3>Tier 1 (Core Circle)</h3>
                    <div class="kpi-value"><?php echo (int) $kpi['tier1_count']; ?></div>
                    <p class="kpi-subtitle"><?php echo round(($kpi['tier1_count'] / max(1, $kpi['total_posts'])) * 100, 1); ?>% of posts</p>
                </div>

                <div class="kpi-card">
                    <h3>Tier 2 (Affinity)</h3>
                    <div class="kpi-value"><?php echo (int) $kpi['tier2_count']; ?></div>
                    <p class="kpi-subtitle"><?php echo round(($kpi['tier2_count'] / max(1, $kpi['total_posts'])) * 100, 1); ?>% of posts</p>
                </div>

                <div class="kpi-card">
                    <h3>Tier 3 (Trending)</h3>
                    <div class="kpi-value"><?php echo (int) $trendingData['trending_count']; ?></div>
                    <p class="kpi-subtitle">Global trending posts</p>
                </div>

                <div class="kpi-card">
                    <h3>Avg EV Score</h3>
                    <div class="kpi-value"><?php echo round((float) $kpi['avg_ev'], 2); ?></div>
                    <p class="kpi-subtitle">Range: <?php echo round((float) $kpi['min_ev'], 0); ?> - <?php echo round((float) $kpi['max_ev'], 0); ?></p>
                </div>

                <div class="kpi-card">
                    <h3>Seed Engagement</h3>
                    <div class="kpi-value"><?php echo round((float) $seedData['avg_seed_engagement'], 1); ?>%</div>
                    <p class="kpi-subtitle">Last 24h seed recipients</p>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="chart-container">
                <h3>Tier Distribution</h3>
                <canvas id="tierChart"></canvas>
            </div>

            <div class="chart-container">
                <h3>Tier Transitions (Last Hour)</h3>
                <div class="transitions-list">
                    <?php if (empty($transitions)): ?>
                        <p class="no-data">No tier transitions in last hour</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($transitions as $t): ?>
                                <li>
                                    <?php
                                    $data = json_decode($t['event_data'], true);
                                    echo ucfirst($data['tier_to'] ?? 'unknown') . ": " . $t['transition_count'] . " transitions";
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="actions-section">
            <h3>Quick Actions</h3>
            <a href="/admin/feed/post-visibility.php" class="btn btn-primary">View Post Visibility</a>
            <a href="/admin/feed/trending.php" class="btn btn-primary">Manage Trending</a>
            <a href="/admin/feed/seed-visibility.php" class="btn btn-primary">Seed Analytics</a>
            <a href="/admin/feed/anti-payola-audit.php" class="btn btn-warning">Anti-Payola Audit</a>
        </section>
    </div>

    <script>
        // Tier distribution chart
        const tierCtx = document.getElementById('tierChart').getContext('2d');
        new Chart(tierCtx, {
            type: 'doughnut',
            data: {
                labels: ['Tier 1', 'Tier 2', 'Tier 3', 'Seed', 'Expired'],
                datasets: [{
                    data: [
                        <?php echo (int) $kpi['tier1_count']; ?>,
                        <?php echo (int) $kpi['tier2_count']; ?>,
                        <?php echo (int) $trendingData['trending_count']; ?>,
                        <?php echo (int) $kpi['seed_count']; ?>,
                        <?php echo (int) $kpi['expired_count']; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#9E9E9E']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>
