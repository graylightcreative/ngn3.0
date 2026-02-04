<?php

/**
 * Discovery Engine - Admin Digests Dashboard
 * Manage Niko's Discovery email digests
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use NGN\Lib\Admin\AdminAuth;
use NGN\Config;
use NGN\Lib\Discovery\NikoDiscoveryService;
use NGN\Lib\Database\ConnectionFactory;
use PDO;

AdminAuth::requireAdmin();

$config = Config::getInstance();
$readConnection = ConnectionFactory::read();
$userId = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null;
$digestWeek = $_GET['week'] ?? date('Y-W');

$user = null;
$digestPreview = [];
$weeklyStats = [];
$error = null;
$message = null;

// Get weekly statistics
try {
    $stmt = $readConnection->prepare(
        'SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN status = "sent" THEN 1 END) as sent,
            COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
            COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as opened,
            COUNT(CASE WHEN clicked_artist_ids IS NOT NULL THEN 1 END) as clicked
         FROM `ngn_2025`.`niko_discovery_digests`
         WHERE digest_week = ?'
    );
    $stmt->execute([$digestWeek]);
    $weeklyStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}

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
        // Handle test send
        if ($action === 'send_test') {
            try {
                $nikoService = new NikoDiscoveryService($config);
                $success = $nikoService->sendDigest($userId);
                $message = $success ? 'Test digest sent successfully' : 'Failed to send digest';
            } catch (Exception $e) {
                $error = 'Error sending: ' . $e->getMessage();
            }
        }

        // Get digest preview
        try {
            $stmt = $readConnection->prepare(
                'SELECT * FROM `ngn_2025`.`niko_discovery_digests` WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$userId]);
            $digestPreview = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get recent digests
try {
    $stmt = $readConnection->prepare(
        'SELECT id, user_id, digest_week, status, sent_at, opened_at FROM `ngn_2025`.`niko_discovery_digests` ORDER BY created_at DESC LIMIT 50'
    );
    $stmt->execute();
    $recentDigests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Discovery Engine - Admin Digests</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; margin-top: 0; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #2196F3; }
        .search-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .search-box input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .search-box button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat .label { font-size: 12px; color: #999; text-transform: uppercase; }
        .stat .value { font-size: 24px; font-weight: bold; color: #2196F3; margin-top: 5px; }
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
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status.sent { background: #e8f5e9; color: #2e7d32; }
        .status.failed { background: #ffebee; color: #c62828; }
        .status.pending { background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="/admin/discovery/overview.php">Overview</a>
            <a href="/admin/discovery/recommendations.php">Recommendations</a>
            <a href="/admin/discovery/similarities.php">Similarities</a>
            <a href="/admin/discovery/digests.php" style="font-weight: bold;">Digests</a>
            <a href="/admin/discovery/affinities.php">Affinities</a>
        </div>

        <h1>Discovery Engine - Niko's Discovery Digests</h1>

        <div class="stats">
            <div class="stat">
                <div class="label">Total Sent</div>
                <div class="value"><?php echo number_format($weeklyStats['sent'] ?? 0); ?></div>
            </div>
            <div class="stat">
                <div class="label">Open Rate</div>
                <div class="value"><?php echo $weeklyStats['sent'] > 0 ? round($weeklyStats['opened'] / $weeklyStats['sent'] * 100, 0) : 0; ?>%</div>
            </div>
            <div class="stat">
                <div class="label">Click Rate</div>
                <div class="value"><?php echo $weeklyStats['sent'] > 0 ? round($weeklyStats['clicked'] / $weeklyStats['sent'] * 100, 0) : 0; ?>%</div>
            </div>
            <div class="stat">
                <div class="label">Failed</div>
                <div class="value" style="color: #f44336;"><?php echo number_format($weeklyStats['failed'] ?? 0); ?></div>
            </div>
        </div>

        <div class="search-box">
            <form method="get">
                <input type="number" name="user_id" placeholder="Enter User ID for digest preview" value="<?php echo htmlspecialchars($userId ?? ''); ?>">
                <button type="submit">Preview Digest</button>
            </form>
        </div>

        <?php if (isset($message)): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($user && $digestPreview): ?>
            <div class="card">
                <h2>Digest Preview - User #<?php echo $user['id']; ?></h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Week:</strong> <?php echo htmlspecialchars($digestPreview['digest_week']); ?></p>
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($digestPreview['subject_line']); ?></p>
                <p><strong>Status:</strong> <span class="status <?php echo $digestPreview['status']; ?>"><?php echo ucfirst($digestPreview['status']); ?></span></p>

                <?php if ($digestPreview['featured_artists']): ?>
                    <h3>Featured Artists:</h3>
                    <?php $artists = json_decode($digestPreview['featured_artists'], true) ?? []; ?>
                    <?php foreach ($artists as $artist): ?>
                        <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin: 10px 0;">
                            <strong><?php echo htmlspecialchars($artist['artist_name'] ?? 'Unknown'); ?></strong>
                            <br>Reason: <?php echo htmlspecialchars($artist['reason'] ?? 'N/A'); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post" style="margin-top: 15px;">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <input type="hidden" name="action" value="send_test">
                    <button type="submit" class="button">Send Test Digest</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Recent Digests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Digest ID</th>
                        <th>User ID</th>
                        <th>Week</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Opened</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDigests as $digest): ?>
                        <tr>
                            <td>#<?php echo $digest['id']; ?></td>
                            <td><a href="?user_id=<?php echo $digest['user_id']; ?>" style="color: #2196F3;"><?php echo $digest['user_id']; ?></a></td>
                            <td><?php echo htmlspecialchars($digest['digest_week']); ?></td>
                            <td><span class="status <?php echo $digest['status']; ?>"><?php echo ucfirst($digest['status']); ?></span></td>
                            <td><?php echo $digest['sent_at'] ? date('M d H:i', strtotime($digest['sent_at'])) : 'N/A'; ?></td>
                            <td><?php echo $digest['opened_at'] ? date('M d H:i', strtotime($digest['opened_at'])) : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
