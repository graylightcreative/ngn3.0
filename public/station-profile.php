<?php
/**
 * Public Station Profile Page
 * Displays radio station information with recent spins and player
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Engagement\EngagementService;

// Get station slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.1 404 Not Found');
    die('Station not found');
}

$config = new Config();
$pdo = ConnectionFactory::read($config);
$engagementService = new EngagementService($pdo);

// Fetch station data
$stmt = $pdo->prepare("
    SELECT
        s.id, s.name, s.slug, s.image_url, s.bio, s.call_sign, s.format, s.region,
        s.stream_url, s.stream_type, s.playlist_url, s.user_id
    FROM `ngn_2025`.`stations` s
    WHERE s.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$station) {
    header('HTTP/1.1 404 Not Found');
    die('Station not found');
}

// Get engagement counts
$counts = [];
try {
    $counts = $engagementService->getCounts('station', (int)$station['id']);
} catch (\Exception $e) {
    $counts = [];
}

// Get recent spins
$recentSpins = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`station_spins` WHERE station_id = :station_id ORDER BY played_at DESC LIMIT 10");
    $stmt->execute([':station_id' => (int)$station['id']]);
    $recentSpins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $recentSpins = [];
}

// Page metadata
$pageTitle = htmlspecialchars($station['name']) . ' | Stations | Next Gen Noise';
$pageDescription = $station['bio'] ? substr(strip_tags($station['bio']), 0, 160) : 'Listen to ' . htmlspecialchars($station['name']) . ' on Next Gen Noise';
$pageImage = (!empty($station['image_url']) && !str_starts_with($station['image_url'], '/'))
    ? "/uploads/stations/{$station['image_url']}"
    : ($station['image_url'] ?? '/assets/images/default-station.jpg');

// Function to render placeholder/upsell
function render_upsell_placeholder($title, $description, $claimed) {
    ?>
    <div class="upsell-placeholder">
        <div class="upsell-content">
            <i class="bi bi-broadcast upsell-icon"></i>
            <h3><?= htmlspecialchars($title) ?></h3>
            <p><?= htmlspecialchars($description) ?></p>
            <?php if (!$claimed): ?>
                <a href="/claim-profile.php?slug=<?= urlencode($GLOBALS['slug']) ?>" class="btn-claim">
                    Claim Your Station Profile & Log Spins
                </a>
            <?php else: ?>
                <p class="text-muted">Start submitting your spins to populate this feed!</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-primary: #0b1020;
            --bg-secondary: #141b2e;
            --bg-tertiary: #1c2642;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border: rgba(148, 163, 184, 0.12);
            --accent: #1DB954;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        
        .profile-header { display: flex; gap: 32px; margin-bottom: 40px; background: var(--bg-secondary); padding: 32px; border-radius: 16px; border: 1px solid var(--border); align-items: center; }
        .profile-image { width: 200px; height: 200px; border-radius: 12px; object-fit: cover; }
        .profile-info { flex: 1; }
        .profile-info h1 { font-size: 48px; margin-bottom: 8px; }
        .station-meta { display: flex; gap: 12px; color: var(--text-secondary); margin-bottom: 16px; font-weight: 600; }

        .player-card { background: linear-gradient(135deg, #1DB954 0%, #191414 100%); border-radius: 24px; padding: 40px; margin-bottom: 48px; display: flex; align-items: center; gap: 32px; color: #fff; }
        .play-btn { width: 80px; height: 80px; border-radius: 50%; background: #fff; color: #000; border: none; font-size: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .play-btn:hover { transform: scale(1.1); }

        .section { margin-bottom: 64px; }
        .section h2 { font-size: 32px; margin-bottom: 32px; font-weight: 800; }

        .spin-item { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .spin-artist { font-weight: 700; font-size: 18px; }
        .spin-title { color: var(--text-secondary); }

        .upsell-placeholder { background: rgba(20, 27, 46, 0.5); border: 2px dashed var(--border); border-radius: 24px; padding: 64px 32px; text-align: center; }
        .upsell-icon { font-size: 48px; color: var(--accent); margin-bottom: 24px; display: block; }
        .btn-claim { display: inline-block; background: var(--accent); color: #000; padding: 16px 32px; border-radius: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; font-size: 14px; margin-top: 20px; }

        @media (max-width: 768px) { .profile-header, .player-card { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($pageImage) ?>" class="profile-image" alt="<?= htmlspecialchars($station['name']) ?>">
            <div class="profile-info">
                <h1><?= htmlspecialchars($station['name']) ?></h1>
                <div class="station-meta">
                    <span><?= htmlspecialchars($station['call_sign'] ?: 'NGN WEB') ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars($station['format'] ?: 'Indie Rock') ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars($station['region'] ?: 'Global') ?></span>
                </div>
                <p><?= nl2br(htmlspecialchars($station['bio'] ?: "Broadcasting the best in underground rock and metal. Part of the NGN verified station network.")) ?></p>
            </div>
        </div>

        <div class="player-card">
            <button class="play-btn"><i class="bi bi-play-fill"></i></button>
            <div style="flex: 1;">
                <div style="text-transform: uppercase; font-size: 12px; font-weight: 800; letter-spacing: 0.1em; opacity: 0.8; margin-bottom: 4px;">Now Playing Live</div>
                <div style="font-size: 24px; font-weight: 800;"><?= htmlspecialchars($station['name']) ?> Stream</div>
                <div style="color: rgba(255,255,255,0.7);"><?= htmlspecialchars($station['format']) ?></div>
            </div>
            <?php if (!$station['stream_url']): ?>
                <div style="background: rgba(0,0,0,0.3); padding: 8px 16px; border-radius: 8px; font-size: 13px;">Offline</div>
            <?php else: ?>
                <div style="color: #fff; font-weight: 700;"><i class="bi bi-broadcast"></i> LIVE</div>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

        <div class="section">
            <h2>Recent Spins</h2>
            <?php if (!empty($recentSpins)): ?>
                <?php foreach ($recentSpins as $spin): ?>
                    <div class="spin-item">
                        <div>
                            <div class="spin-artist"><?= htmlspecialchars($spin['artist_name']) ?></div>
                            <div class="spin-title"><?= htmlspecialchars($spin['song_title']) ?></div>
                        </div>
                        <div style="color: var(--text-muted); font-size: 14px;">
                            <?= date('g:i A', strtotime($spin['played_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php render_upsell_placeholder("Radio Airplay", "Connect your station's automation or log spins manually. Your station's data powers the NGN Regional and Format charts.", !empty($station['user_id'])); ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>