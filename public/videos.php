<?php
/**
 * SEO-Optimized Videos Directory Page
 * Browse all music videos with search, filters, and pagination
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Search/filter
$search = $_GET['search'] ?? '';
$artist = $_GET['artist'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = 'v.title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

if ($artist) {
    $where[] = 'a.slug = :artist';
    $params[':artist'] = $artist;
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM ngn_2025.videos v
    LEFT JOIN ngn_legacy_general.Videos lv ON lv.Slug = v.slug
    LEFT JOIN ngn_2025.cdm_identity_map im ON im.legacy_id = lv.ArtistId AND im.entity = 'artist'
    LEFT JOIN ngn_2025.artists a ON a.id = im.cdm_id
    WHERE $whereClause
");
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// Get videos
$stmt = $pdo->prepare("
    SELECT
        v.id, v.slug, v.title, v.platform, v.external_id,
        v.image_url, v.published_at, v.view_count,
        a.id as artist_id, a.name as artist_name, a.slug as artist_slug
    FROM ngn_2025.videos v
    LEFT JOIN ngn_legacy_general.Videos lv ON lv.Slug = v.slug
    LEFT JOIN ngn_2025.cdm_identity_map im ON im.legacy_id = lv.ArtistId AND im.entity = 'artist'
    LEFT JOIN ngn_2025.artists a ON a.id = im.cdm_id
    WHERE $whereClause
    ORDER BY v.published_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page metadata
$pageTitle = $search ? "Search: $search | Videos | Next Gen Noise" : "Music Videos | Next Gen Noise";
$pageDescription = "Discover the latest music videos from independent artists on Next Gen Noise. Watch, like, comment, and support your favorite artists.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="canonical" href="https://nextgennoise.com/videos.php">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://nextgennoise.com/videos.php">

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
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
        }

        .search-bar {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            gap: 12px;
        }

        .search-bar input {
            flex: 1;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .search-bar button {
            padding: 12px 24px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-bar button:hover {
            background: #1ed760;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .video-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .video-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
        }

        .video-thumbnail {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: #000;
        }

        .video-card-content {
            padding: 16px;
        }

        .video-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-artist {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .video-artist:hover {
            color: var(--accent);
        }

        .video-meta {
            display: flex;
            gap: 12px;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 48px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .pagination .active {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .no-results {
            text-align: center;
            padding: 64px 24px;
            color: var(--text-secondary);
        }

        .no-results i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 32px;
            }

            .video-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Music Videos</h1>
            <p>Discover the latest music videos from independent artists</p>
        </div>

        <!-- Search Bar -->
        <form class="search-bar" method="GET" action="">
            <input
                type="text"
                name="search"
                placeholder="Search videos..."
                value="<?= htmlspecialchars($search) ?>"
            >
            <button type="submit">
                <i class="bi bi-search"></i> Search
            </button>
        </form>

        <!-- Videos Grid -->
        <?php if (empty($videos)): ?>
            <div class="no-results">
                <i class="bi bi-camera-video"></i>
                <h2>No videos found</h2>
                <p>Try adjusting your search or browse all videos</p>
            </div>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <a href="/video.php?slug=<?= urlencode($video['slug']) ?>" class="video-card">
                        <img
                            src="<?= htmlspecialchars($video['image_url'] ?? '/assets/images/default-video.jpg') ?>"
                            alt="<?= htmlspecialchars($video['title']) ?>"
                            class="video-thumbnail"
                            loading="lazy"
                        >
                        <div class="video-card-content">
                            <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>

                            <?php if ($video['artist_name']): ?>
                                <div class="video-artist">
                                    <?= htmlspecialchars($video['artist_name']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="video-meta">
                                <span><?= date('M j, Y', strtotime($video['published_at'])) ?></span>
                                <?php if ($video['view_count']): ?>
                                    <span>•</span>
                                    <span><?= number_format($video['view_count']) ?> views</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
