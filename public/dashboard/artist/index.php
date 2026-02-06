<?php
/**
 * Artist Dashboard - Home/Overview
 * (Bible Ch. 7 - A.1 Dashboard: Quick overview of spins, rankings, and key metrics)
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('artist');

$user = dashboard_get_user();
$entity = dashboard_get_entity('artist');
$pageTitle = 'Dashboard';
$currentPage = 'home';

// Fetch stats
$stats = [
    'ngn_rank' => '-',
    'ngn_score' => 0,
    'releases' => 0,
    'shows' => 0,
];
$recent_spins = [];

$analytics_summary = [];
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        
        // Get rank and score
        $stmt = $pdo->prepare("
            SELECT ranking as rank, score as score
            FROM `ngn_2025`.`entity_scores`
            WHERE entity_type = 'artist' AND entity_id = ?
            LIMIT 1
        ");
        $stmt->execute([$entity['id']]);
        $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($scoreData) {
            $stats['ngn_rank'] = $scoreData['rank'] ?: '-';
            $stats['ngn_score'] = (int)$scoreData['score'];
        }

        // Get release count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`releases` WHERE artist_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['releases'] = (int)$stmt->fetchColumn();
        
        // Get upcoming shows count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`show_lineup` sl JOIN `ngn_2025`.`shows` s ON s.id = sl.show_id WHERE sl.artist_id = ? AND s.starts_at > NOW()");
        $stmt->execute([$entity['id']]);
        $stats['shows'] = (int)$stmt->fetchColumn();

        // Get recent spins using centralized connection pool
        try {
            $spinsPdo = dashboard_pdo_spins();
            $stmt = $spinsPdo->prepare("SELECT ss.*, s.name as station_name FROM `ngn_2025`.`station_spins` ss LEFT JOIN `ngn_2025`.`stations` s ON ss.station_id = s.id WHERE ss.artist_id = ? ORDER BY played_at DESC LIMIT 5");
            $stmt->execute([$entity['id']]);
            $recent_spins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Failed to fetch spins: ' . $e->getMessage());
            $recent_spins = [];
        }

        $analytics_summary = get_artist_analytics_summary($pdo, $entity['id']);

    } catch (PDOException $e) {
        error_log('Dashboard data fetch error: ' . $e->getMessage());
        // Tables may not exist yet
    }
}

// Helper function to safely parse dates
function safeStrtotime($dateStr) {
    if (empty($dateStr)) {
        return false;
    }
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

function get_artist_analytics_summary(PDO $pdo, int $artistId): array
{
    $summary = [
        'spotify_followers' => 0,
        'youtube_subscribers' => 0,
        'ngn_plays' => 0,
    ];

    try {
                $stmt = $pdo->prepare("
                    SELECT provider, metric, value
                    FROM `ngn_2025`.`analytics_snapshots`
                    WHERE entity_type = 'artist' AND entity_id = ?
                    AND (
                        (provider = 'spotify' AND metric = 'followers') OR
                        (provider = 'youtube' AND metric = 'subscribers') OR
                        (provider = 'ngn' AND metric = 'plays')
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
        $summary['ngn_plays'] = (int)($latest_metrics['ngn_plays'] ?? 0);

    } catch (PDOException $e) {
        // Log error or handle gracefully
    }

    return $summary;
}

// Handle Mock Data Generation (Test Accounts Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_mock_artist_data' && dashboard_is_test_account()) {
    if (!$entity) {
        $error = 'Artist profile not found.';
    } elseif (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $pdo = dashboard_pdo();
            
            // 1. Generate Mock Releases
            $mockReleases = [
                ['title' => 'First Flight', 'type' => 'album'],
                ['title' => 'Echoes of Silence', 'type' => 'ep'],
                ['title' => 'Neon Nights', 'type' => 'single']
            ];
            foreach ($mockReleases as $r) {
                $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`releases` 
                    (artist_id, title, slug, type, release_date, status, created_at)
                    VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 30 DAY), 'published', NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()");
                $stmt->execute([
                    $entity['id'],
                    $r['title'],
                    strtolower(str_replace(' ', '-', $r['title'])) . '-' . uniqid(),
                    $r['type']
                ]);
            }

            // 2. Generate Mock Shows
            $mockShows = [
                ['title' => 'Live at The Underground', 'venue_id' => 1],
                ['title' => 'Summer Festival 2026', 'venue_id' => 2]
            ];
            foreach ($mockShows as $s) {
                $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`shows` 
                    (artist_id, title, slug, venue_id, starts_at, status, created_at)
                    VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY), 'published', NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()");
                $stmt->execute([
                    $entity['id'],
                    $s['title'],
                    strtolower(str_replace(' ', '-', $s['title'])) . '-' . uniqid(),
                    $s['venue_id']
                ]);
            }

            // 3. Generate Mock Videos
            $mockVideos = [
                ['title' => 'Official Music Video', 'vid' => 'dQw4w9WgXcQ'],
                ['title' => 'Live Performance', 'vid' => 'y6120QOlsfU']
            ];
            foreach ($mockVideos as $v) {
                $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`videos` 
                    (entity_type, entity_id, artist_id, title, slug, platform, external_id, created_at)
                    VALUES ('artist', ?, ?, ?, ?, 'youtube', ?, NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()");
                $stmt->execute([
                    $entity['id'],
                    $entity['id'],
                    $v['title'],
                    strtolower(str_replace(' ', '-', $v['title'])) . '-' . uniqid(),
                    $v['vid']
                ]);
            }

            $success = "Successfully generated mock releases, shows, and videos for testing.";

            // 4. Generate Mock Score
            $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`entity_scores` 
                (entity_type, entity_id, score, ranking, breakdown)
                VALUES ('artist', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE score = VALUES(score), ranking = VALUES(ranking), breakdown = VALUES(breakdown)");
            $stmt->execute([
                $entity['id'],
                rand(1000, 15000),
                rand(1, 200),
                json_encode([
                    'radio' => rand(100, 500),
                    'social' => rand(100, 400),
                    'streaming' => rand(100, 400),
                    'content' => rand(50, 200),
                    'engagement' => rand(10, 100)
                ])
            ]);
        } catch (\Throwable $e) {
            $error = 'Failed to generate mock data: ' . $e->getMessage();
        }
    }
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Welcome back, <?= htmlspecialchars($user['Title'] ?? 'Artist') ?></h1>
        <p class="page-subtitle">Here's what's happening with your music</p>
    </header>
    
    <div class="page-content">
        <?php if (isset($success) && $success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (isset($error) && $error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Test Account Controls -->
        <?php if (dashboard_is_test_account()): ?>
        <div class="card" style="border: 1px dashed var(--brand); background: rgba(29, 185, 84, 0.05); margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title text-brand"><i class="bi bi-bug"></i> Test Controls</h2>
            </div>
            <p class="text-sm text-secondary mb-4">You are logged into a test account. Use the button below to populate your profile with mock releases, shows, and videos for verification.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                <input type="hidden" name="action" value="generate_mock_artist_data">
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-magic"></i> Generate Mock Artist Data
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Your artist profile hasn't been migrated to NGN 2.0 yet. Some features may be limited.
        </div>
        <?php endif; ?>

        <!-- Upgrade Notification Banner -->
        <?php if (!dashboard_is_test_account()): ?>
        <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 20px 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;"><i class="bi bi-star-fill" style="color: #fbbf24; margin-right: 8px;"></i>Unlock More Features</div>
                <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                    Upgrade to <strong>Pro</strong> for advanced analytics, priority support, and more. <strong>Premium</strong> users get API access and custom branding.
                </p>
            </div>
            <div style="display: flex; gap: 12px; white-space: nowrap; flex-shrink: 0;">
                <a href="/dashboard/artist/tiers.php" style="display: inline-flex; align-items: center; gap: 8px; background: var(--brand); color: #000; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.3s; text-align: center;">
                    <i class="bi bi-arrow-up-right"></i> Upgrade Now
                </a>
                <a href="/?view=pricing" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.2); color: var(--text-primary); padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 255, 255, 0.3); cursor: pointer; transition: all 0.3s;">
                    Compare Plans
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-4" style="margin-bottom: 32px;">
            <div class="stat-card">
                <div class="stat-label">NGN Rank</div>
                <div class="stat-value" style="color: var(--brand);">#<?= $stats['ngn_rank'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">NGN Score</div>
                <div class="stat-value"><?= number_format($stats['ngn_score']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Releases</div>
                <div class="stat-value"><?= $stats['releases'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Upcoming Shows</div>
                <div class="stat-value"><?= $stats['shows'] ?></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="releases.php?action=add" class="btn btn-secondary" style="justify-content: flex-start;">
                    <i class="bi bi-plus-circle"></i> Add Release
                </a>
                <a href="shows.php?action=add" class="btn btn-secondary" style="justify-content: flex-start;">
                    <i class="bi bi-calendar-plus"></i> Add Show
                </a>
                <a href="videos.php?action=add" class="btn btn-secondary" style="justify-content: flex-start;">
                    <i class="bi bi-camera-video"></i> Add Video
                </a>
                <a href="connections.php" class="btn btn-secondary" style="justify-content: flex-start;">
                    <i class="bi bi-share"></i> Connect Socials
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Activity</h2>
            </div>
            <?php if (empty($recent_spins)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-broadcast" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No recent activity to show.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($recent_spins as $spin): ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 12px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--brand); color: #000; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="bi bi-soundwave"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600;">"<?= htmlspecialchars($spin['track_title']) ?>" played on <?= htmlspecialchars($spin['station_name']) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            <?php $ts = safeStrtotime($spin['played_at']); echo ($ts ? date('M j, Y, g:i A', $ts) : 'at unknown time'); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- AI Coach Teaser -->
        <?php if ((new \NGN\Lib\Config())->featureAiEnabled()): ?>
        <div class="card" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(0, 212, 255, 0.1) 100%); border-color: var(--brand);">
            <div class="card-header">
                <h2 class="card-title"><i class="bi bi-robot" style="color: var(--brand);"></i> AI Career Coach</h2>
                <span style="font-size: 12px; background: var(--brand); color: #000; padding: 4px 8px; border-radius: 4px; font-weight: 600;">COMING SOON</span>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                Get personalized recommendations to grow your career. Our AI analyzes your performance data, 
                social engagement, and industry trends to give you actionable insights.
            </p>
            <ul style="color: var(--text-secondary); font-size: 14px; list-style: none; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                <li><i class="bi bi-check-circle-fill" style="color: var(--brand);"></i> Release timing optimization</li>
                <li><i class="bi bi-check-circle-fill" style="color: var(--brand);"></i> Content calendar suggestions</li>
                <li><i class="bi bi-check-circle-fill" style="color: var(--brand);"></i> Score improvement tips</li>
                <li><i class="bi bi-check-circle-fill" style="color: var(--brand);"></i> Audience growth strategies</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Score Breakdown Teaser -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Score Breakdown</h2>
                <a href="score.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View Details</a>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Your NGN Score is calculated from multiple factors. Connect your socials and add content to improve your ranking.
            </p>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div style="text-align: center; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--brand);"><?= number_format($analytics_summary['ngn_plays'] ?? 0) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">NGN Plays</div>
                </div>
                <div style="text-align: center; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--accent);"><?= number_format($analytics_summary['spotify_followers'] ?? 0) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Spotify Followers</div>
                </div>
                <div style="text-align: center; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: 700; color: #a855f7;"><?= number_format($analytics_summary['youtube_subscribers'] ?? 0) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">YouTube Subscribers</div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

