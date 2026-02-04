<?php
/**
 * Label Dashboard - Home/Overview
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'Dashboard';
$currentPage = 'home';

// Fetch stats
$stats = [
    'roster_count' => 0,
    'releases' => 0,
    'total_score' => 0,
    'label_ranking' => '-',
    'label_score' => 0,
    'posts_count' => 0,
    'videos_count' => 0,
];
$rosterArtists = [];

if ($entity) {
    try {
        $pdo = dashboard_pdo();

        // Get roster count and artists
        $stmt = $pdo->prepare("
            SELECT a.id, a.name, a.image_url, a.slug, nri.rank, nri.score
            FROM `ngn_2025`.`artists` a
            LEFT JOIN `ngn_rankings_2025`.`ranking_items` nri ON a.id = nri.entity_id AND nri.entity_type = 'artist'
            LEFT JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id AND nrh.interval = 'weekly'
            WHERE a.label_id = ?
            ORDER BY nri.score DESC, a.name ASC
            LIMIT 5
        ");
        $stmt->execute([$entity['id']]);
        $rosterArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`artists` WHERE label_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['roster_count'] = (int)$stmt->fetchColumn();

        // Get releases count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`releases` WHERE label_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['releases'] = (int)$stmt->fetchColumn();

        // Get label ranking and score
        $stmt = $pdo->prepare("
            SELECT nri.rank, nri.score
            FROM `ngn_rankings_2025`.`ranking_items` nri
            JOIN `ngn_rankings_2025`.`ranking_windows` nrh ON nri.window_id = nrh.id
            WHERE nri.entity_type = 'label' AND nri.entity_id = ? AND nrh.interval = 'weekly'
            ORDER BY nrh.window_end DESC LIMIT 1
        ");
        $stmt->execute([$entity['id']]);
        $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($scoreData) {
            $stats['label_ranking'] = $scoreData['ranking'] ?: '-';
            $stats['label_score'] = (int)$scoreData['score'];
        }

        // Get posts count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`posts` WHERE author_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['posts_count'] = (int)$stmt->fetchColumn();

        // Get videos count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`videos` WHERE entity_type = 'label' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['videos_count'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // Tables may not exist
    }
}

function get_artist_analytics_summary(PDO $pdo, int $artistId): array
{
    $summary = [
        'spotify_followers' => 0,
        'youtube_subscribers' => 0,
    ];

    try {
                $stmt = $pdo->prepare("
                    SELECT provider, metric, value
                    FROM `ngn_2025`.`analytics_snapshots`
                    WHERE entity_type = 'artist' AND entity_id = ?
                    AND (
                        (provider = 'spotify' AND metric = 'followers') OR
                        (provider = 'youtube' AND metric = 'subscribers')
                    )
                    ORDER BY period_end DESC
                ");        $stmt->execute([$artistId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $latest_metrics = [];
        foreach($rows as $row) {
            $key = $row['provider'] . '_' . $row['metric'];
            if(!isset($latest_metrics[$key])) {
                $latest_metrics[$key] = $row['value'];
            }
        }
        
        $summary['spotify_followers'] = (int)($latest_metrics['spotify_followers'] ?? 0);
        $summary['youtube_subscribers'] = (int)($latest_metrics['youtube_subscribers'] ?? 0);

    } catch (PDOException $e) {
        // Log error or handle gracefully
    }

    return $summary;
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Welcome back<?= $entity ? ', ' . htmlspecialchars($entity['name']) : '' ?>!</h1>
        <p class="page-subtitle">Manage your label and roster</p>
    </header>
    
    <div class="page-content">
        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Profile Not Found</strong> - Your label profile hasn't been migrated to NGN 2.0 yet.
            <a href="profile.php">Set up your profile →</a>
        </div>
        <?php endif; ?>

        <!-- Upgrade Notification Banner -->
        <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 20px 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;"><i class="bi bi-star-fill" style="color: #fbbf24; margin-right: 8px;"></i>Unlock More Features</div>
                <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                    Upgrade to <strong>Pro</strong> ($29.99/month) for advanced roster analytics, or <strong>Premium</strong> ($79.99/month) for API access and custom branding.
                </p>
            </div>
            <div style="display: flex; gap: 12px; white-space: nowrap; flex-shrink: 0;">
                <a href="/dashboard/label/tiers.php" style="display: inline-flex; align-items: center; gap: 8px; background: var(--brand); color: #000; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.3s; text-align: center;">
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
                <div class="stat-label">Label Ranking</div>
                <div class="stat-value" style="color: var(--brand);">#<?= $stats['label_ranking'] ?></div>
                <div class="stat-change"><a href="score.php">View score →</a></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">NGN Score</div>
                <div class="stat-value" style="color: var(--accent);"><?= number_format($stats['label_score']) ?></div>
                <div class="stat-change">Points earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Roster Artists</div>
                <div class="stat-value"><?= $stats['roster_count'] ?></div>
                <div class="stat-change"><a href="roster.php">Manage roster →</a></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Releases</div>
                <div class="stat-value"><?= $stats['releases'] ?></div>
                <div class="stat-change">Across all artists</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="roster.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-person-plus" style="font-size: 24px; color: var(--brand);"></i>
                    <span>Add Artist</span>
                </a>
                <a href="releases.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-disc" style="font-size: 24px; color: var(--accent);"></i>
                    <span>New Release</span>
                </a>
                <a href="posts.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-newspaper" style="font-size: 24px; color: #a855f7;"></i>
                    <span>Post News</span>
                </a>
                <a href="connections.php" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-share" style="font-size: 24px; color: #f59e0b;"></i>
                    <span>Connect Socials</span>
                </a>
            </div>
        </div>
        
        <!-- Roster Overview -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Roster</h2>
                <a href="roster.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</a>
            </div>
            
            <?php if (empty($rosterArtists)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-people" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No artists on your roster yet.</p>
                <a href="roster.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add First Artist
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($rosterArtists as $artist): 
                    $analytics = get_artist_analytics_summary($pdo, $artist['id']);
                ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 12px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($artist['image_url'])): ?>
                        <img src="<?= htmlspecialchars($artist['image_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <i class="bi bi-person" style="font-size: 20px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600;"><?= htmlspecialchars($artist['name']) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Rank: #<?= $artist['ranking'] ?? '--' ?> | Score: <?= number_format($artist['score'] ?? 0) ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); display: flex; gap: 1rem; margin-top: 4px;">
                            <span><i class="bi bi-spotify"></i> <?= number_format($analytics['spotify_followers']) ?></span>
                            <span><i class="bi bi-youtube"></i> <?= number_format($analytics['youtube_subscribers']) ?></span>
                        </div>
                    </div>
                    <a href="releases.php?artist_id=<?= $artist['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                        <i class="bi bi-disc"></i> Releases
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php /* TODO: Integrate more aggregated and unified analytics here, e.g., overall label performance trends,
                      top-performing artists across the roster, or AI-driven insights across all artists. */ ?>
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <h2 class="card-title">Aggregated Roster Analytics</h2>
                <button id="refreshAnalyticsBtn" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding: 1rem;">
                <div style="text-align: center; padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                    <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Overall Roster NGN Score</p>
                    <p id="overallScore" style="margin: 0; font-size: 1.5rem; font-weight: bold;">--</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                    <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Average Roster Rank</p>
                    <p id="averageRank" style="margin: 0; font-size: 1.5rem; font-weight: bold;">--</p>
                </div>
                <div style="text-align: center; padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                    <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Total Social Reach</p>
                    <p id="totalSocialReach" style="margin: 0; font-size: 1.5rem; font-weight: bold;">--</p>
                </div>
            </div>
        </div>
        <!-- AI Coach Teaser -->
        <div class="card" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%); border-color: #a855f7;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #a855f7, #00d4ff); display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-robot" style="font-size: 28px; color: #fff;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <h3 style="font-size: 18px; font-weight: 600;">AI Label Coach</h3>
                        <span style="font-size: 10px; background: var(--warning); color: #000; padding: 2px 6px; border-radius: 4px; font-weight: 600;">COMING SOON</span>
                    </div>
                    <p style="font-size: 14px; color: var(--text-secondary); margin: 0;">
                        Get AI-powered insights on roster performance, release timing, and marketing strategies.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

