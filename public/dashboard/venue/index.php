<?php
/**
 * Venue Dashboard - Home/Overview
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Dashboard';
$currentPage = 'home';

// Fetch stats
$stats = [
    'upcoming_shows' => 0,
    'past_shows' => 0,
    'total_artists' => 0,
    'ranking' => '-',
    'score' => 0,
    'posts_count' => 0,
    'videos_count' => 0,
];

if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE venue_id = ? AND starts_at > NOW()");
        $stmt->execute([$entity['id']]);
        $stats['upcoming_shows'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`shows` WHERE venue_id = ? AND starts_at <= NOW()");
        $stmt->execute([$entity['id']]);
        $stats['past_shows'] = (int)$stmt->fetchColumn();

        // Get ranking and score
        $stmt = $pdo->prepare("
            SELECT ranking, score
            FROM `ngn_2025`.`entity_scores`
            WHERE entity_type = 'venue' AND entity_id = ?
            LIMIT 1
        ");
        $stmt->execute([$entity['id']]);
        $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($scoreData) {
            $stats['ranking'] = $scoreData['ranking'] ?: '-';
            $stats['score'] = (int)$scoreData['score'];
        }

        // Get posts count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`posts` WHERE author_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['posts_count'] = (int)$stmt->fetchColumn();

        // Get videos count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`videos` WHERE entity_type = 'venue' AND entity_id = ?");
        $stmt->execute([$entity['id']]);
        $stats['videos_count'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // Tables may not exist
    }
}

// Fetch upcoming shows for display
$upcomingShows = [];
if ($entity) {
    try {
        $pdo = dashboard_pdo();
        $stmt = $pdo->prepare("SELECT id, slug, title, starts_at, ticket_url FROM `ngn_2025`.`shows` WHERE venue_id = ? AND starts_at > NOW() ORDER BY starts_at ASC LIMIT 5");
        $stmt->execute([$entity['id']]);
        $upcomingShows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Helper function to safely parse dates
function safeStrtotime($dateStr) {
    if (empty($dateStr)) {
        return false;
    }
    $timestamp = strtotime($dateStr);
    return ($timestamp !== false) ? $timestamp : null;
}

// Handle Mock Data Generation (Test Accounts Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_mock_venue_data' && dashboard_is_test_account()) {
    if (!$entity) {
        $error = 'Venue profile not found.';
    } elseif (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            $pdo = dashboard_pdo();
            
            // 1. Generate Mock Shows
            $mockShows = [
                ['title' => 'Battle of the Bands', 'artist_id' => 1],
                ['title' => 'Acoustic Night', 'artist_id' => 2],
                ['title' => 'Heavy Metal Saturday', 'artist_id' => 3]
            ];
            foreach ($mockShows as $s) {
                $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`shows` 
                    (venue_id, title, slug, artist_id, starts_at, status, created_at)
                    VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'published', NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()");
                $stmt->execute([
                    $entity['id'],
                    $s['title'],
                    strtolower(str_replace(' ', '-', $s['title'])) . '-' . uniqid(),
                    $s['artist_id']
                ]);
            }

            // 2. Generate Mock Videos
            $mockVideos = [
                ['title' => 'Venue Walkthrough', 'vid' => 'dQw4w9WgXcQ']
            ];
            foreach ($mockVideos as $v) {
                $stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`videos` 
                    (entity_type, entity_id, title, slug, platform, external_id, created_at)
                    VALUES ('venue', ?, ?, ?, 'youtube', ?, NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()");
                $stmt->execute([
                    $entity['id'],
                    $v['title'],
                    strtolower(str_replace(' ', '-', $v['title'])) . '-' . uniqid(),
                    $v['vid']
                ]);
            }

            $success = "Successfully generated mock shows and videos for testing.";
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
        <h1 class="page-title">Welcome back<?= $entity ? ', ' . htmlspecialchars($entity['name']) : '' ?>!</h1>
        <p class="page-subtitle">Manage your venue and events</p>
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
            <p class="text-sm text-secondary mb-4">You are logged into a test account. Use the button below to populate your venue with mock shows and videos for verification.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo dashboard_csrf_token(); ?>">
                <input type="hidden" name="action" value="generate_mock_venue_data">
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-magic"></i> Generate Mock Venue Data
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Profile Not Found</strong> - Your venue profile hasn't been migrated to NGN 2.0 yet.
            <a href="profile.php">Set up your profile →</a>
        </div>
        <?php endif; ?>

        <!-- Upgrade Notification Banner -->
        <?php if (!dashboard_is_test_account()): ?>
        <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 20px 24px; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
            <div>
                <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;"><i class="bi bi-star-fill" style="color: #fbbf24; margin-right: 8px;"></i>Unlock More Features</div>
                <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">
                    Upgrade to <strong>Pro</strong> ($19.99/month) for ticket sales, or <strong>Premium</strong> ($49.99/month) for advanced event analytics and API access.
                </p>
            </div>
            <div style="display: flex; gap: 12px; white-space: nowrap; flex-shrink: 0;">
                <a href="/dashboard/venue/tiers.php" style="display: inline-flex; align-items: center; gap: 8px; background: var(--brand); color: #000; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: all 0.3s; text-align: center;">
                    <i class="bi bi-arrow-up-right"></i> Upgrade Now
                </a>
                <a href="/?view=pricing" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.2); color: var(--text-primary); padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid rgba(255, 255, 255, 0.3); cursor: pointer; transition: all 0.3s;">
                    Compare Plans
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-4">
            <div class="stat-card">
                <div class="stat-label">Venue Ranking</div>
                <div class="stat-value" style="color: var(--brand);">#<?= $stats['ranking'] ?></div>
                <div class="stat-change"><a href="score.php">View score →</a></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">NGN Score</div>
                <div class="stat-value" style="color: var(--accent);"><?= number_format($stats['score']) ?></div>
                <div class="stat-change">Points earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Upcoming Shows</div>
                <div class="stat-value"><?= $stats['upcoming_shows'] ?></div>
                <div class="stat-change"><a href="shows.php">View schedule →</a></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Past Shows</div>
                <div class="stat-value"><?= $stats['past_shows'] ?></div>
                <div class="stat-change">All time</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-4">
                <a href="shows.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-calendar-plus" style="font-size: 24px; color: var(--brand);"></i>
                    <span>Add Show</span>
                </a>
                <a href="videos.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-camera-video" style="font-size: 24px; color: var(--accent);"></i>
                    <span>Add Video</span>
                </a>
                <a href="posts.php?action=add" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-newspaper" style="font-size: 24px; color: #a855f7;"></i>
                    <span>Post News</span>
                </a>
                <a href="profile.php" class="btn btn-secondary" style="display: flex; flex-direction: column; align-items: center; padding: 24px; gap: 8px;">
                    <i class="bi bi-pencil" style="font-size: 24px; color: #f59e0b;"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>

        <!-- Ticket Sales Analytics (Pro/Premium Feature) -->
        <?php if (!dashboard_is_test_account()): ?>
        <div class="card" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%); border: 1px solid rgba(168, 85, 247, 0.2);">
            <div class="card-header" style="border-bottom: 1px solid rgba(168, 85, 247, 0.2);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h2 class="card-title"><i class="bi bi-ticket-detailed" style="color: #a855f7; margin-right: 8px;"></i>Ticket Sales Analytics</h2>
                    <span style="font-size: 12px; background: #a855f7; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: 600;">PRO FEATURE</span>
                </div>
            </div>
            <div style="padding: 24px;">
                <div style="text-align: center; color: var(--text-muted); padding: 48px 24px;">
                    <i class="bi bi-lock" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                    <p style="margin-bottom: 16px; font-weight: 600;">Upgrade to Pro to unlock ticket sales</p>
                    <p style="margin-bottom: 24px;">Start selling tickets for your events, track attendance, and watch real-time revenue analytics.</p>
                    <a href="tiers.php" style="display: inline-flex; align-items: center; gap: 8px; background: #a855f7; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s;">
                        <i class="bi bi-arrow-up-right"></i> Upgrade to Pro
                    </a>
                </div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid rgba(168, 85, 247, 0.1);">
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Total Revenue</div>
                        <div style="font-size: 32px; font-weight: bold; color: #a855f7;">—</div>
                    </div>
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Tickets Sold</div>
                        <div style="font-size: 32px; font-weight: bold; color: #a855f7;">—</div>
                    </div>
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Avg Price</div>
                        <div style="font-size: 32px; font-weight: bold; color: #a855f7;">—</div>
                    </div>
                    <div style="text-align: center; opacity: 0.5;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Attendance Rate</div>
                        <div style="font-size: 32px; font-weight: bold; color: #a855f7;">—</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Talent Discovery -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Talent Discovery</h2>
            </div>
            <form action="artist-discovery.php" method="GET">
                <div class="form-group">
                    <label class="form-label">Search for Artists</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="q" class="form-input" placeholder="Search by name, genre, etc...">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="local" value="1">
                        <span>Search local artists only</span>
                    </label>
                </div>
            </form>
        </div>
        
        <!-- Upcoming Shows -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Upcoming Shows</h2>
                <a href="shows.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">View All</a>
            </div>
            
            <?php if (empty($upcomingShows)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-calendar-x" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No upcoming shows scheduled.</p>
                <a href="shows.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Schedule a Show
                </a>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($upcomingShows as $show):
                    $ts = safeStrtotime($show['starts_at']);
                ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
                    <div style="width: 60px; text-align: center; flex-shrink: 0;">
                        <?php if ($ts): ?>
                        <div style="font-size: 24px; font-weight: 700; color: var(--brand);"><?= date('d', $ts) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;"><?= date('M', $ts) ?></div>
                        <?php else: ?>
                        <div style="font-size: 12px; color: var(--text-muted);">—</div>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($show['title']) ?></div>
                        <div style="font-size: 13px; color: var(--text-muted);"><?= $ts ? date('g:i A', $ts) : '—' ?></div>
                    </div>
                    <?php if (!empty($show['ticket_url'])): ?>
                    <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                        <i class="bi bi-ticket"></i> Tickets
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Venue Info -->
        <?php if ($entity): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Venue Details</h2>
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
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Capacity</div>
                    <div style="font-weight: 500;"><?= $entity['capacity'] ?? 'Not set' ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

