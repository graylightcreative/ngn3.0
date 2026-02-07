<?php
/**
 * Public Label Profile Page
 * Displays label information with roster and releases
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Engagement\EngagementService;

// Get label slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.1 404 Not Found');
    die('Label not found');
}

$config = new Config();
$pdo = ConnectionFactory::read($config);
$engagementService = new EngagementService($pdo);

// Fetch label data
$stmt = $pdo->prepare("
    SELECT
        l.id, l.name, l.slug, l.image_url, l.bio, l.claimed,
        l.website, l.facebook_url, l.instagram_url, l.city, l.state, l.country
    FROM `ngn_2025`.`labels` l
    WHERE l.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$label = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$label) {
    header('HTTP/1.1 404 Not Found');
    die('Label not found');
}

// Get engagement counts
$counts = [];
try {
    $counts = $engagementService->getCounts('label', (int)$label['id']);
} catch (\Exception $e) {
    $counts = [];
}

// Get roster artists
$artists = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE label_id = :label_id ORDER BY name ASC LIMIT 12");
    $stmt->execute([':label_id' => $label['id']]);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $artists = [];
}

// Get recent releases
$releases = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.slug, r.cover_url, r.release_date, a.name as artist_name
        FROM `ngn_2025`.`releases` r
        JOIN `ngn_2025`.`artists` a ON r.artist_id = a.id
        WHERE r.label_id = :label_id
        ORDER BY r.release_date DESC
        LIMIT 6
    ");
    $stmt->execute([':label_id' => (int)$label['id']]);
    $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $releases = [];
}

// Get recent posts
$posts = [];
try {
    $stmt = $pdo->prepare("SELECT id, slug, title, excerpt, featured_image_url, published_at FROM `ngn_2025`.`posts` WHERE (entity_type = 'label' AND entity_id = :label_id) OR (author_id = :label_id) AND status = 'published' ORDER BY published_at DESC LIMIT 6");
    $stmt->execute([':label_id' => (int)$label['id']]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $posts = [];
}

// Page metadata
$pageTitle = htmlspecialchars($label['name']) . ' | Labels | Next Gen Noise';
$pageDescription = $label['bio'] ? substr(strip_tags($label['bio']), 0, 160) : 'Visit ' . htmlspecialchars($label['name']) . ' on Next Gen Noise';
$pageImage = (!empty($label['image_url']) && !str_starts_with($label['image_url'], '/'))
    ? "/uploads/labels/{$label['image_url']}"
    : ($label['image_url'] ?? '/assets/images/default-label.jpg');

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
                    Claim This Profile & Unleash Your Roster
                </a>
            <?php else: ?>
                <p class="text-muted">Upload your content to populate this section!</p>
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
        .profile-info h1 { font-size: 48px; margin-bottom: 16px; }
        .profile-bio { color: var(--text-secondary); margin-bottom: 24px; max-width: 800px; }

        .section { margin-bottom: 64px; }
        .section h2 { font-size: 32px; margin-bottom: 32px; font-weight: 800; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 32px; }
        .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: all 0.3s; }
        .card:hover { transform: translateY(-8px); border-color: var(--accent); }
        .card-image { width: 100%; aspect-ratio: 1; object-fit: cover; }
        .card-content { padding: 20px; }

        .upsell-placeholder { background: rgba(20, 27, 46, 0.5); border: 2px dashed var(--border); border-radius: 24px; padding: 64px 32px; text-align: center; grid-column: 1 / -1; }
        .upsell-icon { font-size: 48px; color: var(--accent); margin-bottom: 24px; display: block; }
        .btn-claim { display: inline-block; background: var(--accent); color: #000; padding: 16px 32px; border-radius: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; font-size: 14px; margin-top: 20px; }

        @media (max-width: 768px) { .profile-header { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($pageImage) ?>" class="profile-image" alt="<?= htmlspecialchars($label['name']) ?>">
            <div class="profile-info">
                <h1><?= htmlspecialchars($label['name']) ?></h1>
                <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
                    <?php if ($label['city']): ?><span class="badge"><?= htmlspecialchars($label['city']) ?></span><?php endif; ?>
                    <?php if ($label['claimed']): ?><span style="color: var(--accent); font-weight: 700;">✓ VERIFIED LABEL</span><?php endif; ?>
                </div>
                <p class="profile-bio"><?= nl2br(htmlspecialchars($label['bio'] ?: "A dedicated home for music talent. Discover the artists and releases driving this label's unique sound.")) ?></p>
            </div>
        </div>

        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

        <!-- Roster Artists -->
        <div class="section">
            <h2>Unified Roster</h2>
            <div class="grid">
                <?php if (!empty($artists)): ?>
                    <?php foreach ($artists as $artist): ?>
                        <a href="/artist/<?= urlencode($artist['slug']) ?>" class="card">
                            <img src="<?= $artist['image_url'] ? "/uploads/artists/{$artist['image_url']}" : '/assets/images/default-artist.jpg' ?>" class="card-image" alt="<?= htmlspecialchars($artist['name']) ?>">
                            <div class="card-content">
                                <div style="font-weight: 700;"><?= htmlspecialchars($artist['name']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("Artist Roster", "Unify your roster on NGN 2.0. Aggregate your artists' scores, monitor collective reach, and optimize your label's growth.", $label['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Releases -->
        <div class="section">
            <h2>Label Releases</h2>
            <div class="grid">
                <?php if (!empty($releases)): ?>
                    <?php foreach ($releases as $release): ?>
                        <div class="card">
                            <img src="<?= $release['cover_url'] ? "/uploads/releases/{$release['cover_url']}" : '/assets/images/default-release.jpg' ?>" class="card-image" alt="<?= htmlspecialchars($release['title']) ?>">
                            <div class="card-content">
                                <div style="font-weight: 700;"><?= htmlspecialchars($release['title']) ?></div>
                                <div style="color: var(--text-secondary); font-size: 14px;"><?= htmlspecialchars($release['artist_name']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("New Releases", "Showcase your entire catalog. Link merchandise, track royalties, and use our AI Coach to find the perfect release timing.", $label['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Posts -->
        <div class="section">
            <h2>Label News</h2>
            <div class="grid">
                <?php if (!empty($posts)): ?>
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
                                <div style="font-weight: 700;"><?= htmlspecialchars($post['title']) ?></div>
                                <div style="color: var(--text-secondary); font-size: 14px;"><?= $post['published_at'] ? date('M d, Y', strtotime($post['published_at'])) : '' ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php render_upsell_placeholder("Press & News", "Publish label updates, signing announcements, and tour press releases directly to your followers.", $label['claimed']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>