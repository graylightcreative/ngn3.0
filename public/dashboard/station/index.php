<?php
/**
 * Station Dashboard - Home/Overview
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Dashboard';
$currentPage = 'home';

// Fetch stats
$stats = [
    'total_spins' => 0,
    'spins_this_week' => 0,
    'unique_artists' => 0,
    'ranking' => null,
    'score' => 0,
];
$recentSpins = [];

if ($entity) {
    try {
        // Use centralized connection pool for spins database
        $spinsPdo = dashboard_pdo_spins();

        // Combined spins queries using centralized connection
        $stationId = $entity['id'];
        $spinStats = dashboard_cached_query(
            "station_spins_stats_{$stationId}",
            function() use ($spinsPdo, $stationId) {
                $stmt = $spinsPdo->prepare("
                    SELECT
                        COUNT(*) as total_spins,
                        COUNT(DISTINCT artist_name) as unique_artists,
                        SUM(CASE WHEN played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as spins_this_week
                    FROM station_spins
                    WHERE station_id = ?
                ");
                $stmt->execute([$stationId]);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            },
            300 // Cache for 5 minutes
        );

        $stats['total_spins'] = (int)($spinStats['total_spins'] ?? 0);
        $stats['spins_this_week'] = (int)($spinStats['spins_this_week'] ?? 0);
        $stats['unique_artists'] = (int)($spinStats['unique_artists'] ?? 0);

        // Recent spins
        $stmt = $spinsPdo->prepare("SELECT * FROM station_spins WHERE station_id = ? ORDER BY played_at DESC LIMIT 5");
        $stmt->execute([$stationId]);
        $recentSpins = $stmt->fetchAll() ?: [];

        // Get ranking from main DB (not cached - should be fresh)
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT score, ranking FROM `ngn_2025`.`entity_scores` WHERE entity_type = 'station' AND entity_id = ?");
        $stmt->execute([$stationId]);
        $scoreRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($scoreRow) {
            $stats['score'] = (float)($scoreRow['score'] ?? 0);
            $stats['ranking'] = (int)($scoreRow['ranking'] ?? null);
        }

    } catch (PDOException $e) {
        error_log('Station dashboard error: ' . $e->getMessage());
        // Tables may not exist
    }
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Welcome back<?= $entity && !empty($entity['name']) ? ', ' . htmlspecialchars($entity['name']) : '' ?>!</h1>
        <p class="page-subtitle">Manage your station and submit spins</p>
    </header>
    
    <div class="page-content">
        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Profile Not Found</strong> - Your station profile hasn't been migrated to NGN 2.0 yet.
            <a href="profile.php">Set up your profile →</a>
        </div>
        <?php endif; ?>

        <!-- Upgrade Notification Banner -->
        <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 20px 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;"><i class="bi bi-star-fill" style="color: #fbbf24; margin-right: 8px;"></i>Unlock More Features</div>
                <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                    Upgrade to <strong>Pro</strong> for advanced analytics and spin tracking, or <strong>Premium</strong> for API access and custom branding.
                </p>
            </div>
            <div style="display: flex; gap: 12px; white-space: nowrap; flex-shrink: 0;">
                <a href="/dashboard/station/tiers.php" style="display: inline-flex; align-items: center; gap: 8px; background: var(--brand); color: #000; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.3s; text-align: center;">
                    <i class="bi bi-arrow-up-right"></i> Upgrade Now
                </a>
                <a href="/?view=pricing" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.2); color: var(--text-primary); padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 255, 255, 0.3); cursor: pointer; transition: all 0.3s;">
                    Compare Plans
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-label">Station Ranking</div>
                <div class="stat-value" style="color: var(--accent);"><?= $stats['ranking'] ? '#' . number_format($stats['ranking']) : '—' ?></div>
                <div class="stat-change">NGN Station Charts</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Station Score</div>
                <div class="stat-value" style="color: var(--brand);"><?= number_format($stats['score'], 1) ?></div>
                <div class="stat-change">Based on activity</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spins</div>
                <div class="stat-value"><?= number_format($stats['total_spins']) ?></div>
                <div class="stat-change">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Week</div>
                <div class="stat-value"><?= number_format($stats['spins_this_week']) ?></div>
                <div class="stat-change"><?= number_format($stats['unique_artists']) ?> artists</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="spins.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-broadcast" style="font-size: 24px; color: var(--brand);"></i>
                    <span>Log Spin</span>
                </a>
                <a href="spins.php" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-list-ul" style="font-size: 24px; color: var(--accent);"></i>
                    <span>View Spins</span>
                </a>
                <a href="videos.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-camera-video" style="font-size: 24px; color: #a855f7;"></i>
                    <span>Add Video</span>
                </a>
                <a href="profile.php" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-pencil" style="font-size: 24px; color: #f59e0b;"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>
        
        <!-- Submit Spins CTA -->
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%); border-color: var(--brand);">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--brand); display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-broadcast" style="font-size: 32px; color: #000;"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 4px;">Submit Your Spins</h3>
                    <p style="font-size: 14px; color: var(--text-secondary); margin: 0;">
                        Log the songs you play to help artists climb the NGN charts. Your spins directly impact artist rankings!
                    </p>
                </div>
                <a href="spins.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Log Spin
                </a>
            </div>
        </div>
        
        <!-- Recent Spins -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Spins</h2>
                <div style="display: flex; gap: 8px;">
                    <a href="spins.php?action=upload" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-upload"></i> CSV</a>
                    <a href="spins.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</a>
                </div>
            </div>

            <?php if (empty($recentSpins)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-broadcast" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No spins logged yet. Start submitting!</p>
                <div style="display: flex; gap: 12px; justify-content: center; margin-top: 16px;">
                    <a href="spins.php?action=upload" class="btn btn-secondary">
                        <i class="bi bi-upload"></i> Bulk Upload CSV
                    </a>
                    <a href="spins.php?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Log First Spin
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($recentSpins as $spin): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-primary); border-radius: 6px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-music-note" style="color: #000;"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($spin['artist_name'] ?? 'Unknown') ?></div>
                        <div style="font-size: 13px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($spin['song_title'] ?? '') ?></div>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); flex-shrink: 0;">
                        <?= date('M j, g:i A', strtotime($spin['played_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Station Info -->
        <?php if ($entity): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Station Details</h2>
                <a href="profile.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Edit</a>
            </div>
            <div class="grid grid-2">
                <div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Location</div>
                    <div style="font-weight: 500;">
                        <?= htmlspecialchars($entity['city'] ?? 'Not set') ?>
                        <?php if (!empty($entity['region'])): ?>, <?= htmlspecialchars($entity['region']) ?><?php endif; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Frequency</div>
                    <div style="font-weight: 500;"><?= htmlspecialchars($entity['frequency'] ?? 'Not set') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

