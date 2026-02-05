<?php
/**
 * Venue Dashboard - Analytics
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Analytics';
$currentPage = 'analytics';

$stats = [
    'total_shows' => 0,
    'shows_this_month' => 0,
    'total_posts' => 0,
    'total_videos' => 0,
    'video_views' => 0,
];

$connectedPlatforms = [];
$socialStats = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        
        // Shows stats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE venue_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['total_shows'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE venue_id = ? AND starts_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$entity['id']]);
        $stats['shows_this_month'] = (int)$stmt->fetchColumn();
        
        // Content stats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`posts` WHERE author_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['total_posts'] = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(view_count), 0) FROM `ngn_2025`.`videos` WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $stats['total_videos'] = (int)$row[0];
        $stats['video_views'] = (int)$row[1];
        
        // Connected platforms
        $stmt = $pdo->prepare("SELECT provider, access_token FROM `ngn_2025`.`oauth_tokens` WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connectedPlatforms[$r['provider']] = $r;
        }
        
        // Get latest analytics snapshots
                $stmt = $pdo->prepare("
                    SELECT provider, metric, value, period_end
                    FROM `ngn_2025`.`analytics_snapshots`
                    WHERE entity_type = 'venue' AND entity_id = ?
                    ORDER BY period_end DESC
                    LIMIT 20
                ");        $stmt->execute([$entity['id']]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $socialStats[$r['provider']][$r['metric']] = $r['value'];
        }
    } catch (PDOException $e) {}
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Analytics</h1>
        <p class="page-subtitle">Track your venue's performance</p>
    </header>
    
    <div class="page-content">
        <?php if (!$entity): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Set up your venue profile first. <a href="profile.php">Set up profile →</a></div>
        <?php else: ?>
        
        <!-- Overview Stats -->
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-label">Total Shows</div>
                <div class="stat-value"><?= $stats['total_shows'] ?></div>
                <div class="stat-change">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Month</div>
                <div class="stat-value" style="color: var(--brand);"><?= $stats['shows_this_month'] ?></div>
                <div class="stat-change">Shows</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Posts</div>
                <div class="stat-value"><?= $stats['total_posts'] ?></div>
                <div class="stat-change">Published</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Video Views</div>
                <div class="stat-value" style="color: var(--accent);"><?= number_format($stats['video_views']) ?></div>
                <div class="stat-change"><?= $stats['total_videos'] ?> videos</div>
            </div>
        </div>
        
        <!-- Social Analytics -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Social Analytics</h2>
                <a href="connections.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Manage Connections</a>
            </div>
            
            <?php if (empty($connectedPlatforms)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-share" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>Connect your social accounts to see analytics here.</p>
                <a href="connections.php" class="btn btn-primary" style="margin-top: 16px;"><i class="bi bi-link-45deg"></i> Connect Accounts</a>
            </div>
            <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($connectedPlatforms as $provider => $token): 
                    $providerStats = $socialStats[$provider] ?? [];
                    $colors = ['facebook' => '#1877f2', 'instagram' => '#e4405f', 'tiktok' => '#000', 'youtube' => '#ff0000'];
                    $icons = ['facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'tiktok' => 'bi-tiktok', 'youtube' => 'bi-youtube'];
                ?>
                <div style="padding: 20px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: <?= $colors[$provider] ?? '#666' ?>20; display: flex; align-items: center; justify-content: center;">
                            <i class="<?= $icons[$provider] ?? 'bi-globe' ?>" style="font-size: 20px; color: <?= $colors[$provider] ?? '#666' ?>;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?= ucfirst($provider) ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);">Connected</div>
                        </div>
                    </div>
                    <div class="grid grid-2" style="gap: 12px;">
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?= number_format($providerStats['followers'] ?? 0) ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);">Followers</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700;"><?= number_format($providerStats['engagement'] ?? 0) ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);">Engagement</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Geographic Reach -->
        <div class="card">
            <div class="card-header"><h2 class="card-title">Geographic Reach</h2></div>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-geo-alt" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>Geographic analytics coming soon.</p>
                <p style="font-size: 13px;">Your shows will appear in local event pools based on your venue location.</p>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>
</body>
</html>

