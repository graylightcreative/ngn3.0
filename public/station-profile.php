<?php
/**
 * Public Station Profile Page
 * Displays radio station information with full engagement features
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
        s.stream_url, s.stream_type, s.playlist_url
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

// Recent spins not available in current schema
$recentSpins = [];

// Page metadata
$pageTitle = htmlspecialchars($station['name']) . ' | Radio Stations | Next Gen Noise';
$pageDescription = 'Visit ' . htmlspecialchars($station['name']) . ' on Next Gen Noise';
$pageImage = (!empty($station['image_url']) && !str_starts_with($station['image_url'], '/'))
    ? "/uploads/stations/{$station['image_url']}"
    : ($station['image_url'] ?? '/assets/images/default-station.jpg');

// Variables for engagement partial
$entity_type = 'station';
$entity_id = (int)$station['id'];
$entity_name = $station['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($station['name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/lib/images/site/site.webmanifest">
    <meta name="theme-color" content="#0b1020">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NGN">
    <meta name="mobile-web-app-capable" content="yes">

    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="/lib/images/site/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/lib/images/site/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/lib/images/site/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/lib/images/site/apple-touch-icon.png">

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
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg-primary); color: var(--text-primary); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .profile-header { display: flex; gap: 32px; margin-bottom: 40px; background: var(--bg-secondary); padding: 32px; border-radius: 16px; border: 1px solid var(--border); }
        .profile-image { width: 200px; height: 200px; border-radius: 12px; object-fit: cover; }
        .profile-info { flex: 1; }
        .profile-info h1 { font-size: 48px; margin-bottom: 16px; }
        .profile-meta { color: var(--text-secondary); margin-bottom: 16px; }
        .profile-bio { color: var(--text-secondary); line-height: 1.8; margin-bottom: 24px; }
        .social-links { display: flex; gap: 12px; flex-wrap: wrap; }
        .social-link { padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .social-link:hover { background: var(--accent); color: #000; }
        .section { margin-bottom: 48px; }
        .section h2 { font-size: 28px; margin-bottom: 24px; }
        .spin-list { display: flex; flex-direction: column; gap: 12px; }
        .spin-item { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; display: flex; justify-content: space-between; align-items: center; }
        .spin-info { flex: 1; }
        .spin-artist { font-weight: 600; font-size: 16px; margin-bottom: 4px; }
        .spin-song { color: var(--text-secondary); }
        .spin-meta { color: var(--text-secondary); font-size: 14px; }

        /* Radio Player Styles */
        .radio-player {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 40px;
            text-align: center;
        }
        .radio-player.no-stream {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .player-station-logo {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
        }
        .player-station-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .player-station-meta {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }
        .player-controls {
            display: flex;
            gap: 16px;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
        }
        .play-button {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent);
            border: none;
            color: #000;
            font-size: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-weight: 600;
        }
        .play-button:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(29, 185, 84, 0.5);
        }
        .play-button:active {
            transform: scale(0.95);
        }
        .play-button.playing {
            background: var(--accent);
        }
        .volume-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .volume-slider {
            width: 120px;
            cursor: pointer;
        }
        .player-status {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .player-status.live {
            color: var(--accent);
            font-weight: 600;
        }
        .stream-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            animation: pulse 1.5s infinite;
            margin-right: 6px;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .no-stream-message {
            padding: 16px;
            background: rgba(255, 193, 7, 0.1);
            border-radius: 8px;
            border-left: 3px solid #ffc107;
            color: var(--text-secondary);
            margin-top: 16px;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?= htmlspecialchars(
                (!empty($station['image_url']) && !str_starts_with($station['image_url'], '/'))
                    ? "/uploads/stations/{$station['image_url']}"
                    : ($station['image_url'] ?? '/assets/images/default-station.jpg')
            ) ?>" alt="<?= htmlspecialchars($station['name']) ?>" class="profile-image">
            <div class="profile-info">
                <h1><?= htmlspecialchars($station['name']) ?></h1>
                <div class="profile-meta">
                    <?php if ($station['format']): ?>
                        <span><?= htmlspecialchars($station['format']) ?></span>
                    <?php endif; ?>
                    <?php if ($station['region']): ?>
                        <span style="margin-left: 12px;"><?= htmlspecialchars($station['region']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($station['bio']): ?>
                    <div class="profile-bio"><?= nl2br(htmlspecialchars($station['bio'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Station Player Section -->
        <div class="station-player-section">
            <div class="station-player-container">
                <div class="station-player-artwork">
                    <img id="station-artwork" src="<?= htmlspecialchars(
                        (!empty($station['image_url']) && !str_starts_with($station['image_url'], '/'))
                            ? "/uploads/stations/{$station['image_url']}"
                            : ($station['image_url'] ?? '/assets/images/default-station.jpg')
                    ) ?>" alt="<?= htmlspecialchars($station['name']) ?>" class="station-artwork-image">
                    <div id="buffering-indicator" class="buffering-indicator" style="display: none;">
                        <div class="buffering-spinner"></div>
                    </div>
                </div>

                <div class="station-player-info">
                    <div class="station-meta">
                        <h2 class="station-name"><?= htmlspecialchars($station['name']) ?></h2>
                        <?php if ($station['call_sign']): ?>
                            <span class="station-call-sign"><?= htmlspecialchars($station['call_sign']) ?></span>
                        <?php endif; ?>
                        <?php if ($station['format']): ?>
                            <span class="station-format"><?= htmlspecialchars($station['format']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="now-playing-section" id="now-playing">
                        <div class="now-playing-label">Now Playing:</div>
                        <div class="now-playing-track" id="track-title">Loading...</div>
                        <div class="now-playing-artist" id="track-artist"></div>
                    </div>

                    <div class="station-stats">
                        <span id="listener-count" class="listener-badge">
                            <i class="bi bi-person-fill"></i> <span id="listener-number">0</span> listeners
                        </span>
                    </div>
                </div>

                <div class="station-player-controls">
                    <button id="play-button" class="play-button">
                        <i class="bi bi-play-fill"></i>
                    </button>
                    <div class="volume-control">
                        <button id="mute-button" class="volume-button">
                            <i class="bi bi-volume-up-fill"></i>
                        </button>
                        <input type="range" id="volume-slider" class="volume-slider" min="0" max="100" value="70">
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement UI -->
        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

    </div>

    <!-- Include StationPlayer.js -->
    <script type="module">
import { StationPlayer } from '/public/js/player/StationPlayer.js';

const stationId = <?= (int)$station['id'] ?>;
const player = new StationPlayer(stationId, '/api/v1');

// UI Elements
const playButton = document.getElementById('play-button');
const muteButton = document.getElementById('mute-button');
const volumeSlider = document.getElementById('volume-slider');
const trackTitle = document.getElementById('track-title');
const trackArtist = document.getElementById('track-artist');
const listenerCount = document.getElementById('listener-number');
const bufferingIndicator = document.getElementById('buffering-indicator');

// Play/Pause button
playButton.addEventListener('click', () => {
    player.togglePlay();
});

// Volume controls
muteButton.addEventListener('click', () => {
    player.toggleMute();
});

volumeSlider.addEventListener('input', (e) => {
    player.setVolume(e.target.value / 100);
});

// Player event listeners
player.on('play', () => {
    playButton.innerHTML = '<i class="bi bi-pause-fill"></i>';
});

player.on('pause', () => {
    playButton.innerHTML = '<i class="bi bi-play-fill"></i>';
});

player.on('buffering', (data) => {
    bufferingIndicator.style.display = data.isBuffering ? 'block' : 'none';
});

player.on('metadata', (metadata) => {
    trackTitle.textContent = metadata.title || 'Unknown Track';
    trackArtist.textContent = metadata.artist || 'Unknown Artist';
});

player.on('volumechange', (data) => {
    if (data.muted) {
        muteButton.innerHTML = '<i class="bi bi-volume-mute-fill"></i>';
    } else {
        muteButton.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
    }
});

// Poll for listener count every 30 seconds
async function updateListenerCount() {
    try {
        const response = await fetch(`/api/v1/stations/${stationId}/info`);
        const data = await response.json();
        if (data.success) {
            listenerCount.textContent = data.data.listener_count || 0;
        }
    } catch (error) {
        console.error('Failed to update listener count:', error);
    }
}

// Initial listener count
updateListenerCount();
setInterval(updateListenerCount, 30000);

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    player.destroy();
});
    </script>
</body>
</html>
