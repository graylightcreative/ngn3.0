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
$validViews = ['home', 'artists', 'labels', 'stations', 'venues', 'charts', 'smr-charts', 'posts', 'videos', 'artist', 'label', 'station', 'venue', 'post', 'video', 'releases', 'songs', 'release', 'song', 'shop', 'shops', 'product', 'pricing', 'agreement', '404'];
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
    
    // Exclude specific "user" entities from main intelligence feed
    // 1286 = Heroes and Villains, 31 = Wake Up! Music Rocks
    $where .= " AND NOT ( (p.entity_type = 'artist' AND p.entity_id = 1286) OR (p.entity_type = 'label' AND p.entity_id = 31) OR p.author_id IN (1286, 31) )";

    if ($search !== '') $where .= " AND p.title LIKE :search";
    $sql = "SELECT p.id, p.slug, p.title, p.excerpt, COALESCE(p.content, p.excerpt) as body, p.featured_image_url, p.published_at, p.created_at, p.updated_at, a.name as author_name
            FROM `ngn_2025`.`posts` p
            LEFT JOIN `ngn_2025`.`artists` a ON p.author_id = a.id
            {$where} ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($posts as &$post) {
        if (!empty($post['featured_image_url'])) {
            if (!str_starts_with($post['featured_image_url'], 'http') && !str_starts_with($post['featured_image_url'], '/')) {
                $post['featured_image_url'] = "/uploads/{$post['featured_image_url']}";
            }
        }
    }
    return $posts;
}

function get_ngn_posts_count(PDO $pdo, string $search = ''): int {
    $where = "WHERE status = 'published'";
    
    // Exclude specific "user" entities from main intelligence feed
    $where .= " AND NOT ( (entity_type = 'artist' AND entity_id = 1286) OR (entity_type = 'label' AND entity_id = 31) OR author_id IN (1286, 31) )";

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
    if (str_starts_with($image, 'http') || str_starts_with($image, '/')) return $image;
    return "/uploads/users/{$slug}/{$image}";
}

/**
 * Render a placeholder with an upsell message for empty profile sections
 */
function render_profile_upsell(string $title, string $description, bool $isClaimed, string $slug) {
    ?>
    <div class="bg-zinc-900/30 rounded-3xl border-2 border-dashed border-white/5 p-12 text-center group/upsell hover:border-brand/20 transition-all">
        <div class="w-16 h-16 rounded-2xl bg-zinc-800 flex items-center justify-center mx-auto mb-6 group-hover/upsell:scale-110 transition-transform">
            <i class="bi bi-rocket-takeoff text-2xl text-zinc-600 group-hover/upsell:text-brand"></i>
        </div>
        <h3 class="text-xl font-black mb-2 tracking-tight"><?= htmlspecialchars($title) ?></h3>
        <p class="text-zinc-500 text-sm max-w-sm mx-auto mb-8"><?= htmlspecialchars($description) ?></p>
        
        <?php if (!$isClaimed): ?>
            <a href="/claim-profile.php?slug=<?= urlencode($slug) ?>" class="inline-flex items-center gap-2 px-8 py-3 bg-brand text-black font-black rounded-full hover:scale-105 transition-all uppercase tracking-widest text-xs">
                Claim Profile to Unlock
            </a>
        <?php else: ?>
            <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest">Awaiting First Upload</div>
        <?php endif; ?>
    </div>
    <?php
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
            $data['songs'] = [];
            try {
                $stmt = $pdo->prepare("SELECT t.*, a.name as artist_name, r.title as release_name, r.cover_url 
                                       FROM `ngn_2025`.`tracks` t 
                                       LEFT JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id 
                                       LEFT JOIN `ngn_2025`.`releases` r ON t.release_id = r.id
                                       ORDER BY t.id DESC LIMIT 5");
                $stmt->execute();
                $data['songs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {}

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
            try {
                $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`videos` WHERE (slug = :slug OR id = :id) LIMIT 1');
                $stmt->execute([':slug' => $identifier, ':id' => $identifier]);
                $video = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($video) {
                    // Map database columns to template expected keys
                    $video['platform'] = $video['video_type'] ?? 'youtube';
                    $video['external_id'] = $video['video_id'] ?? '';
                    $video['is_locked'] = false; 

                    // Get artist info (author)
                    $author_id = $video['entity_id'] ?? null;
                    if ($author_id) {
                        $userStmt = $pdo->prepare('SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1');
                        $userStmt->execute([$author_id]);
                        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                        if ($user) {
                            $video['author_entity'] = $user;
                        }
                    }
                    $data['video'] = $video;
                }
            } catch (\Throwable $e) {
                error_log("Error fetching video: " . $e->getMessage());
            }
        } elseif ($view === 'releases') {
            $offset = ($page - 1) * $perPage;
            $where = $search !== '' ? "WHERE r.title LIKE :search" : '';
            $sql = "SELECT r.*, r.cover_url as cover_image_url, a.name as artist_name FROM `ngn_2025`.`releases` r 
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
        } elseif ($view === 'agreement' && isset($_GET['slug'])) {
            $templateSlug = trim($_GET['slug']);
            try {
                $service = new \NGN\Lib\Services\Legal\AgreementService($pdo);
                $data['agreement_template'] = $service->getTemplate($templateSlug);
                if ($isLoggedIn) {
                    $data['agreement_signed'] = $service->hasSigned((int)$_SESSION['user_id'], $templateSlug);
                }
            } catch (\Throwable $e) {
                error_log("Error fetching agreement: " . $e->getMessage());
            }
        } elseif ($view === 'smr-charts') {
            // SMR Charts - Using dedicated ngn_smr_2025 database
            $data['smr_charts'] = [];
            $data['smr_date'] = null;

            try {
                $smrPdo = \NGN\Lib\DB\ConnectionFactory::named($config, 'smr2025');
                
                // Get most recent chart date
                $stmt = $smrPdo->query('SELECT MAX(window_date) as latest FROM `smr_chart`');
                $latest = $stmt->fetch(PDO::FETCH_ASSOC);
                $latestDate = $latest['latest'] ?? null;

                if ($latestDate) {
                    $data['smr_date'] = date('F j, Y', strtotime($latestDate));

                    // Get top 200 songs from latest chart date
                    $stmt = $smrPdo->prepare('
                        SELECT 
                            sc.artist as Artists, 
                            sc.track as Song, 
                            sc.label as Label,
                            sc.tws as TWS,
                            sc.lws as LWS,
                            sc.woc as WOC,
                            sc.rank as TWP
                        FROM `smr_chart` sc
                        WHERE sc.window_date = ? 
                        ORDER BY sc.rank ASC 
                        LIMIT 200
                    ');
                    $stmt->execute([$latestDate]);
                    $smrData = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Fetch NGN artist details separately to avoid cross-db permission issues
                    $artistNames = array_unique(array_column($smrData, 'Artists'));
                    
                    $ngnArtists = [];
                    if (!empty($artistNames)) {
                        $placeholders = implode(',', array_fill(0, count($artistNames), '?'));
                        $aStmt = $pdo->prepare("SELECT id, name, slug, image_url FROM `ngn_2025`.`artists` WHERE name IN ($placeholders)");
                        $aStmt->execute(array_values($artistNames));
                        while ($row = $aStmt->fetch(PDO::FETCH_ASSOC)) {
                            $ngnArtists[strtolower($row['name'])] = $row;
                        }
                    }

                    // Transform to expected output format
                    foreach ($smrData as &$row) {
                        $row['LWP'] = $row['LWS'] ?: '-';
                        $artistKey = strtolower($row['Artists']);
                        $artistData = $ngnArtists[$artistKey] ?? null;
                        
                        $row['artist'] = [
                            'id' => $artistData['id'] ?? null,
                            'name' => $row['Artists'],
                            'slug' => $artistData['slug'] ?? null,
                            'image_url' => $artistData['image_url'] ?? null
                        ];
                    }
                    $data['smr_charts'] = $smrData;
                }
            } catch (\Throwable $e) {
                error_log("Error fetching SMR charts: " . $e->getMessage());
                $data['smr_charts'] = [];
            }
        } elseif ($view === 'release' && isset($_GET['slug'])) {
            $stmt = $pdo->prepare('SELECT *, cover_url as cover_image_url FROM `ngn_2025`.`releases` WHERE slug = ? LIMIT 1');
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
            $stmt = $pdo->prepare('SELECT id, slug, title, excerpt, COALESCE(content, excerpt) as body, tags, featured_image_url, published_at, created_at, updated_at, author_id, required_tier_id, entity_type, entity_id FROM `ngn_2025`.`posts` WHERE (slug = :id OR id = :id) AND status = :status LIMIT 1');
            $stmt->execute([':id' => $identifier, ':status' => 'published']);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post && !empty($post['featured_image_url'])) {
                if (!str_starts_with($post['featured_image_url'], 'http') && !str_starts_with($post['featured_image_url'], '/')) {
                    $post['featured_image_url'] = "/uploads/{$post['featured_image_url']}";
                }
            }

            if ($post) {
                $isLocked = false;
                if (!empty($post['required_tier_id']) && $currentUser) {
                    // Assuming the post entity_type is 'artist' for tier check with user_fan_subscriptions
                    // is_subscribed function already refactored to use ngn_2025.user_fan_subscriptions
                    $isSubscribed = is_subscribed($pdo, (int)$currentUser['id'], (int)$post['author_id'], (int)$post['required_tier_id']);
                    
                    if (!$isSubscribed) {
                        $isLocked = true;
                        $post['body'] = 'This content is exclusive to subscribers.';
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
                    $stmt = $pdo->prepare('SELECT r.id, r.title, r.slug, r.cover_url as cover_image_url, r.release_date, t.id AS track_id, t.title AS track_title, t.duration_seconds FROM `ngn_2025`.`releases` r LEFT JOIN `ngn_2025`.`tracks` t ON r.id = t.release_id WHERE r.artist_id = ? ORDER BY r.release_date DESC LIMIT 24');
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
                    $stmt = $pdo->prepare('SELECT t.id, t.title, t.duration_seconds, r.title as ReleaseName, r.release_date FROM `ngn_2025`.`tracks` t LEFT JOIN `ngn_2025`.`releases` r ON t.release_id = r.id WHERE t.artist_id = ? ORDER BY t.id DESC LIMIT 50');
                    $stmt->execute([$entity['id']]);
                    $entity['all_songs'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get all videos (unlimited)
                    $stmt = $pdo->prepare('SELECT id, slug, title, platform, external_id, published_at FROM `ngn_2025`.`videos` WHERE artist_id = ? OR (entity_type = "artist" AND entity_id = ?) ORDER BY published_at DESC');
                    $stmt->execute([$entity['id'], $entity['id']]);
                    $entity['all_videos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    // Get posts for this artist
                    $stmt = $pdo->prepare('SELECT id, slug, title, excerpt, COALESCE(content, excerpt) as body, featured_image_url, published_at FROM `ngn_2025`.`posts` WHERE (entity_type = "artist" AND entity_id = ?) OR (author_id = ?) AND status = "published" ORDER BY published_at DESC LIMIT 10');
                    $stmt->execute([$entity['id'], $entity['id']]);
                    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    
                    // Normalize paths
                    foreach ($posts as &$p) {
                        if (!empty($p['featured_image_url']) && !str_starts_with($p['featured_image_url'], 'http') && !str_starts_with($p['featured_image_url'], '/')) {
                            $p['featured_image_url'] = "/uploads/{$p['featured_image_url']}";
                        }
                    }
                    $entity['posts'] = $posts;

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
                    $stmt = $pdo->prepare('SELECT id, slug, title, COALESCE(content, excerpt) as body, featured_image_url, published_at FROM `ngn_2025`.`posts` WHERE (entity_type = "label" AND entity_id = ?) OR (author_id = ?) OR (title LIKE :labelNamePattern OR content LIKE :labelNamePattern OR tags LIKE :labelNamePattern) ORDER BY published_at DESC LIMIT 20');
                    $stmt->execute([$entity['id'], $entity['id'], ':labelNamePattern' => '%' . $entity['name'] . '%']);
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
                    $stmt = $pdo->prepare('SELECT p.* FROM `ngn_2025`.`posts` p WHERE (p.entity_type = "venue" AND p.entity_id = ?) OR (p.author_id = ?) AND p.status = "published" ORDER BY p.published_at DESC LIMIT 10');
                    $stmt->execute([$entity['id'], $entity['id']]);
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
                    // Fetch recent spins from NGN 2.0 native spins table
                    try {
                        $stmt = $pdo->prepare('
                            SELECT artist_name AS Artist, song_title AS Song, played_at AS chart_date
                            FROM `ngn_2025`.`station_spins`
                            WHERE station_id = ?
                            ORDER BY played_at DESC LIMIT 20
                        ');
                        $stmt->execute([$entity['id']]);
                        $entity['smr_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $e) {
                        error_log("Error fetching station spins: " . $e->getMessage());
                    }

                    // Posts mentioning this station
                    $stmt = $pdo->prepare('SELECT id, slug, title, COALESCE(content, excerpt) as body, featured_image_url, published_at FROM `ngn_2025`.`posts` WHERE (entity_type = "station" AND entity_id = ?) OR (author_id = ?) OR (title LIKE :stationNamePattern OR content LIKE :stationNamePattern OR tags LIKE :stationNamePattern) ORDER BY published_at DESC LIMIT 10');
                    $stmt->execute([$entity['id'], $entity['id'], ':stationNamePattern' => '%' . $entity['name'] . '%']);
                    $entity['posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Unified Chart History Fetching
                if (empty($entity['chart_rankings']) && in_array($view, ['artist', 'label', 'venue', 'station'])) {
                    try {
                        $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                        $stmt = $rankingsPdo->prepare('
                            SELECT ri.rank AS RankNum, ri.score AS Score, rw.period_end AS PeriodEnd, rw.interval AS `Interval`
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
} // END if ($pdo)

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
    $seoImage = $data['post']['featured_image_url'] ?? $seoImage;
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
  <script src="https://cdn.tailwindcss.com?v=<?= time() ?>"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css?v=<?= time() ?>">
  <script src="/js/pwa-setup.js?v=<?= \NGN\Lib\Env::get('APP_VERSION') ?>" defer></script>
  <style>
    :root {
      --bg-base: #000000;
      --bg-surface: #121212;
      --bg-elevated: #181818;
      --bg-highlight: #282828;
      --text-main: #ffffff;
      --text-sub: #b3b3b3;
      --brand: #1DB954;
    }

    body {
      background-color: var(--bg-base);
      color: var(--text-main);
      font-family: 'Circular', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
    }

    /* Loading Skeleton Animations */
    @keyframes skeleton-shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .skeleton {
      background: linear-gradient(90deg, #181818 25%, #282828 50%, #181818 75%);
      background-size: 200% 100%;
      animation: skeleton-shimmer 1.5s ease-in-out infinite;
      border-radius: 0.5rem;
    }

    /* Spotify-style scrollbar */
    ::-webkit-scrollbar { width: 12px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #5a5a5a; border-radius: 6px; border: 3px solid var(--bg-base); }
    ::-webkit-scrollbar-thumb:hover { background: #b3b3b3; }

    /* Bottom Nav for Mobile */
    .mobile-bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 70px;
      background: rgba(0,0,0,0.8);
      backdrop-filter: blur(20px);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 100;
      padding-bottom: env(safe-area-inset-bottom);
      border-top: 1px solid rgba(255,255,255,0.05);
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      color: var(--text-sub);
      text-decoration: none;
      font-size: 10px;
      font-weight: 700;
      transition: color 0.2s;
    }

    .nav-item.active { color: var(--text-main); }
    .nav-item i { font-size: 24px; }

    /* Content Area Adjustment */
    .content-container {
      padding-bottom: 160px; /* Space for player bar (80px) + bottom nav (70px) + safety */
    }

    @media (min-width: 1024px) {
      .mobile-bottom-nav { display: none; }
      .content-container { padding-bottom: 90px; } /* Space for desktop player bar */
    }

    /* Spotify-style Card */
    .sp-card {
      background: var(--bg-surface);
      padding: 16px;
      border-radius: 8px;
      transition: background 0.3s;
      height: 100%;
    }
    .sp-card:hover {
      background: var(--bg-highlight);
    }

    /* Global Player Styles */
    .player-bar {
      position: fixed;
      bottom: 70px; /* Above mobile bottom nav */
      left: 0;
      right: 0;
      height: 80px;
      background: #000;
      border-top: 1px solid #282828;
      display: flex;
      align-items: center;
      padding: 0 16px;
      z-index: 90;
      transition: transform 0.3s ease;
    }
    
    @media (min-width: 1024px) {
      .player-bar { bottom: 0; padding: 0 32px; height: 90px; }
    }

    .player-btn {
      color: #b3b3b3;
      transition: all 0.2s;
    }
    .player-btn:hover { color: #fff; transform: scale(1.1); }
    .player-btn.play { color: #fff; background: #fff; color: #000; width: 32px; height: 32px; border-radius: 50%; display: flex; items-center: center; justify-content: center; }
    
    .progress-bar {
      height: 4px;
      background: #4d4d4d;
      border-radius: 2px;
      position: relative;
      cursor: pointer;
    }
    .progress-fill {
      height: 100%;
      background: var(--brand);
      border-radius: 2px;
      width: 0%;
    }
    .progress-bar:hover .progress-fill { background: #1fdf64; }

    /* Hide player when not active */
    .player-bar.hidden { transform: translateY(100%); }

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
<body class="h-full selection:bg-brand/30 dark">
  <div class="min-h-screen flex flex-col lg:flex-row">

    <!-- Desktop Sidebar (Modern Rail Style) -->
    <aside class="hidden lg:flex lg:flex-col w-[280px] bg-black p-6 gap-8 fixed inset-y-0 left-0 z-30">
      <div class="mb-2">
        <a href="/" class="block">
          <img src="/lib/images/site/web-light-1.png" alt="NGN" class="h-10">
        </a>
      </div>

      <nav class="space-y-4">
        <a href="/" class="flex items-center gap-4 text-sm font-bold <?= $view === 'home' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition-colors">
          <i class="bi-house-door-fill text-2xl"></i> Home
        </a>
        <a href="/charts" class="flex items-center gap-4 text-sm font-bold <?= $view === 'charts' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition-colors">
          <i class="bi-bar-chart-fill text-2xl"></i> NGN Charts
        </a>
        <a href="/smr-charts" class="flex items-center gap-4 text-sm font-bold <?= $view === 'smr-charts' ? 'text-white' : 'text-zinc-400 hover:text-white' ?> transition-colors">
          <i class="bi-graph-up text-2xl"></i> SMR Charts
        </a>
        <button data-play-track 
                data-track-url="https://ice1.somafm.com/groovesalad-256-mp3" 
                data-track-title="The Rage Online" 
                data-track-artist="Live Station" 
                data-track-art="/lib/images/site/radio-icon.jpg"
                class="flex items-center gap-4 text-sm font-bold text-brand hover:scale-105 transition-all text-left">
          <i class="bi-broadcast text-2xl"></i> Live Radio
        </button>
      </nav>

      <div class="flex-1 overflow-y-auto mt-4 bg-[#121212] rounded-xl p-4 border border-white/5">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-2 text-zinc-400 font-bold text-sm">
            <i class="bi-collection-play-fill text-xl"></i> Your Library
          </div>
          <button class="text-zinc-400 hover:text-white"><i class="bi-plus-lg"></i></button>
        </div>
        
        <div class="space-y-2">
          <a href="/artists" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'artists' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-music-note-beamed"></i> Artists
          </a>
          <a href="/labels" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'labels' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-building"></i> Labels
          </a>
          <a href="/stations" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'stations' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-broadcast"></i> Stations
          </a>
          <a href="/venues" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'venues' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-geo-alt"></i> Venues
          </a>
          <a href="/videos" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'videos' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-play-circle"></i> Videos
          </a>
          <a href="/releases" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'releases' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-vinyl"></i> Releases
          </a>
          <a href="/posts" class="flex items-center gap-3 p-2 rounded-md hover:bg-white/5 transition-colors text-sm font-bold <?= $view === 'posts' ? 'text-white' : 'text-zinc-400' ?>">
            <i class="bi-newspaper"></i> News
          </a>
        </div>
      </div>

      <div class="mt-auto space-y-4">
        <?php if ($isLoggedIn): ?>
          <?php
            $userRoleId = (int)($currentUser['role_id'] ?? $currentUser['RoleId'] ?? 0);
            $dashboardPath = match($userRoleId) {
              3 => '/dashboard/artist/',
              7 => '/dashboard/label/',
              4, 15 => '/dashboard/station/',
              5, 17 => '/dashboard/venue/',
              default => '/profile.php'
            };
          ?>
          <a href="<?= $dashboardPath ?>" class="flex items-center gap-3 p-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 transition-colors border border-white/5">
            <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? $currentUser['username'] ?? '', $currentUser['Image'] ?? $currentUser['avatar_url'] ?? null)) ?>" alt="" class="w-10 h-10 rounded-full object-cover">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold truncate"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['Title'] ?? 'User') ?></div>
              <div class="text-[10px] text-zinc-500 uppercase font-black">View Dashboard</div>
            </div>
          </a>
          <?php if ($isAdmin): ?>
            <a href="/admin/" class="flex items-center gap-3 px-3 py-2 text-sm font-bold text-zinc-400 hover:text-white"><i class="bi-gear-fill"></i> Admin Console</a>
          <?php endif; ?>
          <a href="/logout.php" class="flex items-center gap-3 px-3 py-2 text-sm font-bold text-red-500/80 hover:text-red-500"><i class="bi-box-arrow-right"></i> Log out</a>
        <?php else: ?>
          <a href="/login.php" class="w-full py-3 bg-white text-black font-black rounded-full hover:scale-105 transition-all text-center block">Log In</a>
          <a href="/register.php" class="w-full py-3 bg-transparent text-white font-black rounded-full hover:scale-105 transition-all text-center block border border-white/20">Sign Up</a>
        <?php endif; ?>
        
        <!-- PWA Install Button -->
        <button id="install-pwa" class="hidden w-full py-3 bg-brand/20 text-brand font-black rounded-full hover:bg-brand/30 transition-all text-center flex items-center justify-center gap-2">
            <i class="bi-download"></i> Install NGN App
        </button>
      </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav lg:hidden">
      <a href="/" class="nav-item <?= $view === 'home' ? 'active' : '' ?>">
        <i class="bi-house-door<?= $view === 'home' ? '-fill' : '' ?>"></i>
        <span>Home</span>
      </a>
      <a href="/charts" class="nav-item <?= $view === 'charts' ? 'active' : '' ?>">
        <i class="bi-bar-chart<?= $view === 'charts' ? '-fill' : '' ?>"></i>
        <span>Charts</span>
      </a>
      <a href="/stations" class="nav-item <?= $view === 'stations' ? 'active' : '' ?>">
        <i class="bi-broadcast"></i>
        <span>Radio</span>
      </a>
      <button id="mobile-menu-trigger" class="nav-item">
        <i class="bi-grid-3x3-gap"></i>
        <span>Library</span>
      </button>
      <a href="<?= $isLoggedIn ? '/dashboard/' : '/login.php' ?>" class="nav-item">
        <i class="bi-person-circle"></i>
        <span>Account</span>
      </a>
    </nav>

    <!-- Mobile Full Menu Overlay -->
    <div id="mobile-full-menu" class="fixed inset-0 z-[100] bg-black hidden animate-in fade-in duration-300 overflow-y-auto">
        <div class="p-8 h-full flex flex-col">
            <div class="flex items-center justify-between mb-12">
                <img src="/lib/images/site/web-light-1.png" alt="NGN" class="h-10">
                <button id="mobile-menu-close" class="text-white text-3xl"><i class="bi-x-lg"></i></button>
            </div>
            
            <nav class="grid grid-cols-2 gap-4 pb-12">
                <a href="/artists" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-music-note-beamed text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Artists</span>
                </a>
                <a href="/labels" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-building text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Labels</span>
                </a>
                <a href="/stations" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-broadcast text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Radio</span>
                </a>
                <a href="/venues" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-geo-alt text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Venues</span>
                </a>
                <a href="/videos" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-play-circle text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Videos</span>
                </a>
                <a href="/releases" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-vinyl text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Releases</span>
                </a>
                <a href="/posts" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-newspaper text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">News</span>
                </a>
                <a href="/charts" class="p-6 bg-zinc-900 rounded-2xl flex flex-col gap-3">
                    <i class="bi bi-bar-chart text-2xl text-brand"></i>
                    <span class="font-black text-sm uppercase tracking-widest">Charts</span>
                </a>
            </nav>

            <div class="mt-auto space-y-4 pt-8">
                <?php if ($isLoggedIn): ?>
                    <a href="/logout.php" class="block w-full py-4 text-center font-black text-red-500 uppercase tracking-widest border border-red-500/20 rounded-2xl">Log Out</a>
                <?php else: ?>
                    <a href="/login.php" class="block w-full py-4 bg-white text-black rounded-full text-center font-black uppercase tracking-widest">Log In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trigger = document.getElementById('mobile-menu-trigger');
            const close = document.getElementById('mobile-menu-close');
            const menu = document.getElementById('mobile-full-menu');

            if (trigger && menu) {
                trigger.onclick = (e) => {
                    e.preventDefault();
                    menu.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                };
                close.onclick = (e) => {
                    e.preventDefault();
                    menu.classList.add('hidden');
                    document.body.style.overflow = '';
                };
            }
        });
    </script>

    <!-- Main Content Area -->
    <main class="flex-1 lg:ml-[280px] content-container">
      <!-- Mobile Top Bar -->
      <header class="lg:hidden fixed top-0 left-0 right-0 z-[110] bg-black/95 backdrop-blur-xl px-4 py-3 flex items-center justify-between border-b border-white/5 h-[56px]">
        <img src="/lib/images/site/web-light-1.png" alt="NGN" class="h-8">
        <div class="flex items-center gap-4">
          <form method="get" action="/" class="relative">
            <input type="hidden" name="view" value="artists">
            <button type="submit" class="text-white text-xl"><i class="bi-search"></i></button>
          </form>
          <a href="/dashboard/" class="text-white text-2xl"><i class="bi-person-circle"></i></a>
        </div>
      </header>

      <div class="lg:hidden h-[56px]"></div> <!-- Spacer for fixed header -->

      <!-- Desktop Header -->
      <header class="hidden lg:flex items-center justify-between px-8 h-16 sticky top-0 z-20 bg-zinc-900/50 backdrop-blur-md">
        <div class="flex items-center gap-4">
          <div class="flex gap-2">
            <button onclick="history.back()" class="w-8 h-8 rounded-full bg-black/40 flex items-center justify-center text-white hover:bg-black/60"><i class="bi-chevron-left"></i></button>
            <button onclick="history.forward()" class="w-8 h-8 rounded-full bg-black/40 flex items-center justify-center text-white hover:bg-black/60"><i class="bi-chevron-right"></i></button>
          </div>
          <form method="get" action="/" class="relative group">
            <input type="hidden" name="view" value="<?= in_array($view, ['artists','labels','stations','venues']) ? htmlspecialchars($view) : 'artists' ?>">
            <i class="bi-search absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 group-focus-within:text-white transition-colors"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search artists, labels, or news..." class="w-80 h-10 pl-10 pr-4 rounded-full bg-zinc-800 border-none text-sm text-white focus:ring-2 focus:ring-white transition-all">
          </form>
        </div>
        
        <div class="flex items-center gap-6">
          <a href="/pricing" class="text-sm font-bold text-zinc-400 hover:text-white hover:scale-105 transition-all">Premium</a>
          <a href="/shop" class="text-sm font-bold text-zinc-400 hover:text-white hover:scale-105 transition-all">Merch</a>
          <div class="h-8 w-[1px] bg-white/10"></div>
          <?php if ($isLoggedIn): ?>
            <div class="flex items-center gap-3">
                <span class="text-sm font-bold text-white"><?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['Title'] ?? '') ?></span>
                <button class="w-10 h-10 rounded-full bg-black flex items-center justify-center border border-white/10 hover:scale-105 transition-all overflow-hidden">
                  <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? $currentUser['username'] ?? '', $currentUser['Image'] ?? $currentUser['avatar_url'] ?? null)) ?>" class="w-full h-full object-cover">
                </button>
            </div>
          <?php else: ?>
            <div class="flex items-center gap-8">
              <a href="/register.php" class="text-zinc-400 hover:text-white font-bold text-sm">Sign up</a>
              <a href="/login.php" class="px-8 py-3 bg-white text-black font-black rounded-full hover:scale-105 transition-all">Log in</a>
            </div>
          <?php endif; ?>
        </div>
      </header>

      <!-- View Wrapper -->
      <div class="px-4 lg:px-8 py-6">

      <?php if ($view === 'home'): ?>
        <?php
        $featuredPosts = get_ngn_posts($pdo, '', 1, 4);
        ?>
        <!-- HERO -->
        <div class="relative rounded-3xl overflow-hidden mb-12 group">
            <?php if (!empty($featuredPosts)): ?>
                <!-- Carousel Background -->
                <div class="absolute inset-0">
                    <?php foreach ($featuredPosts as $index => $post): ?>
                        <?php 
                            $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
                        ?>
                        <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>" data-carousel-item>
                            <img src="<?= htmlspecialchars($postImg) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-1000" alt="<?= htmlspecialchars($post['title'] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Overlay -->
            <div class="absolute inset-0 bg-black/60"></div>

            <!-- Content -->
            <div class="relative p-12 lg:p-24 text-center text-white min-h-[400px] flex items-center justify-center">
                <?php if (!empty($featuredPosts)): ?>
                    <div class="max-w-4xl mx-auto">
                        <?php foreach ($featuredPosts as $index => $post): ?>
                            <div class="transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'block' : 'hidden' ?>" data-carousel-content>
                                <h1 class="text-4xl lg:text-6xl font-black mb-6 tracking-tighter"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></h1>
                                <div class="flex justify-center gap-4">
                                    <a href="/post/<?= htmlspecialchars(($post['slug'] ?? $post['id']) ?? '') ?>" class="inline-block bg-white text-black font-black py-4 px-10 rounded-full hover:scale-105 transition-all uppercase tracking-widest text-sm">Read Story</a>
                                    <?php
                                    // If we have any tracks, let them listen
                                    $anyTrack = $data['songs'][0] ?? null;
                                    if ($anyTrack):
                                    ?>
                                    <button class="inline-block bg-brand text-black font-black py-4 px-10 rounded-full hover:scale-105 transition-all shadow-xl shadow-brand/20 uppercase tracking-widest text-sm"
                                            data-play-track
                                            data-track-url="<?= htmlspecialchars($anyTrack['mp3_url'] ?? '') ?>"
                                            data-track-title="<?= htmlspecialchars($anyTrack['title'] ?? 'Unknown Track') ?>"
                                            data-track-artist="<?= htmlspecialchars($anyTrack['artist_name'] ?? 'NGN Artist') ?>"
                                            data-track-art="<?= htmlspecialchars($anyTrack['cover_url'] ?? DEFAULT_AVATAR) ?>">
                                        <i class="bi-play-fill mr-2"></i> Listen Now
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <h1 class="text-4xl lg:text-6xl font-black mb-4">Welcome to NGN 2.0</h1>
                        <p class="text-lg lg:text-xl mb-8 font-bold text-zinc-400 uppercase tracking-widest">The command center for indie rock & metal</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const carouselItems = document.querySelectorAll('[data-carousel-item]');
                const carouselContents = document.querySelectorAll('[data-carousel-content]');
                let currentIndex = 0;

                function showItem(index) {
                    carouselItems.forEach((item, i) => {
                        item.classList.toggle('opacity-100', i === index);
                        item.classList.toggle('opacity-0', i !== index);
                    });
                    carouselContents.forEach((content, i) => {
                        content.classList.toggle('block', i === index);
                        content.classList.toggle('hidden', i !== index);
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
        <!-- Stats Grid (Spotify-style cards) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
          <div class="sp-card border border-white/5">
            <div class="text-sm font-black text-zinc-500 uppercase tracking-widest mb-2">Artists</div>
            <div class="text-4xl font-black text-white"><?= number_format($counts['artists']) ?></div>
          </div>
          <div class="sp-card border border-white/5">
            <div class="text-sm font-black text-zinc-500 uppercase tracking-widest mb-2">Labels</div>
            <div class="text-4xl font-black text-white"><?= number_format($counts['labels']) ?></div>
          </div>
          <div class="sp-card border border-white/5">
            <div class="text-sm font-black text-zinc-500 uppercase tracking-widest mb-2">Stations</div>
            <div class="text-4xl font-black text-white"><?= number_format($counts['stations']) ?></div>
          </div>
          <div class="sp-card border border-white/5">
            <div class="text-sm font-black text-zinc-500 uppercase tracking-widest mb-2">Venues</div>
            <div class="text-4xl font-black text-white"><?= number_format($counts['venues']) ?></div>
          </div>
        </div>

        <!-- Stream Now Section -->
        <?php if (!empty($data['songs'])): ?>
        <section class="mb-12">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black tracking-tight text-white">Stream Now</h2>
            <a href="/songs" class="text-sm font-black text-zinc-500 hover:text-white uppercase tracking-widest">Show All</a>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Featured Station -->
            <div class="sp-card border border-brand/30 bg-brand/5 flex flex-col justify-center p-8 relative overflow-hidden group cursor-pointer"
                 data-play-track
                 data-track-url="https://ice1.somafm.com/groovesalad-256-mp3"
                 data-track-title="The Rage Online"
                 data-track-artist="Live Radio"
                 data-track-art="/lib/images/site/radio-icon.jpg">
                <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-1000">
                    <i class="bi-broadcast text-9xl text-brand"></i>
                </div>
                <div class="text-[10px] font-black text-brand uppercase tracking-[0.2em] mb-2">Currently On Air</div>
                <h3 class="text-2xl font-black text-white mb-4">The Rage Online</h3>
                <button class="bg-brand text-black w-12 h-12 rounded-full flex items-center justify-center shadow-xl">
                    <i class="bi-play-fill text-2xl"></i>
                </button>
            </div>

            <?php foreach (array_slice($data['songs'], 0, 2) as $song): ?>
            <div class="group sp-card border border-white/5 flex items-center gap-6 cursor-pointer"
                 data-play-track
                 data-track-url="<?= htmlspecialchars($song['mp3_url'] ?? '') ?>"
                 data-track-title="<?= htmlspecialchars($song['title'] ?? 'Unknown Track') ?>"
                 data-track-artist="<?= htmlspecialchars($song['artist_name'] ?? 'NGN Artist') ?>"
                 data-track-art="<?= htmlspecialchars($song['cover_url'] ?? DEFAULT_AVATAR) ?>">
                <div class="relative w-20 h-20 flex-shrink-0 shadow-2xl">
                    <img src="<?= htmlspecialchars(($song['cover_url'] ?? null) ?: DEFAULT_AVATAR) ?>" class="w-full h-full object-cover rounded-lg">
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                        <i class="bi-play-fill text-3xl text-white"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-black text-white text-lg truncate"><?= htmlspecialchars($song['title'] ?? 'Untitled') ?></div>
                    <div class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1"><?= htmlspecialchars($song['artist_name'] ?? 'Unknown Artist') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Trending Artists -->
        <?php if (!empty($data['trending_artists'])): ?>
        <section class="mb-12">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black tracking-tight text-white">Trending Artists</h2>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            <?php foreach ($data['trending_artists'] as $artist): ?>
            <a href="/artist/<?= htmlspecialchars($artist['slug'] ?? $artist['id']) ?>" class="group sp-card border border-white/5 flex flex-col">
              <div class="relative aspect-square mb-4 shadow-2xl">
                <img src="<?= htmlspecialchars(($artist['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" alt="" class="w-full h-full object-cover rounded-xl bg-zinc-800 shadow-xl group-hover:scale-[1.02] transition-transform duration-500" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                <button class="absolute bottom-3 right-3 w-12 h-12 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-xl shadow-black/40">
                    <i class="bi-play-fill text-2xl"></i>
                </button>
              </div>
              <div class="font-black truncate text-white"><?= htmlspecialchars($artist['name'] ?? 'Unknown Artist') ?></div>
              <div class="text-xs font-bold text-zinc-500 uppercase tracking-tighter mt-1"><?= htmlspecialchars($artist['engagement_count'] ?? '0') ?> signals</div>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Top Labels -->
        <?php if (!empty($data['labels'])): ?>
        <section class="mb-12">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black tracking-tight text-white">Top Labels</h2>
            <a href="/labels" class="text-sm font-black text-zinc-500 hover:text-white uppercase tracking-widest">Show All</a>
          </div>
          <div class="grid grid-cols-3 md:grid-cols-6 gap-6">
            <?php foreach ($data['labels'] as $label): ?>
            <a href="/label/<?= htmlspecialchars($label['slug'] ?? $label['id']) ?>" class="group text-center">
              <div class="relative w-full aspect-square mb-3">
                <img src="<?= htmlspecialchars(($label['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" alt="" class="w-full h-full object-cover rounded-full bg-zinc-800 shadow-xl group-hover:scale-105 transition-all duration-500 border-4 border-transparent group-hover:border-brand/20" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              </div>
              <div class="text-sm font-black truncate text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($label['name'] ?? 'Unknown Label') ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Latest News -->
        <?php if (!empty($data['posts'])): ?>
        <section class="mb-12">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-black tracking-tight text-white">Latest News</h2>
            <a href="/posts" class="text-sm font-black text-zinc-500 hover:text-white uppercase tracking-widest">Show All</a>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($data['posts'] as $post): ?>
            <?php 
                $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
            ?>
            <a href="/post/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="group flex flex-col">
              <div class="aspect-video rounded-xl overflow-hidden mb-4 border border-white/5">
                <img src="<?= htmlspecialchars($postImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
              </div>
              <div class="font-black text-sm text-white line-clamp-2 leading-tight group-hover:text-brand transition-colors"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></div>
              <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-2"><?= ($post['published_at'] ?? null) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
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
                        <div class="font-black text-lg"><?= number_format($item['TWS'] ?? 0) ?></div>
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
        
              <?php elseif (in_array($view, ['artists', 'labels', 'stations', 'venues'])): ?>
        <!-- ENTITY LIST VIEW -->
        <div class="flex items-center justify-between mb-8">
          <div>
            <h1 class="text-4xl font-black capitalize tracking-tighter text-white"><?= $view ?></h1>
            <p class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1">Discover the NGN network</p>
          </div>
          <div class="text-right">
            <div class="text-3xl font-black text-brand"><?= number_format($total) ?></div>
            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest">Active</div>
          </div>
        </div>

        <?php $items = $data[$view] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
          <?php foreach ($items as $item): ?>
          <?php
            $imgUrl = user_image($item['slug'] ?? '', $item['image_url'] ?? null);
          ?>
          <a href="/<?= rtrim($view, 's') ?>/<?= htmlspecialchars($item['slug'] ?? $item['id']) ?>" class="group sp-card border border-white/5">
            <div class="aspect-square mb-4 shadow-2xl relative">
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="w-full h-full object-cover <?= $view === 'labels' ? 'rounded-full' : 'rounded-xl' ?> bg-zinc-800 shadow-xl group-hover:scale-[1.02] transition-transform duration-500" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
                <button class="absolute bottom-2 right-2 w-10 h-10 bg-brand text-black rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all shadow-lg">
                    <i class="bi-play-fill text-xl"></i>
                </button>
            </div>
            <div class="font-black text-sm truncate text-white"><?= htmlspecialchars($item['name'] ?? $item['title'] ?? 'Unknown') ?></div>
            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($item['city'] ?? 'Active') ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 sp-card border border-dashed border-white/10">
            <i class="bi-search text-4xl text-zinc-700 mb-4 block"></i>
            <h2 class="text-xl font-black">No results found</h2>
            <p class="text-zinc-500">Try adjusting your search filters.</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-4 mt-16">
          <?php if ($page > 1): ?>
          <a href="/<?= $view ?>?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
          <a href="/<?= $view ?>?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'posts'): ?>
        <!-- POSTS LIST VIEW -->
        <div class="mb-12">
            <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">NGN Newswire</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">Industry reports & daily music intelligence</p>
        </div>

        <?php $items = $data['posts'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <?php foreach ($items as $post): ?>
          <?php 
              $postImg = $post['featured_image_url'] ?? DEFAULT_AVATAR;
          ?>
          <a href="/post/<?= htmlspecialchars(($post['slug'] ?? $post['id']) ?? '') ?>" class="group flex flex-col sp-card border border-white/5">
            <div class="aspect-video rounded-xl overflow-hidden mb-6 shadow-2xl">
              <img src="<?= htmlspecialchars($postImg ?? DEFAULT_AVATAR) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.onerror=null;this.src='<?= DEFAULT_AVATAR ?>'">
            </div>
            <div class="flex-1">
                <div class="text-[10px] font-black text-brand uppercase tracking-[0.2em] mb-3">Feature Article</div>
                <h3 class="text-xl font-black text-white line-clamp-2 leading-tight group-hover:text-brand transition-colors mb-4"><?= htmlspecialchars($post['title'] ?? 'Untitled Story') ?></h3>
                <?php if (!empty($post['excerpt'])): ?>
                <p class="text-zinc-400 text-sm line-clamp-3 leading-relaxed mb-6 font-medium"><?= htmlspecialchars($post['excerpt'] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between pt-6 border-t border-white/5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-zinc-800 flex items-center justify-center text-[10px] font-black text-brand">NGN</div>
                <div class="text-[10px] font-black text-zinc-500 uppercase"><?= htmlspecialchars($post['author_name'] ?? 'Staff') ?></div>
              </div>
              <div class="text-[10px] font-black text-zinc-600 uppercase tracking-widest"><?= ($post['published_at'] ?? null) ? date('M j, Y', strtotime($post['published_at'])) : '' ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 sp-card border border-dashed border-white/10">
            <i class="bi-newspaper text-4xl text-zinc-700 mb-4 block"></i>
            <h2 class="text-xl font-black">No posts available</h2>
            <p class="text-zinc-500">Check back later for fresh updates.</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-4 mt-16">
          <?php if ($page > 1): ?>
          <a href="/posts?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
          <?php endif; ?>
          <a href="/posts?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'releases'): ?>
        <!-- RELEASES LIST VIEW -->
        <div class="mb-12">
            <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">Latest Releases</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">New albums, EPs, and singles from the NGN network</p>
        </div>

        <?php $items = $data['releases'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
          <?php foreach ($items as $release): ?>
          <?php 
              $releaseImg = ($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR; 
              if ($releaseImg && !str_starts_with($releaseImg, 'http') && !str_starts_with($releaseImg, '/')) {
                  $releaseImg = "/uploads/releases/{$releaseImg}";
              }
          ?>
          <a href="/release/<?= htmlspecialchars($release['slug'] ?? $release['id']) ?>" class="group flex flex-col sp-card border border-white/5">
            <div class="aspect-square rounded-xl overflow-hidden mb-4 relative shadow-2xl">
              <img src="<?= htmlspecialchars($releaseImg) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 bg-zinc-800" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
              <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="bi-play-fill text-5xl text-white"></i>
              </div>
            </div>
            <div class="font-black text-white truncate"><?= htmlspecialchars($release['title']) ?></div>
            <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['artist_name'] ?? 'Unknown Artist') ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 sp-card border border-dashed border-white/10">
            <i class="bi-vinyl text-4xl text-zinc-700 mb-4 block"></i>
            <h2 class="text-xl font-black">No releases found</h2>
            <p class="text-zinc-500">New music is being cataloged. Check back soon.</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-4 mt-16">
          <?php if ($page > 1): ?>
          <a href="/releases?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
          <?php endif; ?>
          <a href="/releases?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'songs'): ?>
        <!-- SONGS LIST VIEW -->
        <div class="mb-12">
            <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">All Tracks</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">Individual tracks and singles from across the platform</p>
        </div>

        <?php $items = $data['songs'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($items as $song): ?>
          <div class="group sp-card border border-white/5 flex items-center gap-6 p-4 hover:bg-white/5 transition-all cursor-pointer"
               data-play-track
               data-track-url="<?= htmlspecialchars($song['mp3_url'] ?? '') ?>"
               data-track-title="<?= htmlspecialchars($song['title']) ?>"
               data-track-artist="<?= htmlspecialchars($song['artist_name'] ?? 'Unknown Artist') ?>"
               data-track-art="<?= htmlspecialchars(($song['cover_url'] ?? '') ?: DEFAULT_AVATAR) ?>">
            <div class="relative w-16 h-16 flex-shrink-0 shadow-xl">
                <img src="<?= htmlspecialchars(($song['cover_url'] ?? '') ?: DEFAULT_AVATAR) ?>" class="w-full h-full object-cover rounded-lg" onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                    <i class="bi-play-fill text-2xl text-white"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-black text-white truncate"><?= htmlspecialchars($song['title']) ?></div>
                <div class="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-1"><?= htmlspecialchars($song['artist_name'] ?? 'Unknown Artist') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 sp-card border border-dashed border-white/10">
            <i class="bi-music-note-beamed text-4xl text-zinc-700 mb-4 block"></i>
            <h2 class="text-xl font-black">No songs available</h2>
            <p class="text-zinc-500">The library is currently being synchronized.</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-4 mt-16">
          <?php if ($page > 1): ?>
          <a href="/songs?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
          <?php endif; ?>
          <a href="/songs?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'videos'): ?>
        <!-- VIDEOS LIST VIEW -->
        <div class="mb-12">
            <h1 class="text-4xl font-black tracking-tighter mb-2 text-white">Video Vault</h1>
            <p class="text-zinc-500 font-bold uppercase tracking-[0.2em] text-[10px]">Exclusive premieres, interviews & live performances</p>
        </div>

        <?php $items = $data['videos'] ?? []; ?>
        <?php if (!empty($items)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <?php foreach ($items as $video): ?>
          <a href="/video/<?= htmlspecialchars($video['slug']) ?>" class="group sp-card border border-white/5 flex flex-col">
            <div class="aspect-video bg-zinc-900 relative overflow-hidden rounded-xl mb-6 shadow-2xl">
              <?php if ($video['platform'] === 'youtube' && !empty($video['external_id'])): ?>
              <img src="https://img.youtube.com/vi/<?= htmlspecialchars($video['external_id']) ?>/maxresdefault.jpg" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($video['external_id']) ?>/hqdefault.jpg'">
              <div class="absolute inset-0 flex items-center justify-center group-hover:bg-black/20 transition-all">
                <div class="bg-brand text-black w-16 h-16 rounded-full flex items-center justify-center scale-90 group-hover:scale-100 transition-all shadow-2xl shadow-brand/40">
                    <i class="bi-play-fill text-4xl ml-1"></i>
                </div>
              </div>
              <?php else: ?>
              <div class="w-full h-full flex items-center justify-center">
                <i class="bi-play-circle text-zinc-700 text-6xl"></i>
              </div>
              <?php endif; ?>
              
              <div class="absolute bottom-3 right-3 px-2 py-1 bg-black/80 rounded font-black text-[10px] text-white uppercase tracking-widest border border-white/10">
                <?= htmlspecialchars($video['platform']) ?>
              </div>
            </div>
            
            <div class="flex-1">
                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2"><?= $video['published_at'] ? date('F j, Y', strtotime($video['published_at'])) : '' ?></div>
                <h3 class="text-xl font-black text-white leading-tight group-hover:text-brand transition-colors"><?= htmlspecialchars($video['title']) ?></h3>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 sp-card border border-dashed border-white/10">
            <i class="bi-play-circle text-4xl text-zinc-700 mb-4 block"></i>
            <h2 class="text-xl font-black">The vault is currently locked</h2>
            <p class="text-zinc-500">New video content is being processed. Check back soon.</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-4 mt-16">
          <?php if ($page > 1): ?>
          <a href="/videos?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-zinc-800 text-white font-black hover:bg-zinc-700 transition-all">Previous</a>
          <?php endif; ?>
          <a href="/videos?page=<?= $page + 1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-8 py-3 rounded-full bg-white text-black font-black hover:scale-105 transition-all">Next</a>
        </div>
        <?php endif; ?>

      <?php elseif ($view === 'video' && !empty($data['video'])): ?>
        <!-- SINGLE VIDEO VIEW (Spotify Immersion) -->
        <?php $video = $data['video']; ?>
        <a href="/videos" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white mb-8 font-black uppercase tracking-widest text-xs transition-colors">
          <i class="bi-arrow-left"></i> Back to Vault
        </a>

        <article class="max-w-6xl mx-auto">
            <div class="bg-zinc-900/50 rounded-3xl overflow-hidden border border-white/5 shadow-2xl">
                <div class="aspect-video bg-black relative">
                    <?php if ($video['is_locked']): ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-12 bg-gradient-to-br from-zinc-900 to-black">
                            <i class="bi-lock-fill text-6xl text-brand mb-6 opacity-50"></i>
                            <h3 class="text-3xl font-black mb-4">Subscribers Only</h3>
                            <p class="text-zinc-400 mb-8 max-w-md mx-auto">This video is exclusive to NGN Premium members. Support the artists and unlock full access.</p>
                            <a href="/pricing" class="px-10 py-4 bg-white text-black font-black rounded-full hover:scale-105 transition-all uppercase tracking-widest text-sm">Get Premium Access</a>
                        </div>
                    <?php else: ?>
                        <iframe
                          src="https://www.youtube.com/embed/<?= htmlspecialchars($video['external_id']) ?>?rel=0&modestbranding=1&autoplay=1"
                          class="w-full h-full"
                          frameborder="0"
                          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                          allowfullscreen
                        ></iframe>
                    <?php endif; ?>
                </div>
                
                <div class="p-8 lg:p-12">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
                        <div>
                            <h1 class="text-3xl lg:text-5xl font-black tracking-tighter mb-4 leading-tight"><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></h1>
                            <div class="flex items-center gap-6 text-sm font-black uppercase tracking-widest text-zinc-500">
                                <?php if (!empty($video['author_entity'])): ?>
                                    <a href="/artist/<?= htmlspecialchars($video['author_entity']['slug'] ?? '') ?>" class="flex items-center gap-3 text-brand hover:text-white transition-colors">
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-zinc-800">
                                            <img src="<?= htmlspecialchars(($video['author_entity']['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" class="w-full h-full object-cover">
                                        </div>
                                        <span><?= htmlspecialchars($video['author_entity']['name'] ?? 'Unknown') ?></span>
                                    </a>
                                <?php endif; ?>
                                <span class="hidden md:inline">•</span>
                                <span><i class="bi-calendar3 mr-2"></i> <?= date('F j, Y', strtotime($video['published_at'] ?? $video['created_at'] ?? 'now')) ?></span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button class="w-12 h-12 rounded-full bg-zinc-800 text-white flex items-center justify-center hover:bg-zinc-700 transition-all"><i class="bi-share-fill"></i></button>
                            <button class="w-12 h-12 rounded-full bg-zinc-800 text-white flex items-center justify-center hover:bg-zinc-700 transition-all"><i class="bi-heart-fill text-brand"></i></button>
                        </div>
                    </div>
                    
                    <?php if (!empty($video['description'])): ?>
                    <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-relaxed border-t border-white/5 pt-8">
                        <?= nl2br($video['description']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>

      <?php elseif ($view === 'agreement' && !empty($data['agreement_template'])): ?>
        <!-- AGREEMENT VIEW -->
        <div class="max-w-4xl mx-auto py-12 px-6">
            <div class="text-center mb-12">
                <h1 class="text-4xl font-black text-white mb-4">Legal Verification</h1>
                <p class="text-zinc-500 max-w-2xl mx-auto">Please review and sign the agreement below to continue.</p>
            </div>

            <?php 
                $template = $data['agreement_template'];
                $isSigned = $data['agreement_signed'] ?? false;
                include __DIR__ . '/../lib/partials/legal/agreement-viewer.php';
            ?>
        </div>

      <?php elseif ($view === 'post' && !empty($data['post'])): ?>
        <!-- SINGLE POST VIEW (Modern Editorial) -->
        <?php $post = $data['post']; ?>
        <div class="max-w-4xl mx-auto">
            <a href="/posts" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white mb-8 font-black uppercase tracking-widest text-xs transition-colors">
              <i class="bi-arrow-left"></i> Back to News
            </a>

            <header class="mb-12">
                <div class="text-xs font-black text-brand uppercase tracking-[0.3em] mb-4">NGN Intelligence Report</div>
                <h1 class="text-4xl lg:text-7xl font-black tracking-tighter mb-8 leading-[0.9]"><?= htmlspecialchars($post['title'] ?? '') ?></h1>
                
                <div class="flex flex-wrap items-center gap-6 text-sm font-black uppercase tracking-widest text-zinc-500 mb-12 border-y border-white/5 py-6">
                    <?php if (!empty($post['author_entity'])): ?>
                        <a href="/artist/<?= htmlspecialchars($post['author_entity']['slug'] ?? '') ?>" class="flex items-center gap-3 text-white hover:text-brand transition-colors">
                            <img src="<?= htmlspecialchars(($post['author_entity']['image_url'] ?? null) ?: DEFAULT_AVATAR) ?>" class="w-10 h-10 rounded-full object-cover">
                            <span><?= htmlspecialchars($post['author_entity']['name'] ?? 'Staff') ?></span>
                        </a>
                    <?php endif; ?>
                    <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                    <span><?= date('F j, Y', strtotime($post['published_at'] ?? 'now')) ?></span>
                    <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                    <span class="text-zinc-600">5 min read</span>
                </div>

                <?php 
                    $postImg = $post['featured_image_url'] ?? '';
                ?>
                <?php if (!empty($postImg)): ?>
                <div class="rounded-3xl overflow-hidden shadow-2xl border border-white/5 aspect-[21/9]">
                    <img src="<?= htmlspecialchars($postImg) ?>" class="w-full h-full object-cover" alt="">
                </div>
                <?php endif; ?>
            </header>

            <article class="prose prose-invert prose-lg max-w-none">
                <?php if ($post['is_locked']): ?>
                    <div class="bg-zinc-900/80 backdrop-blur-xl border border-brand/20 p-12 rounded-3xl text-center">
                        <i class="bi-shield-lock-fill text-6xl text-brand mb-6 block"></i>
                        <h2 class="text-3xl font-black text-white mb-4">Premium Intelligence</h2>
                        <p class="text-zinc-400 mb-8 max-w-md mx-auto">This report is restricted to NGN Pro members. Gain the competitive edge with full platform access.</p>
                        <a href="/pricing" class="inline-block bg-brand text-black font-black py-4 px-10 rounded-full hover:scale-105 transition-all shadow-xl shadow-brand/20 uppercase tracking-widest text-xs">Upgrade to Pro</a>
                    </div>
                <?php else: ?>
                    <div class="text-zinc-300 font-medium leading-[1.8]">
                        <?= $post['body'] ?? 'No content available.' ?>
                    </div>
                <?php endif; ?>
            </article>
            
            <!-- Engagement Controls -->
            <div class="mt-16 p-8 bg-zinc-900/50 rounded-3xl border border-white/5 flex flex-wrap items-center justify-between gap-6">
                <div class="flex gap-4">
                    <button class="flex items-center gap-2 px-6 py-3 bg-zinc-800 hover:bg-zinc-700 rounded-full font-black text-xs uppercase tracking-widest transition-all"><i class="bi-heart"></i> Like</button>
                    <button class="flex items-center gap-2 px-6 py-3 bg-zinc-800 hover:bg-zinc-700 rounded-full font-black text-xs uppercase tracking-widest transition-all"><i class="bi-chat-dots"></i> Comment</button>
                </div>
                <button class="px-6 py-3 text-zinc-500 hover:text-white font-black text-xs uppercase tracking-widest transition-all"><i class="bi-flag"></i> Report</button>
            </div>
        </div>

      <?php elseif ($view === 'release' && !empty($data['release'])): ?>
        <!-- SINGLE RELEASE VIEW (Premium Immersion) -->
        <?php $release = $data['release']; ?>
        <div class="max-w-6xl mx-auto">
            <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white mb-12 font-black uppercase tracking-widest text-xs transition-colors">
                <i class="bi-arrow-left"></i> Back to Artist
            </a>

            <div class="flex flex-col md:flex-row gap-12 mb-16 items-end">
                <div class="w-full md:w-80 flex-shrink-0 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                    <img src="<?= htmlspecialchars(($release['cover_url'] ?? $release['cover_image_url'] ?? '') ?: DEFAULT_AVATAR) ?>" 
                         class="w-full aspect-square object-cover rounded-2xl border border-white/5 group-hover:scale-105 transition-transform duration-1000" alt=""
                         onerror="this.src='<?= DEFAULT_AVATAR ?>'">
                </div>
                <div class="flex-1">
                    <span class="text-xs font-black uppercase tracking-[0.3em] text-brand mb-4 block"><?= ucfirst($release['type'] ?? 'Album') ?></span>
                    <h1 class="text-5xl lg:text-8xl font-black mb-6 tracking-tighter leading-none"><?= htmlspecialchars($release['title'] ?? 'Untitled Release') ?></h1>
                    <div class="flex items-center gap-4 text-sm font-black text-zinc-400">
                        <a href="/artist/<?= htmlspecialchars($release['artist']['slug'] ?? '') ?>" class="text-white hover:text-brand transition-colors"><?= htmlspecialchars($release['artist']['name'] ?? 'Unknown Artist') ?></a>
                        <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                        <span><?= !empty($release['release_date']) ? date('Y', strtotime($release['release_date'])) : 'N/A' ?></span>
                        <span class="w-1 h-1 bg-zinc-800 rounded-full"></span>
                        <span><?= count($release['tracks'] ?? []) ?> Tracks</span>
                    </div>
                </div>
            </div>

            <!-- Tracklist (Immersion Grid) -->
            <div class="bg-zinc-900/30 rounded-3xl border border-white/5 overflow-hidden mb-16">
                <div class="p-8 border-b border-white/5 flex items-center justify-between bg-white/5">
                    <h2 class="text-2xl font-black tracking-tight">Tracklist</h2>
                    <button class="bg-brand text-black w-14 h-14 rounded-full flex items-center justify-center hover:scale-105 transition-all shadow-2xl shadow-brand/20">
                        <i class="bi-play-fill text-3xl ml-1"></i>
                    </button>
                </div>
                <div class="divide-y divide-white/5">
                    <?php if (!empty($release['tracks'])): ?>
                        <?php foreach ($release['tracks'] as $i => $track): ?>
                        <div class="flex items-center gap-6 p-6 hover:bg-white/5 transition-all group">
                            <span class="w-8 text-center text-zinc-600 font-black group-hover:text-white"><?= $i + 1 ?></span>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-lg truncate text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($track['title']) ?></div>
                                <div class="text-[10px] font-black text-zinc-500 uppercase tracking-widest mt-1"><?= htmlspecialchars($release['artist']['name'] ?? '') ?></div>
                            </div>
                            <div class="text-xs font-mono text-zinc-500 font-bold"><?= ($track['duration_seconds'] ?? 0) ? gmdate('i:s', $track['duration_seconds']) : '--:--' ?></div>
                            <div class="flex gap-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button class="text-zinc-500 hover:text-white"><i class="bi-plus-circle text-xl"></i></button>
                                <button class="text-zinc-500 hover:text-white"><i class="bi-three-dots text-xl"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-20 text-center text-zinc-600 font-black uppercase tracking-widest">No Tracks Indexed</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($release['description'])): ?>
            <div class="sp-card border border-white/5 p-12 mb-16">
                <h3 class="text-sm font-black uppercase tracking-[0.3em] text-zinc-500 mb-6">About this release</h3>
                <div class="prose prose-invert max-w-none text-zinc-400 font-medium leading-relaxed">
                    <?= nl2br($release['description']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

      <?php elseif ($view === 'song' && !empty($data['track'])): ?>
        <!-- SINGLE TRACK VIEW (Immersion) -->
        <?php $track = $data['track']; ?>
        <div class="max-w-4xl mx-auto py-24 text-center">
            <div class="mb-12 relative inline-block">
                <div class="w-64 h-64 mx-auto rounded-3xl bg-zinc-800 flex items-center justify-center shadow-[0_30px_60px_rgba(0,0,0,0.6)] border border-white/5">
                    <i class="bi-music-note-beamed text-zinc-700 text-8xl"></i>
                </div>
                <button class="absolute -bottom-6 -right-6 w-20 h-20 bg-brand text-black rounded-full flex items-center justify-center shadow-2xl hover:scale-110 transition-all">
                    <i class="bi-play-fill text-5xl ml-1"></i>
                </button>
            </div>
            
            <h1 class="text-5xl lg:text-7xl font-black mb-4 tracking-tighter leading-none"><?= htmlspecialchars($track['title'] ?? 'Untitled Song') ?></h1>
            <p class="text-zinc-500 mb-12 font-black uppercase tracking-[0.3em] text-xs"><?= htmlspecialchars($track['artist_name'] ?? 'High Fidelity Stream') ?></p>
            
            <div class="flex flex-wrap justify-center gap-6">
                <button class="px-12 py-4 bg-brand text-black font-black rounded-full hover:scale-105 transition-all shadow-2xl shadow-brand/20 uppercase tracking-widest text-sm">
                    Listen Now
                </button>
                <button class="px-12 py-4 bg-white/5 text-white font-black rounded-full hover:bg-white/10 transition-all border border-white/10 uppercase tracking-widest text-sm">
                    Add to Collection
                </button>
            </div>
        </div>

      <?php elseif (in_array($view, ['artist', 'label', 'station', 'venue']) && $entity): ?>
        <!-- SINGLE ENTITY VIEW -->
        <?php include __DIR__ . "/../lib/partials/profiles/{$view}.php"; ?>

      <?php elseif ($view === '404'): ?>
        <!-- 404 PAGE (Immersive) -->
        <?php http_response_code(404); ?>
        <div class="text-center py-32 sp-card border border-white/5 max-w-2xl mx-auto mt-12">
          <div class="text-9xl mb-8 animate-bounce">🎸</div>
          <h1 class="text-5xl font-black tracking-tighter mb-4">Void Reached.</h1>
          <p class="text-zinc-500 font-bold uppercase tracking-widest text-sm mb-12">The content you seek has returned to the underground.</p>
          <a href="/" class="inline-block bg-white text-black font-black py-4 px-10 rounded-full hover:scale-105 transition-all uppercase tracking-widest text-sm">Return Home</a>
        </div>
      <?php endif; ?>
      </div> <!-- End px-4 lg:px-8 py-6 -->
    </main>
  </div>

  <!-- GLOBAL MUSIC PLAYER -->
  <div id="global-player" class="player-bar hidden">
    <div class="flex items-center w-full max-w-[100vw] gap-4 lg:gap-8">
        
        <!-- Track Info -->
        <div class="flex items-center gap-4 w-[30%] min-w-0">
            <div class="w-14 h-14 rounded bg-zinc-800 flex-shrink-0 overflow-hidden shadow-lg">
                <img id="player-art" src="<?= DEFAULT_AVATAR ?>" class="w-full h-full object-cover">
            </div>
            <div class="min-w-0">
                <div id="player-title" class="text-sm font-black text-white truncate">Select a Track</div>
                <div id="player-artist" class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest truncate">NextGenNoise</div>
            </div>
            <button class="text-zinc-500 hover:text-brand transition-colors ml-2"><i class="bi-heart"></i></button>
        </div>

        <!-- Controls & Progress -->
        <div class="flex-1 flex flex-col items-center max-w-[40%]">
            <div class="flex items-center gap-6 mb-2">
                <button class="player-btn text-xl"><i class="bi-shuffle"></i></button>
                <button class="player-btn text-2xl"><i class="bi-skip-start-fill"></i></button>
                <button id="player-play-toggle" class="player-btn play shadow-xl"><i class="bi-play-fill text-2xl"></i></button>
                <button class="player-btn text-2xl"><i class="bi-skip-end-fill"></i></button>
                <button class="player-btn text-xl"><i class="bi-repeat"></i></button>
            </div>
            <div class="w-full flex items-center gap-3">
                <span id="player-time-cur" class="text-[10px] font-mono text-zinc-500 w-10 text-right">0:00</span>
                <div class="progress-bar flex-1" id="player-progress-container">
                    <div id="player-progress" class="progress-fill"></div>
                </div>
                <span id="player-time-total" class="text-[10px] font-mono text-zinc-500 w-10">0:00</span>
            </div>
        </div>

        <!-- Volume / Extra -->
        <div class="hidden md:flex items-center justify-end gap-4 w-[30%]">
            <button class="player-btn"><i class="bi-mic-fill"></i></button>
            <button class="player-btn"><i class="bi-list-ul"></i></button>
            <div class="flex items-center gap-2 w-32">
                <i class="bi-volume-up text-zinc-500"></i>
                <div class="progress-bar flex-1 h-1">
                    <div class="progress-fill w-3/4"></div>
                </div>
            </div>
            <button class="player-btn"><i class="bi-fullscreen"></i></button>
        </div>
    </div>
  </div>

  <audio id="audio-engine" class="hidden"></audio>

  <!-- Global Loader -->
  <div class="loading-overlay" id="global-loader">
    <div class="loading-spinner"></div>
  </div>

  <script>
    const audio = document.getElementById('audio-engine');
    const player = document.getElementById('global-player');
    const playBtn = document.getElementById('player-play-toggle');
    const playerTitle = document.getElementById('player-title');
    const playerArtist = document.getElementById('player-artist');
    const playerArt = document.getElementById('player-art');
    const progressBar = document.getElementById('player-progress');
    const timeCur = document.getElementById('player-time-cur');
    const timeTotal = document.getElementById('player-time-total');

    function formatTime(secs) {
        if (!secs || isNaN(secs)) return "0:00";
        const m = Math.floor(secs / 60);
        const s = Math.floor(secs % 60);
        return m + ":" + (s < 10 ? "0" : "") + s;
    }

    function playTrack(url, title, artist, art) {
        if (!url) return;
        
        player.classList.remove('hidden');
        audio.src = url;
        audio.play();
        
        playerTitle.innerText = title;
        playerArtist.innerText = artist;
        if (art) playerArt.src = art;
        
        updatePlayIcon(true);
    }

    function togglePlay() {
        if (audio.paused) {
            audio.play();
            updatePlayIcon(true);
        } else {
            audio.pause();
            updatePlayIcon(false);
        }
    }

    function updatePlayIcon(isPlaying) {
        playBtn.innerHTML = isPlaying ? '<i class="bi-pause-fill text-2xl"></i>' : '<i class="bi-play-fill text-2xl"></i>';
    }

    playBtn.addEventListener('click', togglePlay);

    audio.addEventListener('timeupdate', () => {
        const pct = (audio.currentTime / audio.duration) * 100;
        progressBar.style.width = pct + '%';
        timeCur.innerText = formatTime(audio.currentTime);
        timeTotal.innerText = formatTime(audio.duration);
    });

    // Add play events to all data-play-track elements
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-play-track]');
        if (btn) {
            e.preventDefault();
            playTrack(
                btn.dataset.trackUrl,
                btn.dataset.trackTitle,
                btn.dataset.trackArtist,
                btn.dataset.trackArt
            );
        }
    });
  </script>
</body>
</html>