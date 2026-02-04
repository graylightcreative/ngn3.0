<?php
/**
 * Public Artist Profile Page
 * Displays artist information with full engagement features
 * Bible Ch. 22 - Social Feed & Engagement compliant
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Engagement\EngagementService;

// Get artist slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.1 404 Not Found');
    die('Artist not found');
}

$config = new Config();
$pdo = ConnectionFactory::read($config);
$engagementService = new EngagementService($pdo);

// Fetch artist data
$stmt = $pdo->prepare("
    SELECT
        a.id, a.name, a.slug, a.image_url, a.bio, a.claimed,
        a.website, a.facebook_url, a.instagram_url, a.youtube_url, a.spotify_url, a.tiktok_url
    FROM `ngn_2025`.`artists` a
    WHERE a.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$artist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artist) {
    header('HTTP/1.1 404 Not Found');
    die('Artist not found');
}

// Get engagement counts
$counts = [];
try {
    $counts = $engagementService->getCounts('artist', (int)$artist['id']);
} catch (\Exception $e) {
    $counts = [];
}

// Get recent releases
$releases = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, cover_url, release_date FROM `ngn_2025`.`releases` WHERE artist_id = :artist_id ORDER BY release_date DESC LIMIT 6");
    $stmt->execute([':artist_id' => (int)$artist['id']]);
    $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    // Releases fetch failed, continue without them
    $releases = [];
}

// Shows table doesn't exist - skip this section
$shows = [];

// Get recent videos
$videos = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, thumbnail_url, video_id, video_type FROM `ngn_2025`.`videos` WHERE entity_type = 'artist' AND entity_id = :artist_id LIMIT 6");
    $stmt->execute([':artist_id' => (int)$artist['id']]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    // Videos fetch failed, continue without them
    $videos = [];
}

// Products table doesn't exist - skip this section
$products = [];

// Get artist tracks for player
$tracks = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            t.id, t.title, t.duration,
            a.name as artist_name,
            r.title as album_name,
            r.cover_url
        FROM `ngn_2025`.`tracks` t
        LEFT JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id
        LEFT JOIN `ngn_2025`.`releases` r ON t.release_id = r.id
        WHERE t.artist_id = :artist_id
        ORDER BY r.release_date DESC, t.track_number ASC
        LIMIT 50
    ");
    $stmt->execute([':artist_id' => (int)$artist['id']]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    // Tracks fetch failed, continue without them
    $tracks = [];
}

// Page metadata
$pageTitle = htmlspecialchars($artist['name']) . ' | Artists | Next Gen Noise';
$pageDescription = $artist['bio'] ? substr(strip_tags($artist['bio']), 0, 160) : 'Visit ' . htmlspecialchars($artist['name']) . ' on Next Gen Noise';
$pageImage = (!empty($artist['image_url']) && !str_starts_with($artist['image_url'], '/'))
    ? "/uploads/artists/{$artist['image_url']}"
    : ($artist['image_url'] ?? '/assets/images/default-artist.jpg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="profile">
    <meta property="og:url" content="https://nextgennoise.com/artist/<?= htmlspecialchars($slug) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($artist['name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?= htmlspecialchars($artist['name']) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars($pageImage) ?>">

    <!-- Schema.org Structured Data: MusicGroup (Artist) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "MusicGroup",
        "name": "<?= htmlspecialchars($artist['name'], ENT_QUOTES) ?>",
        "url": "https://nextgennoise.com/artist/<?= htmlspecialchars($artist['slug'], ENT_QUOTES) ?>",
        "image": "<?= htmlspecialchars($pageImage, ENT_QUOTES) ?>",
        "description": "<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>"
        <?php
            $sameAs = [];
            if ($artist['website']) $sameAs[] = '"' . htmlspecialchars($artist['website'], ENT_QUOTES) . '"';
            if ($artist['facebook_url']) $sameAs[] = '"' . htmlspecialchars($artist['facebook_url'], ENT_QUOTES) . '"';
            if ($artist['instagram_url']) $sameAs[] = '"' . htmlspecialchars($artist['instagram_url'], ENT_QUOTES) . '"';
            if ($artist['youtube_url']) $sameAs[] = '"' . htmlspecialchars($artist['youtube_url'], ENT_QUOTES) . '"';
            if ($artist['spotify_url']) $sameAs[] = '"' . htmlspecialchars($artist['spotify_url'], ENT_QUOTES) . '"';
            if ($artist['tiktok_url']) $sameAs[] = '"' . htmlspecialchars($artist['tiktok_url'], ENT_QUOTES) . '"';
            if (!empty($sameAs)):
        ?>,
        "sameAs": [<?= implode(',', $sameAs) ?>]
        <?php endif; ?>
        <?php if (!empty($releases)): ?>,
        "album": [
            <?php $albumData = array_map(function($r) {
                return '{
                    "@type": "MusicAlbum",
                    "name": "' . htmlspecialchars($r['title'], ENT_QUOTES) . '",
                    "image": "' . htmlspecialchars($r['cover_url'] ?? '/assets/images/default-release.jpg', ENT_QUOTES) . '",
                    "datePublished": "' . ($r['release_date'] ? date('Y-m-d', strtotime($r['release_date'])) : '') . '"
                }';
            }, array_slice($releases, 0, 3)); ?>
            <?= implode(',', $albumData) ?>
        ]
        <?php endif; ?>
    }
    </script>

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

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-primary: #0b1020;
            --bg-secondary: #141b2e;
            --bg-tertiary: #1c2642;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border: rgba(148, 163, 184, 0.12);
            --accent: #1DB954;
            --accent-hover: #1ed760;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header */
        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
            text-decoration: none;
        }

        .nav {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
            color: var(--text-primary);
        }

        /* Artist Hero */
        .artist-hero {
            padding: 48px 0;
            background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
        }

        .artist-hero-content {
            display: flex;
            gap: 32px;
            align-items: flex-start;
        }

        .artist-image {
            width: 200px;
            height: 200px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }

        .artist-info {
            flex: 1;
        }

        .artist-name {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .artist-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .artist-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-tertiary);
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .artist-bio {
            color: var(--text-secondary);
            margin-bottom: 24px;
            max-width: 800px;
        }

        /* Engagement Buttons */
        .engagement-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .engagement-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .engagement-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .engagement-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #06120a;
        }

        .engagement-btn.spark {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            border-color: #f59e0b;
            color: #fff;
        }

        .engagement-btn.spark:hover {
            background: linear-gradient(135deg, #fbbf24 0%, #fb923c 100%);
        }

        .engagement-count {
            font-weight: 700;
            opacity: 0.9;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 50%;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        /* Content Sections */
        .section {
            padding: 48px 0;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
        }

        .grid {
            display: grid;
            gap: 24px;
        }

        .grid-3 { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }

        /* Cards */
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Comment Section */
        .comments-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-top: 48px;
        }

        .comment-composer {
            margin-bottom: 32px;
        }

        .comment-textarea {
            width: 100%;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            min-height: 80px;
        }

        .comment-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--accent);
            color: #06120a;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            background: var(--accent-hover);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .comment-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .comment {
            display: flex;
            gap: 16px;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .comment-content {
            flex: 1;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .comment-author {
            font-weight: 600;
            color: var(--text-primary);
        }

        .comment-time {
            color: var(--text-muted);
            font-size: 13px;
        }

        .comment-text {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 24px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .artist-hero-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .artist-name {
                font-size: 36px;
            }

            .grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-content">
            <a href="/" class="logo">NGN</a>
            <nav class="nav">
                <a href="/artists">Artists</a>
                <a href="/charts">Charts</a>
                <a href="/stations">Stations</a>
                <a href="/venues">Venues</a>
                <a href="#" id="auth-btn">Sign In</a>
            </nav>
        </div>
    </header>

    <!-- Artist Hero -->
    <section class="artist-hero">
        <div class="container">
            <div class="artist-hero-content">
                <?= ngn_image(
                    (!empty($artist['image_url']) && !str_starts_with($artist['image_url'], '/'))
                        ? "/uploads/artists/{$artist['image_url']}"
                        : ($artist['image_url'] ?? '/assets/images/default-artist.jpg'),
                    $artist['name'],
                    'artist-image',
                    true,
                    'eager'  // Hero image should load eagerly (above the fold)
                ) ?>

                <div class="artist-info">
                    <h1 class="artist-name"><?= htmlspecialchars($artist['name']) ?></h1>

                    <div class="artist-meta">
                        <?php if ($artist['claimed']): ?>
                        <span class="artist-badge">
                            <i class="bi bi-patch-check-fill" style="color: var(--accent);"></i>
                            Verified Artist
                        </span>
                        <?php endif; ?>

                        <span class="artist-badge">
                            <i class="bi bi-lightning-fill"></i>
                            <?= number_format($counts['eqs_score'] ?? 0) ?> EQS
                        </span>
                    </div>

                    <?php if ($artist['bio']): ?>
                    <p class="artist-bio"><?= nl2br(htmlspecialchars($artist['bio'])) ?></p>
                    <?php endif; ?>

                    <!-- Social Links -->
                    <div class="social-links">
                        <?php if ($artist['website']): ?>
                        <a href="<?= htmlspecialchars($artist['website']) ?>" target="_blank" class="social-link" title="Website">
                            <i class="bi bi-globe"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($artist['facebook_url']): ?>
                        <a href="<?= htmlspecialchars($artist['facebook_url']) ?>" target="_blank" class="social-link" title="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($artist['instagram_url']): ?>
                        <a href="<?= htmlspecialchars($artist['instagram_url']) ?>" target="_blank" class="social-link" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($artist['tiktok_url']): ?>
                        <a href="<?= htmlspecialchars($artist['tiktok_url']) ?>" target="_blank" class="social-link" title="TikTok">
                            <i class="bi bi-tiktok"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($artist['youtube_url']): ?>
                        <a href="<?= htmlspecialchars($artist['youtube_url']) ?>" target="_blank" class="social-link" title="YouTube">
                            <i class="bi bi-youtube"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($artist['spotify_url']): ?>
                        <a href="<?= htmlspecialchars($artist['spotify_url']) ?>" target="_blank" class="social-link" title="Spotify">
                            <i class="bi bi-spotify"></i>
                        </a>
                        <?php endif; ?>
                    </div>


                    <!-- Engagement Buttons -->
                    <div class="engagement-bar">
                        <button class="engagement-btn" id="like-btn" data-entity-type="artist" data-entity-id="<?= $artist['id'] ?>">
                            <i class="bi bi-heart"></i>
                            <span class="engagement-count"><?= number_format($counts['likes_count']) ?></span>
                        </button>

                        <button class="engagement-btn" id="share-btn">
                            <i class="bi bi-share-fill"></i>
                            <span class="engagement-count"><?= number_format($counts['shares_count']) ?></span>
                        </button>

                        <button class="engagement-btn" id="comment-btn">
                            <i class="bi bi-chat-dots-fill"></i>
                            <span class="engagement-count"><?= number_format($counts['comments_count']) ?></span>
                        </button>

                        <button class="engagement-btn spark" id="spark-btn">
                            <i class="bi bi-lightning-charge-fill"></i>
                            Send Sparks
                            <span class="engagement-count">(<?= number_format($counts['sparks_count']) ?>)</span>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Releases -->
    <?php if ($releases): ?>
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Releases</h2>
            </div>
            <div class="grid grid-3">
                <?php foreach ($releases as $release): ?>
                <div class="card">
                    <?php if ($release['cover_url']): ?>
                    <?= ngn_image($release['cover_url'], $release['title'], 'card-image') ?>
                    <?php endif; ?>
                    <h3 class="card-title"><?= htmlspecialchars($release['title']) ?></h3>
                    <p class="card-subtitle"><?= $release['release_date'] ? date('M d, Y', strtotime($release['release_date'])) : 'TBA' ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Videos -->
    <?php if ($videos): ?>
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Videos</h2>
            </div>
            <div class="grid grid-3">
                <?php foreach ($videos as $video): ?>
                <div class="card">
                    <?php if ($video['thumbnail_url']): ?>
                    <?= ngn_image($video['thumbnail_url'], $video['title'], 'card-image') ?>
                    <?php endif; ?>
                    <h3 class="card-title"><?= htmlspecialchars($video['title']) ?></h3>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Comments Section -->
    <section class="section">
        <div class="container">
            <div class="comments-section">
                <h2 class="section-title" style="margin-bottom: 24px;">Comments</h2>

                <!-- Comment Composer -->
                <div class="comment-composer">
                    <textarea class="comment-textarea" id="comment-text" placeholder="Share your thoughts..."></textarea>
                    <div style="display: flex; gap: 12px; margin-top: 12px; justify-content: flex-end;">
                        <button class="btn btn-secondary" id="clear-comment-btn">Clear</button>
                        <button class="btn" id="submit-comment-btn">
                            <i class="bi bi-send-fill"></i> Post Comment
                        </button>
                    </div>
                </div>

                <!-- Comment List -->
                <div id="comment-list" class="comment-list">
                    <!-- Comments will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
    </section>

    <!-- Spark Modal -->
    <div id="spark-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Send Sparks to <?= htmlspecialchars($artist['name']) ?></h2>
                <button class="modal-close" id="spark-modal-close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-secondary); margin-bottom: 24px;">
                    Support <?= htmlspecialchars($artist['name']) ?> by sending Sparks!<br>
                    <strong>1 Spark = $0.01 USD</strong> (90% to artist, 10% platform fee)
                </p>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px;">
                    <button class="btn btn-secondary spark-amount-btn" data-amount="100">
                        100 Sparks<br><small>($1.00)</small>
                    </button>
                    <button class="btn btn-secondary spark-amount-btn" data-amount="500">
                        500 Sparks<br><small>($5.00)</small>
                    </button>
                    <button class="btn btn-secondary spark-amount-btn" data-amount="1000">
                        1000 Sparks<br><small>($10.00)</small>
                    </button>
                </div>

                <div class="form-group">
                    <label style="color: var(--text-secondary); display: block; margin-bottom: 8px;">Custom Amount</label>
                    <input type="number" id="custom-spark-amount" class="comment-textarea" placeholder="Enter spark count..." min="1" style="min-height: auto; padding: 12px 16px;">
                </div>

                <div id="spark-preview" style="background: var(--bg-tertiary); border-radius: 8px; padding: 16px; margin-top: 16px; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Gross Amount:</span>
                        <span id="spark-gross">$0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Platform Fee (10%):</span>
                        <span id="spark-fee">$0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid var(--border);">
                        <span style="font-weight: 700;">Artist Receives:</span>
                        <span id="spark-net" style="font-weight: 700; color: var(--accent);">$0.00</span>
                    </div>
                </div>
            </div>
            <?php /* TODO: Integrate more detailed Sparks analytics or a clearer visualization of earnings over time. */ ?>
            <div class="card" style="margin-top: 1.5rem; border: 1px dashed var(--warning);">
                <div class="card-header">
                    <h3 class="card-title">Sparks Analytics Summary</h3>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                        <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Total Sparks Received</p>
                        <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">--</p>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--bg-primary); border-radius: 8px;">
                        <p style="margin: 0 0 0.5rem 0; color: var(--text-muted); font-size: 0.875rem;">Sparks Trend (Last 30 Days)</p>
                        <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">--</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancel-spark-btn">Cancel</button>
                <button class="btn" id="confirm-spark-btn" disabled>
                    <i class="bi bi-lightning-charge-fill"></i> Send Sparks
                </button>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="share-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Share <?= htmlspecialchars($artist['name']) ?></h2>
                <button class="modal-close" id="share-modal-close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <button class="btn btn-secondary" id="share-facebook">
                        <i class="bi bi-facebook"></i> Facebook
                    </button>
                    <button class="btn btn-secondary" id="share-twitter">
                        <i class="bi bi-twitter"></i> Twitter
                    </button>
                    <button class="btn btn-secondary" id="share-reddit">
                        <i class="bi bi-reddit"></i> Reddit
                    </button>
                    <button class="btn btn-secondary" id="copy-link">
                        <i class="bi bi-link-45deg"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const ARTIST_ID = <?= $artist['id'] ?>;
        const ARTIST_SLUG = '<?= addslashes($artist['slug']) ?>';
        const ENTITY_TYPE = 'artist';
        const API_BASE = '/api/v1';

        // State
        let isLiked = false;
        let currentUser = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            checkAuth();
            loadComments();
            checkUserEngagement();
            setupEventListeners();
        });

        // Check if user is authenticated
        function checkAuth() {
            const token = localStorage.getItem('auth_token');
            if (token) {
                // TODO: Validate token and get user info
                currentUser = { id: 1, name: 'Test User' }; // Mock for now
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Like button
            document.getElementById('like-btn').addEventListener('click', handleLike);

            // Share button
            document.getElementById('share-btn').addEventListener('click', () => {
                document.getElementById('share-modal').classList.add('active');
            });

            // Comment button - scroll to comments
            document.getElementById('comment-btn').addEventListener('click', () => {
                document.querySelector('.comments-section').scrollIntoView({ behavior: 'smooth' });
                document.getElementById('comment-text').focus();
            });

            // Spark button
            document.getElementById('spark-btn').addEventListener('click', () => {
                if (!currentUser) {
                    alert('Please sign in to send sparks');
                    return;
                }
                document.getElementById('spark-modal').classList.add('active');
            });

            // Submit comment
            document.getElementById('submit-comment-btn').addEventListener('click', submitComment);
            document.getElementById('clear-comment-btn').addEventListener('click', () => {
                document.getElementById('comment-text').value = '';
            });

            // Spark modal
            document.getElementById('spark-modal-close').addEventListener('click', closeSparkModal);
            document.getElementById('cancel-spark-btn').addEventListener('click', closeSparkModal);
            document.getElementById('confirm-spark-btn').addEventListener('click', sendSparks);

            // Spark amount buttons
            document.querySelectorAll('.spark-amount-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const amount = parseInt(e.currentTarget.dataset.amount);
                    document.getElementById('custom-spark-amount').value = amount;
                    updateSparkPreview(amount);
                });
            });

            document.getElementById('custom-spark-amount').addEventListener('input', (e) => {
                const amount = parseInt(e.target.value) || 0;
                updateSparkPreview(amount);
            });

            // Share modal
            document.getElementById('share-modal-close').addEventListener('click', () => {
                document.getElementById('share-modal').classList.remove('active');
            });

            document.getElementById('share-facebook').addEventListener('click', () => shareOn('facebook'));
            document.getElementById('share-twitter').addEventListener('click', () => shareOn('twitter'));
            document.getElementById('share-reddit').addEventListener('click', () => shareOn('reddit'));
            document.getElementById('copy-link').addEventListener('click', copyLink);

            // Close modals on background click
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                    }
                });
            });
        }

        // Check if user has already engaged
        async function checkUserEngagement() {
            if (!currentUser) return;

            try {
                const response = await fetch(`${API_BASE}/engagements/check/${ENTITY_TYPE}/${ARTIST_ID}/like`, {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('auth_token')}` }
                });
                const data = await response.json();

                if (data.success && data.data.has_engaged) {
                    isLiked = true;
                    document.getElementById('like-btn').classList.add('active');
                }
            } catch (error) {
                console.error('Error checking engagement:', error);
            }
        }

        // Handle like/unlike
        async function handleLike() {
            if (!currentUser) {
                alert('Please sign in to like this artist');
                return;
            }

            const likeBtn = document.getElementById('like-btn');
            const countEl = likeBtn.querySelector('.engagement-count');
            const currentCount = parseInt(countEl.textContent.replace(/,/g, ''));

            if (isLiked) {
                // Unlike (delete engagement)
                // TODO: Implement unlike via DELETE /engagements/:id
                isLiked = false;
                likeBtn.classList.remove('active');
                countEl.textContent = (currentCount - 1).toLocaleString();
            } else {
                // Like
                try {
                    const response = await fetch(`${API_BASE}/engagements`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                        },
                        body: JSON.stringify({
                            entity_type: ENTITY_TYPE,
                            entity_id: ARTIST_ID,
                            type: 'like'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        isLiked = true;
                        likeBtn.classList.add('active');
                        countEl.textContent = (currentCount + 1).toLocaleString();
                    }
                } catch (error) {
                    console.error('Error liking:', error);
                    alert('Failed to like. Please try again.');
                }
            }
        }

        // Load comments
        async function loadComments() {
            try {
                const response = await fetch(`${API_BASE}/engagements/${ENTITY_TYPE}/${ARTIST_ID}?type=comment&limit=20`);
                const data = await response.json();

                const commentList = document.getElementById('comment-list');

                if (data.success && data.data.length > 0) {
                    commentList.innerHTML = data.data.map(comment => `
                        <div class="comment">
                            <div class="comment-avatar">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div class="comment-content">
                                <div class="comment-header">
                                    <span class="comment-author">User ${comment.user_id}</span>
                                    <span class="comment-time">${timeAgo(comment.created_at)}</span>
                                </div>
                                <p class="comment-text">${escapeHtml(comment.comment_text)}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    commentList.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-chat-dots"></i>
                            <p>No comments yet. Be the first to comment!</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }

        // Submit comment
        async function submitComment() {
            if (!currentUser) {
                alert('Please sign in to comment');
                return;
            }

            const commentText = document.getElementById('comment-text').value.trim();

            if (!commentText) {
                alert('Please enter a comment');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/engagements`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        entity_type: ENTITY_TYPE,
                        entity_id: ARTIST_ID,
                        type: 'comment',
                        comment_text: commentText
                    })
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('comment-text').value = '';
                    loadComments(); // Reload comments

                    // Update comment count
                    const commentBtn = document.getElementById('comment-btn');
                    const countEl = commentBtn.querySelector('.engagement-count');
                    const currentCount = parseInt(countEl.textContent.replace(/,/g, ''));
                    countEl.textContent = (currentCount + 1).toLocaleString();
                }
            } catch (error) {
                console.error('Error submitting comment:', error);
                alert('Failed to post comment. Please try again.');
            }
        }

        // Update spark preview
        function updateSparkPreview(amount) {
            if (amount < 1) {
                document.getElementById('spark-preview').style.display = 'none';
                document.getElementById('confirm-spark-btn').disabled = true;
                return;
            }

            const gross = (amount * 0.01).toFixed(2);
            const fee = (gross * 0.10).toFixed(2);
            const net = (gross - fee).toFixed(2);

            document.getElementById('spark-gross').textContent = `$${gross}`;
            document.getElementById('spark-fee').textContent = `$${fee}`;
            document.getElementById('spark-net').textContent = `$${net}`;
            document.getElementById('spark-preview').style.display = 'block';
            document.getElementById('confirm-spark-btn').disabled = false;
        }

        // Send sparks
        async function sendSparks() {
            const amount = parseInt(document.getElementById('custom-spark-amount').value);

            if (!amount || amount < 1) {
                alert('Please enter a valid spark amount');
                return;
            }

            try {
                // TODO: Implement Stripe payment flow first
                // For now, just call the royalty API
                const response = await fetch(`${API_BASE}/royalties/spark`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        entity_type: ENTITY_TYPE,
                        entity_id: ARTIST_ID,
                        spark_count: amount,
                        payment_reference: 'test_' + Date.now()
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Successfully sent ${amount} sparks!`);
                    closeSparkModal();

                    // Update spark count
                    const sparkBtn = document.getElementById('spark-btn');
                    const countEl = sparkBtn.querySelector('.engagement-count');
                    const currentText = countEl.textContent;
                    const currentCount = parseInt(currentText.match(/\d+/)[0]);
                    countEl.textContent = `(${(currentCount + amount).toLocaleString()})`;
                } else {
                    alert(data.message || 'Failed to send sparks');
                }
            } catch (error) {
                console.error('Error sending sparks:', error);
                alert('Failed to send sparks. Please try again.');
            }
        }

        // Close spark modal
        function closeSparkModal() {
            document.getElementById('spark-modal').classList.remove('active');
            document.getElementById('custom-spark-amount').value = '';
            document.getElementById('spark-preview').style.display = 'none';
            document.getElementById('confirm-spark-btn').disabled = true;
        }

        // Share functions
        function shareOn(platform) {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent(`Check out ${<?= json_encode($artist['name']) ?>} on Next Gen Noise!`);

            let shareUrl;
            switch (platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
                    break;
                case 'reddit':
                    shareUrl = `https://reddit.com/submit?url=${url}&title=${text}`;
                    break;
            }

            window.open(shareUrl, '_blank', 'width=600,height=400');

            // Record share engagement
            recordShare(platform);
        }

        async function recordShare(platform) {
            if (!currentUser) return;

            try {
                await fetch(`${API_BASE}/engagements`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
                    },
                    body: JSON.stringify({
                        entity_type: ENTITY_TYPE,
                        entity_id: ARTIST_ID,
                        type: 'share',
                        share_platform: platform
                    })
                });

                // Update share count
                const shareBtn = document.getElementById('share-btn');
                const countEl = shareBtn.querySelector('.engagement-count');
                const currentCount = parseInt(countEl.textContent.replace(/,/g, ''));
                countEl.textContent = (currentCount + 1).toLocaleString();
            } catch (error) {
                console.error('Error recording share:', error);
            }
        }

        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Link copied to clipboard!');
            });
        }

        // Utility functions
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const seconds = Math.floor((new Date() - date) / 1000);

            let interval = Math.floor(seconds / 31536000);
            if (interval > 1) return interval + ' years ago';

            interval = Math.floor(seconds / 2592000);
            if (interval > 1) return interval + ' months ago';

            interval = Math.floor(seconds / 86400);
            if (interval > 1) return interval + ' days ago';

            interval = Math.floor(seconds / 3600);
            if (interval > 1) return interval + ' hours ago';

            interval = Math.floor(seconds / 60);
            if (interval > 1) return interval + ' minutes ago';

            return 'just now';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>

    <!-- Playlist Data for NGN Player -->
    <script type="application/json" id="playlistData">
    <?= json_encode([
        'tracks' => array_map(function($track) {
            return [
                'id' => (int)$track['id'],
                'title' => $track['title'],
                'artist_name' => $track['artist_name'] ?? 'Unknown Artist',
                'album_name' => $track['album_name'] ?? 'Unknown Album',
                'duration' => (int)($track['duration'] ?? 0),
                'cover_lg' => $track['cover_url'] ? "/uploads/releases/{$track['cover_url']}" : null,
                'cover_md' => $track['cover_url'] ? "/uploads/releases/{$track['cover_url']}" : null,
                'cover_sm' => $track['cover_url'] ? "/uploads/releases/{$track['cover_url']}" : null
            ];
        }, $tracks)
    ]) ?>
    </script>
</body>
</html>
