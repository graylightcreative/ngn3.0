<?php
/**
 * Public Venue Profile Page
 * Displays venue information with upcoming shows
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
        v.id, v.name, v.slug, v.image_url, v.bio, v.capacity, v.city, v.region, v.user_id
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

// Get upcoming shows
$shows = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.starts_at, s.ticket_url, a.name as artist_name, a.slug as artist_slug
        FROM `ngn_2025`.`shows` s
        LEFT JOIN `ngn_2025`.`artists` a ON s.artist_id = a.id
        WHERE s.venue_id = :venue_id AND s.starts_at > NOW()
        ORDER BY s.starts_at ASC LIMIT 10
    ");
    $stmt->execute([':venue_id' => (int)$venue['id']]);
    $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $shows = [];
}

// Page metadata
$pageTitle = htmlspecialchars($venue['name']) . ' | Venues | Next Gen Noise';
$pageDescription = $venue['bio'] ? substr(strip_tags($venue['bio']), 0, 160) : 'Visit ' . htmlspecialchars($venue['name']) . ' on Next Gen Noise';
$pageImage = $venue['image_url'] ?? '/assets/images/default-venue.jpg';

// Function to render placeholder/upsell
function render_upsell_placeholder($title, $description, $claimed) {
    ?>
    <div class="upsell-placeholder">
        <div class="upsell-content">
            <i class="bi bi-calendar-event upsell-icon"></i>
            <h3><?= htmlspecialchars($title) ?></h3>
            <p><?= htmlspecialchars($description) ?></p>
            <?php if (!$claimed): ?>
                <a href="/claim-profile.php?slug=<?= urlencode($GLOBALS['slug']) ?>" class="btn-claim">
                    Claim This Venue & Start Selling Tickets
                </a>
            <?php else: ?>
                <p class="text-muted">No shows currently scheduled. Stay tuned!</p>
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
        .venue-meta { display: flex; gap: 12px; color: var(--text-secondary); margin-bottom: 16px; }

        .section { margin-bottom: 64px; }
        .section h2 { font-size: 32px; margin-bottom: 32px; font-weight: 800; }

        .show-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 16px; padding: 24px; display: flex; gap: 24px; align-items: center; margin-bottom: 16px; transition: all 0.2s; }
        .show-card:hover { border-color: var(--accent); transform: translateX(8px); }
        .show-date { width: 80px; text-align: center; }
        .date-day { font-size: 32px; font-weight: 900; line-height: 1; color: var(--accent); }
        .date-month { font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }

        .upsell-placeholder { background: rgba(20, 27, 46, 0.5); border: 2px dashed var(--border); border-radius: 24px; padding: 64px 32px; text-align: center; }
        .upsell-icon { font-size: 48px; color: var(--accent); margin-bottom: 24px; display: block; }
        .btn-claim { display: inline-block; background: var(--accent); color: #000; padding: 16px 32px; border-radius: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; font-size: 14px; margin-top: 20px; }

        @media (max-width: 768px) { .profile-header { flex-direction: column; text-align: center; } .show-card { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <img src="<?= htmlspecialchars($pageImage) ?>" class="profile-image" alt="<?= htmlspecialchars($venue['name']) ?>">
            <div class="profile-info">
                <h1><?= htmlspecialchars($venue['name']) ?></h1>
                <div class="venue-meta">
                    <span><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($venue['city']) ?>, <?= htmlspecialchars($venue['region']) ?></span>
                    <span>·</span>
                    <span><i class="bi bi-people-fill"></i> Capacity: <?= number_format($venue['capacity']) ?></span>
                </div>
                <p><?= nl2br(htmlspecialchars($venue['bio'] ?: "A premier destination for live rock and metal. Check out our upcoming calendar and experience the noise.")) ?></p>
            </div>
        </div>

        <?php include __DIR__ . '/lib/partials/engagement-ui.php'; ?>

        <div class="section">
            <h2>Show Calendar</h2>
            <?php if (!empty($shows)): ?>
                <?php foreach ($shows as $show): 
                    $ts = strtotime($show['starts_at']);
                ?>
                    <div class="show-card">
                        <div class="show-date">
                            <div class="date-day"><?= date('d', $ts) ?></div>
                            <div class="date-month"><?= date('M', $ts) ?></div>
                        </div>
                        <div style="flex: 1;">
                            <div style="text-transform: uppercase; font-size: 11px; font-weight: 800; letter-spacing: 0.1em; color: var(--accent); margin-bottom: 4px;">Live Performance</div>
                            <div style="font-size: 20px; font-weight: 800;"><?= htmlspecialchars($show['title']) ?></div>
                            <div style="color: var(--text-secondary);">Featuring: <?= htmlspecialchars($show['artist_name']) ?></div>
                        </div>
                        <?php if ($show['ticket_url']): ?>
                            <a href="<?= htmlspecialchars($show['ticket_url']) ?>" class="btn-claim" style="margin-top: 0; padding: 12px 24px;">Buy Tickets</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php render_upsell_placeholder("Upcoming Events", "Schedule your shows and sell tickets directly through NGN. Automated payouts, fan notifications, and verified check-ins.", !empty($venue['user_id'])); ?>
            <?php endif; ?>
        </div>
    </div>
<?php require dirname(__DIR__) . "/lib/partials/global-footer.php"; ?>

</body>
</html>