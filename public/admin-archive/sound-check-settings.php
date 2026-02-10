<?php
/**
 * Sound Check Notification Settings
 *
 * Admin page for managing artist Sound Check notification preferences.
 * Allows filtering and bulk management of notification settings.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Retention\SoundCheckNotificationService;
use NGN\Lib\Retention\PushNotificationService;

$pdo = NGN\Lib\DB\ConnectionFactory::write($config);
$pushService = new PushNotificationService($pdo);
$soundCheckService = new SoundCheckNotificationService($pdo, $pushService);

// Handle updates
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['artist_id'])) {
    $artistId = (int)$_POST['artist_id'];
    $updates = [
        'notifications_enabled' => isset($_POST['enabled']) ? 1 : 0,
        'notify_on_started' => isset($_POST['on_started']) ? 1 : 0,
        'notify_on_completed' => isset($_POST['on_completed']) ? 1 : 0,
        'notify_on_failed' => isset($_POST['on_failed']) ? 1 : 0,
        'ios_haptic_enabled' => isset($_POST['haptic']) ? 1 : 0,
    ];
    
    if ($soundCheckService->updatePreferences($artistId, $updates)) {
        $message = '<div class="alert alert-success">Preferences updated for Artist #' . $artistId . '</div>';
    } else {
        $message = '<div class="alert alert-danger">Failed to update preferences</div>';
    }
}

// Fetch all artists with their preferences
$stmt = $pdo->prepare("
    SELECT a.id, a.name, p.* 
    FROM `ngn_2025`.`artists` a
    LEFT JOIN `ngn_2025`.`sound_check_preferences` p ON a.id = p.artist_id
    ORDER BY a.name ASC
    LIMIT 100
");
$stmt->execute();
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sound Check Notification Settings | NGN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { margin-top: 2rem; }
        .table-container { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sound Check Notification Settings</h1>
        <p class="text-muted">Manage notification preferences for iOS Sound Check events.</p>
        
        <?= $message ?>

        <div class="table-container mt-4">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Artist</th>
                        <th>Enabled</th>
                        <th>On Started</th>
                        <th>On Completed</th>
                        <th>On Failed</th>
                        <th>iOS Haptic</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artists as $artist): ?>
                    <form method="POST">
                        <input type="hidden" name="artist_id" value="<?= $artist['id'] ?>">
                        <tr>
                            <td><strong><?= htmlspecialchars($artist['name']) ?></strong><br><small class="text-muted">ID: <?= $artist['id'] ?></small></td>
                            <td><input type="checkbox" name="enabled" <?= ($artist['notifications_enabled'] ?? 1) ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="on_started" <?= ($artist['notify_on_started'] ?? 0) ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="on_completed" <?= ($artist['notify_on_completed'] ?? 1) ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="on_failed" <?= ($artist['notify_on_failed'] ?? 1) ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="haptic" <?= ($artist['ios_haptic_enabled'] ?? 1) ? 'checked' : '' ?>></td>
                            <td><button type="submit" class="btn btn-sm btn-primary">Save</button></td>
                        </tr>
                    </form>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
