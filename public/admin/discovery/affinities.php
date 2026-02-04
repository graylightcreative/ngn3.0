<?php

/**
 * Discovery Engine - Admin Affinities Dashboard
 * View user affinity data and debug affinity calculations
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Config;
use NGN\Lib\Discovery\AffinityService;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

AdminAuth::requireAdmin();

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null;

$user = null;
$artistAffinities = [];
$genreAffinities = [];
$recentEngagements = [];
$error = null;
$message = null;

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
        // Handle manual recalculation
        if ($action === 'recalculate') {
            try {
                $affinityService = new AffinityService($config);
                $affinityService->recalculateAllAffinities($userId);
                $message = 'Affinities recalculated successfully';
            } catch (Exception $e) {
                $error = 'Error recalculating: ' . $e->getMessage();
            }
        }

        // Get artist affinities
        try {
            $affinityService = new AffinityService($config);
            $artistAffinities = $affinityService->getUserTopArtistAffinities($userId, 20);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // Get genre affinities
        try {
            $affinityService = new AffinityService($config);
            $genreAffinities = $affinityService->getUserGenreAffinities($userId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // Get recent engagements
        try {
            $stmt = $readConnection->prepare(
                'SELECT ce.id, ce.artist_id, ce.engagement_type AS type, ce.created_at FROM `ngn_2025`.`cdm_engagements` ce
                 WHERE ce.user_id = ?
                 ORDER BY ce.created_at DESC
                 LIMIT 20'
            );
            $stmt->execute([$userId]);
            $recentEngagements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Discovery Engine - Admin Affinities</title>
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
        .score-bar { width: 150px; height: 8px; background: #eee; border-radius: 4px; display: inline-block; overflow: hidden; margin-right: 10px; vertical-align: middle; }
        .score-fill { height: 100%; background: #2196F3; }
        .engagement-type { font-size: 11px; padding: 2px 6px; border-radius: 3px; display: inline-block; }
        .engagement-type.spark { background: #fff3e0; color: #e65100; }
        .engagement-type.engagement { background: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/discovery/overview.php">Overview</a>
            <a href="/admin/discovery/recommendations.php">Recommendations</a>
            <a href="/admin/discovery/similarities.php">Similarities</a>
            <a href="/admin/discovery/digests.php">Digests</a>
            <a href="/admin/discovery/affinities.php" style="font-weight: bold;">Affinities</a>
        </div>

        <h1>Discovery Engine - Affinity Analysis</h1>

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
                    <input type="hidden" name="action" value="recalculate">
                    <button type="submit" class="button">Manually Recalculate All Affinities</button>
                </form>
            </div>

            <div class="card">
                <h2>Artist Affinities (<?php echo count($artistAffinities); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Artist ID</th>
                            <th>Score</th>
                            <th>Sparks</th>
                            <th>Engagements</th>
                            <th>Following</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($artistAffinities)): ?>
                            <tr><td colspan="6">No affinities found</td></tr>
                        <?php else: ?>
                            <?php foreach ($artistAffinities as $aff): ?>
                                <tr>
                                    <td><?php echo $aff['artist_id']; ?></td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($aff['affinity_score']); ?>%"></div>
                                        </div>
                                        <?php echo round($aff['affinity_score'], 2); ?>
                                    </td>
                                    <td><?php echo number_format($aff['total_sparks']); ?></td>
                                    <td><?php echo $aff['total_engagements']; ?></td>
                                    <td><?php echo $aff['is_following'] ? '✓' : '—'; ?></td>
                                    <td><?php echo $aff['last_engagement_at'] ? date('M d H:i', strtotime($aff['last_engagement_at'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Genre Affinities (<?php echo count($genreAffinities); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Genre</th>
                            <th>Score</th>
                            <th>Artists</th>
                            <th>Total Engagements</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($genreAffinities)): ?>
                            <tr><td colspan="5">No genre affinities found</td></tr>
                        <?php else: ?>
                            <?php foreach ($genreAffinities as $genre): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($genre['genre_name']); ?></td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($genre['affinity_score']); ?>%"></div>
                                        </div>
                                        <?php echo round($genre['affinity_score'], 2); ?>
                                    </td>
                                    <td><?php echo $genre['artist_count']; ?></td>
                                    <td><?php echo number_format($genre['total_engagements']); ?></td>
                                    <td><?php echo $genre['last_engagement_at'] ? date('M d H:i', strtotime($genre['last_engagement_at'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Recent Engagement History (<?php echo count($recentEngagements); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Artist ID</th>
                            <th>Type</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentEngagements)): ?>
                            <tr><td colspan="4">No recent engagements</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentEngagements as $eng): ?>
                                <tr>
                                    <td>#<?php echo $eng['id']; ?></td>
                                    <td><?php echo $eng['artist_id']; ?></td>
                                    <td><span class="engagement-type <?php echo $eng['type']; ?>"><?php echo ucfirst($eng['type']); ?></span></td>
                                    <td><?php echo date('M d H:i:s', strtotime($eng['created_at'])); ?></td>
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
