<?php

/**
 * Post Analytics Dashboard
 * Admin view for analyzing post engagement sources and fraud detection
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Config;
use NGN\Lib\Analytics\PostAnalyticsService;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

AdminAuth::requireAdmin();

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$analyticsService = new PostAnalyticsService($config);

$postId = $_GET['post_id'] ?? $_POST['post_id'] ?? null;
$action = $_POST['action'] ?? null;

$post = null;
$analytics = null;
$breakdown = [];
$fraudFlags = [];
$dailyAnalytics = [];
$topPosts = [];
$error = null;
$message = null;

// Get top posts by suspicious activity
try {
    $stmt = $readConnection->prepare(
        'SELECT p.id, p.title, pea.fraud_suspicion_score, pea.authentication_rate, pea.total_authenticated_engagement, pea.total_anonymous_engagement
         FROM `ngn_2025`.`posts` p
         JOIN `ngn_2025`.`post_engagement_analytics` pea ON p.id = pea.post_id
         WHERE pea.fraud_suspicion_score > 0.3
         ORDER BY pea.fraud_suspicion_score DESC
         LIMIT 20'
    );
    $stmt->execute();
    $topPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}

if ($postId && is_numeric($postId)) {
    $postId = (int) $postId;

    // Get post info
    try {
        $stmt = $readConnection->prepare(
            'SELECT id, title, author_id AS creator_id, status, created_at FROM `ngn_2025`.`posts` WHERE id = ?'
        );
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if ($post) {
        // Handle actions
        if ($action === 'detect_duplicates') {
            try {
                $analyticsService->detectDuplicateEngagements($postId);
                $analyticsService->updatePostAnalytics($postId);
                $message = 'Duplicate detection completed';
            } catch (Exception $e) {
                $error = 'Error detecting duplicates: ' . $e->getMessage();
            }
        }

        // Get analytics
        try {
            $analytics = $analyticsService->getPostAnalytics($postId);
            $breakdown = $analyticsService->getEngagementSourceBreakdown($postId);
            $fraudFlags = $analyticsService->getFraudFlags($postId);

            // Get daily analytics for last 30 days
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $dailyAnalytics = $analyticsService->getDailyAnalytics($postId, $startDate, $endDate);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Post Analytics Dashboard</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; margin-top: 0; }
        .search-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .search-box input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .search-box button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .metric { background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #2196F3; }
        .metric .label { font-size: 12px; color: #999; text-transform: uppercase; }
        .metric .value { font-size: 24px; font-weight: bold; color: #333; margin-top: 5px; }
        .metric .subtext { font-size: 12px; color: #999; margin-top: 5px; }
        .warning { border-left-color: #ff9800; }
        .danger { border-left-color: #f44336; }
        .success { border-left-color: #4caf50; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .button { padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .button:hover { background: #1976D2; }
        .error { color: #c62828; margin: 15px 0; padding: 10px; background: #ffebee; border-radius: 4px; }
        .success-msg { color: #2e7d32; margin: 15px 0; padding: 10px; background: #e8f5e9; border-radius: 4px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-high { background: #f44336; color: white; }
        .badge-medium { background: #ff9800; color: white; }
        .badge-low { background: #2196F3; color: white; }
        .bar { background: #eee; border-radius: 3px; height: 20px; display: inline-block; }
        .bar-fill { background: #2196F3; height: 100%; border-radius: 3px; display: flex; align-items: center; justify-content: flex-end; color: white; font-size: 11px; padding-right: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Post Analytics Dashboard</h1>

        <div class="search-box">
            <form method="get">
                <input type="number" name="post_id" placeholder="Enter Post ID" value="<?php echo htmlspecialchars($postId ?? ''); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="success-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($post): ?>
            <div class="card">
                <h2>Post #<?php echo $post['id']; ?> - <?php echo htmlspecialchars($post['title']); ?></h2>
                <p>Creator: <?php echo $post['creator_id']; ?> | Status: <?php echo htmlspecialchars($post['status']); ?> | Created: <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></p>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <input type="hidden" name="action" value="detect_duplicates">
                    <button type="submit" class="button">Detect Duplicate Engagements</button>
                </form>
            </div>

            <?php if ($analytics): ?>
                <div class="metrics">
                    <div class="metric">
                        <div class="label">Authenticated Engagement</div>
                        <div class="value"><?php echo number_format($analytics['total_authenticated_engagement']); ?></div>
                    </div>
                    <div class="metric">
                        <div class="label">Anonymous Engagement</div>
                        <div class="value"><?php echo number_format($analytics['total_anonymous_engagement']); ?></div>
                    </div>
                    <div class="metric <?php echo $analytics['authentication_rate'] < 50 ? 'warning' : 'success'; ?>">
                        <div class="label">Authentication Rate</div>
                        <div class="value"><?php echo round($analytics['authentication_rate'], 1); ?>%</div>
                    </div>
                    <div class="metric <?php echo $analytics['fraud_suspicion_score'] > 0.5 ? 'danger' : 'success'; ?>">
                        <div class="label">Fraud Suspicion Score</div>
                        <div class="value"><?php echo round($analytics['fraud_suspicion_score'], 3); ?></div>
                    </div>
                </div>

                <div class="card">
                    <h2>Engagement Breakdown</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Authenticated</th>
                                <th>Anonymous</th>
                                <th>Auth %</th>
                                <th>Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($breakdown['breakdown'] as $type => $data): ?>
                                <tr>
                                    <td><?php echo ucfirst($type); ?></td>
                                    <td><?php echo number_format($data['authenticated']); ?></td>
                                    <td><?php echo number_format($data['anonymous']); ?></td>
                                    <td>
                                        <?php
                                            $total = $data['authenticated'] + $data['anonymous'];
                                            $percent = $total > 0 ? round(($data['authenticated'] / $total) * 100, 0) : 0;
                                            echo $percent . '%';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="bar" style="width: 200px;">
                                            <div class="bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $percent > 60 ? '#4caf50' : ($percent > 30 ? '#ff9800' : '#f44336'); ?>">
                                                <?php echo $percent; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($fraudFlags) > 0): ?>
                    <div class="card">
                        <h2>Fraud Flags (<?php echo count($fraudFlags); ?>)</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Description</th>
                                    <th>Value</th>
                                    <th>Threshold</th>
                                    <th>Created</th>
                                    <th>Reviewed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fraudFlags as $flag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($flag['flag_type']); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower($flag['severity']); ?>"><?php echo ucfirst($flag['severity']); ?></span></td>
                                        <td><?php echo htmlspecialchars($flag['description']); ?></td>
                                        <td><?php echo round($flag['metric_value'], 2); ?></td>
                                        <td><?php echo round($flag['threshold_value'], 2); ?></td>
                                        <td><?php echo date('M d H:i', strtotime($flag['created_at'])); ?></td>
                                        <td><?php echo $flag['reviewed_at'] ? date('M d H:i', strtotime($flag['reviewed_at'])) : 'Not reviewed'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (count($dailyAnalytics) > 0): ?>
                    <div class="card">
                        <h2>Daily Analytics (Last 30 Days)</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Auth Views</th>
                                    <th>Anon Views</th>
                                    <th>Total Engagement</th>
                                    <th>Auth Rate</th>
                                    <th>Fraud Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($dailyAnalytics) as $daily): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($daily['date_key'])); ?></td>
                                        <td><?php echo number_format($daily['authenticated_views']); ?></td>
                                        <td><?php echo number_format($daily['anonymous_views']); ?></td>
                                        <td><?php echo number_format($daily['authenticated_engagements'] + $daily['anonymous_engagements']); ?></td>
                                        <td><?php echo round($daily['authentication_rate'], 1); ?>%</td>
                                        <td><?php echo round($daily['fraud_suspicion_score'], 3); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (count($topPosts) > 0): ?>
            <div class="card">
                <h2>Posts with Suspicious Activity</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Post ID</th>
                            <th>Title</th>
                            <th>Fraud Score</th>
                            <th>Auth Rate</th>
                            <th>Auth Engagement</th>
                            <th>Anon Engagement</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topPosts as $suspPost): ?>
                            <tr>
                                <td><?php echo $suspPost['id']; ?></td>
                                <td><?php echo htmlspecialchars(substr($suspPost['title'], 0, 60)); ?></td>
                                <td><span class="badge badge-<?php echo $suspPost['fraud_suspicion_score'] > 0.7 ? 'high' : ($suspPost['fraud_suspicion_score'] > 0.4 ? 'medium' : 'low'); ?>"><?php echo round($suspPost['fraud_suspicion_score'], 3); ?></span></td>
                                <td><?php echo round($suspPost['authentication_rate'], 1); ?>%</td>
                                <td><?php echo number_format($suspPost['total_authenticated_engagement']); ?></td>
                                <td><?php echo number_format($suspPost['total_anonymous_engagement']); ?></td>
                                <td><a href="?post_id=<?php echo $suspPost['id']; ?>" style="color: #2196F3; text-decoration: none;">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
