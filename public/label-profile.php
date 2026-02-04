<?php
/**
 * Public Label Profile Page
 * Displays label information with full engagement features
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
    $stmt = $pdo->prepare("SELECT id, name, slug FROM `ngn_2025`.`artists` WHERE label_id = :label_id ORDER BY name ASC LIMIT 12");
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

// Page metadata
$pageTitle = htmlspecialchars($label['name']) . ' | Labels | Next Gen Noise';
$pageDescription = 'Visit ' . htmlspecialchars($label['name']) . ' on Next Gen Noise';
$pageImage = (!empty($label['image_url']) && !str_starts_with($label['image_url'], '/'))
    ? "/uploads/labels/{$label['image_url']}"
    : ($label['image_url'] ?? '/assets/images/default-label.jpg');

// Variables for engagement partial
$entity_type = 'label';
$entity_id = (int)$label['id'];
$entity_name = $label['name'];
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
    <meta property="og:url" content="https://nextgennoise.com/label/<?= htmlspecialchars($slug) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($label['name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">

    <!-- Schema.org Structured Data: Organization (Label) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?= htmlspecialchars($label['name'], ENT_QUOTES) ?>",
        "url": "https://nextgennoise.com/label/<?= htmlspecialchars($slug, ENT_QUOTES) ?>",
        "image": "<?= htmlspecialchars($pageImage, ENT_QUOTES) ?>",
        "description": "<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>"
        <?php
            $sameAs = [];
            if ($label['website']) $sameAs[] = '"' . htmlspecialchars($label['website'], ENT_QUOTES) . '"';
            if ($label['facebook_url']) $sameAs[] = '"' . htmlspecialchars($label['facebook_url'], ENT_QUOTES) . '"';
            if ($label['instagram_url']) $sameAs[] = '"' . htmlspecialchars($label['instagram_url'], ENT_QUOTES) . '"';
            if (!empty($sameAs)):
        ?>,
        "sameAs": [<?= implode(',', $sameAs) ?>]
        <?php endif; ?>
        <?php if (!empty($artists)): ?>,
        "team": [
            <?php $artistData = array_map(function($a) {
                return '{
                    "@type": "MusicGroup",
                    "name": "' . htmlspecialchars($a['name'], ENT_QUOTES) . '",
                    "image": "' . htmlspecialchars($a['image_url'] ?? '/assets/images/default-artist.jpg', ENT_QUOTES) . '"
                }';
            }, array_slice($artists, 0, 5)); ?>
            <?= implode(',', $artistData) ?>
        ]
        <?php endif; ?>
        <?php if ($label['city'] || $label['state'] || $label['country']): ?>,
        "address": {
            "@type": "PostalAddress"
            <?php if ($label['city']): ?>,
            "addressLocality": "<?= htmlspecialchars($label['city'], ENT_QUOTES) ?>"
            <?php endif; ?>
            <?php if ($label['state']): ?>,
            "addressRegion": "<?= htmlspecialchars($label['state'], ENT_QUOTES) ?>"
            <?php endif; ?>
            <?php if ($label['country']): ?>,
            "addressCountry": "<?= htmlspecialchars($label['country'], ENT_QUOTES) ?>"
            <?php endif; ?>
        }
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

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .profile-header {
            display: flex;
            gap: 32px;
            margin-bottom: 40px;
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .profile-image {
            width: 200px;
            height: 200px;
            border-radius: 12px;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .profile-meta {
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .profile-bio {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 24px;
        }

        .social-links {
            display: flex;
            gap: 12px;
        }

        .social-link {
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .social-link:hover {
            background: var(--accent);
            color: #000;
        }

        .section {
            margin-bottom: 48px;
        }

        .section h2 {
            font-size: 28px;
            margin-bottom: 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 24px;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
        }

        .card-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
        }

        .card-content {
            padding: 16px;
        }

        .card-title {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-meta {
            color: var(--text-secondary);
            font-size: 14px;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <?= ngn_image(
                (!empty($label['image_url']) && !str_starts_with($label['image_url'], '/'))
                    ? "/uploads/labels/{$label['image_url']}"
                    : ($label['image_url'] ?? '/assets/images/default-label.jpg'),
                $label['name'],
                'profile-image',
                true,
                'eager'  // Hero image should load eagerly
            ) ?>

            <div class="profile-info">
                <h1><?= htmlspecialchars($label['name']) ?></h1>

                <div class="profile-meta">
                    <?php if ($label['city'] && $label['state']): ?>
                        <span><?= htmlspecialchars($label['city']) ?>, <?= htmlspecialchars($label['state']) ?></span>
                    <?php endif; ?>
                    <?php if ($label['claimed']): ?>
                        <span style="color: var(--accent); margin-left: 12px;">✓ Verified</span>
                    <?php endif; ?>
                </div>

                <?php if ($label['bio']): ?>
                    <div class="profile-bio">
                        <?= nl2br(htmlspecialchars($label['bio'])) ?>
                    </div>
                <?php endif; ?>

                <div class="social-links">
                    <?php if ($label['website']): ?>
                        <a href="<?= htmlspecialchars($label['website']) ?>" target="_blank" class="social-link">
                            <i class="bi bi-globe"></i> Website
                        </a>
                    <?php endif; ?>

                    <?php if ($label['facebook_url']): ?>
                        <a href="<?= htmlspecialchars($label['facebook_url']) ?>" target="_blank" class="social-link">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                    <?php endif; ?>

                    <?php if ($label['instagram_url']): ?>
                        <a href="<?= htmlspecialchars($label['instagram_url']) ?>" target="_blank" class="social-link">
                            <i class="bi bi-instagram"></i> Instagram
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Engagement UI -->
        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

        <!-- Roster Artists -->
        <?php if (!empty($artists)): ?>
        <div class="section">
            <h2>Roster Artists</h2>
            <div class="grid">
                <?php foreach ($artists as $artist): ?>
                    <a href="/artist/<?= urlencode($artist['slug']) ?>" class="card">
                        <?= ngn_image(
                            $artist['image_url'] ?? '/assets/images/default-artist.jpg',
                            $artist['name'],
                            'card-image'
                        ) ?>
                        <div class="card-content">
                            <div class="card-title"><?= htmlspecialchars($artist['name']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Releases -->
        <?php if (!empty($releases)): ?>
        <div class="section">
            <h2>Recent Releases</h2>
            <div class="grid">
                <?php foreach ($releases as $release): ?>
                    <div class="card">
                        <?= ngn_image(
                            $release['cover_url'] ?? '/assets/images/default-release.jpg',
                            $release['title'],
                            'card-image'
                        ) ?>
                        <div class="card-content">
                            <div class="card-title"><?= htmlspecialchars($release['title']) ?></div>
                            <div class="card-meta">
                                <?= htmlspecialchars($release['artist_name']) ?><br>
                                <?= $release['release_date'] ? date('M d, Y', strtotime($release['release_date'])) : 'TBA' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
