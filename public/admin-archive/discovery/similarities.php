<?php

/**
 * Discovery Engine - Admin Similarities Dashboard
 * Manage artist similarity computations
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Config;
use NGN\Lib\Discovery\SimilarityService;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

AdminAuth::requireAdmin();

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$artistId = $_GET['artist_id'] ?? $_POST['artist_id'] ?? null;
$action = $_POST['action'] ?? null;

$artist = null;
$similarArtists = [];
$error = null;
$message = null;

if ($artistId && is_numeric($artistId)) {
    $artistId = (int) $artistId;

    // Get artist info
    try {
        $stmt = $readConnection->prepare('SELECT id, name, primary_genre FROM artists WHERE id = ?');
        $stmt->execute([$artistId]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if ($artist) {
        // Handle actions
        if ($action === 'recompute') {
            try {
                $similarityService = new SimilarityService($config);
                $similarityService->batchComputeSimilarities($artistId);
                $message = 'Similarity computations completed';
            } catch (Exception $e) {
                $error = 'Error recomputing: ' . $e->getMessage();
            }
        }

        // Get similar artists
        try {
            $similarityService = new SimilarityService($config);
            $similarArtists = $similarityService->getSimilarArtists($artistId, 20);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Discovery Engine - Admin Similarities</title>
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
        .button.danger { background: #f44336; }
        .button.danger:hover { background: #d32f2f; }
        .error { color: #c62828; margin: 15px 0; }
        .success { color: #2e7d32; margin: 15px 0; }
        .score-bar { width: 100px; height: 6px; background: #eee; border-radius: 3px; display: inline-block; overflow: hidden; }
        .score-fill { height: 100%; background: #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/discovery/overview.php">Overview</a>
            <a href="/admin/discovery/recommendations.php">Recommendations</a>
            <a href="/admin/discovery/similarities.php" style="font-weight: bold;">Similarities</a>
            <a href="/admin/discovery/digests.php">Digests</a>
            <a href="/admin/discovery/affinities.php">Affinities</a>
        </div>

        <h1>Discovery Engine - Similarities Management</h1>

        <div class="search-box">
            <form method="get">
                <input type="number" name="artist_id" placeholder="Enter Artist ID" value="<?php echo htmlspecialchars($artistId ?? ''); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if (isset($message)): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($artist): ?>
            <div class="card">
                <h2>Artist #<?php echo $artist['id']; ?> - <?php echo htmlspecialchars($artist['name']); ?></h2>
                <p>Genre: <?php echo htmlspecialchars($artist['primary_genre']); ?></p>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="artist_id" value="<?php echo $artist['id']; ?>">
                    <input type="hidden" name="action" value="recompute">
                    <button type="submit" class="button">Recompute Similarities</button>
                </form>
            </div>

            <div class="card">
                <h2>Similar Artists (<?php echo count($similarArtists); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Similar Artist</th>
                            <th>Similarity Score</th>
                            <th>Genre Match</th>
                            <th>Fanbase Overlap</th>
                            <th>Engagement Pattern</th>
                            <th>Shared Fans</th>
                            <th>Computed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($similarArtists)): ?>
                            <tr><td colspan="7">No similar artists found</td></tr>
                        <?php else: ?>
                            <?php foreach ($similarArtists as $similar): ?>
                                <tr>
                                    <td>
                                        <?php echo $similar['similar_artist_id']; ?>
                                    </td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($similar['similarity_score'] * 100); ?>%"></div>
                                        </div>
                                        <?php echo round($similar['similarity_score'], 3); ?>
                                    </td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($similar['genre_match_score'] * 100); ?>%"></div>
                                        </div>
                                        <?php echo round($similar['genre_match_score'], 3); ?>
                                    </td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($similar['fanbase_overlap_score'] * 100); ?>%"></div>
                                        </div>
                                        <?php echo round($similar['fanbase_overlap_score'], 3); ?>
                                    </td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($similar['engagement_pattern_score'] * 100); ?>%"></div>
                                        </div>
                                        <?php echo round($similar['engagement_pattern_score'], 3); ?>
                                    </td>
                                    <td><?php echo number_format($similar['shared_fans_count']); ?></td>
                                    <td style="font-size: 12px;"><?php echo date('M d H:i', strtotime($similar['computed_at'])); ?></td>
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
