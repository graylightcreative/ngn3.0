<?php
/**
 * SEO-Optimized Video Landing Page
 * Individual video page with full meta tags, engagement, and schema.org markup
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Engagement\EngagementService;

// Get video slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.1 404 Not Found');
    die('Video not found');
}

$config = new Config();
$pdo = ConnectionFactory::read($config);
$engagementService = new EngagementService($pdo);

// Fetch video data
$stmt = $pdo->prepare("
    SELECT
        v.id, v.slug, v.title, v.platform, v.external_id,
        v.image_url, v.published_at, v.view_count, v.created_at,
        a.id as artist_id, a.name as artist_name, a.slug as artist_slug,
        a.image_url as artist_image
    FROM ngn_2025.videos v
    LEFT JOIN ngn_2025.cdm_identity_map im ON im.entity = 'video' AND im.cdm_slug = v.slug
    LEFT JOIN ngn_2025.artists a ON a.id = v.user_id OR (im.legacy_id IS NOT NULL AND im.legacy_id = a.id)
    WHERE v.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    // Try finding by artist association through identity map
    $stmt = $pdo->prepare("
        SELECT
            v.id, v.slug, v.title, v.platform, v.external_id,
            v.image_url, v.published_at, v.view_count, v.created_at,
            a.id as artist_id, a.name as artist_name, a.slug as artist_slug,
            a.image_url as artist_image
        FROM ngn_2025.videos v
        LEFT JOIN ngn_legacy_general.Videos lv ON lv.Slug = v.slug
        LEFT JOIN ngn_2025.cdm_identity_map im ON im.legacy_id = lv.ArtistId AND im.entity = 'artist'
        LEFT JOIN ngn_2025.artists a ON a.id = im.cdm_id
        WHERE v.slug = :slug
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$video) {
    header('HTTP/1.1 404 Not Found');
    die('Video not found');
}

// Get engagement counts
$counts = $engagementService->getCounts('video', (int)$video['id']);

// Build video URLs
$videoUrl = '';
$embedUrl = '';
if ($video['platform'] === 'youtube') {
    $videoUrl = 'https://www.youtube.com/watch?v=' . $video['external_id'];
    $embedUrl = 'https://www.youtube.com/embed/' . $video['external_id'];
} elseif ($video['platform'] === 'vimeo') {
    $videoUrl = 'https://vimeo.com/' . $video['external_id'];
    $embedUrl = 'https://player.vimeo.com/video/' . $video['external_id'];
}

// Page metadata
$pageTitle = htmlspecialchars($video['title']) . ' by ' . htmlspecialchars($video['artist_name'] ?? 'Unknown Artist') . ' | Next Gen Noise';
$pageDescription = 'Watch ' . htmlspecialchars($video['title']) . ' by ' . htmlspecialchars($video['artist_name'] ?? 'Unknown Artist') . ' on Next Gen Noise';
$pageImage = $video['image_url'] ?? '/assets/images/default-video.jpg';
$pageUrl = 'https://nextgennoise.com/video/' . htmlspecialchars($slug);

// Schema.org structured data
$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'VideoObject',
    'name' => $video['title'],
    'description' => $pageDescription,
    'thumbnailUrl' => $pageImage,
    'uploadDate' => date('c', strtotime($video['published_at'])),
    'contentUrl' => $videoUrl,
    'embedUrl' => $embedUrl,
    'interactionStatistic' => [
        [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/WatchAction',
            'userInteractionCount' => $video['view_count'] ?? 0
        ],
        [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/LikeAction',
            'userInteractionCount' => $counts['like_count'] ?? 0
        ],
        [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/CommentAction',
            'userInteractionCount' => $counts['comment_count'] ?? 0
        ]
    ]
];

if ($video['artist_name']) {
    $schemaData['creator'] = [
        '@type' => 'MusicGroup',
        'name' => $video['artist_name'],
        'url' => 'https://nextgennoise.com/artist-profile.php?slug=' . $video['artist_slug']
    ];
}

// Variables for engagement partial
$entity_type = 'video';
$entity_id = (int)$video['id'];
$entity_name = $video['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="<?= $pageUrl ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="video.other">
    <meta property="og:url" content="<?= $pageUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($video['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta property="og:video" content="<?= htmlspecialchars($videoUrl) ?>">
    <meta property="og:video:secure_url" content="<?= htmlspecialchars($videoUrl) ?>">
    <meta property="og:video:type" content="text/html">
    <meta property="og:video:width" content="1280">
    <meta property="og:video:height" content="720">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="player">
    <meta name="twitter:title" content="<?= htmlspecialchars($video['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($pageImage) ?>">
    <meta name="twitter:player" content="<?= htmlspecialchars($embedUrl) ?>">
    <meta name="twitter:player:width" content="1280">
    <meta name="twitter:player:height" content="720">

    <!-- Schema.org structured data -->
    <script type="application/ld+json">
    <?= json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
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

        .video-header {
            margin-bottom: 32px;
        }

        .video-header h1 {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .video-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-secondary);
        }

        .artist-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .artist-link:hover {
            text-decoration: underline;
        }

        .artist-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .video-player {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 32px;
            aspect-ratio: 16 / 9;
        }

        .video-player iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .video-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .info-value {
            color: var(--text-primary);
        }

        .watch-on-platform {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--accent);
            color: #000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            transition: all 0.2s;
            margin-top: 16px;
        }

        .watch-on-platform:hover {
            background: #1ed760;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .video-header h1 {
                font-size: 24px;
            }

            .video-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Video Header -->
        <div class="video-header">
            <h1><?= htmlspecialchars($video['title']) ?></h1>

            <div class="video-meta">
                <?php if ($video['artist_name']): ?>
                    <a href="/artist-profile.php?slug=<?= urlencode($video['artist_slug']) ?>" class="artist-link">
                        <?php if ($video['artist_image']): ?>
                            <img src="<?= htmlspecialchars($video['artist_image']) ?>" alt="<?= htmlspecialchars($video['artist_name']) ?>" class="artist-avatar">
                        <?php endif; ?>
                        <span><?= htmlspecialchars($video['artist_name']) ?></span>
                    </a>
                <?php endif; ?>

                <span>•</span>
                <span><?= date('M j, Y', strtotime($video['published_at'])) ?></span>

                <?php if ($video['view_count']): ?>
                    <span>•</span>
                    <span><?= number_format($video['view_count']) ?> views</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Video Player -->
        <div class="video-player">
            <iframe
                src="<?= htmlspecialchars($embedUrl) ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                loading="lazy"
                title="<?= htmlspecialchars($video['title']) ?>"
            ></iframe>
        </div>

        <!-- Video Info -->
        <div class="video-info">
            <div class="info-row">
                <span class="info-label">Platform</span>
                <span class="info-value"><?= ucfirst($video['platform']) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">Published</span>
                <span class="info-value"><?= date('F j, Y \a\t g:i A', strtotime($video['published_at'])) ?></span>
            </div>

            <?php if ($video['view_count']): ?>
            <div class="info-row">
                <span class="info-label">Views</span>
                <span class="info-value"><?= number_format($video['view_count']) ?></span>
            </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" rel="noopener" class="watch-on-platform">
                <i class="bi bi-play-circle-fill"></i>
                Watch on <?= ucfirst($video['platform']) ?>
            </a>
        </div>

        <!-- Engagement UI -->
        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>
    </div>

    <!-- Track view count -->
    <script>
    // Increment view count when video plays
    const iframe = document.querySelector('.video-player iframe');
    if (iframe) {
        iframe.addEventListener('load', function() {
            // Could implement view tracking here
            console.log('Video loaded: <?= $video['title'] ?>');
        });
    }
    </script>
<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>
