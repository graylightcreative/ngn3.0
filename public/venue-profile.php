<?php
/**
 * Public Venue Profile Page
 * Displays venue information with full engagement features
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Engagement\EngagementService;

// Get venue slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.1 404 Not Found');
    die('Venue not found');
}

$config = new Config();
$pdo = ConnectionFactory::read($config);
$engagementService = new EngagementService($pdo);

// Fetch venue data
$stmt = $pdo->prepare("
    SELECT
        v.id, v.name, v.slug, v.image_url, v.bio, v.capacity, v.city, v.region
    FROM `ngn_2025`.`venues` v
    WHERE v.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$venue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venue) {
    header('HTTP/1.1 404 Not Found');
    die('Venue not found');
}

// Get engagement counts
$counts = [];
try {
    $counts = $engagementService->getCounts('venue', (int)$venue['id']);
} catch (\Exception $e) {
    $counts = [];
}

// Shows table doesn't exist - skip this section
$shows = [];
$pastShows = [];

// Page metadata
$pageTitle = htmlspecialchars($venue['name']) . ' | Venues | Next Gen Noise';
$pageDescription = 'Visit ' . htmlspecialchars($venue['name']) . ' on Next Gen Noise';
$pageImage = $venue['image_url'] ?? '/assets/images/default-venue.jpg';

// Variables for engagement partial
$entity_type = 'venue';
$entity_id = (int)$venue['id'];
$entity_name = $venue['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($venue['name']) ?>">
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
        .social-links { display: flex; gap: 12px; }
        .social-link { padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 8px; color: var(--text-primary); text-decoration: none; transition: all 0.2s; }
        .social-link:hover { background: var(--accent); color: #000; }
        .section { margin-bottom: 48px; }
        .section h2 { font-size: 28px; margin-bottom: 24px; }
        .show-list { display: flex; flex-direction: column; gap: 16px; }
        .show-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; transition: all 0.2s; }
        .show-card:hover { border-color: var(--accent); transform: translateX(4px); }
        .show-date { color: var(--accent); font-weight: 600; margin-bottom: 8px; }
        .show-artist { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
        .show-title { color: var(--text-secondary); }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <img src="<?= htmlspecialchars($venue['image_url'] ?? '/assets/images/default-venue.jpg') ?>" alt="<?= htmlspecialchars($venue['name']) ?>" class="profile-image">
            <div class="profile-info">
                <h1><?= htmlspecialchars($venue['name']) ?></h1>
                <div class="profile-meta">
                    <?php if ($venue['city']): ?>
                        <span><?= htmlspecialchars($venue['city']) ?></span>
                    <?php endif; ?>
                    <?php if ($venue['capacity']): ?>
                        <span style="margin-left: 12px;">Capacity: <?= number_format($venue['capacity']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($venue['bio']): ?>
                    <div class="profile-bio"><?= nl2br(htmlspecialchars($venue['bio'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Engagement UI -->
        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

    </div>
</body>
</html>
