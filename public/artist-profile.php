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
    $releases = [];
}

// Get recent videos
$videos = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, slug, thumbnail_url, video_id, video_type FROM `ngn_2025`.`videos` WHERE entity_type = 'artist' AND entity_id = :artist_id LIMIT 6");
    $stmt->execute([':artist_id' => (int)$artist['id']]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $videos = [];
}

// Get recent posts
$posts = [];
try {
    $stmt = $pdo->prepare("SELECT id, slug, title, excerpt, featured_image_url, published_at FROM `ngn_2025`.`posts` WHERE (entity_type = 'artist' AND entity_id = :artist_id) OR (author_id = :artist_id) AND status = 'published' ORDER BY published_at DESC LIMIT 6");
    $stmt->execute([':artist_id' => (int)$artist['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $posts = [];
}

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
    $tracks = [];
}

// Page metadata
$pageTitle = htmlspecialchars($artist['name']) . ' | Artists | Next Gen Noise';
$pageDescription = $artist['bio'] ? substr(strip_tags($artist['bio']), 0, 160) : 'Visit ' . htmlspecialchars($artist['name']) . ' on Next Gen Noise';
$pageImage = (!empty($artist['image_url']) && !str_starts_with($artist['image_url'], '/'))
    ? "/uploads/artists/{$artist['image_url']}"
    : ($artist['image_url'] ?? '/assets/images/default-artist.jpg');

// Function to render placeholder/upsell
function render_upsell_placeholder($title, $description, $claimed) {
    ?>
    <div class="upsell-placeholder">
        <div class="upsell-content">
            <i class="bi bi-rocket-takeoff upsell-icon"></i>
            <h3><?= htmlspecialchars($title) ?></h3>
            <p><?= htmlspecialchars($description) ?></p>
            <?php if (!$claimed): ?>
                <a href="/claim-profile.php?slug=<?= urlencode($GLOBALS['slug']) ?>" class="btn-claim">
                    Claim This Profile to Add <?= htmlspecialchars($title) ?> & Start Earning
                </a>
            <?php else: ?>
                <p class="text-muted">This feature is ready for your first upload!</p>
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

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/lib/images/site/site.webmanifest">
    <meta name="theme-color" content="#0b1020">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="/lib/images/site/favicon.ico">
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
            --brand: #1DB954;
        }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        
        .header { background: var(--bg-secondary); border-bottom: 1px solid var(--border); padding: 16px 0; position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); }
        .header-content { display: flex; align-items: center; justify-content: space-between; }
        .logo { font-size: 24px; font-weight: 800; color: var(--accent); text-decoration: none; }
        .nav { display: flex; gap: 24px; align-items: center; }
        .nav a { color: var(--text-secondary); text-decoration: none; font-weight: 500; }

        .artist-hero { padding: 64px 0; background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); border-bottom: 1px solid var(--border); }
        .artist-hero-content { display: flex; gap: 48px; align-items: center; }
        .artist-image { width: 240px; height: 240px; border-radius: 24px; object-fit: cover; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        .artist-name { font-size: 56px; font-weight: 900; margin-bottom: 12px; letter-spacing: -0.02em; }
        
        .section { padding: 64px 0; border-bottom: 1px solid var(--border); }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .section-title { font-size: 32px; font-weight: 800; letter-spacing: -0.01em; }

        .grid { display: grid; gap: 32px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card:hover { transform: translateY(-8px); border-color: var(--accent); box-shadow: 0 12px 24px rgba(0,0,0,0.2); }
        .card-image { width: 100%; aspect-ratio: 1; object-fit: cover; }
        .card-content { padding: 20px; }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .card-subtitle { color: var(--text-secondary); font-size: 14px; }

        /* Upsell Placeholder */
        .upsell-placeholder {
            background: rgba(20, 27, 46, 0.5);
            border: 2px dashed var(--border);
            border-radius: 24px;
            padding: 64px 32px;
            text-align: center;
            grid-column: 1 / -1;
        }
        .upsell-icon { font-size: 48px; color: var(--accent); margin-bottom: 24px; display: block; }
        .upsell-content h3 { font-size: 24px; margin-bottom: 12px; }
        .upsell-content p { color: var(--text-secondary); margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto; }
        .btn-claim {
            display: inline-block;
            background: var(--accent);
            color: #000;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 14px;
        }
        .btn-claim:hover { transform: scale(1.05); background: #fff; }

        .engagement-bar { display: flex; gap: 16px; margin-top: 32px; }
        .eng-btn { background: var(--bg-tertiary); border: 1px solid var(--border); color: #fff; padding: 12px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .eng-btn:hover { background: var(--border); }
        .eng-btn i { color: var(--accent); }

        @media (max-width: 768px) {
            .artist-hero-content { flex-direction: column; text-align: center; }
            .artist-name { font-size: 36px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <a href="/" class="logo">NGN</a>
            <nav class="nav">
                <a href="/artists">Artists</a>
                <a href="/charts">Charts</a>
                <a href="/posts">News</a>
                <a href="/login.php">Sign In</a>
            </nav>
        </div>
    </header>

    <section class="artist-hero">
        <div class="container">
            <div class="artist-hero-content">
                <img src="<?= htmlspecialchars($pageImage) ?>" class="artist-image" alt="<?= htmlspecialchars($artist['name']) ?>">
                <div class="artist-info">
                    <h1 class="artist-name"><?= htmlspecialchars($artist['name']) ?></h1>
                    <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                        <?php if ($artist['claimed']): ?>
                            <span style="background: rgba(29, 185, 84, 0.1); color: var(--accent); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid var(--accent);">VERIFIED</span>
                        <?php endif; ?>
                        <span style="background: var(--bg-tertiary); color: var(--text-secondary); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;"><?= number_format($counts['eqs_score'] ?? 0) ?> EQS</span>
                    </div>
                    <p class="artist-bio"><?= nl2br(htmlspecialchars($artist['bio'] ?: "One of the emerging sounds on NextGenNoise. Explore their discography and support their journey.")) ?></p>
                    
                    <div class="engagement-bar">
                        <button class="eng-btn"><i class="bi bi-heart-fill"></i> <?= number_format($counts['likes_count'] ?? 0) ?></button>
                        <button class="eng-btn"><i class="bi bi-chat-fill"></i> <?= number_format($counts['comments_count'] ?? 0) ?></button>
                        <button class="eng-btn" style="background: linear-gradient(135deg, #f59e0b, #f97316); border: none; color: #fff;">
                            <i class="bi bi-lightning-charge-fill" style="color: #fff;"></i> Send Sparks
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Top Tracks / Songs -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Top Tracks</h2>
            </div>
            <?php if ($tracks): ?>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($tracks as $track): ?>
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border);">
                            <div style="width: 40px; height: 40px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; cursor: pointer;">
                                <i class="bi bi-play-fill"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($track['title']) ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($track['album_name']) ?></div>
                            </div>
                            <div style="font-size: 14px; color: var(--text-muted);"><?= floor($track['duration'] / 60) ?>:<?= sprintf("%02d", $track['duration'] % 60) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php render_upsell_placeholder("Music & Songs", "Upload your tracks to our global intelligence engine. Let fans listen, tip sparks, and boost your NGN score.", $artist['claimed']); ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Discography -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Discography</h2>
            </div>
            <div class="grid">
                <?php if ($releases): ?>
                    <?php foreach ($releases as $release): ?>
                        <div class="card">
                            <img src="<?= $release['cover_url'] ? "/uploads/releases/{$release['cover_url']}" : '/assets/images/default-release.jpg' ?>" class="card-image" alt="<?= htmlspecialchars($release['title']) ?>">
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($release['title']) ?></h3>
                                <p class="card-subtitle"><?= $release['release_date'] ? date('Y', strtotime($release['release_date'])) : 'TBA' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("Releases", "Manage your albums, EPs, and singles. Link your merch and build your professional discography on NGN 2.0.", $artist['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Latest Posts -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Latest Updates</h2>
            </div>
            <div class="grid">
                <?php if ($posts): ?>
                    <?php foreach ($posts as $post): ?>
                        <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="card" style="text-decoration: none; color: inherit;">
                            <?php 
                                $postImg = $post['featured_image_url'] ?? '';
                                if ($postImg && !str_starts_with($postImg, 'http') && !str_starts_with($postImg, '/')) {
                                    $postImg = "/uploads/{$postImg}";
                                }
                                if (empty($postImg)) $postImg = DEFAULT_AVATAR;
                            ?>
                            <img src="<?= htmlspecialchars($postImg) ?>" class="card-image" alt="<?= htmlspecialchars($post['title']) ?>">
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($post['title']) ?></h3>
                                <p class="card-subtitle"><?= $post['published_at'] ? date('M d, Y', strtotime($post['published_at'])) : '' ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("Social Feed", "Connect with your fans directly. Share behind-the-scenes content, tour dates, and official announcements.", $artist['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Videos -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Videos</h2>
            </div>
            <div class="grid">
                <?php if ($videos): ?>
                    <?php foreach ($videos as $video): ?>
                        <div class="card">
                            <img src="<?= $video['thumbnail_url'] ?>" class="card-image" alt="<?= htmlspecialchars($video['title']) ?>">
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($video['title']) ?></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("Video Content", "Showcase your music videos, interviews, and live performances. Video engagement is a high-weight factor for NGN Rankings.", $artist['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer style="padding: 64px 0; text-align: center; color: var(--text-muted); font-size: 14px; border-top: 1px solid var(--border);">
        <div class="container">
            <p>&copy; <?= date('Y') ?> NextGenNoise. All rights reserved.</p>
        </div>
    </footer>
<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>