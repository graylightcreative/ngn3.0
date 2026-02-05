<?php
/**
 * NGN 2.0 Frontend App
 * Tailwind-based single-page application
 */

$root = dirname(__DIR__) . '/';
require_once $root . 'lib/bootstrap.php';


use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Commerce\ProductService;

// Default avatar for all entities
define('DEFAULT_AVATAR', '/lib/images/user-default-avatar.jpg');

// Database connections
$pdo = null;
$config = null;
$productService = null;

try {
    $config = new Config();
    $pdo = ConnectionFactory::write($config); // Primary ngn_2025 connection
    
    // Services
    $productService = new ProductService($config);
} catch (\Throwable $e) {
    // Log error or handle gracefully
    error_log("Failed to initialize database connections or services: " . $e->getMessage());
}

// Session & Auth
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$isAdmin = false;
$isLoggedIn = false;
$currentUser = null;
$adminRoleIds = ['1'];
try {
    if ($config) $adminRoleIds = array_map('strval', $config->legacyAdminRoleIds());
} catch (\Throwable $e) {}
if (!empty($_SESSION['User']['role_id'])) {
    $rid = (string)$_SESSION['User']['role_id'];
    $isAdmin = in_array($rid, $adminRoleIds, true);
    $isLoggedIn = true;
    $currentUser = $_SESSION['User'] ?? null;
}

// Router
$view = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'home';
$validViews = ['home', 'artists', 'labels', 'stations', 'venues', 'charts', 'smr-charts', 'posts', 'videos', 'artist', 'label', 'station', 'venue', 'post', 'video', 'releases', 'songs', 'release', 'song', 'shop', 'shops', 'product', 'pricing', '404'];
if (!in_array($view, $validViews, true)) $view = '404';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 24;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Data helpers for ngn_2025 tables
function ngn_query(PDO $pdo, string $table, string $search = '', int $page = 1, int $perPage = 24): array {
    $offset = ($page - 1) * $perPage;
    $where = $search !== '' ? "WHERE name LIKE :search" : '';
    $sql = "SELECT * FROM `ngn_2025`.`{$table}` {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ngn_count(PDO $pdo, string $table, string $search = ''): int {
    $where = $search !== '' ? "WHERE name LIKE :search" : '';
    $sql = "SELECT COUNT(*) FROM `ngn_2025`.`{$table}` {$where}";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function ngn_get(PDO $pdo, string $table, $id): ?array {
    $col = is_numeric($id) ? 'id' : 'slug';
    $sql = "SELECT * FROM `ngn_2025`.`{$table}` WHERE {$col} = :val LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':val', is_numeric($id) ? (int)$id : $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Legacy data helpers (Posts, Videos from primary DB)
function get_ngn_posts(PDO $pdo, string $search = '', int $page = 1, int $perPage = 24): array {
    $offset = ($page - 1) * $perPage;
    $where = "WHERE p.status = 'published'";
    if ($search !== '') $where .= " AND p.title LIKE :search";
    $sql = "SELECT p.id, p.slug, p.title, p.excerpt, p.featured_image_url, p.published_at, p.created_at, p.updated_at, a.name as author_name
            FROM `ngn_2025`.`posts` p
            LEFT JOIN `ngn_2025`.`artists` a ON p.author_id = a.id
            {$where} ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_ngn_posts_count(PDO $pdo, string $search = ''): int {
    $where = "WHERE status = 'published'";
    if ($search !== '') $where .= " AND title LIKE :search";
    $sql = "SELECT COUNT(*) FROM `ngn_2025`.`posts` {$where}";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function legacy_videos(PDO $pdo, string $search = '', int $page = 1, int $perPage = 24): array {
    $offset = ($page - 1) * $perPage;
    $where = "WHERE 1=1";
    if ($search !== '') $where .= " AND v.title LIKE :search";

    $sql = "SELECT v.id, v.slug, v.title, v.description, v.video_type as platform,
                   v.video_id as external_id, v.published_at, v.created_at, v.entity_id as artist_id,
                   a.name as ArtistName, a.slug as ArtistSlug, a.id as ArtistId
            FROM `ngn_2025`.`videos` v
            LEFT JOIN `ngn_2025`.`artists` a ON v.entity_id = a.id AND v.entity_type = 'artist'
            {$where} ORDER BY v.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    // Videos are transformed to match template expectations
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $videos;
}

function legacy_videos_count(PDO $pdo, string $search = ''): int {
    $where = "WHERE 1=1";
    if ($search !== '') $where .= " AND title LIKE :search";
    $sql = "SELECT COUNT(*) FROM `ngn_2025`.`videos` {$where}";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_trending_artists(PDO $pdo, int $limit = 5): array {
    // Identify trending artists based on recent engagements
    $sql = "
        SELECT a.id, a.name, a.slug, a.image_url, COUNT(ce.id) as engagement_count
        FROM `ngn_2025`.`artists` a
        JOIN `ngn_2025`.`cdm_engagements` ce ON a.id = ce.artist_id
        WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) -- Engagements in last 7 days
        GROUP BY a.id, a.name, a.slug, a.image_url
        ORDER BY engagement_count DESC
        LIMIT :limit
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function is_subscribed(PDO $pdo, int $userId, int $artistId, int $tierId): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM `ngn_2025`.`user_fan_subscriptions` 
            WHERE user_id = ? AND artist_id = ? AND tier_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId, $artistId, $tierId]);
        return $stmt->fetch() !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

// Get user image path
function user_image(string $slug, ?string $image): string {
    if (empty($image)) return DEFAULT_AVATAR;
    return "/uploads/users/{$slug}/{$image}";
}

// Fetch data based on view
$data = [];
$total = 0;
$entity = null;
$counts = ['artists' => 0, 'labels' => 0, 'stations' => 0, 'venues' => 0, 'posts' => 0, 'videos' => 0];

if ($pdo) {
    try {
        // Get counts for sidebar (ngn_2025 for entities, ngn_2025 for posts/videos)
        $counts['artists'] = ngn_count($pdo, 'artists');
        $counts['labels'] = ngn_count($pdo, 'labels');
        $counts['stations'] = ngn_count($pdo, 'stations');
        $counts['venues'] = ngn_count($pdo, 'venues');
        // Content counts
        $counts['posts'] = ngn_count($pdo, 'posts');
        $counts['videos'] = ngn_count($pdo, 'videos');
        $counts['releases'] = ngn_count($pdo, 'releases');
        $counts['songs'] = ngn_count($pdo, 'tracks');

        // Fetch view-specific data
        if ($view === 'home') {
            $data['artists'] = ngn_query($pdo, 'artists', '', 1, 8);
            $data['labels'] = ngn_query($pdo, 'labels', '', 1, 6);
            $data['stations'] = ngn_query($pdo, 'stations', '', 1, 4);
            $data['venues'] = ngn_query($pdo, 'venues', '', 1, 4);
            $data['trending_artists'] = get_trending_artists($pdo, 5);
            // Recent posts and videos for home (now from ngn_2025 database)
            $data['posts'] = get_ngn_posts($pdo, '', 1, 4);
            $data['videos'] = legacy_videos($pdo, '', 1, 4); // Use refactored legacy_videos with $pdo

            // Chart data for home (NGN Rankings from ngn_rankings_2025)
            $data['artist_rankings'] = [];
            $data['label_rankings'] = [];
            
            // Get top 10 artist rankings (latest window available)
            try {
                $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                $stmt = $rankingsPdo->prepare('SELECT ri.entity_id, a.name AS Name, ri.score AS Score, a.slug, a.image_url 
                                         FROM `ngn_rankings_2025`.`ranking_items` ri
                                         JOIN `ngn_2025`.`artists` a ON ri.entity_id = a.id
                                         WHERE ri.entity_type = \'artist\' AND ri.window_id = (SELECT MAX(window_id) FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = \'artist\')
                                         ORDER BY ri.rank ASC LIMIT 10');
                $stmt->execute();
                $data['artist_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log("Error fetching artist rankings: " . $e->getMessage());
            }

            // Get top 10 label rankings (latest window available)
            try {
                $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                $stmt = $rankingsPdo->prepare('SELECT ri.entity_id, l.name AS Name, ri.score AS Score, l.slug, l.image_url 
                                         FROM `ngn_rankings_2025`.`ranking_items` ri
                                         JOIN `ngn_2025`.`labels` l ON ri.entity_id = l.id
                                         WHERE ri.entity_type = \'label\' AND ri.window_id = (SELECT MAX(window_id) FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = \'label\')
                                         ORDER BY ri.rank ASC LIMIT 10');
                $stmt->execute();
                $data['label_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log("Error fetching label rankings: " . $e->getMessage());
            }

            // SMR Chart data for home - Not currently configured (will be handled in smr-charts view)
            $data['smr_charts'] = [];
            $data['smr_date'] = null;
        } elseif (in_array($view, ['artists', 'labels', 'stations', 'venues'])) {
            $table = $view;  // Direct table names: artists, labels, stations, venues
            $data[$view] = ngn_query($pdo, $table, $search, $page, $perPage);
            $total = ngn_count($pdo, $table, $search);
        } elseif ($view === 'posts') { // Removed legacyPdo check
            $data['posts'] = get_ngn_posts($pdo, $search, $page, $perPage);
            $total = get_ngn_posts_count($pdo, $search);
        } elseif ($view === 'videos') { // Removed legacyPdo check
            $data['videos'] = legacy_videos($pdo, $search, $page, $perPage); // Use $pdo for consistency
            $total = legacy_videos_count($pdo, $search);
        } elseif ($view === 'video' && (isset($_GET['slug']) || isset($_GET['id']))) {
            $identifier = trim($_GET['slug'] ?? $_GET['id']);
            $stmt = $pdo->prepare('SELECT id, slug, title, description, video_type as platform, video_id as external_id, published_at, created_at, entity_id FROM `ngn_2025`.`videos` WHERE (slug = :id OR id = :id) LIMIT 1');
            $stmt->execute([':id' => $identifier]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($video) {
                // Get artist info (author)
                $author_id = $video['entity_id'] ?? null;
                if ($author_id) {
                    try {
                        $userStmt = $pdo->prepare('SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1');
                        $userStmt->execute([$author_id]);
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        if ($user) {
                            $video['author_entity'] = [
                                'name' => $user['name'],
                                'slug' => $user['slug']
                            ];
                        }
                    } catch (\Throwable $e) {
                        error_log("Error fetching video author: " . $e->getMessage());
                    }
                }

                // Transform to match template expectations
                $video['is_locked'] = false; 
                $data['video'] = $video;
            }
        } elseif ($view === 'releases') {
            $offset = ($page - 1) * $perPage;
            $where = $search !== '' ? "WHERE r.title LIKE :search" : '';
            $sql = "SELECT r.*, a.name as artist_name FROM `ngn_2025`.`releases` r 
                    LEFT JOIN `ngn_2025`.`artists` a ON r.artist_id = a.id 
                    {$where} ORDER BY r.release_date DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data['releases'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            $sqlCount = "SELECT COUNT(*) FROM `ngn_2025`.`releases` r {$where}";
            $stmtCount = $pdo->prepare($sqlCount);
            if ($search !== '') $stmtCount->bindValue(':search', '%'.$search.'%');
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();
        } elseif ($view === 'songs') {
            $offset = ($page - 1) * $perPage;
            $where = $search !== '' ? "WHERE t.title LIKE :search" : '';
            $sql = "SELECT t.*, a.name as artist_name, r.title as release_name, r.cover_url 
                    FROM `ngn_2025`.`tracks` t 
                    LEFT JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id 
                    LEFT JOIN `ngn_2025`.`releases` r ON t.release_id = r.id
                    {$where} ORDER BY t.id DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data['songs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            $sqlCount = "SELECT COUNT(*) FROM `ngn_2025`.`tracks` t {$where}";
            $stmtCount = $pdo->prepare($sqlCount);
            if ($search !== '') $stmtCount->bindValue(':search', '%'.$search.'%');
            $stmtCount->execute();
            $total = (int)$stmtCount->fetchColumn();
        } elseif ($view === 'charts') {
            // NGN Rankings from ngn_rankings_2025 database
            $chartType = $_GET['type'] ?? 'artists';
            $data['chart_type'] = $chartType;
            $data['artist_rankings'] = [];
            $data['label_rankings'] = [];

            // Get top 100 artist rankings
            try {
                $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                $stmt = $rankingsPdo->prepare('SELECT ri.entity_id, a.name AS Name, ri.score AS Score, a.slug, a.image_url 
                                         FROM `ngn_rankings_2025`.`ranking_items` ri
                                         JOIN `ngn_2025`.`artists` a ON ri.entity_id = a.id
                                         WHERE ri.entity_type = \'artist\' AND ri.window_id = (SELECT MAX(window_id) FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = \'artist\')
                                         ORDER BY ri.rank ASC LIMIT 100');
                $stmt->execute();
                $data['artist_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log("Error fetching artist rankings: " . $e->getMessage());
            }

            // Get top 100 label rankings
            try {
                $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                $stmt = $rankingsPdo->prepare('SELECT ri.entity_id, l.name AS Name, ri.score AS Score, l.slug, l.image_url 
                                         FROM `ngn_rankings_2025`.`ranking_items` ri
                                         JOIN `ngn_2025`.`labels` l ON ri.entity_id = l.id
                                         WHERE ri.entity_type = \'label\' AND ri.window_id = (SELECT MAX(window_id) FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = \'label\')
                                         ORDER BY ri.rank ASC LIMIT 100');
                $stmt->execute();
                $data['label_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log("Error fetching label rankings: " . $e->getMessage());
            }
        } elseif ($view === 'smr-charts') {
            // SMR Charts from ngn_smr_2025 database
            $data['smr_charts'] = [];
            $data['smr_date'] = null;

            try {
                $smrPdo = ConnectionFactory::named($config, 'smr2025'); // Get the smr2025 connection
                // Get most recent chart date
                $stmt = $smrPdo->query('SELECT MAX(window_date) as latest FROM `ngn_smr_2025`.`smr_chart`');
                $latest = $stmt->fetch(PDO::FETCH_ASSOC);
                $latestDate = $latest['latest'] ?? null;

                if ($latestDate) {
                    $data['smr_date'] = date('F j, Y', strtotime($latestDate));

                    // Get top 100 songs from latest chart date
                    $stmt = $smrPdo->prepare('SELECT sc.*, a.name AS artist_name, a.slug AS artist_slug, a.image_url AS artist_image_url
                                             FROM `ngn_smr_2025`.`smr_chart` sc
                                             LEFT JOIN `ngn_2025`.`artists` a ON sc.artist_id = a.id
                                             WHERE DATE(sc.window_date) = DATE(?) ORDER BY sc.rank ASC LIMIT 100');
                    $stmt->execute([$latestDate]);
                    $smrData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Transform to expected output
                    foreach ($smrData as $row) {
                        $row['TWP'] = $row['rank']; // Map 'rank' to 'TWP'
                        $row['LWP'] = $row['prev_rank'] ?? '-'; // Map 'prev_rank' to 'LWP'
                        $row['Artists'] = $row['artist_name'] ?? $row['artist']; // Use artist_name from join, fallback to raw artist
                        $row['Song'] = $row['track'];
                        $row['Label'] = $row['label'];
                        $row['WOC'] = $row['woc'] ?? '-'; // Assuming 'woc' exists or needs calculation
                        $row['artist'] = [ // Structure for template
                            'id' => $row['artist_id'],
                            'name' => $row['artist_name'],
                            'slug' => $row['artist_slug'],
                            'image_url' => $row['artist_image_url']
                        ];
                        $data['smr_charts'][] = $row;
                    }
                }
            } catch (\Throwable $e) {
                error_log("Error fetching SMR charts: " . $e->getMessage());
                $data['smr_charts'] = [];
            }
        } elseif ($view === 'release' && isset($_GET['slug'])) {
            $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`releases` WHERE slug = ? LIMIT 1');
            $stmt->execute([trim($_GET['slug'])]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($release) {
                // Get tracks
                $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`tracks` WHERE release_id = ? ORDER BY track_number ASC');
                $stmt->execute([$release['id']]);
                $release['tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                
                // Get artist
                $stmt = $pdo->prepare('SELECT id, name, slug FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1');
                $stmt->execute([$release['artist_id']]);
                $release['artist'] = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $data['release'] = $release;
            }
        } elseif ($view === 'song' && isset($_GET['slug'])) {
            $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`tracks` WHERE slug = ? LIMIT 1');
            $stmt->execute([trim($_GET['slug'])]);
            $track = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($track) {
                $data['track'] = $track;
            }
        } elseif ($view === 'pricing') {
            // Pricing page - show all tiers
            $data['tiers'] = [
                'free' => [
                    'name' => 'Free',
                    'price' => 0,
                    'billing' => 'Forever free',
                    'cta' => 'Current Plan',
                    'cta_url' => '/register.php',
                    'description' => 'Perfect for getting started',
                    'features' => [
                        'Basic Profile' => true,
                        'Upload Content' => true,
                        'View Charts' => true,
                        'Basic Analytics' => false,
                        'Priority Support' => false,
                        'Custom Domain' => false,
                        'API Access' => false,
                        'Remove Ads' => false,
                    ]
                ],
                'pro' => [
                    'name' => 'Pro',
                    'price' => 9.99,
                    'billing' => '/month (artist, station)',
                    'price_label' => '$9.99',
                    'label_price' => 29.99,
                    'label_price_label' => '$29.99',
                    'venue_price' => 19.99,
                    'venue_price_label' => '$19.99',
                    'cta' => 'Upgrade to Pro',
                    'cta_url' => '#upgrade-pro',
                    'description' => 'For serious creators',
                    'popular' => true,
                    'features' => [
                        'Basic Profile' => true,
                        'Upload Content' => true,
                        'View Charts' => true,
                        'Basic Analytics' => true,
                        'Priority Support' => true,
                        'Custom Domain' => false,
                        'API Access' => false,
                        'Remove Ads' => true,
                    ]
                ],
                'premium' => [
                    'name' => 'Premium',
                    'price' => 24.99,
                    'billing' => '/month (artist, station)',
                    'price_label' => '$24.99',
                    'label_price' => 79.99,
                    'label_price_label' => '$79.99',
                    'venue_price' => 49.99,
                    'venue_price_label' => '$49.99',
                    'cta' => 'Upgrade to Premium',
                    'cta_url' => '#upgrade-premium',
                    'description' => 'For scaling your business',
                    'features' => [
                        'Basic Profile' => true,
                        'Upload Content' => true,
                        'View Charts' => true,
                        'Basic Analytics' => true,
                        'Priority Support' => true,
                        'Custom Domain' => true,
                        'API Access' => true,
                        'Remove Ads' => true,
                    ]
                ],
                'enterprise' => [
                    'name' => 'Enterprise',
                    'price_label' => 'Custom',
                    'billing' => 'Custom pricing',
                    'cta' => 'Contact Sales',
                    'cta_url' => 'mailto:sales@nextgennoise.com',
                    'description' => 'For enterprise needs',
                    'features' => [
                        'Basic Profile' => true,
                        'Upload Content' => true,
                        'View Charts' => true,
                        'Basic Analytics' => true,
                        'Priority Support' => true,
                        'Custom Domain' => true,
                        'API Access' => true,
                        'Remove Ads' => true,
                    ]
                ]
            ];
        } elseif ($view === 'post' && (isset($_GET['slug']) || isset($_GET['id']))) {
            $identifier = trim($_GET['slug'] ?? $_GET['id']);
            $stmt = $pdo->prepare('SELECT id, slug, title, excerpt, content as body, tags, featured_image_url, published_at, created_at, updated_at, author_id, required_tier_id, entity_type, entity_id FROM `ngn_2025`.`posts` WHERE (slug = :id OR id = :id) AND status = :status LIMIT 1');
            $stmt->execute([':id' => $identifier, ':status' => 'published']);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                $isLocked = false;
                if (!empty($post['required_tier_id']) && $currentUser) {
                    // Assuming the post entity_type is 'artist' for tier check with user_fan_subscriptions
                    // is_subscribed function already refactored to use ngn_2025.user_fan_subscriptions
                    $isSubscribed = is_subscribed($pdo, (int)$currentUser['id'], (int)$post['author_id'], (int)$post['required_tier_id']);
                    
                    if (!$isSubscribed) {
                        $isLocked = true;
                        $post['content'] = 'This content is exclusive to subscribers.';
                    }
                }
                $post['is_locked'] = $isLocked;

                // Get author info
                if (!empty($post['author_id'])) { // Changed from entity_type/entity_id to author_id
                    // Assume author_id maps to an artist in ngn_2025.artists
                    $author_table = 'artists'; 
                    $authorStmt = $pdo->prepare("SELECT id, name, slug, image_url FROM `ngn_2025`.`{$author_table}` WHERE id = ? LIMIT 1");
                    $authorStmt->execute([$post['author_id']]);
                    $post['author_entity'] = $authorStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                $data['post'] = $post;
            }
        } elseif (in_array($view, ['artist', 'label', 'station', 'venue']) && (isset($_GET['id']) || isset($_GET['slug']))) {
            $entity = null;
            $entityTable = $view . 's'; // artists, labels, stations, venues

            // Lookup by ID
            if (isset($_GET['id'])) {
                $entity = ngn_get($pdo, $entityTable, $_GET['id']);
            } 
            // Lookup by slug (always against ngn_2025.entities)
            elseif (isset($_GET['slug'])) {
                $slug = trim($_GET['slug']);
                $entity = ngn_get($pdo, $entityTable, $slug);
            }

            // Debug mode
            $debugEntity = isset($_GET['debug']);

            // Enrich entity with ngn_2025 data
            if ($entity) {
                // Get user info linked to this entity
                if (!empty($entity['user_id'])) {
                    $stmt = $pdo->prepare('SELECT id, display_name, username, avatar_url, email, role_id FROM `ngn_2025`.`users` WHERE id = ? LIMIT 1');
                    $stmt->execute([$entity['user_id']]);
                    $entity['user_profile'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                if ($debugEntity) {
                    $entity['_debug'] = [
                        'ngn2025_id' => $entity['id'] ?? null,
                        'user_id' => $entity['user_id'] ?? null,
                        'slug' => $entity['slug'] ?? null,
                        'view_type' => $view,
                    ];
                }

                // Get NGN scores for artists/labels (from ngn_rankings_2025)
                if (in_array($view, ['artist', 'label'])) {
                    try {
                        $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                        $stmt = $rankingsPdo->prepare('SELECT score FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = :entityType AND entity_id = :entityId ORDER BY window_id DESC LIMIT 1');
                        $stmt->execute([':entityType' => $view, ':entityId' => $entity['id']]);
                        $rankingData = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($rankingData) {
                            $entity['scores'] = ['Score' => $rankingData['score']]; // Map to old key for template
                        }
                    } catch (\Throwable $e) {
                        error_log("Error fetching NGN score for {$view} {$entity['id']}: " . $e->getMessage());
                    }
                }

                // Get social links from oauth_tokens
                try {
                    $stmt = $pdo->prepare('SELECT provider, access_token, refresh_token FROM `ngn_2025`.`oauth_tokens` WHERE entity_type = :entityType AND entity_id = :entityId');
                    $stmt->execute([':entityType' => $view, ':entityId' => $entity['id']]);
                    $oauthTokens = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $entity['social_links'] = [];
                    foreach ($oauthTokens as $token) {
                        $provider = strtolower($token['provider']);
                        // Map provider to legacy keys used in templates
                        if ($provider === 'facebook') $entity['legacy']['FacebookUrl'] = $token['access_token']; // Note: access_token might be used as URL or we might need actual profile URL
                        if ($provider === 'instagram') $entity['legacy']['InstagramUrl'] = $token['access_token'];
                        if ($provider === 'spotify') $entity['legacy']['SpotifyUrl'] = $token['access_token'];
                        $entity['social_links'][$provider] = $token['access_token'];
                    }
                } catch (\Throwable $e) {
                    error_log("Error fetching social links for {$view} {$entity['id']}: " . $e->getMessage());
                }

                // Get releases for artists (with tracks)
                if ($view === 'artist') {
                    $stmt = $pdo->prepare('SELECT r.id, r.title, r.slug, r.cover_image_url, r.release_date, t.id AS track_id, t.title AS track_title, t.duration_seconds FROM `ngn_2025`.`releases` r LEFT JOIN `ngn_2025`.`tracks` t ON r.id = t.release_id WHERE r.artist_id = ? ORDER BY r.release_date DESC LIMIT 24');
                    $stmt->execute([$entity['id']]);
                    $releases_raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $releases = [];
                    foreach ($releases_raw as $row) {
                        $releaseId = $row['id'];
                        if (!isset($releases[$releaseId])) {
                            $releases[$releaseId] = [
                                'id' => $row['id'],
                                'title' => $row['title'],
                                'slug' => $row['slug'],
                                'cover_image_url' => $row['cover_image_url'],
                                'release_date' => $row['release_date'],
                                'tracks' => []
                            ];
                        }
                        if ($row['track_id']) {
                            $releases[$releaseId]['tracks'][] = [
                                'id' => $row['track_id'],
                                'title' => $row['track_title'],
                                'duration_seconds' => $row['duration_seconds']
                            ];
                        }
                    }
                    $entity['releases'] = array_values($releases);
                    
                    // Get all songs by this artist (for "top tracks" section)
                    $stmt = $pdo->prepare('SELECT t.id, t.title, t.duration_seconds, r.title as ReleaseName, r.release_date FROM `ngn_2025`.`tracks` t JOIN `ngn_2025`.`releases` r ON t.release_id = r.id WHERE r.artist_id = ? ORDER BY t.id DESC LIMIT 50');
                    $stmt->execute([$entity['id']]);
                    $entity['all_songs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get all videos (unlimited)
                    $stmt = $pdo->prepare('SELECT id, slug, title, platform, external_id, published_at FROM `ngn_2025`.`videos` WHERE artist_id = ? ORDER BY published_at DESC');
                    $stmt->execute([$entity['id']]);
                    $entity['all_videos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get upcoming and past shows
                    $stmt = $pdo->prepare('SELECT s.id, s.title, s.starts_at, s.venue_id, v.name as venue_name FROM `ngn_2025`.`shows` s LEFT JOIN `ngn_2025`.`venues` v ON s.venue_id = v.id WHERE s.artist_id = ? ORDER BY s.starts_at DESC LIMIT 30');
                    $stmt->execute([$entity['id']]);
                    $entity['all_shows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get past shows count
                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `ngn_2025`.`shows` WHERE artist_id = ? AND starts_at < NOW()');
                    $stmt->execute([$entity['id']]);
                    $entity['past_shows_count'] = $stmt->fetchColumn();

                    // Get collaborators (other artists on same labels or releases)
                    // This logic is complex and might need a dedicated service. For now, simplifying.
                    $entity['collaborators'] = []; // Placeholder

                    // Get artist mentions/references in posts
                    $stmt = $pdo->prepare('SELECT id, slug, title, published_at FROM `ngn_2025`.`posts` WHERE author_id = :authorId OR (title LIKE :namePattern OR body LIKE :namePattern OR tags LIKE :namePattern) ORDER BY published_at DESC LIMIT 20');
                    $stmt->execute([':authorId' => $entity['id'], ':namePattern' => '%' . $entity['name'] . '%']);
                    $entity['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get label information
                    $stmt = $pdo->prepare('SELECT l.id, l.name, l.slug, l.image_url FROM `ngn_2025`.`labels` l JOIN `ngn_2025`.`artists` a ON l.id = a.label_id WHERE a.id = ? LIMIT 1');
                    $stmt->execute([$entity['id']]);
                    $entity['label_info'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    
                    // Get NGN chart rankings for this artist (already fetched at higher scope)
                    // SMR rankings handled by smr-charts view

                    // Get streaming URLs from releases (Spotify, Apple Music, etc)
                    $streaming_urls = []; // Populate from releases.listening_url, releases.watch_url if present
                    foreach ($entity['releases'] as $release) {
                        if (!empty($release['listening_url'])) {
                            $streaming_urls['listening'] = $release['listening_url'];
                        }
                        if (!empty($release['watch_url'])) {
                            $streaming_urls['watching'] = $release['watch_url'];
                        }
                    }
                    $entity['streaming_urls'] = $streaming_urls;
                    
                    // Calculate engagement metrics
                    $entity['engagement_metrics'] = [
                        'total_videos' => count($entity['all_videos'] ?? []),
                        'total_songs' => count($entity['all_songs'] ?? []),
                        'total_releases' => count($entity['releases'] ?? []),
                        'total_shows' => count($entity['all_shows'] ?? []),
                        'total_posts' => count($entity['posts'] ?? []),
                    ];
                }

                // Get data for labels
                if ($view === 'label') {
                    // Artist roster
                    $stmt = $pdo->prepare('SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE label_id = ? AND status = \'active\' ORDER BY name ASC LIMIT 24');
                    $stmt->execute([$entity['id']]);
                    $entity['roster'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Label releases (from all artists on the label)
                    $stmt = $pdo->prepare('SELECT r.id, r.title, r.slug, r.release_date, r.cover_image_url, a.name as ArtistName, a.slug as ArtistSlug FROM `ngn_2025`.`releases` r JOIN `ngn_2025`.`artists` a ON r.artist_id = a.id WHERE a.label_id = ? ORDER BY r.release_date DESC LIMIT 12');
                    $stmt->execute([$entity['id']]);
                    $entity['releases'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Label videos (from all artists on the label)
                    $stmt = $pdo->prepare('SELECT v.id, v.title, v.slug, v.platform, v.external_id, v.published_at, a.name as ArtistName, a.slug as ArtistSlug FROM `ngn_2025`.`videos` v JOIN `ngn_2025`.`artists` a ON v.artist_id = a.id WHERE a.label_id = ? ORDER BY v.published_at DESC LIMIT 8');
                    $stmt->execute([$entity['id']]);
                    $entity['videos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Posts mentioning this label
                    $stmt = $pdo->prepare('SELECT id, slug, title, published_at FROM `ngn_2025`.`posts` WHERE (title LIKE :labelNamePattern OR body LIKE :labelNamePattern OR tags LIKE :labelNamePattern) ORDER BY published_at DESC LIMIT 20');
                    $stmt->execute([':labelNamePattern' => '%' . $entity['name'] . '%']);
                    $entity['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Get data for venues (already refactored this block previously)
                if ($view === 'venue') {
                    // Upcoming shows hosted by this venue
                    $stmt = $pdo->prepare('SELECT s.* FROM `ngn_2025`.`shows` s WHERE s.venue_id = ? AND s.starts_at > NOW() ORDER BY s.starts_at ASC LIMIT 30');
                    $stmt->execute([$entity['id']]);
                    $entity['upcoming_shows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Past shows at this venue
                    $stmt = $pdo->prepare('SELECT s.* FROM `ngn_2025`.`shows` s WHERE s.venue_id = ? AND s.starts_at <= NOW() ORDER BY s.starts_at DESC LIMIT 20');
                    $stmt->execute([$entity['id']]);
                    $entity['past_shows'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // All shows (for count)
                    $entity['all_shows'] = array_merge($entity['upcoming_shows'] ?? [], $entity['past_shows'] ?? []);

                    // Videos of shows at this venue
                    $stmt = $pdo->prepare('SELECT v.* FROM `ngn_2025`.`videos` v WHERE v.entity_type = ? AND v.entity_id = ? ORDER BY v.created_at DESC LIMIT 8');
                    $stmt->execute(['venue', $entity['id']]);
                    $entity['videos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Posts about this venue
                    $stmt = $pdo->prepare('SELECT p.* FROM `ngn_2025`.`posts` p WHERE p.entity_type = ? AND p.entity_id = ? AND p.status = ? ORDER BY p.published_at DESC LIMIT 10');
                    $stmt->execute(['venue', $entity['id'], 'published']);
                    $entity['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Engagement metrics for venue
                    $entity['engagement_metrics'] = [
                        'total_upcoming_shows' => count($entity['upcoming_shows'] ?? []),
                        'total_past_shows' => count($entity['past_shows'] ?? []),
                        'total_videos' => count($entity['videos'] ?? []),
                        'total_posts' => count($entity['posts'] ?? []),
                    ];
                }

                // Get data for stations
                if ($view === 'station') {
                    // Fetch recent spins from SMR
                    try {
                        $smrPdo = ConnectionFactory::named($config, 'smr2025');
                        $stmt = $smrPdo->prepare('
                            SELECT sc.track AS Song, sc.artist AS Artist, sc.window_date AS chart_date
                            FROM `ngn_smr_2025`.`smr_chart` sc
                            WHERE sc.station_id = :stationId
                            ORDER BY sc.window_date DESC LIMIT 20
                        ');
                        $stmt->execute([':stationId' => $entity['id']]);
                        $entity['smr_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $e) {
                        error_log("Error fetching station spins: " . $e->getMessage());
                    }

                    // Posts mentioning this station
                    $stmt = $pdo->prepare('SELECT id, slug, title, published_at FROM `ngn_2025`.`posts` WHERE (title LIKE :stationNamePattern OR body LIKE :stationNamePattern OR tags LIKE :stationNamePattern) ORDER BY published_at DESC LIMIT 10');
                    $stmt->execute([':stationNamePattern' => '%' . $entity['name'] . '%']);
                    $entity['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Unified Chart History Fetching
                if (empty($entity['chart_rankings']) && in_array($view, ['artist', 'label', 'venue', 'station'])) {
                    try {
                        $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                        $stmt = $rankingsPdo->prepare('
                            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS Interval
                            FROM `ngn_rankings_2025`.`ranking_items` ri
                            JOIN `ngn_rankings_2025`.`ranking_windows` rw ON ri.window_id = rw.id
                            WHERE ri.entity_type = :entityType AND ri.entity_id = :entityId
                            ORDER BY rw.period_end DESC, rw.id DESC LIMIT 10
                        ');
                        $stmt->execute([':entityType' => $view, ':entityId' => $entity['id']]);
                        $entity['chart_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $e) {
                        error_log("Error fetching chart history for {$view}: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $data['error'] = $e->getMessage();
    }
}

$totalPages = $total > 0 ? ceil($total / $perPage) : 1;

// SEO Meta
$seoTitle = 'NextGenNoise - Metal & Rock Music Platform';
$seoDesc = 'Discover the best in metal and rock music. Charts, artists, labels, stations, venues, and more.';
$seoImage = '/lib/images/site/og-image.jpg';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'nextgennoise.com');
$seoUrl = $baseUrl . '/';

if ($view === 'post' && !empty($data['post'])) {
    $seoTitle = htmlspecialchars($data['post']['title'] ?? '') . ' | NextGenNoise';
    $seoDesc = htmlspecialchars(substr(strip_tags($data['post']['excerpt'] ?? $data['post']['content'] ?? ''), 0, 160));
    if (!empty($data['post']['featured_image_url'])) $seoImage = $data['post']['featured_image_url'];
    $postSlug = $data['post']['slug'] ?? $data['post']['id'];
    $seoUrl = "{$baseUrl}/post/{$postSlug}";
} elseif (in_array($view, ['artist', 'label', 'station', 'venue']) && $entity) {
    $entityName = $entity['name'] ?? ucfirst($view);
    $seoTitle = htmlspecialchars($entityName) . ' | NextGenNoise';
    $seoDesc = htmlspecialchars(substr(strip_tags($entity['bio'] ?? ''), 0, 160)) ?: "Discover {$entityName} on NextGenNoise.";
    $entitySlug = $entity['slug'] ?? '';
    if (!empty($entity['image_url'])) $seoImage = $entity['image_url'];
    $seoUrl = "{$baseUrl}/{$view}/{$entitySlug}";
} elseif ($view === 'charts') {
    $seoTitle = 'NGN Charts | NextGenNoise';
    $seoDesc = 'Top ranked metal and rock artists and labels on NextGenNoise.';
    $seoUrl = "{$baseUrl}/charts";
} elseif ($view === 'smr-charts') {
    $seoTitle = 'SMR Charts | NextGenNoise';
    $seoDesc = 'Spins Music Radio chart rankings for metal and rock.';
    $seoUrl = "{$baseUrl}/smr-charts";
} elseif ($view === 'pricing') {
    $seoTitle = 'Pricing | NextGenNoise';
    $seoDesc = 'Affordable plans for artists, labels, venues, and stations. Start free or upgrade for pro features.';
    $seoUrl = "{$baseUrl}/pricing";
} elseif (in_array($view, ['artists', 'labels', 'stations', 'venues', 'posts', 'videos', 'releases', 'songs'])) {
    $seoTitle = ucfirst($view) . ' | NextGenNoise';
    $seoUrl = "{$baseUrl}/{$view}";
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $seoTitle ?></title>
  <meta name="description" content="<?= $seoDesc ?>">
  <meta property="og:title" content="<?= $seoTitle ?>">
  <meta property="og:description" content="<?= $seoDesc ?>">
  <meta property="og:image" content="<?= $seoImage ?>">
  <meta property="og:url" content="<?= $seoUrl ?>">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $seoTitle ?>">
  <meta name="twitter:description" content="<?= $seoDesc ?>">
  <meta name="twitter:image" content="<?= $seoImage ?>">
  <link rel="canonical" href="<?= $seoUrl ?>">

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

  <script>
    window.tailwind = { config: { darkMode: 'class', theme: { extend: { colors: { brand: { DEFAULT: '#1DB954', dark: '#169c45' } } } } } };
    (function(){
      const saved = localStorage.getItem('ngn_theme');
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved ? saved === 'dark' : prefersDark) document.documentElement.classList.add('dark');
    })();
  </script>
  <script src="https://cdn.tailwindcss.com?v=<?= \NGN\Lib\Env::get('APP_VERSION') ?>"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css?v=<?= \NGN\Lib\Env::get('APP_VERSION') ?>">
  <script src="/js/pwa-setup.js?v=<?= \NGN\Lib\Env::get('APP_VERSION') ?>" defer></script>
  <style>
    /* Loading Skeleton Animations */
    @keyframes skeleton-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.4; }
    }
    @keyframes skeleton-shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .skeleton {
      background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
      background-size: 200% 100%;
      animation: skeleton-shimmer 1.5s ease-in-out infinite;
      border-radius: 0.5rem;
    }
    .dark .skeleton {
      background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
      background-size: 200% 100%;
    }
    .skeleton-text { height: 1rem; margin-bottom: 0.5rem; }
    .skeleton-text-sm { height: 0.75rem; margin-bottom: 0.25rem; }
    .skeleton-avatar { width: 3rem; height: 3rem; border-radius: 9999px; }
    .skeleton-card { height: 12rem; }
    .skeleton-image { aspect-ratio: 16/9; }

    /* Loading overlay for interactions */
    .loading-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s;
    }
    .loading-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }
    .loading-spinner {
      width: 3rem;
      height: 3rem;
      border: 3px solid rgba(255,255,255,0.3);
      border-top-color: #1DB954;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Button loading state */
    .btn-loading {
      position: relative;
      color: transparent !important;
      pointer-events: none;
    }
    .btn-loading::after {
      content: '';
      position: absolute;
      width: 1rem;
      height: 1rem;
      top: 50%;
      left: 50%;
      margin: -0.5rem 0 0 -0.5rem;
      border: 2px solid currentColor;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }

    /* Page transition */
    .page-loading {
      opacity: 0.5;
      pointer-events: none;
      transition: opacity 0.2s;
    }
  </style>
</head>
<body class="h-full bg-gray-50 text-gray-900 dark:bg-[#0a0a0f] dark:text-gray-100">
  <div class="min-h-screen flex">

    <!-- Sidebar -->
    <aside class="hidden lg:flex lg:flex-col w-64 bg-white dark:bg-[#111118] border-r border-gray-200 dark:border-white/10 fixed inset-y-0 left-0 z-30">
      <div class="p-4 border-b border-gray-200 dark:border-white/10">
        <a href="/" class="block">
          <img src="/lib/images/site/web-light-1.png" alt="NGN" class="h-10 hidden dark:block">
          <img src="/lib/images/site/web-dark-1.png" alt="NGN" class="h-10 dark:hidden">
        </a>
      </div>

      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'home' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <i class="bi-house text-lg"></i> Home
        </a>
        <a href="/charts" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'charts' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <i class="bi-bar-chart-fill text-lg"></i> NGN Charts
        </a>
        <a href="/smr-charts" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'smr-charts' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <i class="bi-graph-up text-lg"></i> SMR Charts
        </a>

        <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Browse</div>

        <a href="/artists" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'artists' || $view === 'artist' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-music-note-beamed text-lg"></i> Artists</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['artists']) ?></span>
        </a>
        <a href="/labels" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'labels' || $view === 'label' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-building text-lg"></i> Labels</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['labels']) ?></span>
        </a>
        <a href="/stations" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'stations' || $view === 'station' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-broadcast text-lg"></i> Stations</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['stations']) ?></span>
        </a>
        <a href="/venues" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'venues' || $view === 'venue' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-geo-alt text-lg"></i> Venues</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['venues']) ?></span>
        </a>

        <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Content</div>

        <a href="/posts" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'posts' || $view === 'post' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-newspaper text-lg"></i> Posts</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['posts']) ?></span>
        </a>
        <a href="/videos" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'videos' || $view === 'video' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-play-circle text-lg"></i> Videos</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['videos']) ?></span>
        </a>
        <a href="/releases" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'releases' || $view === 'release' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-vinyl text-lg"></i> Releases</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['releases'] ?? 0) ?></span>
        </a>
        <a href="/songs" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'songs' || $view === 'song' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <span class="flex items-center gap-3"><i class="bi-music-note-beamed text-lg"></i> Songs</span>
          <span class="text-xs bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded-full"><?= number_format($counts['songs'] ?? 0) ?></span>
        </a>

        <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Account</div>

        <a href="/pricing" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'pricing' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <i class="bi-credit-card text-lg"></i> Pricing
        </a>

        <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Shop</div>

        <a href="/shop" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= in_array($view, ['shop', 'shops']) ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
          <i class="bi-bag text-lg"></i> Merch Shops
          <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full ml-auto">Soon</span>
        </a>
      </nav>

      <div class="p-4 border-t border-gray-200 dark:border-white/10">
        <?php if ($isLoggedIn): ?>
          <?php
            $userRoleId = (int)($currentUser['RoleId'] ?? 0);
            $dashboardPath = match($userRoleId) {
              3 => '/dashboard/artist/',
              7 => '/dashboard/label/',
              4, 15 => '/dashboard/station/',
              5, 17 => '/dashboard/venue/',
              default => '/'
            };
          ?>
          <div class="flex items-center gap-3 px-3 py-2 mb-2">
            <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? '', $currentUser['Image'] ?? null)) ?>" alt="" class="w-8 h-8 rounded-full object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= htmlspecialchars($currentUser['Title'] ?? 'User') ?></div>
              <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($currentUser['Email'] ?? '') ?></div>
            </div>
          </div>
          <a href="<?= $dashboardPath ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
            <i class="bi-speedometer2 text-lg"></i> Dashboard
          </a>
          <?php if ($isAdmin): ?>
          <a href="/admin/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
            <i class="bi-gear text-lg"></i> Admin
          </a>
          <?php endif; ?>
          <a href="/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
            <i class="bi-box-arrow-right text-lg"></i> Logout
          </a>
        <?php else: ?>
          <a href="/login.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium bg-brand text-white hover:bg-brand/90">
            <i class="bi-box-arrow-in-right text-lg"></i> Sign In
          </a>
        <?php endif; ?>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 lg:ml-64">
      <!-- Top Bar -->
      <header class="sticky top-0 z-20 bg-white/80 dark:bg-[#0a0a0f]/80 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
        <div class="flex items-center justify-between px-4 lg:px-6 h-16">
          <div class="flex items-center gap-4">
            <button class="lg:hidden p-2 -ml-2" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
              <i class="bi-list text-2xl"></i>
            </button>
            <a href="/" class="lg:hidden flex items-center">
              <img src="/lib/images/site/web-light-1.png" alt="NextGenNoise" class="h-8 hidden dark:block">
              <img src="/lib/images/site/web-dark-1.png" alt="NextGenNoise" class="h-8 dark:hidden">
            </a>
            <form method="get" class="hidden sm:flex items-center gap-2">
              <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
              <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="w-64 px-4 py-2 rounded-lg bg-gray-100 dark:bg-white/10 border-0 text-sm focus:ring-2 focus:ring-brand">
            </form>
          </div>
          <div class="flex items-center gap-3">
            <button onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('ngn_theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10">
              <i class="bi-moon-fill dark:hidden"></i>
              <i class="bi-sun-fill hidden dark:inline"></i>
            </button>
            <?php if ($isLoggedIn && $currentUser): ?>
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($currentUser['Title'] ?? $currentUser['Email'] ?? '') ?></span>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <!-- Mobile Menu -->
      <div id="mobile-menu" class="hidden lg:hidden fixed inset-0 z-40 bg-black/50" onclick="this.classList.add('hidden')">
        <div class="w-72 h-full bg-white dark:bg-[#111118] overflow-y-auto" onclick="event.stopPropagation()">
          <!-- Mobile Menu Header -->
          <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-white/10">
            <a href="/" class="flex items-center">
              <img src="/lib/images/site/web-light-1.png" alt="NextGenNoise" class="h-8 hidden dark:block">
              <img src="/lib/images/site/web-dark-1.png" alt="NextGenNoise" class="h-8 dark:hidden">
            </a>
            <button onclick="document.getElementById('mobile-menu').classList.add('hidden')" class="p-2 hover:bg-gray-100 dark:hover:bg-white/10 rounded-lg">
              <i class="bi-x-lg text-xl"></i>
            </button>
          </div>

          <nav class="p-4 space-y-1">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Main</div>
            <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'home' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-house text-lg"></i> Home</a>
            <a href="/charts" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'charts' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-bar-chart-fill text-lg"></i> NGN Charts</a>
            <a href="/smr-charts" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'smr-charts' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-graph-up text-lg"></i> SMR Charts</a>

            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2 mt-4">Discover</div>
            <a href="/artists" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'artists' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-music-note-beamed text-lg"></i> Artists</a>
            <a href="/labels" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'labels' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-building text-lg"></i> Labels</a>
            <a href="/stations" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'stations' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-broadcast text-lg"></i> Stations</a>
            <a href="/venues" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'venues' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-geo-alt text-lg"></i> Venues</a>

            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2 mt-4">Content</div>
            <a href="/posts" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'posts' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-newspaper text-lg"></i> News & Posts</a>
            <a href="/videos" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= $view === 'videos' ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-play-circle text-lg"></i> Videos</a>

            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2 mt-4">Shop</div>
            <a href="/shop" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium <?= in_array($view, ['shop', 'shops']) ? 'bg-brand/10 text-brand' : 'text-gray-600 dark:text-gray-400' ?>"><i class="bi-bag text-lg"></i> Merch Shops</a>
          </nav>

          <!-- Mobile Menu Footer -->
          <div class="p-4 border-t border-gray-200 dark:border-white/10 mt-auto">
            <?php if ($isLoggedIn && $currentUser): ?>
              <?php
                $userRoleId = (int)($currentUser['RoleId'] ?? 0);
                $dashboardPath = match($userRoleId) {
                  3 => '/dashboard/artist/',
                  7 => '/dashboard/label/',
                  4, 15 => '/dashboard/station/',
                  5, 17 => '/dashboard/venue/',
                  default => '/'
                };
              ?>
              <div class="flex items-center gap-3 px-3 py-2 mb-2">
                <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? '', $currentUser['Image'] ?? null)) ?>" alt="" class="w-8 h-8 rounded-full object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium truncate"><?= htmlspecialchars($currentUser['Title'] ?? 'User') ?></div>
                  <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($currentUser['Email'] ?? '') ?></div>
                </div>
              </div>
              <a href="<?= $dashboardPath ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                <i class="bi-speedometer2 text-lg"></i> Dashboard
              </a>
              <?php if ($isAdmin): ?>
              <a href="/admin/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                <i class="bi-gear text-lg"></i> Admin
              </a>
              <?php endif; ?>
              <a href="/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                <i class="bi-box-arrow-right text-lg"></i> Logout
              </a>
            <?php else: ?>
              <a href="/login.php" class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-brand text-white hover:bg-brand/90">
                <i class="bi-box-arrow-in-right"></i> Sign In
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Construction Banner -->
      <div class="bg-amber-500 text-black px-4 py-2 text-center text-sm font-medium">
        <i class="bi-cone-striped mr-2"></i>
        <strong>v2.0 Under Construction</strong> — Some features may be incomplete. Use at your own risk.
        <a href="/maintenance/" class="underline ml-2 hover:no-underline">View Roadmap</a>
      </div>

      <!-- Page Content -->
      <div class="p-4 lg:p-6">

      <?php if ($view === 'home'): ?>
        <?php
        $featuredPosts = get_ngn_posts($pdo, '', 1, 4);
        ?>
        <!-- HERO -->
        <div class="relative bg-cover bg-center rounded-xl overflow-hidden mb-8">
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative p-12 lg:p-24 text-center text-white">
                <?php if (!empty($featuredPosts)): ?>
                    <div class="flex-1">
                        <div class="relative" style="height: 400px;">
                            <?php foreach ($featuredPosts as $index => $post): ?>
                                <?php $postImg = !empty($post['featured_image_url']) ? "/uploads/posts/{$post['featured_image_url']}" : DEFAULT_AVATAR; ?>
                                <div class="absolute inset-0 transition-opacity duration-500 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>" data-carousel-item>
                                    <img src="<?= htmlspecialchars($postImg) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($post['title']) ?>">
                                    <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                        <div class="text-center">
                                            <h1 class="text-4xl lg:text-6xl font-bold mb-4"><?= htmlspecialchars($post['title']) ?></h1>
                                            <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="bg-brand text-white font-bold py-3 px-8 rounded-lg hover:bg-brand/80 transition-colors">Read More</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="text-4xl lg:text-6xl font-bold mb-4">Welcome to NextGenNoise</h1>
                    <p class="text-lg lg:text-xl mb-8">The command center for indie rock & metal</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const carouselItems = document.querySelectorAll('[data-carousel-item]');
                let currentIndex = 0;

                function showItem(index) {
                    carouselItems.forEach((item, i) => {
                        item.classList.toggle('opacity-100', i === index);
                        item.classList.toggle('opacity-0', i !== index);
                    });
                }

                function nextItem() {
                    currentIndex = (currentIndex + 1) % carouselItems.length;
                    showItem(currentIndex);
                }

                if (carouselItems.length > 1) {
                    setInterval(nextItem, 5000);
                }
            });
        </script>
        <!-- HOME -->
        

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10">
            <div class="text-3xl font-bold text-brand"><?= number_format($counts['artists']) ?></div>
            <div class="text-sm text-gray-500">Artists</div>
          </div>
          <div class="bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10">
            <div class="text-3xl font-bold text-brand"><?= number_format($counts['labels']) ?></div>
            <div class="text-sm text-gray-500">Labels</div>
          </div>
          <div class="bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10">
            <div class="text-3xl font-bold text-brand"><?= number_format($counts['stations']) ?></div>
            <div class="text-sm text-gray-500">Stations</div>
          </div>
          <div class="bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10">
            <div class="text-3xl font-bold text-brand"><?= number_format($counts['venues']) ?></div>
            <div class="text-sm text-gray-500">Venues</div>
          </div>
        </div>

        <!-- Trending Artists -->
        <?php if (!empty($data['trending_artists'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Trending Artists</h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <?php foreach ($data['trending_artists'] as $artist): ?>
            <a href="/artist/<?= htmlspecialchars($artist['slug'] ?? $artist['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
              <img src="<?= htmlspecialchars(($artist['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" alt="" class="w-full aspect-square object-cover rounded-lg mb-3 bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="font-semibold truncate group-hover:text-brand"><?= htmlspecialchars($artist['name']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($artist['engagement_count']) ?> engagements</div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Featured Artists -->
        <?php if (!empty($data['artists'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Featured Artists</h2>
            <a href="/artists" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php foreach ($data['artists'] as $artist): ?>
            <div class="group bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
              <a href="/artist/<?= htmlspecialchars($artist['slug'] ?? $artist['id']) ?>">
                <?= ngn_image(
                    ($artist['image_url'] ?? null) ?: DEFAULT_AVATAR,
                    $artist['name'],
                    'w-full aspect-square object-cover rounded-lg mb-3 bg-gray-100 dark:bg-white/10'
                ) ?>
                <div class="font-semibold truncate group-hover:text-brand"><?= htmlspecialchars($artist['name']) ?></div>
              </a>
              <div class="flex items-center justify-between mt-2">
                <span class="text-xs text-gray-500">Artist</span>
                <!-- TODO: Implement follow functionality -->
                <button class="px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-white/10 rounded-lg hover:bg-brand hover:text-white transition-colors">Follow</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Featured Labels -->
        <?php if (!empty($data['labels'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Featured Labels</h2>
            <a href="/labels" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
            <?php foreach ($data['labels'] as $label): ?>
            <a href="/label/<?= htmlspecialchars($label['slug'] ?? $label['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10 hover:border-brand transition-colors text-center">
              <?php
              $labelImg = DEFAULT_AVATAR;
              if (!empty($label['image_url']) && !str_starts_with($label['image_url'], '/')) {
                  $labelImg = "/uploads/labels/{$label['image_url']}";
              } elseif (!empty($label['image_url'])) {
                  $labelImg = $label['image_url'];
              }
              ?>
              <img src="<?= htmlspecialchars($labelImg) ?>" alt="" class="w-16 h-16 mx-auto object-cover rounded-full mb-2 bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="font-medium text-sm truncate group-hover:text-brand"><?= htmlspecialchars($label['name']) ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Recent Posts -->
        <?php if (!empty($data['posts'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Latest News</h2>
            <a href="/posts" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($data['posts'] as $post): ?>
            <?php $postImg = !empty($post['featured_image_url']) ? "/uploads/posts/{$post['featured_image_url']}" : DEFAULT_AVATAR; ?>
            <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
              <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full aspect-video object-cover bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="p-4">
                <div class="font-semibold text-sm line-clamp-2 group-hover:text-brand mb-1"><?= htmlspecialchars($post['title']) ?></div>
                <div class="text-xs text-gray-500"><?= $post['published_at'] ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Recent Videos -->
        <?php if (!empty($data['videos'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Latest Videos</h2>
            <a href="/videos" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($data['videos'] as $video): ?>
            <a href="/video/<?= htmlspecialchars($video['slug']) ?>" class="group bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
              <div class="aspect-video bg-black relative overflow-hidden">
                <?php if ($video['platform'] === 'youtube' && !empty($video['external_id'])): ?>
                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($video['external_id']) ?>/sddefault.jpg" alt="" class="w-full h-full object-cover group-hover:opacity-75 transition-opacity">
                <div class="absolute inset-0 flex items-center justify-center group-hover:bg-black/20 transition-all">
                  <div class="bg-brand rounded-full p-3 group-hover:scale-110 transition-transform"><i class="bi-play-fill text-white text-lg"></i></div>
                </div>
                <?php else: ?>
                <div class="w-full h-full bg-gray-300 dark:bg-gray-700 flex items-center justify-center">
                  <i class="bi-video text-gray-500 text-3xl"></i>
                </div>
                <?php endif; ?>
              </div>
              <div class="p-3">
                <div class="font-semibold text-sm line-clamp-2 mb-1 group-hover:text-brand"><?= htmlspecialchars($video['title']) ?></div>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($video['platform']) ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- NGN Rankings - Artists Chart -->
        <?php if (!empty($data['artist_rankings'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Top Artists</h2>
            <a href="/charts" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                  <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">#</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Artist</th>
                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Score</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($data['artist_rankings'] as $i => $ranking): ?>
                  <tr class="border-b border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-500"><?= $i + 1 ?></td>
                    <td class="px-4 py-3">
                      <a href="/artist/<?= htmlspecialchars($ranking['slug'] ?? '') ?>" class="text-brand hover:underline font-medium">
                        <?= htmlspecialchars($ranking['Name'] ?? 'Unknown') ?>
                      </a>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold"><?= number_format($ranking['Score'] ?? 0, 0) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- NGN Rankings - Labels Chart -->
        <?php if (!empty($data['label_rankings'])): ?>
        <div class="mb-8">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Top Labels</h2>
            <a href="/charts" class="text-brand text-sm font-medium hover:underline">View All →</a>
          </div>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                  <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">#</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Label</th>
                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Score</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($data['label_rankings'] as $i => $label): ?>
                  <tr class="border-b border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-500"><?= $i + 1 ?></td>
                    <td class="px-4 py-3">
                      <a href="/label/<?= htmlspecialchars($label['slug'] ?? '') ?>" class="text-brand hover:underline font-medium">
                        <?= htmlspecialchars($label['Name'] ?? 'Unknown') ?>
                      </a>
                    </td>
                    <td class="px-4 py-3 text-right text-sm font-semibold"><?= number_format($label['Score'] ?? 0, 0) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>


      <?php elseif (in_array($view, ['artists', 'labels', 'stations', 'venues'])): ?>
        <!-- ENTITY LIST VIEW -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold capitalize"><?= $view ?></h1>
          <span class="text-sm text-gray-500"><?= number_format($total) ?> total</span>
        </div>

        <?php $items = $data[$view] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <?php foreach ($items as $item): ?>
          <?php
            $imgUrl = DEFAULT_AVATAR;
            if (!empty($item['image_url'])) {
              if (str_starts_with($item['image_url'], '/')) {
                $imgUrl = $item['image_url'];
              } else {
                $imgUrl = "/uploads/{$view}/{$item['image_url']}";
              }
            }
          ?>
          <a href="/<?= rtrim($view, 's') ?>/<?= htmlspecialchars($item['slug'] ?? $item['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="w-full aspect-square object-cover rounded-lg mb-3 bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
            <div class="font-semibold text-sm truncate group-hover:text-brand"><?= htmlspecialchars($item['name'] ?? $item['title'] ?? 'Unknown') ?></div>
            <?php if (!empty($item['city'])): ?>
            <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($item['city']) ?></div>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-12 text-gray-500">No <?= $view ?> found.</div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-8">
          <?php if ($page > 1): ?>
          <a href="/<?= $view ?><?= $page > 1 ? '?page='.($page-1) : '' ?><?= $search ? ($page > 1 ? '&' : '?') . 'q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">← Prev</a>
          <?php endif; ?>
          <span class="px-4 py-2 text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/<?= $view ?>?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">Next →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'posts'): ?>
        <!-- POSTS LIST VIEW -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold">News & Articles</h1>
          <span class="text-sm text-gray-500"><?= number_format($total) ?> total</span>
        </div>

        <?php $items = $data['posts'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($items as $post): ?>
          <?php $postImg = !empty($post['featured_image_url']) ? "/uploads/posts/{$post['featured_image_url']}" : DEFAULT_AVATAR; ?>
          <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
            <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full aspect-video object-cover bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
            <div class="p-4">
              <div class="font-semibold line-clamp-2 group-hover:text-brand mb-2"><?= htmlspecialchars($post['title']) ?></div>
              <?php if (!empty($post['excerpt'])): ?>
              <p class="text-sm text-gray-500 line-clamp-2 mb-2"><?= htmlspecialchars($post['excerpt']) ?></p>
              <?php endif; ?>
              <div class="flex items-center gap-2 text-xs text-gray-400">
                <span><?= $post['published_at'] ? date('M j, Y', strtotime($post['published_at'])) : '' ?></span>
                <?php if (!empty($post['author_name'])): ?>
                <span>•</span>
                <span>by <?= htmlspecialchars($post['author_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-12 text-gray-500">No posts found.</div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-8">
          <?php if ($page > 1): ?>
          <a href="/posts<?= $page > 1 ? '?page='.($page-1) : '' ?><?= $search ? ($page > 1 ? '&' : '?') . 'q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">← Prev</a>
          <?php endif; ?>
          <span class="px-4 py-2 text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/posts?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">Next →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'videos'): ?>
        <!-- VIDEOS LIST VIEW -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold">Videos</h1>
          <span class="text-sm text-gray-500"><?= number_format($total) ?> total</span>
        </div>

        <?php $items = $data['videos'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($items as $video): ?>
          <a href="/video/<?= htmlspecialchars($video['slug']) ?>" class="group bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
            <div class="aspect-video bg-black relative overflow-hidden">
              <?php if ($video['platform'] === 'youtube' && !empty($video['external_id'])): ?>
              <img src="https://img.youtube.com/vi/<?= htmlspecialchars($video['external_id']) ?>/sddefault.jpg" alt="" class="w-full h-full object-cover group-hover:opacity-75 transition-opacity">
              <div class="absolute inset-0 flex items-center justify-center group-hover:bg-black/20 transition-all">
                <div class="bg-brand rounded-full p-3 group-hover:scale-110 transition-transform"><i class="bi-play-fill text-white text-lg"></i></div>
              </div>
              <?php else: ?>
              <div class="w-full h-full bg-gray-300 dark:bg-gray-700 flex items-center justify-center">
                <i class="bi-video text-gray-500 text-3xl"></i>
              </div>
              <?php endif; ?>
            </div>
            <div class="p-4">
              <div class="font-semibold line-clamp-2 group-hover:text-brand mb-2"><?= htmlspecialchars($video['title']) ?></div>
              <div class="flex items-center gap-2 text-xs text-gray-400">
                <span><?= $video['published_at'] ? date('M j, Y', strtotime($video['published_at'])) : '' ?></span>
                <span>•</span>
                <span><?= htmlspecialchars($video['platform']) ?></span>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-12 text-gray-500">No videos found.</div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-8">
          <?php if ($page > 1): ?>
          <a href="/videos<?= $page > 1 ? '?page='.($page-1) : '' ?><?= $search ? ($page > 1 ? '&' : '?') . 'q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">← Prev</a>
          <?php endif; ?>
          <span class="px-4 py-2 text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/videos?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">Next →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'video' && !empty($data['video'])): ?>
        <!-- SINGLE VIDEO VIEW -->
        <?php $video = $data['video']; ?>
        <a href="/videos" class="inline-flex items-center gap-2 text-brand hover:underline mb-6">
          <i class="bi-arrow-left"></i> Back to Videos
        </a>

        <article class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
          <div class="p-6 lg:p-8">
            <h1 class="text-3xl lg:text-4xl font-bold mb-4"><?= htmlspecialchars($video['title'] ?? 'Untitled') ?></h1>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-200 dark:border-white/10">
              <?php if (!empty($video['author_entity'])): ?>
              <a href="/artist/<?= htmlspecialchars($video['author_entity']['slug'] ?? '') ?>" class="flex items-center gap-2 hover:text-brand">
                <span><?= htmlspecialchars($video['author_entity']['name'] ?? 'Unknown') ?></span>
              </a>
              <?php endif; ?>
              <span><i class="bi-calendar3"></i> <?= date('F j, Y', strtotime($video['created_at'])) ?></span>
            </div>
            
            <?php if ($video['is_locked']): ?>
                <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 1rem; text-align: center; border: 1px solid var(--border);">
                    <h3 class="text-xl font-bold">This video is exclusive to subscribers.</h3>
                    <p>Subscribe to this artist to get access to this video and other exclusive content.</p>
                    <a href="#" class="btn btn-primary mt-4">Subscribe Now</a>
                </div>
            <?php else: ?>
                <div class="aspect-video mb-6">
                    <iframe
                      src="https://www.youtube.com/embed/<?= htmlspecialchars($video['external_id']) ?>?rel=0&modestbranding=1"
                      class="w-full h-full"
                      frameborder="0"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      allowfullscreen
                      loading="lazy"
                    ></iframe>
                </div>
            <?php endif; ?>
          </div>
        </article>

        <?php
          // Engagement UI for Videos
          $entity_type = 'video';
          $entity_id = (int)($video['Id'] ?? $video['id'] ?? 0);
          $entity_name = $video['title'];
          // include __DIR__ . '/lib/partials/engagement-ui.php'; // Not yet adapted for videos
        ?>
        
      <?php elseif ($view === 'releases'): ?>
        <!-- RELEASES LISTING -->
        <div class="mb-8">
            <h1 class="text-3xl font-black mb-2">New Releases</h1>
            <p class="text-gray-500">Discover the latest music across the NGN network.</p>
        </div>

        <?php if (!empty($data['releases'])): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
            <?php foreach ($data['releases'] as $release): ?>
            <?php $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; ?>
            <a href="/release/<?= htmlspecialchars($release['slug'] ?? '') ?>" class="group">
                <div class="aspect-square rounded-2xl overflow-hidden mb-4 border border-white/5 group-hover:border-brand transition-colors shadow-lg">
                    <img src="<?= htmlspecialchars($releaseImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                </div>
                <div class="font-bold truncate group-hover:text-brand transition-colors"><?= htmlspecialchars($release['title']) ?></div>
                <div class="text-xs text-white/40 truncate"><?= htmlspecialchars($release['artist_name'] ?? 'Unknown Artist') ?></div>
                <div class="text-[10px] text-white/20 uppercase font-black mt-1"><?= !empty($release['release_date']) ? date('M j, Y', strtotime($release['release_date'])) : '' ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-12 text-center text-gray-500 bg-white/5 rounded-3xl border border-white/5">
            <i class="bi-vinyl text-4xl mb-4 block"></i>
            No releases found.
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if (($totalPages ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-12">
          <?php if ($page > 1): ?>
          <a href="/releases?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-6 py-2 rounded-xl bg-white/5 border border-white/5 hover:border-brand transition-colors">Prev</a>
          <?php endif; ?>
          <span class="text-white/40 font-bold">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/releases?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-6 py-2 rounded-xl bg-white/5 border border-white/5 hover:border-brand transition-colors">Next</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'songs'): ?>
        <!-- SONGS LISTING -->
        <div class="mb-8">
            <h1 class="text-3xl font-black mb-2">Tracks</h1>
            <p class="text-gray-500">Popular songs and new discoveries.</p>
        </div>

        <?php if (!empty($data['songs'])): ?>
        <div class="bg-white/5 rounded-3xl border border-white/5 overflow-hidden">
            <div class="divide-y divide-white/5">
                <?php foreach ($data['songs'] as $song): ?>
                <div class="flex items-center gap-4 p-4 hover:bg-white/5 transition-colors group">
                    <div class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-white/10 border border-white/5">
                        <img src="<?= htmlspecialchars(($song['cover_url'] ?? '') ?: DEFAULT_AVATAR) ?>" class="w-full h-full object-cover" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                    </div>
                    <div class="flex-1 min-w-0">
                        <a href="/song/<?= htmlspecialchars($song['slug']) ?>" class="font-bold truncate group-hover:text-brand block"><?= htmlspecialchars($song['title']) ?></a>
                        <div class="text-xs text-white/40 truncate">
                            <a href="/artist/<?= htmlspecialchars($song['artist_id'] ?? '') ?>" class="hover:text-white"><?= htmlspecialchars($song['artist_name'] ?? 'Unknown Artist') ?></a>
                             • 
                            <span class="italic"><?= htmlspecialchars($song['release_name'] ?? 'Single') ?></span>
                        </div>
                    </div>
                    <div class="text-sm text-white/20 font-mono hidden sm:block"><?= ($song['duration_seconds'] ?? 0) ? gmdate('i:s', $song['duration_seconds']) : '--:--' ?></div>
                    <button class="bg-brand/10 text-brand w-10 h-10 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all">
                        <i class="bi-play-fill text-xl"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="p-12 text-center text-gray-500 bg-white/5 rounded-3xl border border-white/5">
            <i class="bi-music-note-beamed text-4xl mb-4 block"></i>
            No songs found.
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if (($totalPages ?? 1) > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-12">
          <?php if ($page > 1): ?>
          <a href="/songs?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-6 py-2 rounded-xl bg-white/5 border border-white/5 hover:border-brand transition-colors">Prev</a>
          <?php endif; ?>
          <span class="text-white/40 font-bold">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/songs?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-6 py-2 rounded-xl bg-white/5 border border-white/5 hover:border-brand transition-colors">Next</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'charts'): ?>
        <!-- NGN CHARTS OVERHAUL -->
        <div class="mb-12">
          <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
              <h1 class="text-4xl font-black mb-2 tracking-tight">NGN Charts</h1>
              <p class="text-gray-500 dark:text-gray-400">The industry standard for metal & rock popularity, driven by real-time engagement data.</p>
            </div>
            
            <?php $chartType = $data['chart_type'] ?? 'artists'; ?>
            <div class="flex bg-gray-100 dark:bg-white/5 p-1 rounded-xl w-fit">
              <a href="/charts?type=artists" class="px-6 py-2 rounded-lg text-sm font-bold transition-all <?= $chartType === 'artists' ? 'bg-white dark:bg-white/10 shadow-sm text-brand' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' ?>">Artists</a>
              <a href="/charts?type=labels" class="px-6 py-2 rounded-lg text-sm font-bold transition-all <?= $chartType === 'labels' ? 'bg-white dark:bg-white/10 shadow-sm text-brand' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' ?>">Labels</a>
            </div>
          </div>
        </div>

        <?php 
          $rankings = ($chartType === 'artists') ? ($data['artist_rankings'] ?? []) : ($data['label_rankings'] ?? []);
          $entityPath = ($chartType === 'artists') ? 'artist' : 'label';
        ?>

        <?php if (!empty($rankings)): ?>
        <div class="space-y-4">
          <?php foreach ($rankings as $i => $item): ?>
          <?php 
            $slug = $item['slug'] ?? ''; 
            $img = ($item['image_url'] ?? null) ?: DEFAULT_AVATAR;
            $isTop3 = $i < 3;
          ?>
          <a href="/<?= $entityPath ?>/<?= htmlspecialchars($slug) ?>" class="group flex items-center gap-6 p-4 rounded-2xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 hover:border-brand dark:hover:border-brand/50 transition-all hover:scale-[1.01] hover:shadow-xl hover:shadow-brand/5">
            <div class="w-12 text-center flex-shrink-0">
              <?php if ($i === 0): ?>
                <span class="text-3xl">🥇</span>
              <?php elseif ($i === 1): ?>
                <span class="text-3xl">🥈</span>
              <?php elseif ($i === 2): ?>
                <span class="text-3xl">🥉</span>
              <?php else: ?>
                <span class="text-xl font-black text-gray-300 dark:text-white/20">#<?= $i + 1 ?></span>
              <?php endif; ?>
            </div>
            
            <div class="relative flex-shrink-0">
              <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-16 h-16 rounded-xl object-cover shadow-lg group-hover:rotate-3 transition-transform" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <?php if ($isTop3): ?>
                <div class="absolute -top-2 -right-2 w-6 h-6 bg-brand text-black rounded-full flex items-center justify-center text-[10px] font-black border-2 border-white dark:border-gray-900">
                  <i class="bi-star-fill"></i>
                </div>
              <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
              <div class="font-black text-lg truncate group-hover:text-brand transition-colors"><?= htmlspecialchars($item['Name'] ?? 'Unknown') ?></div>
              <div class="flex items-center gap-3 mt-1 text-xs font-bold uppercase tracking-widest text-gray-500">
                <span class="flex items-center gap-1"><i class="bi-lightning-charge-fill text-brand"></i> <?= number_format($item['Score'], 1) ?> pts</span>
                <span class="w-1 h-1 bg-gray-300 dark:bg-white/20 rounded-full"></span>
                <span class="text-gray-400">Stable</span>
              </div>
            </div>

            <div class="hidden md:block text-right flex-shrink-0">
                <div class="text-[10px] uppercase font-black text-gray-400 mb-1">Rank Velocity</div>
                <div class="flex items-center justify-end gap-1 text-brand">
                    <i class="bi-caret-up-fill"></i>
                    <span class="font-bold">Hot</span>
                </div>
            </div>
            
            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-white/5 flex items-center justify-center group-hover:bg-brand group-hover:text-black transition-colors">
              <i class="bi-chevron-right"></i>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-white/5 rounded-3xl border-2 border-dashed border-gray-200 dark:border-white/10 p-20 text-center">
          <div class="text-6xl mb-6">📊</div>
          <h2 class="text-2xl font-bold mb-2">Compiling Chart Data...</h2>
          <p class="text-gray-500 max-sm mx-auto">We're currently aggregating the latest engagement signals. Check back in a few minutes for the updated rankings.</p>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'smr-charts'): ?>
        <!-- SMR CHARTS OVERHAUL -->
        <div class="mb-12">
          <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
              <h1 class="text-4xl font-black mb-2 tracking-tight">SMR Airplay Charts</h1>
              <p class="text-gray-500 dark:text-gray-400">Spins Music Radio - Official radio airplay monitoring for independent metal & rock.</p>
            </div>
            
            <?php if ($data['smr_date']): ?>
            <div class="px-4 py-2 bg-brand/10 text-brand rounded-full text-xs font-black uppercase tracking-tighter border border-brand/20">
              Week of <?= htmlspecialchars($data['smr_date']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($data['smr_charts'])): ?>
        <div class="bg-white dark:bg-white/5 rounded-3xl border border-gray-200 dark:border-white/10 overflow-hidden shadow-2xl">
          <div class="grid grid-cols-12 gap-4 p-6 bg-gray-50 dark:bg-white/5 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] border-b border-gray-200 dark:border-white/10">
            <div class="col-span-1 text-center">TW</div>
            <div class="col-span-1 text-center">LW</div>
            <div class="col-span-5 md:col-span-4">Artist / Track</div>
            <div class="hidden md:block col-span-3">Label</div>
            <div class="col-span-2 text-center">Spins</div>
            <div class="col-span-1 text-center">WOC</div>
          </div>
          
          <div class="divide-y divide-gray-100 dark:divide-white/5">
            <?php foreach ($data['smr_charts'] as $i => $item): ?>
            <div class="grid grid-cols-12 gap-4 p-6 hover:bg-gray-50 dark:hover:bg-white/5 items-center transition-all group">
              <div class="col-span-1 text-center font-black text-xl <?= $i < 10 ? 'text-brand' : 'text-gray-400' ?>">
                <?= $item['TWP'] ?? ($i + 1) ?>
              </div>
              <div class="col-span-1 text-center text-gray-400 font-bold text-xs">
                <?= $item['LWP'] ?? '-' ?>
              </div>
              
              <div class="col-span-5 md:col-span-4 flex items-center gap-4 min-w-0">
                <div class="relative flex-shrink-0">
                  <img src="<?= htmlspecialchars($item['artist']['image_url'] ?? DEFAULT_AVATAR) ?>" class="w-12 h-12 rounded-lg object-cover shadow-md" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                  <button class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 rounded-lg transition-opacity text-white">
                    <i class="bi-play-fill text-xl"></i>
                  </button>
                </div>
                <div class="min-w-0">
                  <a href="/artist/<?= htmlspecialchars($item['artist']['slug'] ?? '') ?>" class="font-black truncate block hover:text-brand transition-colors"><?= htmlspecialchars($item['Artists'] ?? 'Unknown Artist') ?></a>
                  <div class="text-sm text-gray-500 truncate font-medium"><?= htmlspecialchars($item['Song'] ?? 'Unknown Track') ?></div>
                </div>
              </div>
              
              <div class="hidden md:block col-span-3 truncate text-xs font-bold text-gray-400 uppercase tracking-wider">
                <?= htmlspecialchars($item['Label'] ?? 'Independent') ?>
              </div>
              
              <div class="col-span-2 text-center">
                <div class="font-black text-lg"><?= number_format($item['spins'] ?? 0) ?></div>
                <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">Total Spins</div>
              </div>
              
              <div class="col-span-1 text-center font-bold text-gray-400">
                <?= $item['WOC'] ?? '1' ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-white/5 rounded-3xl border-2 border-dashed border-gray-200 dark:border-white/10 p-20 text-center">
          <div class="text-6xl mb-6">📻</div>
          <h2 class="text-2xl font-bold mb-2">Waiting for Radio Reports</h2>
          <p class="text-gray-500 max-w-sm mx-auto">The SMR airplay data is being synchronized with our tracking partners. Please check back shortly.</p>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'pricing'): ?>
        <!-- PRICING PAGE -->
        <div class="mb-8">
          <h1 class="text-3xl lg:text-4xl font-bold mb-2">Pricing Plans</h1>
          <p class="text-lg text-gray-600 dark:text-gray-400">Choose the perfect plan for your music career</p>
        </div>

        <!-- Pricing Cards Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
          <?php foreach ($data['tiers'] as $tierKey => $tier): ?>
          <div class="relative bg-white dark:bg-white/5 rounded-xl border <?= ($tier['popular'] ?? false) ? 'border-brand lg:scale-105' : 'border-gray-200 dark:border-white/10' ?> overflow-hidden transition-transform">
            <?php if ($tier['popular'] ?? false): ?>
            <div class="absolute top-0 right-0 bg-brand text-white px-4 py-1 text-xs font-bold rounded-bl-lg">POPULAR</div>
            <?php endif; ?>

            <div class="p-6 h-full flex flex-col">
              <!-- Tier Header -->
              <div class="mb-6">
                <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($tier['name']) ?></h3>
                <div class="flex items-baseline gap-2 mb-2">
                  <?php if (isset($tier['price']) && $tier['price'] > 0): ?>
                    <span class="text-3xl lg:text-4xl font-bold text-brand"><?= htmlspecialchars($tier['price_label'] ?? '$' . number_format($tier['price'], 2)) ?></span>
                    <span class="text-gray-500 text-sm"><?= htmlspecialchars($tier['billing']) ?></span>
                  <?php else: ?>
                    <span class="text-3xl lg:text-4xl font-bold"><?= htmlspecialchars($tier['price_label'] ?? 'Free') ?></span>
                  <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($tier['description']) ?></p>
              </div>

              <!-- Alternative Pricing (Labels/Venues) -->
              <?php if ($tier['label_price'] ?? null): ?>
              <div class="mb-4 pb-4 border-b border-gray-200 dark:border-white/10 text-sm">
                <div class="text-gray-500">For Labels: <?= htmlspecialchars($tier['label_price_label'] ?? '$' . number_format($tier['label_price'], 2)) ?>/month</div>
                <?php if ($tier['venue_price'] ?? null): ?>
                <div class="text-gray-500">For Venues: <?= htmlspecialchars($tier['venue_price_label'] ?? '$' . number_format($tier['venue_price'], 2)) ?>/month</div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <!-- CTA Button -->
              <div class="mb-6">
                <?php if ($tier['cta_url'] === 'mailto:sales@nextgennoise.com'): ?>
                  <a href="<?= htmlspecialchars($tier['cta_url']) ?>" class="w-full block text-center px-4 py-3 rounded-lg font-semibold transition-colors bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-white/20">
                    <?= htmlspecialchars($tier['cta']) ?>
                  </a>
                <?php elseif ($tier['cta'] === 'Current Plan'): ?>
                  <div class="w-full text-center px-4 py-3 rounded-lg font-semibold bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-400">
                    <?= htmlspecialchars($tier['cta']) ?>
                  </div>
                <?php else: ?>
                  <a href="<?= htmlspecialchars($tier['cta_url']) ?>" class="w-full block text-center px-4 py-3 rounded-lg font-semibold transition-colors <?= ($tier['popular'] ?? false) ? 'bg-brand text-white hover:bg-brand/80' : 'bg-brand/10 text-brand hover:bg-brand/20' ?>">
                    <?= htmlspecialchars($tier['cta']) ?>
                  </a>
                <?php endif; ?>
              </div>

              <!-- Features List -->
              <div class="space-y-3 flex-1">
                <div class="text-xs font-semibold text-gray-500 uppercase">Included Features:</div>
                <?php foreach ($tier['features'] as $feature => $included): ?>
                <div class="flex items-center gap-3 text-sm">
                  <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center <?= $included ? 'bg-brand/20 text-brand' : 'bg-gray-100 dark:bg-white/5 text-gray-400' ?>">
                    <i class="bi <?= $included ? 'bi-check2' : 'bi-x' ?> text-xs"></i>
                  </span>
                  <span class="<?= $included ? '' : 'text-gray-400' ?>"><?= htmlspecialchars($feature) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Feature Comparison Table -->
        <div class="mb-12">
          <h2 class="text-2xl font-bold mb-6">Detailed Feature Comparison</h2>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                  <th class="text-left p-4 font-semibold text-gray-900 dark:text-white">Feature</th>
                  <?php foreach ($data['tiers'] as $tierKey => $tier): ?>
                  <th class="text-center p-4 font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($tier['name']) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                // Get all unique features
                $allFeatures = [];
                foreach ($data['tiers'] as $tier) {
                  $allFeatures = array_merge($allFeatures, array_keys($tier['features']));
                }
                $allFeatures = array_unique($allFeatures);
                sort($allFeatures);
                ?>
                <?php foreach ($allFeatures as $feature): ?>
                <tr class="border-b border-gray-100 dark:border-white/5">
                  <td class="p-4 font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($feature) ?></td>
                  <?php foreach ($data['tiers'] as $tierKey => $tier): ?>
                  <td class="text-center p-4">
                    <?php $hasFeature = $tier['features'][$feature] ?? false; ?>
                    <span class="inline-flex w-5 h-5 rounded-full items-center justify-center <?= $hasFeature ? 'bg-brand/20 text-brand' : 'bg-gray-100 dark:bg-white/5 text-gray-400' ?>">
                      <i class="bi <?= $hasFeature ? 'bi-check2' : 'bi-x' ?> text-xs"></i>
                    </span>
                  </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- FAQ Section -->
        <div class="max-w-3xl mx-auto">
          <h2 class="text-2xl font-bold mb-6">Frequently Asked Questions</h2>

          <div class="space-y-4">
            <details class="bg-white dark:bg-white/5 rounded-lg border border-gray-200 dark:border-white/10 p-6 group">
              <summary class="font-semibold cursor-pointer flex items-center justify-between">
                Can I change plans anytime?
                <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <p class="text-gray-600 dark:text-gray-400 mt-4">Yes! You can upgrade or downgrade your plan at any time. Changes take effect immediately, and we'll pro-rate any charges.</p>
            </details>

            <details class="bg-white dark:bg-white/5 rounded-lg border border-gray-200 dark:border-white/10 p-6 group">
              <summary class="font-semibold cursor-pointer flex items-center justify-between">
                Do you offer discounts for annual billing?
                <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <p class="text-gray-600 dark:text-gray-400 mt-4">Yes! Pay annually and save 20% compared to monthly billing. That's two months free!</p>
            </details>

            <details class="bg-white dark:bg-white/5 rounded-lg border border-gray-200 dark:border-white/10 p-6 group">
              <summary class="font-semibold cursor-pointer flex items-center justify-between">
                What payment methods do you accept?
                <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <p class="text-gray-600 dark:text-gray-400 mt-4">We accept all major credit cards (Visa, Mastercard, American Express), PayPal, and ACH transfers for enterprise customers.</p>
            </details>

            <details class="bg-white dark:bg-white/5 rounded-lg border border-gray-200 dark:border-white/10 p-6 group">
              <summary class="font-semibold cursor-pointer flex items-center justify-between">
                Is there a free trial?
                <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <p class="text-gray-600 dark:text-gray-400 mt-4">Free plans are forever free with no trial period needed. Paid plans include a 14-day free trial—no credit card required.</p>
            </details>

            <details class="bg-white dark:bg-white/5 rounded-lg border border-gray-200 dark:border-white/10 p-6 group">
              <summary class="font-semibold cursor-pointer flex items-center justify-between">
                Do you offer refunds?
                <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
              </summary>
              <p class="text-gray-600 dark:text-gray-400 mt-4">Yes, we offer a 30-day money-back guarantee. If you're not satisfied, we'll refund your payment, no questions asked.</p>
            </details>
          </div>
        </div>

      <?php elseif ($view === 'post' && !empty($data['post'])): ?>
        <!-- SINGLE POST VIEW -->
        <?php $post = $data['post']; $postImg = !empty($post['featured_image_url']) ? "/uploads/posts/{$post['featured_image_url']}" : DEFAULT_AVATAR; ?>
        <a href="/posts" class="inline-flex items-center gap-2 text-brand hover:underline mb-6">
          <i class="bi-arrow-left"></i> Back to Posts
        </a>

        <article class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
          <?php if (!empty($post['featured_image_url'])): ?>
          <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full aspect-video object-cover" onerror="this.style.display='none'">
          <?php endif; ?>
          <div class="p-6 lg:p-8">
            <h1 class="text-3xl lg:text-4xl font-bold mb-4"><?= htmlspecialchars($post['title'] ?? 'Untitled') ?></h1>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-200 dark:border-white/10">
              <?php if (!empty($post['author_user'])): ?>
              <a href="/artist/<?= htmlspecialchars($post['author_user']['slug'] ?? $post['author_user']['id']) ?>" class="flex items-center gap-2 hover:text-brand">
                <img src="<?= htmlspecialchars(user_image($post['author_user']['slug'] ?? '', $post['author_user']['image_url'] ?? null)) ?>" alt="" class="w-8 h-8 rounded-full object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                <span><?= htmlspecialchars($post['author_user']['display_name'] ?? 'Unknown') ?></span>
              </a>
              <?php endif; ?>
              <?php if (!empty($post['published_at'])): ?>
              <span><i class="bi-calendar3"></i> <?= date('F j, Y', strtotime($post['published_at'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="prose prose-lg dark:prose-invert max-w-none">
                <?php if ($post['is_locked']): ?>
                    <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 1rem; text-align: center; border: 1px solid var(--border);">
                        <h3 class="text-xl font-bold">This content is exclusive to subscribers.</h3>
                        <p>Subscribe to this artist to get access to this post and other exclusive content.</p>
                        <a href="#" class="btn btn-primary mt-4">Subscribe Now</a>
                    </div>
                <?php else: ?>
                    <?= $post['body'] ?? '' ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($post['tags'])): ?>
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-white/10">
              <div class="flex flex-wrap gap-2">
                <?php foreach (explode(',', $post['tags']) as $tag): ?>
                <span class="px-3 py-1 bg-gray-100 dark:bg-white/10 rounded-full text-sm"><?= htmlspecialchars(trim($tag)) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </article>

        <?php
          // Engagement UI for Posts
          $entity_type = 'post';
          $entity_id = (int)$post['id'];
          $entity_name = $post['title'];
          include __DIR__ . '/lib/partials/engagement-ui.php';
        ?>

      <?php elseif (in_array($view, ['artist', 'label', 'station', 'venue']) && $entity): ?>
        <!-- SINGLE ENTITY VIEW -->
        <?php
          $legacy = $entity['legacy'] ?? [];
          $scores = $entity['scores'] ?? [];
          $legacySlug = $legacy['Slug'] ?? $entity['slug'] ?? '';
          $artistSlugForReleases = $legacySlug;
          $entityImg = !empty($legacy['Image']) ? user_image($legacySlug, $legacy['Image']) : (($entity['image_url'] ?? null) ?: DEFAULT_AVATAR);
        ?>
        <a href="/<?= $view ?>s" class="inline-flex items-center gap-2 text-brand hover:underline mb-6">
          <i class="bi-arrow-left"></i> Back to <?= ucfirst($view) . 's' ?>
        </a>

        <?php if (!empty($entity['_debug'])): ?>
        <div class="bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded-lg p-4 mb-6 text-sm">
          <strong>Debug Info:</strong>
          <pre class="mt-2 text-xs overflow-auto"><?= htmlspecialchars(json_encode($entity['_debug'], JSON_PRETTY_PRINT)) ?></pre>
          <div class="mt-2">
            <strong>Releases count:</strong> <?= count($entity['releases'] ?? []) ?><br>
            <strong>Videos count:</strong> <?= count($entity['videos'] ?? []) ?><br>
            <strong>Posts count:</strong> <?= count($entity['posts'] ?? []) ?><br>
            <strong>Shows count:</strong> <?= count($entity['shows'] ?? []) ?><br>
            <strong>Legacy user found:</strong> <?= !empty($entity['legacy']) ? 'Yes' : 'No' ?><br>
            <strong>Scores found:</strong> <?= !empty($entity['scores']) ? 'Yes' : 'No' ?>
          </div>
          <?php if (!empty($entity['scores'])): ?>
          <div class="mt-2 p-2 bg-white/50 rounded">
            <strong>All Scores:</strong><br>
            <?php foreach ($entity['scores'] as $k => $v): ?>
            <span class="inline-block mr-3"><?= $k ?>: <b><?= $v ?></b></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden mb-6">
          <div class="md:flex">
            <div class="md:w-64 lg:w-80 flex-shrink-0">
              <img src="<?= htmlspecialchars($entityImg) ?>" alt="" class="w-full aspect-square object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            <div class="p-6 flex-1">
              <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($entity['name']) ?></h1>
              <?php if (!empty($entity['city']) || !empty($entity['region'])): ?>
              <div class="text-gray-500 mb-4"><i class="bi-geo-alt"></i> <?= htmlspecialchars(trim(($entity['city'] ?? '') . ', ' . ($entity['region'] ?? ''), ', ')) ?></div>
              <?php endif; ?>

              <!-- NGN Score Badge -->
              <?php if (!empty($scores['Score'])): ?>
              <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand/10 text-brand font-bold mb-4">
                <i class="bi-trophy"></i> NGN Score: <?= number_format((float)$scores['Score'], 2) ?>
              </div>
              <?php endif; ?>

              <!-- Social Links -->
              <?php if (!empty($legacy['WebsiteUrl']) || !empty($legacy['FacebookUrl']) || !empty($legacy['InstagramUrl']) || !empty($legacy['YoutubeUrl']) || !empty($legacy['TiktokUrl'])): ?>
              <div class="flex flex-wrap gap-2 mb-4">
                <?php if (!empty($legacy['WebsiteUrl'])): ?>
                  <a href="<?= htmlspecialchars($legacy['WebsiteUrl']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/10 text-sm hover:bg-brand hover:text-white transition-colors">
                    <i class="bi bi-globe"></i> Website
                  </a>
                <?php endif; ?>
                <?php if (!empty($legacy['FacebookUrl'])): ?>
                  <a href="<?= htmlspecialchars($legacy['FacebookUrl']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/10 text-sm hover:bg-brand hover:text-white transition-colors">
                    <i class="bi bi-facebook"></i> Facebook
                  </a>
                <?php endif; ?>
                <?php if (!empty($legacy['InstagramUrl'])): ?>
                  <a href="<?= htmlspecialchars($legacy['InstagramUrl']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/10 text-sm hover:bg-brand hover:text-white transition-colors">
                    <i class="bi bi-instagram"></i> Instagram
                  </a>
                <?php endif; ?>
                <?php if (!empty($legacy['YoutubeUrl'])): ?>
                  <a href="<?= htmlspecialchars($legacy['YoutubeUrl']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/10 text-sm hover:bg-brand hover:text-white transition-colors">
                    <i class="bi bi-youtube"></i> YouTube
                  </a>
                <?php endif; ?>
                <?php if (!empty($legacy['TiktokUrl'])): ?>
                  <a href="<?= htmlspecialchars($legacy['TiktokUrl']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-white/10 text-sm hover:bg-brand hover:text-white transition-colors">
                    <i class="bi bi-tiktok"></i> TikTok
                  </a>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($entity['bio']) || !empty($legacy['Body'])): ?>
              <div class="prose dark:prose-invert">
                <?= $entity['bio'] ?: $legacy['Body'] ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Upgrade CTA Section -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800/50 rounded-xl p-6 mb-6">
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-blue-900 dark:text-blue-100 mb-1">
                <i class="bi-star-fill text-yellow-500 mr-2"></i>Unlock More Features
              </h3>
              <p class="text-sm text-blue-800 dark:text-blue-200">Upgrade to <?= $view === 'artist' || $view === 'station' ? 'Pro or Premium' : 'Pro' ?> for advanced analytics, custom branding, and priority support.</p>

              <div class="flex flex-wrap gap-2 mt-3">
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-white/10 rounded text-xs font-medium text-blue-900 dark:text-blue-100">
                  <i class="bi-graph-up text-blue-600"></i> Advanced Analytics
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-white/10 rounded text-xs font-medium text-blue-900 dark:text-blue-100">
                  <i class="bi-palette text-blue-600"></i> Custom Branding
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-white/10 rounded text-xs font-medium text-blue-900 dark:text-blue-100">
                  <i class="bi-headset text-blue-600"></i> Priority Support
                </span>
                <?php if ($view === 'venue'): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-white/10 rounded text-xs font-medium text-blue-900 dark:text-blue-100">
                  <i class="bi-ticket-detailed text-blue-600"></i> Ticket Sales
                </span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0 flex-wrap">
              <button onclick="openFeatureModal()" class="px-6 py-2 rounded-lg bg-white dark:bg-white/10 text-blue-900 dark:text-blue-100 font-medium hover:bg-gray-100 dark:hover:bg-white/20 transition-colors flex items-center gap-2 whitespace-nowrap">
                <i class="bi-table"></i> Compare Features
              </button>
              <a href="/pricing" class="px-6 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 transition-colors flex items-center gap-2 whitespace-nowrap">
                <i class="bi-arrow-up-right"></i> See Pricing
              </a>
              <a href="/dashboard/<?= $view ?>/settings.php?tab=billing" class="px-6 py-2 rounded-lg bg-white dark:bg-white/10 text-blue-900 dark:text-blue-100 font-medium hover:bg-gray-100 dark:hover:bg-white/20 transition-colors flex items-center gap-2 whitespace-nowrap">
                <i class="bi-credit-card"></i> Upgrade Now
              </a>
            </div>
          </div>
        </div>

        <!-- Streaming/Watching CTA - NGN Exclusive -->
        <?php if (!empty($entity['streaming_urls'])): ?>
        <div class="bg-gradient-to-r from-brand to-brand/70 text-white rounded-xl p-6 mb-6">
          <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold mb-2"><i class="bi-play-circle mr-2"></i>Stream <?= htmlspecialchars($entity['name']) ?></h3>
              <p class="text-sm opacity-90">Listen on all major platforms - only on NGN</p>
            </div>
            <div class="flex gap-3 flex-wrap">
              <?php if (!empty($entity['streaming_urls']['listening'])): ?>
              <a href="<?= htmlspecialchars($entity['streaming_urls']['listening']) ?>" target="_blank" class="px-6 py-2 rounded-lg bg-white text-brand font-medium hover:bg-gray-100 transition-colors flex items-center gap-2">
                <i class="bi-music-note"></i> Listen Now
              </a>
              <?php endif; ?>
              <?php if (!empty($entity['streaming_urls']['watching'])): ?>
              <a href="<?= htmlspecialchars($entity['streaming_urls']['watching']) ?>" target="_blank" class="px-6 py-2 rounded-lg bg-white/20 text-white font-medium hover:bg-white/30 transition-colors flex items-center gap-2 border border-white/50">
                <i class="bi-play-btn"></i> Watch
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Engagement Metrics Dashboard - What Makes NGN Different -->
        <?php if (!empty($entity['engagement_metrics'])): ?>
        <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-6 mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-lightning-charge text-brand"></i> NGN Engagement</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
            <div class="text-center">
              <div class="text-3xl font-bold text-brand"><?= $entity['engagement_metrics']['total_videos'] ?></div>
              <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Videos</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-brand"><?= $entity['engagement_metrics']['total_songs'] ?></div>
              <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Tracks</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-brand"><?= $entity['engagement_metrics']['total_releases'] ?></div>
              <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Releases</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-brand"><?= $entity['engagement_metrics']['total_shows'] ?></div>
              <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Shows</div>
            </div>
            <div class="text-center">
              <div class="text-3xl font-bold text-brand"><?= $entity['engagement_metrics']['total_posts'] ?></div>
              <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Posts</div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Label Information -->
        <?php if (!empty($entity['labels'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-building text-brand"></i> Record Label</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($entity['labels'] as $label): ?>
            <a href="/label/<?= htmlspecialchars($label['Slug']) ?>" class="group bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden hover:border-brand transition-colors">
              <img src="<?= htmlspecialchars(!empty($label['Image']) ? user_image($label['Slug'], $label['Image']) : DEFAULT_AVATAR) ?>" alt="" class="w-full aspect-square object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="p-4">
                <h3 class="font-bold text-lg group-hover:text-brand"><?= htmlspecialchars($label['Title']) ?></h3>
                <?php if (!empty($label['Body'])): ?>
                <p class="text-sm text-gray-500 line-clamp-2 mt-2"><?= htmlspecialchars(strip_tags($label['Body'])) ?></p>
                <?php endif; ?>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- NGN Chart Rankings - Data-Driven Insights -->
        <?php if (!empty($entity['chart_rankings'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-bar-chart-fill text-brand"></i> NGN Chart History</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="grid grid-cols-4 gap-2 p-3 bg-gray-50 dark:bg-white/5 text-xs font-semibold text-gray-500 uppercase">
              <div>Date</div>
              <div>Rank</div>
              <div>Score</div>
              <div>Interval</div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
              <?php foreach ($entity['chart_rankings'] as $rank): ?>
              <div class="grid grid-cols-4 gap-2 p-3 hover:bg-gray-50 dark:hover:bg-white/5 items-center">
                <div class="text-sm"><?= date('M j, Y', strtotime($rank['PeriodEnd'])) ?></div>
                <div class="text-lg font-bold text-brand">
                  <?php if ($rank['RankNum'] && $rank['RankNum'] <= 10): ?>
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand/10">
                    #<?= $rank['RankNum'] ?>
                  </span>
                  <?php else: ?>
                  #<?= $rank['RankNum'] ?? '—' ?>
                  <?php endif; ?>
                </div>
                <div class="font-semibold"><?= number_format((float)$rank['Score'], 0) ?></div>
                <div class="text-xs text-gray-500"><?= ucfirst($rank['Interval']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="text-center mt-3 text-sm text-gray-500">
            <i class="bi-info-circle mr-1"></i> NGN Charts are updated daily with data-driven rankings across multiple factors
          </div>
        </div>
        <?php endif; ?>

        <!-- SMR Rankings - Spins Music Radio -->
        <?php if (!empty($entity['smr_rankings'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-broadcast text-brand"></i> SMR Radio Ranking</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="grid grid-cols-5 gap-2 p-3 bg-gray-50 dark:bg-white/5 text-xs font-semibold text-gray-500 uppercase">
              <div>Date</div>
              <div>TWP</div>
              <div>Song</div>
              <div>Label</div>
              <div>WOC</div>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5 max-h-80 overflow-y-auto">
              <?php foreach ($entity['smr_rankings'] as $rank): ?>
              <div class="grid grid-cols-5 gap-2 p-3 hover:bg-gray-50 dark:hover:bg-white/5 items-center text-sm">
                <div><?= date('M d', strtotime($rank['chart_date'])) ?></div>
                <div class="font-bold <?= ($rank['TWP'] ?? 0) <= 10 ? 'text-brand' : '' ?>"><?= $rank['TWP'] ?? '—' ?></div>
                <div class="truncate"><?= htmlspecialchars($rank['Song'] ?? '—') ?></div>
                <div class="truncate text-xs text-gray-500"><?= htmlspecialchars($rank['Label'] ?? '—') ?></div>
                <div><?= $rank['WOC'] ?? '—' ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="text-center mt-3 text-sm text-gray-500">
            <i class="bi-info-circle mr-1"></i> Spins Music Radio Weekly Chart Rankings
          </div>
        </div>
        <?php endif; ?>

        <!-- NGN Features Showcase -->
        <div class="mb-6">
          <div class="bg-gradient-to-br from-brand/5 to-transparent border border-brand/20 rounded-xl p-6">
            <h3 class="text-lg font-bold mb-4"><i class="bi-star text-brand mr-2"></i>Why NGN is Different</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex gap-3">
                <div class="flex-shrink-0">
                  <div class="flex items-center justify-center h-8 w-8 rounded-md bg-brand text-white">
                    <i class="bi-graph-up"></i>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold">Data-Driven Charts</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Real-time rankings powered by multiple data sources</p>
                </div>
              </div>
              <div class="flex gap-3">
                <div class="flex-shrink-0">
                  <div class="flex items-center justify-center h-8 w-8 rounded-md bg-brand text-white">
                    <i class="bi-people"></i>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold">Artist Community</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Connect with collaborators and discover new talent</p>
                </div>
              </div>
              <div class="flex gap-3">
                <div class="flex-shrink-0">
                  <div class="flex items-center justify-center h-8 w-8 rounded-md bg-brand text-white">
                    <i class="bi-radio"></i>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold">Radio Tracking</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Monitor your music across radio stations and platforms</p>
                </div>
              </div>
              <div class="flex gap-3">
                <div class="flex-shrink-0">
                  <div class="flex items-center justify-center h-8 w-8 rounded-md bg-brand text-white">
                    <i class="bi-play-circle"></i>
                  </div>
                </div>
                <div>
                  <h4 class="font-semibold">Multi-Platform</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Streaming, videos, events, and more in one place</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Score Breakdown (Artists/Labels) -->
        <?php if (!empty($scores) && in_array($view, ['artist', 'label'])): ?>
        <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-6 mb-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold"><i class="bi-bar-chart-fill text-brand"></i> Score Breakdown</h2>
            <div class="text-2xl font-bold text-brand"><?= number_format((float)($scores['Score'] ?? 0), 2) ?> pts</div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
            <?php
            $scoreFields = [
              'SMR_Score_Active' => ['SMR', 'bi-broadcast'],
              'Post_Mentions_Score_Active' => ['Mentions', 'bi-chat-quote'],
              'Views_Score_Active' => ['Views', 'bi-eye'],
              'Spins_Score_Active' => ['Spins', 'bi-disc'],
              'Releases_Score_Active' => ['releases', 'bi-vinyl'],
              'Posts_Score_Active' => ['posts', 'bi-file-text'],
              'Videos_Score_Active' => ['videos', 'bi-play-btn'],
              'Social_Score_Active' => ['Social', 'bi-share'],
            ];
            if ($view === 'artist') {
              $scoreFields['Label_Boost_Score'] = ['Label Boost', 'bi-building'];
            } else {
              $scoreFields['Artist_Boost_Score'] = ['Artist Boost', 'bi-people'];
            }
            foreach ($scoreFields as $field => [$label, $icon]):
              $val = $scores[$field] ?? 0;
            ?>
            <div class="bg-gray-50 dark:bg-white/5 rounded-lg p-3 text-center">
              <i class="<?= $icon ?> text-brand text-lg"></i>
              <div class="text-xl font-bold <?= $val > 0 ? '' : 'text-gray-300 dark:text-gray-600' ?>"><?= number_format((float)$val, 1) ?></div>
              <div class="text-xs text-gray-500"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Releases (Artists/Labels) -->
        <?php if (!empty($entity['releases'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-vinyl-fill text-brand"></i> Releases</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($entity['releases'] as $release): ?>
            <?php
              $releaseImg = !empty($release['Image']) ? "/uploads/releases/{$artistSlugForReleases}/{$release['Image']}" : DEFAULT_AVATAR;
            ?>
            <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
              <a href="/release/<?= htmlspecialchars($release['Slug']) ?>">
                <img src="<?= htmlspecialchars($releaseImg) ?>" alt="" class="w-full aspect-square object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              </a>
              <div class="p-4">
                <h3 class="font-bold text-lg"><?= htmlspecialchars($release['Title']) ?></h3>
                <p class="text-sm text-gray-500"><?= ucfirst($release['Type']) ?> • <?= date('Y', strtotime($release['ReleaseDate'])) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Videos (Artists/Labels) -->
        <?php if (!empty($entity['videos'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-play-btn-fill text-brand"></i> Videos</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($entity['videos'] as $video): ?>
            <div class="bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10">
              <div class="aspect-video">
                <iframe
                  src="https://www.youtube.com/embed/<?= htmlspecialchars($video['VideoId']) ?>?rel=0&modestbranding=1"
                  class="w-full h-full"
                  frameborder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen
                  loading="lazy"
                ></iframe>
              </div>
              <div class="p-3">
                <div class="font-semibold text-sm line-clamp-2"><?= htmlspecialchars($video['Title']) ?></div>
                <?php if (!empty($video['ArtistName'])): ?>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($video['ArtistName']) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Shows (Artists) -->
        <?php if (!empty($entity['shows'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-calendar-event-fill text-brand"></i> Upcoming Shows</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <?php foreach ($entity['shows'] as $show): ?>
            <div class="flex items-center gap-4 p-4 border-b border-gray-100 dark:border-white/5 last:border-0">
              <div class="text-center bg-brand/10 rounded-lg p-2 w-16">
                <div class="text-xs text-brand font-medium"><?= date('M', strtotime($show['ShowDate'])) ?></div>
                <div class="text-2xl font-bold text-brand"><?= date('j', strtotime($show['ShowDate'])) ?></div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold truncate"><?= htmlspecialchars($show['Venue'] ?? 'TBA') ?></div>
                <div class="text-sm text-gray-500"><?= htmlspecialchars(($show['City'] ?? '') . ($show['State'] ? ', ' . $show['State'] : '')) ?></div>
              </div>
              <?php if (!empty($show['TicketUrl'])): ?>
              <a href="<?= htmlspecialchars($show['TicketUrl']) ?>" target="_blank" class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-medium hover:bg-brand/90">Tickets</a>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Upcoming Shows (Venues) -->
        <?php if ($view === 'venue' && !empty($entity['upcoming_shows'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-calendar2-event text-brand"></i> Upcoming Shows (<?= count($entity['upcoming_shows']) ?>)</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-white/5">
              <?php foreach ($entity['upcoming_shows'] as $show):
                $showDate = strtotime($show['starts_at']);
                $showDateFormatted = date('F j, Y @ g:i A', $showDate);
              ?>
              <div class="flex items-start gap-4 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                <div class="text-center bg-brand/10 rounded-lg p-2 w-20 flex-shrink-0">
                  <div class="text-xs text-brand font-medium"><?= date('M', $showDate) ?></div>
                  <div class="text-2xl font-bold text-brand"><?= date('j', $showDate) ?></div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold"><?= htmlspecialchars($show['title'] ?? $show['name'] ?? 'Show') ?></div>
                  <div class="text-sm text-gray-500 mt-1">
                    <i class="bi-calendar3 mr-1"></i> <?= $showDateFormatted ?>
                  </div>
                  <?php if (!empty($show['description'])): ?>
                  <div class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-2"><?= htmlspecialchars($show['description']) ?></div>
                  <?php endif; ?>
                </div>
                <?php if (!empty($show['ticket_url'])): ?>
                <a href="<?= htmlspecialchars($show['ticket_url']) ?>" target="_blank" class="px-4 py-2 rounded bg-brand text-white font-medium hover:bg-brand/90 flex-shrink-0 whitespace-nowrap">
                  <i class="bi-ticket-detailed mr-1"></i> Get Tickets
                </a>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Past Shows (Venues) -->
        <?php if ($view === 'venue' && !empty($entity['past_shows'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-calendar2-check text-gray-400"></i> Past Shows (<?= count($entity['past_shows']) ?>)</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden opacity-75">
            <div class="divide-y divide-gray-100 dark:divide-white/5 max-h-96 overflow-y-auto">
              <?php $count = 0; foreach ($entity['past_shows'] as $show): if ($count >= 10) break; $count++;
                $showDate = strtotime($show['starts_at']);
              ?>
              <div class="flex items-center gap-4 p-4 text-sm">
                <div class="text-gray-400 font-medium w-24 flex-shrink-0"><?= date('M j, Y', $showDate) ?></div>
                <div class="flex-1 min-w-0">
                  <div class="text-gray-700 dark:text-gray-300 truncate"><?= htmlspecialchars($show['title'] ?? $show['name'] ?? 'Show') ?></div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (count($entity['past_shows']) > 10): ?>
              <div class="text-center p-4 text-sm text-gray-500 border-t border-gray-100 dark:border-white/5">
                +<?= count($entity['past_shows']) - 10 ?> more past shows
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Venue Ticket Sales Upsell (Venues) -->
        <?php if ($view === 'venue'): ?>
        <div class="bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 border border-purple-200 dark:border-purple-800/50 rounded-xl p-6 mb-6">
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-purple-900 dark:text-purple-100 mb-2">
                <i class="bi-ticket-detailed text-pink-600 mr-2"></i>Sell Tickets, Grow Revenue
              </h3>
              <p class="text-sm text-purple-800 dark:text-purple-200 mb-3">
                Upgrade to <strong>Pro</strong> to start selling tickets for your events directly on NGN. We handle payments, QR codes, and guest management. Keep 90% of ticket sales.
              </p>
              <ul class="space-y-2 text-sm text-purple-800 dark:text-purple-200">
                <li><i class="bi-check-circle text-pink-600 mr-2"></i>Online ticket sales with automatic payment processing</li>
                <li><i class="bi-check-circle text-pink-600 mr-2"></i>QR code entry scanning and guest management</li>
                <li><i class="bi-check-circle text-pink-600 mr-2"></i>Real-time attendance tracking and analytics</li>
                <li><i class="bi-check-circle text-pink-600 mr-2"></i>Customizable event pages and ticket tiers</li>
              </ul>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <a href="/dashboard/venue/settings.php?tab=billing" class="px-6 py-3 rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 transition-colors flex items-center gap-2 whitespace-nowrap">
                <i class="bi-credit-card"></i> Upgrade to Pro
              </a>
              <a href="/pricing" class="px-6 py-3 rounded-lg bg-white dark:bg-white/10 text-purple-900 dark:text-purple-100 font-medium hover:bg-gray-100 dark:hover:bg-white/20 transition-colors flex items-center gap-2 whitespace-nowrap">
                <i class="bi-info-circle"></i> Learn More
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Top Tracks/Songs (Artists) -->
        <?php if (!empty($entity['all_songs']) && $view === 'artist'): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-music-note-beamed text-brand"></i> Top Tracks</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-white/5">
              <?php $trackCount = 0; foreach ($entity['all_songs'] as $song): if ($trackCount >= 15) break; $trackCount++; ?>
              <div class="flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate"><?= htmlspecialchars($song['Title'] ?? 'Untitled') ?></div>
                  <div class="text-sm text-gray-500 truncate"><?= htmlspecialchars($song['ReleaseName'] ?? 'Unknown Release') ?></div>
                </div>
                <div class="text-sm text-gray-500 ml-4"><?= ($song['Duration'] ?? null) ? gmdate('i:s', $song['Duration']) : 'N/A' ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if (count($entity['all_songs']) > 15): ?>
          <div class="text-center mt-3 text-sm text-gray-500">+<?= count($entity['all_songs']) - 15 ?> more tracks</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- All Videos (Artists) -->
        <?php if (!empty($entity['all_videos']) && $view === 'artist' && count($entity['all_videos']) > count($entity['videos'] ?? [])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-play-circle-fill text-brand"></i> All Videos (<?= count($entity['all_videos']) ?>)</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php $videoCount = 0; foreach ($entity['all_videos'] as $video): if ($videoCount >= 18) break; $videoCount++; ?>
            <div class="bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10">
              <div class="aspect-video bg-black relative overflow-hidden">
                <?php if (!empty($video['VideoId'])): ?>
                <img src="https://img.youtube.com/vi/<?= htmlspecialchars($video['VideoId']) ?>/sddefault.jpg" alt="" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                <div class="absolute inset-0 flex items-center justify-center bg-black/20 hover:bg-black/40 transition-all">
                  <div class="bg-brand rounded-full p-3"><i class="bi-play-fill text-white text-lg"></i></div>
                </div>
                <?php else: ?>
                <div class="w-full h-full bg-gray-300 dark:bg-gray-700 flex items-center justify-center">
                  <i class="bi-video text-gray-500 text-3xl"></i>
                </div>
                <?php endif; ?>
              </div>
              <div class="p-3">
                <div class="font-semibold text-sm line-clamp-2"><?= htmlspecialchars($video['Title'] ?? 'Untitled') ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= date('M j, Y', strtotime($video['ReleaseDate'] ?? date('Y-m-d'))) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($entity['all_videos']) > 18): ?>
          <div class="text-center mt-3 text-sm text-gray-500">+<?= count($entity['all_videos']) - 18 ?> more videos</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- All Shows (Artists) -->
        <?php if (!empty($entity['all_shows']) && $view === 'artist' && count($entity['all_shows']) > count($entity['shows'] ?? [])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-calendar2-event text-brand"></i> Tour Dates (<?= count($entity['all_shows']) ?>)</h2>
          <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-white/5">
              <?php $showCount = 0; foreach ($entity['all_shows'] as $show): if ($showCount >= 20) break; $showCount++;
                $showDate = strtotime($show['ShowDate'] ?? date('Y-m-d'));
                $isUpcoming = $showDate >= strtotime('today');
              ?>
              <div class="flex items-center gap-4 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors <?= !$isUpcoming ? 'opacity-60' : '' ?>">
                <div class="text-center bg-brand/10 rounded-lg p-2 w-16 flex-shrink-0">
                  <div class="text-xs text-brand font-medium"><?= date('M', $showDate) ?></div>
                  <div class="text-2xl font-bold text-brand"><?= date('j', $showDate) ?></div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold truncate"><?= htmlspecialchars($show['Title'] ?? $show['Venue'] ?? 'TBA') ?></div>
                  <div class="text-sm text-gray-500"><?= htmlspecialchars(($show['Venue'] ?? '') . ($show['City'] ? ' - ' . $show['City'] : '') . ($show['State'] ? ', ' . $show['State'] : '')) ?></div>
                </div>
                <?php if (!empty($show['TicketUrl']) && $isUpcoming): ?>
                <a href="<?= htmlspecialchars($show['TicketUrl']) ?>" target="_blank" class="px-3 py-1.5 rounded text-sm bg-brand text-white hover:bg-brand/90 flex-shrink-0">Tickets</a>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if (count($entity['all_shows']) > 20): ?>
          <div class="text-center mt-3 text-sm text-gray-500"><?= $entity['past_shows_count'] ? 'Showing 20 of ' . count($entity['all_shows']) . ' total shows (' . $entity['past_shows_count'] . ' past)' : 'Showing 20 of ' . count($entity['all_shows']) . ' total shows' ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Collaborators (Artists) -->
        <?php if (!empty($entity['collaborators']) && $view === 'artist'): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-people-fill text-brand"></i> Collaborators</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($entity['collaborators'] as $collab): ?>
            <a href="/artist/<?= htmlspecialchars($collab['Slug']) ?>" class="group text-center">
              <img src="<?= htmlspecialchars(!empty($collab['Image']) ? user_image($collab['Slug'], $collab['Image']) : DEFAULT_AVATAR) ?>" alt="" class="w-full aspect-square rounded-lg object-cover mb-2 border border-gray-200 dark:border-white/10 group-hover:border-brand transition-colors" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="font-medium text-sm line-clamp-2 group-hover:text-brand"><?= htmlspecialchars($collab['Title']) ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Posts by Artist -->
        <?php if (!empty($entity['posts'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-file-text-fill text-brand"></i> Posts</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($entity['posts'] as $post): ?>
            <?php $postImg = !empty($post['image_url']) ? "/uploads/posts/{$post['image_url']}" : DEFAULT_AVATAR; ?>
            <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group bg-white dark:bg-white/5 rounded-xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-brand transition-colors">
              <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full aspect-video object-cover bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="p-4">
                <div class="font-semibold line-clamp-2 group-hover:text-brand mb-1"><?= htmlspecialchars($post['title']) ?></div>
                <div class="text-xs text-gray-500"><?= $post['published_at'] ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Label Roster -->
        <?php if (!empty($entity['roster'])): ?>
        <div class="mb-6">
          <h2 class="text-xl font-bold mb-4"><i class="bi-people-fill text-brand"></i> Artist Roster</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($entity['roster'] as $artist): ?>
            <?php $artistImg = user_image($artist['Slug'], $artist['Image']); ?>
            <a href="/artist/<?= htmlspecialchars($artist['Slug']) ?>" class="group bg-white dark:bg-white/5 rounded-xl p-4 border border-gray-200 dark:border-white/10 hover:border-brand transition-colors text-center">
              <img src="<?= htmlspecialchars($artistImg) ?>" alt="" class="w-16 h-16 mx-auto object-cover rounded-full mb-2 bg-gray-100 dark:bg-white/10" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="font-medium text-sm truncate group-hover:text-brand"><?= htmlspecialchars($artist['Title']) ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      <?php elseif (in_array($view, ['shop', 'shops'])): ?>
        <!-- SHOPS -->
        <?php 
          $shopType = $_GET['type'] ?? 'all'; 
          $entityTypeFilter = null;
          if ($shopType === 'artists') $entityTypeFilter = 'artist';
          if ($shopType === 'labels') $entityTypeFilter = 'label';
          if ($shopType === 'stations') $entityTypeFilter = 'station';
          if ($shopType === 'venues') $entityTypeFilter = 'venue';
          if ($shopType === 'ngn') $entityTypeFilter = 'ngn';

          $products = [];
          $totalProducts = 0;
          if ($productService) {
              $result = $productService->list(
                  $entityTypeFilter, 
                  null, 
                  null, 
                  $search, 
                  true, 
                  $page, 
                  $perPage, 
                  'created_at', 
                  'desc'
              );
              $products = $result['items'];
              $totalProducts = $result['total'];
          }
          $totalPages = $totalProducts > 0 ? ceil($totalProducts / $perPage) : 1;
        ?>
        <div class="mb-6">
          <h1 class="text-2xl font-bold">Merch Shops</h1>
          <p class="text-gray-500">Official merchandise from NGN, artists, labels, stations & venues</p>
        </div>

        <!-- Shop Type Tabs -->
        <div class="flex flex-wrap gap-2 mb-6">
          <a href="/shop?type=all" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'all' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">All Shops</a>
          <a href="/shop?type=ngn" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'ngn' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">NGN Official</a>
          <a href="/shop?type=artists" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'artists' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">Artist Shops</a>
          <a href="/shop?type=labels" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'labels' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">Label Shops</a>
          <a href="/shop?type=stations" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'stations' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">Station Shops</a>
          <a href="/shop?type=venues" class="px-4 py-2 rounded-lg font-medium <?= $shopType === 'venues' ? 'bg-brand text-white' : 'bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand' ?>">Venue Shops</a>
        </div>

        <?php if (empty($products)): ?>
        <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-12 text-center">
          <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-brand/10 flex items-center justify-center">
            <i class="bi-bag-heart text-4xl text-brand"></i>
          </div>
          <h2 class="text-2xl font-bold mb-2">Shops Coming Soon</h2>
          <p class="text-gray-500 max-w-md mx-auto mb-6">
            Our integrated merch platform is launching soon! Artists, labels, stations, and venues will be able to create their own shops powered by Printful + Stripe.
          </p>
          <div class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-gray-100 dark:bg-white/10 text-gray-500">
            <i class="bi-clock"></i> No products found
          </div>
        </div>
        <?php else: ?>
        
        <!-- Product Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($products as $product): ?>
            <a href="/product/<?= htmlspecialchars($product['slug']) ?>" class="group bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-4 hover:border-brand transition-colors text-center">
              <div class="w-full aspect-square bg-gray-100 dark:bg-white/10 rounded-lg mb-3 flex items-center justify-center overflow-hidden">
                <?php if (!empty($product['image_url'])): ?>
                  <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                <?php else: ?>
                  <i class="bi-bag text-4xl text-gray-300 dark:text-gray-600"></i>
                <?php endif; ?>
              </div>
              <div class="font-medium text-sm truncate group-hover:text-brand"><?= htmlspecialchars($product['name']) ?></div>
              <div class="text-brand font-bold mt-1"><?= htmlspecialchars($product['currency']) ?> <?= number_format($product['price'], 2) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-2 mt-8">
          <?php if ($page > 1): ?>
          <a href="/shop?type=<?= $shopType ?>&page=<?= $page - 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">← Prev</a>
          <?php endif; ?>
          <span class="px-4 py-2 text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
          <a href="/shop?type=<?= $shopType ?>&page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-4 py-2 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 hover:border-brand">Next →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

      <?php elseif ($view === 'product'): ?>
        <!-- SINGLE PRODUCT VIEW -->
        <?php 
          $slug = $_GET['slug'] ?? '';
          $product = $productService ? $productService->getBySlug($slug) : null;
        ?>
        
        <?php if (!$product): ?>
          <div class="text-center py-20">
            <h1 class="text-2xl font-bold mb-4">Product Not Found</h1>
            <a href="/shop" class="text-brand hover:underline">Back to Shops</a>
          </div>
        <?php else: ?>
          <a href="/shop" class="inline-flex items-center gap-2 text-brand hover:underline mb-6">
            <i class="bi-arrow-left"></i> Back to Shops
          </a>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-8 bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 p-6 lg:p-8">
            <!-- Product Images -->
            <div>
              <div class="aspect-square bg-gray-100 dark:bg-white/10 rounded-xl overflow-hidden mb-4 flex items-center justify-center">
                <?php if (!empty($product['image_url'])): ?>
                  <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                  <i class="bi-bag text-6xl text-gray-300 dark:text-gray-600"></i>
                <?php endif; ?>
              </div>
              <!-- Thumbnails could go here -->
            </div>

            <!-- Product Details -->
            <div>
              <div class="mb-4">
                <?php if (!empty($product['category'])): ?>
                  <span class="text-xs uppercase tracking-wide text-gray-500 font-semibold"><?= htmlspecialchars($product['category']) ?></span>
                <?php endif; ?>
                <h1 class="text-3xl font-bold mt-1 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                <div class="text-2xl font-bold text-brand"><?= htmlspecialchars($product['currency']) ?> <?= number_format($product['price'], 2) ?></div>
              </div>

              <div class="prose prose-sm dark:prose-invert text-gray-600 dark:text-gray-300 mb-6">
                <?= nl2br(htmlspecialchars($product['description'] ?? '')) ?>
              </div>

              <!-- Add to Cart (Placeholder for now) -->
              <div class="border-t border-gray-200 dark:border-white/10 pt-6">
                <button class="w-full py-3 rounded-lg bg-brand text-white font-bold hover:bg-brand/90 transition-colors shadow-lg shadow-brand/20">
                  <i class="bi-cart-plus mr-2"></i> Add to Cart
                </button>
                <p class="text-xs text-center text-gray-500 mt-3">
                  <i class="bi-shield-check mr-1"></i> Secure checkout via Stripe
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

      <?php elseif ($view === 'release' && !empty($data['release'])): ?>
        <!-- SINGLE RELEASE VIEW -->
        <?php $release = $data['release']; ?>
        <div class="max-w-5xl mx-auto">
            <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="inline-flex items-center gap-2 text-brand hover:underline mb-8 font-bold">
                <i class="bi-arrow-left"></i> Back to Artist
            </a>

            <div class="flex flex-col md:flex-row gap-10 mb-12">
                <div class="w-full md:w-80 flex-shrink-0">
                    <img src="<?= htmlspecialchars(($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR) ?>" 
                         class="w-full aspect-square object-cover rounded-2xl shadow-2xl border border-white/5" alt=""
                         onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                </div>
                <div class="flex-1 flex flex-col justify-end">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-brand mb-2"><?= ucfirst($release['type'] ?? 'Album') ?></span>
                    <h1 class="text-4xl lg:text-6xl font-black mb-4 tracking-tighter"><?= htmlspecialchars($release['title']) ?></h1>
                    <div class="flex items-center gap-3 text-sm font-bold text-white/60">
                        <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="hover:text-white transition-colors underline decoration-brand underline-offset-4"><?= htmlspecialchars($release['artist']['name'] ?? 'Unknown Artist') ?></a>
                        <span class="w-1 h-1 bg-white/20 rounded-full"></span>
                        <span><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : 'N/A' ?></span>
                        <span class="w-1 h-1 bg-white/20 rounded-full"></span>
                        <span><?= count($release['tracks'] ?? []) ?> Songs</span>
                    </div>
                </div>
            </div>

            <!-- Tracklist -->
            <div class="bg-white/5 rounded-3xl border border-white/5 overflow-hidden mb-12">
                <div class="p-6 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-xl font-bold">Tracklist</h2>
                    <button class="bg-brand text-black w-12 h-12 rounded-full flex items-center justify-center hover:scale-105 transition-all shadow-lg shadow-brand/20">
                        <i class="bi-play-fill text-2xl"></i>
                    </button>
                </div>
                <div class="divide-y divide-white/5">
                    <?php if (!empty($release['tracks'])): ?>
                        <?php foreach ($release['tracks'] as $i => $track): ?>
                        <div class="flex items-center gap-4 p-4 hover:bg-white/5 transition-colors group">
                            <span class="w-6 text-center text-white/20 font-bold group-hover:text-brand"><?= $i + 1 ?></span>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold truncate"><?= htmlspecialchars($track['title']) ?></div>
                                <div class="text-xs text-white/40"><?= htmlspecialchars($release['artist']['name'] ?? '') ?></div>
                            </div>
                            <div class="text-sm text-white/40 font-mono"><?= ($track['duration_seconds'] ?? 0) ? gmdate('i:s', $track['duration_seconds']) : '--:--' ?></div>
                            <button class="text-white/20 hover:text-brand transition-colors"><i class="bi-plus-circle"></i></button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-12 text-center text-white/40">No tracks listed for this release.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- About Release -->
            <?php if (!empty($release['description'])): ?>
            <div class="prose prose-invert max-w-none bg-white/5 p-8 rounded-3xl border border-white/5">
                <h3 class="text-white">About this release</h3>
                <?= nl2br($release['description']) ?>
            </div>
            <?php endif; ?>
        </div>

      <?php elseif ($view === 'song' && !empty($data['track'])): ?>
        <!-- SINGLE TRACK VIEW -->
        <?php $track = $data['track']; ?>
        <div class="max-w-3xl mx-auto py-12 text-center">
            <div class="mb-8">
                <i class="bi-music-note-beamed text-brand text-6xl opacity-20"></i>
            </div>
            <h1 class="text-4xl font-black mb-4"><?= htmlspecialchars($track['title']) ?></h1>
            <p class="text-gray-500 mb-8 font-bold uppercase tracking-widest">Single Track View</p>
            
            <div class="flex flex-wrap justify-center gap-4">
                <button class="px-8 py-3 bg-brand text-black font-black rounded-full hover:scale-105 transition-all shadow-lg shadow-brand/20 flex items-center gap-2">
                    <i class="bi-play-fill text-xl"></i> PLAY SONG
                </button>
                <button class="px-8 py-3 bg-white/5 text-white font-bold rounded-full hover:bg-white/10 transition-all border border-white/10">
                    ADD TO PLAYLIST
                </button>
            </div>
        </div>

      <?php elseif ($view === '404'): ?>
        <!-- 404 PAGE -->
        <?php http_response_code(404); ?>
        <div class="text-center py-20">
          <div class="text-8xl mb-6">🎸</div>
          <h1 class="text-4xl font-bold mb-4">404 - Page Not Found</h1>
          <p class="text-gray-500 mb-8 max-w-md mx-auto">The page you're looking for doesn't exist or has been moved. Maybe try searching for what you need?</p>
          <div class="flex flex-wrap justify-center gap-4">
            <a href="/" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand text-white font-medium hover:bg-brand/90">
              <i class="bi-house"></i> Go Home
            </a>
            <a href="/charts" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 font-medium hover:border-brand">
              <i class="bi-bar-chart-fill"></i> View Charts
            </a>
            <a href="/artists" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 font-medium hover:border-brand">
              <i class="bi-music-note-beamed"></i> Browse Artists
            </a>
          </div>
        </div>

      <?php else: ?>
        <!-- FALLBACK 404 -->
        <div class="text-center py-20">
          <div class="text-6xl mb-4">🎸</div>
          <h1 class="text-2xl font-bold mb-2">Page Not Found</h1>
          <p class="text-gray-500 mb-6">The page you're looking for doesn't exist.</p>
          <a href="/" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand text-white font-medium">
            <i class="bi-house"></i> Go Home
          </a>
        </div>
      <?php endif; ?>

      </div>
    </main>
  </div>

  <!-- Audio Player (Fixed Bottom) -->
  <div id="audio-player" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-white/10 p-3 transform translate-y-full transition-transform z-50" style="display:none;">
    <div class="max-w-7xl mx-auto flex items-center gap-4">
      <button onclick="togglePlay()" id="play-btn" class="w-10 h-10 flex items-center justify-center rounded-full bg-brand text-white">
        <i class="bi-play-fill" id="play-icon"></i>
      </button>
      <div class="flex-1 min-w-0">
        <div id="track-title" class="font-medium text-sm truncate">-</div>
        <div id="track-artist" class="text-xs text-gray-500 truncate">-</div>
      </div>
      <div class="flex items-center gap-2 text-xs text-gray-500">
        <span id="current-time">0:00</span>
        <input type="range" id="seek-bar" class="w-24 md:w-48 accent-brand" min="0" max="100" value="0">
        <span id="duration">0:00</span>
      </div>
      <button onclick="closePlayer()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
        <i class="bi-x-lg"></i>
      </button>
    </div>
    <audio id="audio-element"></audio>
  </div>

  <script>
    const audio = document.getElementById('audio-element');
    const player = document.getElementById('audio-player');
    const playBtn = document.getElementById('play-btn');
    const playIcon = document.getElementById('play-icon');
    const seekBar = document.getElementById('seek-bar');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const trackTitleEl = document.getElementById('track-title');
    const trackArtistEl = document.getElementById('track-artist');

    function formatTime(s) {
      const m = Math.floor(s / 60);
      const sec = Math.floor(s % 60);
      return m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    function playTrack(url, title, artist) {
      audio.src = url;
      trackTitleEl.textContent = title;
      trackArtistEl.textContent = artist;
      player.style.display = 'block';
      setTimeout(() => player.classList.remove('translate-y-full'), 10);
      audio.play();
      playIcon.className = 'bi-pause-fill';
    }

    function togglePlay() {
      if (audio.paused) {
        audio.play();
        playIcon.className = 'bi-pause-fill';
      } else {
        audio.pause();
        playIcon.className = 'bi-play-fill';
      }
    }

    function closePlayer() {
      audio.pause();
      player.classList.add('translate-y-full');
      setTimeout(() => player.style.display = 'none', 300);
    }

    audio.addEventListener('timeupdate', () => {
      if (audio.duration) {
        seekBar.value = (audio.currentTime / audio.duration) * 100;
        currentTimeEl.textContent = formatTime(audio.currentTime);
      }
    });

    audio.addEventListener('loadedmetadata', () => {
      durationEl.textContent = formatTime(audio.duration);
    });

    audio.addEventListener('ended', () => {
      playIcon.className = 'bi-play-fill';
    });

    seekBar.addEventListener('input', () => {
      if (audio.duration) {
        audio.currentTime = (seekBar.value / 100) * audio.duration;
      }
    });

    // Loading overlay for page transitions
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(loadingOverlay);

    function showLoading() {
      loadingOverlay.classList.add('active');
    }
    function hideLoading() {
      loadingOverlay.classList.remove('active');
    }

    // Add loading state to navigation links
    document.querySelectorAll('a:not([href^="#"]):not([target="_blank"])').forEach(link => {
      link.addEventListener('click', function(e) {
        // Don't show loading for same-page anchors, external links, or javascript
        const href = this.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || this.target === '_blank') return;
        showLoading();
      });
    });

    // Add loading state to forms
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function() {
        showLoading();
        const btn = this.querySelector('button[type="submit"]');
        if (btn) btn.classList.add('btn-loading');
      });
    });

    // Hide loading on page load (for back/forward navigation)
    window.addEventListener('pageshow', hideLoading);

    // Feature Comparison Modal functions
    function openFeatureModal() {
      const modal = document.getElementById('featureModal');
      if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeFeatureModal() {
      const modal = document.getElementById('featureModal');
      if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    }

    // Close modal when clicking outside
    document.getElementById('featureModal')?.addEventListener('click', function(e) {
      if (e.target === this) {
        closeFeatureModal();
      }
    });

    // Expose functions for onclick handlers
    window.openFeatureModal = openFeatureModal;
    window.closeFeatureModal = closeFeatureModal;
  </script>

  <!-- Feature Comparison Modal -->
  <div id="featureModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#111118] rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
      <div class="sticky top-0 bg-white dark:bg-[#111118] border-b border-gray-200 dark:border-white/10 p-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold">Feature Comparison</h2>
        <button onclick="closeFeatureModal()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
          <i class="bi-x text-2xl"></i>
        </button>
      </div>

      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="text-left p-3 font-semibold">Feature</th>
                <th class="text-center p-3 font-semibold">Free</th>
                <th class="text-center p-3 font-semibold">Pro</th>
                <th class="text-center p-3 font-semibold">Premium</th>
                <th class="text-center p-3 font-semibold">Enterprise</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Use tier data for modal
              $tierComparison = [
                'Basic Profile' => ['free' => true, 'pro' => true, 'premium' => true, 'enterprise' => true],
                'Upload Content' => ['free' => true, 'pro' => true, 'premium' => true, 'enterprise' => true],
                'View Charts' => ['free' => true, 'pro' => true, 'premium' => true, 'enterprise' => true],
                'Basic Analytics' => ['free' => false, 'pro' => true, 'premium' => true, 'enterprise' => true],
                'Priority Support' => ['free' => false, 'pro' => true, 'premium' => true, 'enterprise' => true],
                'Custom Domain' => ['free' => false, 'pro' => false, 'premium' => true, 'enterprise' => true],
                'API Access' => ['free' => false, 'pro' => false, 'premium' => true, 'enterprise' => true],
                'Remove Ads' => ['free' => false, 'pro' => true, 'premium' => true, 'enterprise' => true],
              ];
              ?>
              <?php foreach ($tierComparison as $feature => $tiers): ?>
              <tr class="border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5">
                <td class="p-3 font-medium"><?= htmlspecialchars($feature) ?></td>
                <td class="text-center p-3">
                  <span class="inline-flex w-5 h-5 rounded-full items-center justify-center <?= $tiers['free'] ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 dark:bg-white/5 text-gray-400' ?>">
                    <i class="bi <?= $tiers['free'] ? 'bi-check2' : 'bi-x' ?> text-xs"></i>
                  </span>
                </td>
                <td class="text-center p-3">
                  <span class="inline-flex w-5 h-5 rounded-full items-center justify-center <?= $tiers['pro'] ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 dark:bg-white/5 text-gray-400' ?>">
                    <i class="bi <?= $tiers['pro'] ? 'bi-check2' : 'bi-x' ?> text-xs"></i>
                  </span>
                </td>
                <td class="text-center p-3">
                  <span class="inline-flex w-5 h-5 rounded-full items-center justify-center <?= $tiers['premium'] ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 dark:bg-white/5 text-gray-400' ?>">
                    <i class="bi <?= $tiers['premium'] ? 'bi-check2' : 'bi-x' ?> text-xs"></i>
                  </span>
                </td>
                <td class="text-center p-3">
                  <span class="inline-flex w-5 h-5 rounded-full items-center justify-center bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                    <i class="bi bi-check2 text-xs"></i>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-white/10">
          <h3 class="text-lg font-bold mb-4">Pricing Summary</h3>
          <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-white/5 rounded-lg">
              <div class="font-semibold mb-2">Free</div>
              <div class="text-2xl font-bold text-brand mb-2">$0</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Forever</div>
            </div>
            <div class="p-4 bg-brand/10 rounded-lg border border-brand/30">
              <div class="font-semibold mb-2">Pro</div>
              <div class="text-2xl font-bold text-brand mb-2">$9.99</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">/month (artists, stations)</div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-white/5 rounded-lg">
              <div class="font-semibold mb-2">Premium</div>
              <div class="text-2xl font-bold text-brand mb-2">$24.99</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">/month (artists, stations)</div>
            </div>
            <div class="p-4 bg-gray-50 dark:bg-white/5 rounded-lg">
              <div class="font-semibold mb-2">Enterprise</div>
              <div class="text-2xl font-bold text-brand mb-2">Custom</div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Contact sales</div>
            </div>
          </div>
        </div>

        <div class="mt-6 flex gap-3 justify-center">
          <a href="/pricing" class="px-6 py-3 rounded-lg bg-brand text-white font-medium hover:bg-brand/80 transition-colors">
            See Full Pricing
          </a>
          <button onclick="closeFeatureModal()" class="px-6 py-3 rounded-lg bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

</body>
</html>

