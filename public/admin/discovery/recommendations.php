<?php

/**
 * Discovery Engine - Admin Recommendations Dashboard
 * View and debug recommendations for specific users
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Config;
use NGN\Lib\Discovery\DiscoveryEngineService;
use NGN\Lib\Discovery\AffinityService;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

AdminAuth::requireAdmin();

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null;

$user = null;
$affinities = [];
$recommendations = [];
$error = null;

if ($userId && is_numeric($userId)) {
    $userId = (int) $userId;

    // Get user info
    try {
        $stmt = $readConnection->prepare('SELECT id, email, display_name AS name FROM `ngn_2025`.`users` WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if ($user) {
        // Handle actions
        if ($action === 'refresh_cache') {
            $discoveryEngine = new DiscoveryEngineService($config);
            $discoveryEngine->invalidateCache($userId);
            $message = 'Cache invalidated successfully';
        }

        // Get top affinities
        try {
            $affinityService = new AffinityService($config);
            $affinities = $affinityService->getUserTopArtistAffinities($userId, 10);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // Get recommendations
        try {
            $discoveryEngine = new DiscoveryEngineService($config);
            $recommendations = $discoveryEngine->getRecommendedArtists($userId, 10);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Discovery Engine - Admin Recommendations</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; margin-top: 0; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #2196F3; }
        .search-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .search-box input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .search-box button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 1px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .button { padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .button:hover { background: #1976D2; }
        .error { color: #c62828; margin: 15px 0; }
        .success { color: #2e7d32; margin: 15px 0; }
        .score-high { color: #2e7d32; font-weight: bold; }
        .score-medium { color: #f57f17; font-weight: bold; }
        .score-low { color: #c62828; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/discovery/overview.php">Overview</a>
            <a href="/admin/discovery/recommendations.php" style="font-weight: bold;">Recommendations</a>
            <a href="/admin/discovery/similarities.php">Similarities</a>
            <a href="/admin/discovery/digests.php">Digests</a>
            <a href="/admin/discovery/affinities.php">Affinities</a>
        </div>

        <h1>Discovery Engine - Recommendations Debug</h1>

        <div class="search-box">
            <form method="get">
                <input type="number" name="user_id" placeholder="Enter User ID" value="<?php echo htmlspecialchars($userId ?? ''); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if (isset($message)): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="card">
                <h2>User #<?php echo $user['id']; ?> - <?php echo htmlspecialchars($user['email']); ?></h2>
                <p><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></p>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <input type="hidden" name="action" value="refresh_cache">
                    <button type="submit" class="button">Refresh Recommendation Cache</button>
                </form>
            </div>

            <div class="card">
                <h2>Top Artist Affinities</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Artist ID</th>
                            <th>Affinity Score</th>
                            <th>Total Sparks</th>
                            <th>Engagements</th>
                            <th>Following</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($affinities)): ?>
                            <tr><td colspan="5">No affinities found</td></tr>
                        <?php else: ?>
                            <?php foreach ($affinities as $aff): ?>
                                <tr>
                                    <td><?php echo $aff['artist_id']; ?></td>
                                    <td><span class="<?php echo $aff['affinity_score'] > 70 ? 'score-high' : ($aff['affinity_score'] > 40 ? 'score-medium' : 'score-low'); ?>">
                                        <?php echo round($aff['affinity_score'], 2); ?>
                                    </span></td>
                                    <td><?php echo number_format($aff['total_sparks']); ?></td>
                                    <td><?php echo $aff['total_engagements']; ?></td>
                                    <td><?php echo $aff['is_following'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Generated Recommendations</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Artist ID</th>
                            <th>Score</th>
                            <th>Reason</th>
                            <th>Emerging</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recommendations)): ?>
                            <tr><td colspan="4">No recommendations generated</td></tr>
                        <?php else: ?>
                            <?php foreach ($recommendations as $rec): ?>
                                <tr>
                                    <td><?php echo $rec['artist_id']; ?></td>
                                    <td><span class="<?php echo ($rec['score'] ?? $rec['affinity_score'] ?? 0) > 70 ? 'score-high' : 'score-medium'; ?>">
                                        <?php echo round($rec['score'] ?? $rec['affinity_score'] ?? 0, 2); ?>
                                    </span></td>
                                    <td><?php echo htmlspecialchars($rec['reason'] ?? 'N/A'); ?></td>
                                    <td><?php echo $rec['is_emerging'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
