<?php
/**
 * NextGenNoise Sovereign Engine v3.0.0
 * The Pressurized Infrastructure for the Independent Empire.
 * Bible Ref: Chapter 1 (Architecture) // Core Orchestrator
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/definitions/site-settings.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Session State
$isLoggedIn = !empty($_SESSION['LoggedIn']) && $_SESSION['LoggedIn'] === 1;
$currentUser = $_SESSION['User'] ?? null;

// View Routing Protocol
$view = $_GET['view'] ?? 'home';
$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$perPage = 24;

// Auth Guards
if (in_array($view, ['dashboard', 'profile']) && !$isLoggedIn) {
    header('Location: /login.php');
    exit;
}

// Global UI Constants
define('DEFAULT_AVATAR', '/lib/images/site/2026/default-avatar.png');
define('DEFAULT_POST', '/lib/images/site/2026/NGN-Emblem-Light.png');

// Data Enrichment & Engine Initialization
$data = [];
$root = __DIR__ . '/../';

// View Control Flow (Business Logic)
try {
    if ($view === 'home') {
        // Fetch core counts for ticker
        $counts = [
            'artists' => ngn_count($pdo, 'artists'),
            'labels' => ngn_count($pdo, 'labels'),
            'stations' => ngn_count($pdo, 'stations'),
            'venues' => ngn_count($pdo, 'venues')
        ];
        $data['trending_artists'] = get_trending_artists($pdo, 5);
        $data['artist_rankings'] = get_top_rankings($pdo, 'artist', 10);
        $data['label_rankings'] = get_top_rankings($pdo, 'label', 10);
        $data['posts'] = get_ngn_posts($pdo, '', 1, 4);
        
    } elseif ($view === 'post' && ($slug || $id)) {
        $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`posts` WHERE (slug = :slug OR id = :id) AND status = :status LIMIT 1');
        $stmt->execute([':slug' => $slug, ':id' => $id, ':status' => 'published']);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($post) {
            $post['is_locked'] = false; // Add tier logic here later
            $data['post'] = $post;
        }
        
    } elseif ($view === 'release' && $slug) {
        $stmt = $pdo->prepare('SELECT r.*, a.name as artist_name, a.slug as artist_slug FROM `ngn_2025`.`releases` r JOIN `ngn_2025`.`artists` a ON r.artist_id = a.id WHERE r.slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $release = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($release) {
            $stmt = $pdo->prepare('SELECT * FROM `ngn_2025`.`tracks` WHERE release_id = ? ORDER BY track_number ASC');
            $stmt->execute([$release['id']]);
            $release['tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $data['release'] = $release;
        }

    } elseif (in_array($view, ['artists', 'labels', 'stations', 'venues'])) {
        $offset = ($page - 1) * $perPage;
        $total = ngn_count($pdo, $view);
        $totalPages = ceil($total / $perPage);
        $data[$view] = ngn_query($pdo, $view, $search, $page, $perPage);

    } elseif ($view === 'posts') {
        $total = get_ngn_posts_count($pdo, $search);
        $totalPages = ceil($total / $perPage);
        $data['posts'] = get_ngn_posts($pdo, $search, $page, $perPage);

    } elseif ($view === 'releases') {
        $total = ngn_count($pdo, 'releases');
        $totalPages = ceil($total / $perPage);
        $data['releases'] = get_releases_list($pdo, $search, $page, $perPage);

    } elseif ($view === 'charts') {
        $type = $_GET['type'] ?? 'artists';
        $data['chart_type'] = $type;
        $data['artist_rankings'] = get_top_rankings($pdo, 'artist', 50);
        $data['label_rankings'] = get_top_rankings($pdo, 'label', 50);

    } elseif ($view === 'smr-charts') {
        $data['smr_charts'] = get_smr_charts($pdo);
        $data['smr_date'] = date('F j, Y');
    }

} catch (\Throwable $e) {
    error_log("NGN Core Error: " . $e->getMessage());
    $data['error'] = $e->getMessage();
}

/**
 * ENGINE HELPERS
 */
function ngn_count(PDO $pdo, string $table): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM `ngn_2025`.`{$table}`")->fetchColumn();
}

function ngn_query(PDO $pdo, string $table, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = $search !== '' ? "WHERE name LIKE :search" : '';
    $sql = "SELECT * FROM `ngn_2025`.`{$table}` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ngn_get(PDO $pdo, string $table, $id): ?array {
    $col = is_numeric($id) ? 'id' : 'slug';
    $sql = "SELECT * FROM `ngn_2025`.`{$table}` WHERE {$col} = :val LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':val', is_numeric($id) ? (int)$id : $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_top_rankings(PDO $pdo, string $type, int $limit = 10): array {
    global $config;
    try {
        $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
        $sql = "SELECT i.score as Score, e.name as Name, e.slug as slug, e.image_url as image_url 
                FROM `ngn_rankings_2025`.`ranking_items` i
                JOIN `ngn_2025`.`{$type}s` e ON i.entity_id = e.id
                WHERE i.entity_type = ?
                ORDER BY i.score DESC LIMIT ?";
        $stmt = $rankingsPdo->prepare($sql);
        $stmt->execute([$type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log("Rankings Fetch Error: " . $e->getMessage());
        return [];
    }
}

function get_trending_artists(PDO $pdo, int $limit = 5): array {
    $sql = "SELECT a.id, a.name, a.slug, a.image_url, COUNT(ce.id) as engagement_count
            FROM `ngn_2025`.`artists` a
            JOIN `ngn_2025`.`cdm_engagements` ce ON a.id = ce.artist_id
            WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY a.id, a.name, a.slug, a.image_url
            ORDER BY engagement_count DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_ngn_posts(PDO $pdo, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = "WHERE p.status = 'published'";
    if ($search !== '') $where .= " AND p.title LIKE :search";
    $sql = "SELECT p.*, a.name as author_name FROM `ngn_2025`.`posts` p 
            LEFT JOIN `ngn_2025`.`artists` a ON p.author_id = a.id 
            {$where} ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_ngn_posts_count(PDO $pdo, string $search): int {
    $where = "WHERE status = 'published'";
    if ($search !== '') $where .= " AND title LIKE :search";
    $sql = "SELECT COUNT(*) FROM `ngn_2025`.`posts` {$where}";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_releases_list(PDO $pdo, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = $search !== '' ? "WHERE r.title LIKE :search" : '';
    $sql = "SELECT r.*, a.name as artist_name, a.slug as artist_slug FROM `ngn_2025`.`releases` r 
            LEFT JOIN `ngn_2025`.`artists` a ON r.artist_id = a.id 
            {$where} ORDER BY r.release_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_smr_charts(PDO $pdo): array {
    global $config;
    try {
        $smrPdo = ConnectionFactory::named($config, 'smr2025');
        // Fetch latest chart data
        $stmt = $smrPdo->query("SELECT * FROM `ngn_smr_2025`.`smr_chart_data` ORDER BY TWP ASC LIMIT 100");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Enrich with artist data from main DB
        foreach ($rows as &$row) {
            $stmt = $pdo->prepare("SELECT name, slug, image_url FROM `ngn_2025`.`artists` WHERE name = ? LIMIT 1");
            $stmt->execute([$row['Artist']]);
            $row['artist'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => $row['Artist'], 'slug' => '', 'image_url' => null];
        }
        return $rows;
    } catch (\Throwable $e) {
        error_log("SMR Fetch Error: " . $e->getMessage());
        return [];
    }
}

// SEO Metadata Protocol
$seoTitle = "NextGenNoise // The Sovereign Music Infrastructure";
$seoDesc = "Own your sound. NextGenNoise provides the cryptographic source of truth for independent music.";
$seoImage = "https://nextgennoise.com/lib/images/site/og-image-2026.jpg";
$seoUrl = "https://nextgennoise.com" . $_SERVER['REQUEST_URI'];

// View Specific Metadata
if ($view === 'post' && !empty($data['post'])) {
    $seoTitle = $data['post']['title'] . " // NGN Intelligence";
    $seoDesc = htmlspecialchars(substr(strip_tags($data['post']['excerpt'] ?? $data['post']['content'] ?? ''), 0, 160));
}

?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $seoTitle ?></title>
  <meta name="description" content="<?= $seoDesc ?>">
  
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>

<body class="h-full selection:bg-brand/30 dark">
  <?php include $root . 'lib/partials/pwa-mobilizer.php'; ?>
  
  <div class="app-frame flex flex-col">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 content-container flex flex-col">
      <!-- Sovereign App Header -->
      <header class="flex items-center justify-between px-6 h-20 sticky top-0 z-40 bg-black/80 backdrop-blur-2xl border-b border-white/5 w-full">
        <div class="flex items-center gap-8">
            <a href="/" class="flex-shrink-0">
                <img src="/lib/images/site/2026/NGN-Emblem-Light.png" class="h-10 w-auto lg:hidden" alt="NGN">
                <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" class="h-10 w-auto hidden lg:block" alt="Next Generation Noise">
            </a>
            
            <!-- Desktop Primary Nav (Header Integrated) -->
            <nav class="hidden md:flex items-center gap-6 text-[11px] font-black uppercase tracking-widest text-zinc-500">
                <a href="/charts" class="hover:text-brand transition-colors">Charts</a>
                <a href="/releases" class="hover:text-brand transition-colors">Music</a>
                <a href="/posts" class="hover:text-brand transition-colors">Newswire</a>
                <a href="/artists" class="hover:text-brand transition-colors">Fleet</a>
            </nav>
        </div>
        
        <div class="flex items-center gap-3">
          <form method="get" action="/" class="relative" id="global-search-form">
            <input type="text" name="q" id="global-search-input" autocomplete="off" placeholder="Search..." class="w-24 focus:w-40 md:w-32 md:focus:w-48 h-9 pl-4 pr-4 rounded-full bg-zinc-800 border-none text-[11px] text-white transition-all">
            <div id="search-autocomplete" class="absolute top-full left-0 right-0 mt-2 bg-zinc-900 border border-white/10 rounded-2xl shadow-2xl hidden z-[200] overflow-hidden">
                <div id="autocomplete-results" class="max-h-[300px] overflow-y-auto py-2"></div>
            </div>
          </form>
          
          <?php if ($isLoggedIn): ?>
            <button class="w-8 h-8 rounded-full bg-black border border-white/10 overflow-hidden flex-shrink-0">
              <img src="<?= htmlspecialchars(user_image($currentUser['Slug'] ?? $currentUser['username'] ?? '', $currentUser['Image'] ?? $currentUser['avatar_url'] ?? null)) ?>" class="w-full h-full object-cover">
            </button>
          <?php endif; ?>

          <button onclick="toggleSovereignMenu()" class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center text-zinc-400 hover:text-white transition-all flex-shrink-0">
            <i class="bi bi-three-dots-vertical text-xl"></i>
          </button>
        </div>
      </header>

      <?php include $root . 'lib/partials/search-logic.php'; ?>

      <!-- View Wrapper -->
      <div class="px-4 py-6">
        <?php 
        // Dynamic View Orchestration
        if ($view === 'home') include $root . 'lib/partials/view-home.php';
        elseif (in_array($view, ['artists', 'labels', 'stations', 'venues'])) include $root . 'lib/partials/view-listings.php';
        elseif ($view === 'posts') include $root . 'lib/partials/view-posts.php';
        elseif ($view === 'post') include $root . 'lib/partials/view-post-single.php';
        elseif ($view === 'releases') include $root . 'lib/partials/view-releases.php';
        elseif ($view === 'release') include $root . 'lib/partials/view-release-single.php';
        elseif ($view === 'song') include $root . 'lib/partials/view-song-single.php';
        elseif ($view === 'charts') include $root . 'lib/partials/view-charts.php';
        elseif ($view === 'smr-charts') include $root . 'lib/partials/view-smr-charts.php';
        elseif ($view === 'advertisers') include $root . 'lib/partials/view-advertiser.php';
        elseif (in_array($view, ['artist', 'label', 'station', 'venue'])) {
            $entity = ngn_get($pdo, $view . 's', $slug ?: $id);
            if ($entity) {
                // Fetch NGN scores for profiles
                try {
                    $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                    $stmt = $rankingsPdo->prepare('SELECT score FROM `ngn_rankings_2025`.`ranking_items` WHERE entity_type = :entityType AND entity_id = :entityId ORDER BY window_id DESC LIMIT 1');
                    $stmt->execute([':entityType' => $view, ':entityId' => $entity['id']]);
                    $rankingData = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($rankingData) $entity['scores'] = ['Score' => $rankingData['score']];
                } catch (\Throwable $e) {}

                include $root . "lib/partials/profiles/{$view}.php";
            } else {
                include $root . 'lib/partials/view-404.php';
            }
        }
        else include $root . 'lib/partials/view-404.php';
        ?>
      </div>
    </main>
  </div>

  <?php require $root . 'lib/partials/footer.php'; ?>

</body>
</html>
