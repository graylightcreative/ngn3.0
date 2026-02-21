<?php
/**
 * NextGenNoise Sovereign Engine v3.0.7
 * The Pressurized Infrastructure for the Independent Empire.
 */

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/definitions/site-settings.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use NGN\Lib\Artists\EntityResolver;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$config = new Config();
$pdo = ConnectionFactory::read($config);

$isLoggedIn = !empty($_SESSION['LoggedIn']) && $_SESSION['LoggedIn'] === 1;
$currentUser = $_SESSION['User'] ?? null;

$view = $_GET['view'] ?? 'home';
$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$perPage = 24;

// Host-based Routing Fallbacks
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'forge.') === 0 || strpos($host, 'beta.') === 0) {
    if (empty($_GET['view'])) {
        require_once __DIR__ . '/forge.php';
        exit;
    }
}

// Terminology Overrides
if ($view === 'partner') $view = 'artist';
if ($view === 'capital-group') $view = 'label';

if (in_array($view, ['dashboard', 'profile']) && !$isLoggedIn) {
    header('Location: /login.php'); exit;
}

define('DEFAULT_AVATAR', '/lib/images/site/2026/default-avatar.png');
define('DEFAULT_POST', '/lib/images/site/2026/NGN-Emblem-Light.png');

$data = [];
$root = __DIR__ . '/../';
$total = 0;
$totalPages = 1;

try {
    if ($view === 'home') {
        $data['counts'] = [
            'active_partners' => (int)$pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
            'production_labs' => (int)$pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn(),
            'growth_score' => 84, // Fixed benchmark for layman view
            'goal_impact' => 1000000
        ];
        $data['trending_partners'] = get_trending_artists($pdo, 5);
        $data['partner_rankings'] = get_top_rankings($pdo, 'artist', 10);
        $data['label_rankings'] = get_top_rankings($pdo, 'label', 10);
        $data['news_reports'] = get_ngn_posts($pdo, '', 1, 4);
        
    } elseif ($view === 'post' && ($slug || $id)) {
        $stmt = $pdo->prepare('SELECT * FROM `posts` WHERE (slug = :slug OR id = :id) AND status = :status LIMIT 1');
        $stmt->execute([':slug' => $slug, ':id' => $id, ':status' => 'published']);
        $data['post'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } elseif (in_array($view, ['artists', 'partners', 'labels', 'stations', 'venues'])) {
        $table = ($view === 'partners') ? 'artists' : $view;
        $total = ngn_count($pdo, $table, $search);
        $totalPages = max(1, ceil($total / $perPage));
        $data[$view] = ngn_query($pdo, $table, $search, $page, $perPage);

    } elseif ($view === 'posts' || $view === 'market-reports') {
        $total = get_ngn_posts_count($pdo, $search);
        $totalPages = max(1, ceil($total / $perPage));
        $data['posts'] = get_ngn_posts($pdo, $search, $page, $perPage);
        $data['market_reports'] = $data['posts'];

    } elseif ($view === 'releases') {
        $total = (int)$pdo->query("SELECT COUNT(*) FROM releases")->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));
        $data['releases'] = get_releases_list($pdo, $search, $page, $perPage);

    } elseif ($view === 'release' && ($slug || $id)) {
        $col = is_numeric($id) ? 'id' : 'slug';
        $val = is_numeric($id) ? $id : $slug;
        $stmt = $pdo->prepare("SELECT r.*, a.name as artist_name, a.slug as artist_slug FROM `releases` r LEFT JOIN `artists` a ON r.artist_id = a.id WHERE r.{$col} = ? LIMIT 1");
        $stmt->execute([$val]);
        $rel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rel) {
            $rel['artist'] = [
                'name' => $rel['artist_name'] ?? 'Unknown Artist',
                'slug' => $rel['artist_slug'] ?? ''
            ];
            // Fetch tracks
            $stmtT = $pdo->prepare("SELECT * FROM tracks WHERE release_id = ? ORDER BY track_number ASC, disc_number ASC, id ASC");
            $stmtT->execute([$rel['id']]);
            $rel['tracks'] = $stmtT->fetchAll(PDO::FETCH_ASSOC);
            $data['release'] = $rel;
            $seoTitle = htmlspecialchars($rel['title']) . " // " . htmlspecialchars($rel['artist_name']) . " // NextGenNoise";
        }

    } elseif ($view === 'charts' || $view === 'smr-charts') {
        $smr = get_smr_charts($pdo);
        $data['smr_charts'] = $smr['items'];
        $data['smr_date'] = $smr['date'];
        $data['matched_artists'] = []; 
        if ($view === 'charts') {
            $data['chart_type'] = $_GET['type'] ?? 'artists';
            $data['partner_rankings'] = get_top_rankings($pdo, 'artist', 200);
            $data['label_rankings'] = get_top_rankings($pdo, 'label', 200);
        }

    } elseif (in_array($view, ['artist', 'label', 'station', 'venue'])) {
        $identifier = $slug ?: $id;
        $entity = ngn_get($pdo, $view . 's', $identifier);
        
        if ($entity) {
            $data['entity'] = $entity;
            // Enrichment...
            try {
                $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
                $stmt = $rankingsPdo->prepare('SELECT score, flags FROM `ranking_items` WHERE entity_type = :type AND entity_id = :eid ORDER BY window_id DESC LIMIT 1');
                $stmt->execute([':type' => $view, ':eid' => $entity['id']]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $data['entity']['scores'] = ['Score' => $r['score']];
                    $data['entity']['ranking_flags'] = !empty($r['flags']) ? json_decode($r['flags'], true) : [];
                }
            } catch (\Throwable $e) {}

            try {
                $pdoSpins = ConnectionFactory::named($config, 'spins2025');
                $spinWeight = 0.25;
                
                // RECENT SPINS (Artist & Station)
                if (in_array($view, ['artist', 'station'])) {
                    $col = ($view === 'artist') ? 'artist_id' : 'station_id';
                    $stmt = $pdoSpins->prepare("SELECT * FROM station_spins WHERE {$col} = ? ORDER BY played_at DESC LIMIT 20");
                    $stmt->execute([$entity['id']]);
                    $spins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Special Logic for JC's Kick Ass Rock Show (Station ID 12)
                    if ($view === 'station' && $entity['id'] == 12) {
                        // Merging logic if needed, but primary source is now station_spins
                    }

                    if (!empty($spins)) {
                        $ids = ($view === 'artist') ? array_unique(array_column($spins, 'station_id')) : array_unique(array_column($spins, 'artist_id'));
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $targetTable = ($view === 'artist') ? 'stations' : 'artists';
                        $stmtM = $pdo->prepare("SELECT id, name, slug FROM {$targetTable} WHERE id IN ($placeholders)");
                        $stmtM->execute($ids);
                        $mMap = [];
                        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $m) $mMap[$m['id']] = $m;
                        foreach ($spins as &$spin) {
                            $row = $mMap[$spin[($view === 'artist' ? 'station_id' : 'artist_id')]] ?? null;
                            if ($view === 'artist') {
                                $spin['station_name'] = $row['name'] ?? 'Unknown Station';
                                $spin['station_slug'] = $row['slug'] ?? '';
                            } else {
                                $spin['artist_name'] = $row['name'] ?? 'Unknown Artist';
                                $spin['artist_slug'] = $row['slug'] ?? '';
                            }
                        }
                        $data['entity']['recent_spins'] = $spins;
                    }
                }

                // ROSTERS (Label & Station)
                if ($view === 'label') {
                    $stmt = $pdo->prepare("SELECT id, name, slug, image_url FROM artists WHERE label_id = ? AND status = 'active' ORDER BY name ASC");
                    $stmt->execute([$entity['id']]);
                    $data['entity']['roster'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($view === 'station') {
                    // Current rotation artists as 'roster'
                    if (!empty($data['entity']['recent_spins'])) {
                        $rosterIds = array_unique(array_column($data['entity']['recent_spins'], 'artist_id'));
                        $placeholders = implode(',', array_fill(0, count($rosterIds), '?'));
                        $stmt = $pdo->prepare("SELECT id, name, slug, image_url FROM artists WHERE id IN ($placeholders)");
                        $stmt->execute($rosterIds);
                        $data['entity']['roster'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }

                // AUDIT FACTORS (The "Prove It" / Scoring Logic)
                $spinCount = (int)$pdoSpins->query("SELECT COUNT(*) FROM station_spins WHERE " . ($view === 'artist' ? 'artist_id' : 'station_id') . " = " . (int)$entity['id'])->fetchColumn();
                $socialCount = (int)$pdo->query("SELECT COUNT(*) FROM oauth_tokens WHERE entity_type='{$view}' AND entity_id=" . (int)$entity['id'])->fetchColumn();
                
                $factors = [];
                $factors[] = ['label' => 'Verified Claims', 'value' => !empty($entity['claimed']) ? 1000 : 0, 'status' => 'Verified'];
                
                if ($view === 'artist') {
                    $releaseCount = (int)$pdo->query("SELECT COUNT(*) FROM releases WHERE artist_id=" . (int)$entity['id'])->fetchColumn();
                    $factors[] = ['label' => 'Radio Rotation', 'value' => $spinCount * $spinWeight, 'status' => $spinCount . ' Spins'];
                    $factors[] = ['label' => 'Social Moat', 'value' => $socialCount * 50, 'status' => 'Connected'];
                    $factors[] = ['label' => 'Production History', 'value' => $releaseCount * 25, 'status' => 'On-Chain'];
                } elseif ($view === 'station') {
                    $factors[] = ['label' => 'Broadcast Impact', 'value' => $spinCount * 2, 'status' => 'Active'];
                    $factors[] = ['label' => 'Node Reliability', 'value' => 500, 'status' => '99.9% Uptime'];
                } elseif ($view === 'label') {
                    $artCount = (int)$pdo->query("SELECT COUNT(*) FROM artists WHERE label_id=" . (int)$entity['id'])->fetchColumn();
                    $factors[] = ['label' => 'Vanguard Roster', 'value' => $artCount * 100, 'status' => $artCount . ' Artists'];
                    $factors[] = ['label' => 'Capital Impact', 'value' => 2500, 'status' => 'Primary'];
                } elseif ($view === 'venue') {
                    $factors[] = ['label' => 'Physical Footprint', 'value' => 1500, 'status' => 'Active'];
                    $factors[] = ['label' => 'Signal Strength', 'value' => 750, 'status' => 'Anycast Enabled'];
                }

                $data['entity']['audit'] = ['factors' => $factors];
                
            } catch (\Throwable $e) {}
        }
    }
} catch (\Throwable $e) { $data['error'] = $e->getMessage(); }

function ngn_count(PDO $pdo, string $table, string $search = ''): int {
    $where = $search !== '' ? "WHERE name LIKE ?" : '';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` {$where}");
    if ($search !== '') $stmt->execute(['%'.$search.'%']); else $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function ngn_query(PDO $pdo, string $table, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = $search !== '' ? "WHERE name LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset");
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ngn_get(PDO $pdo, string $table, $val): ?array {
    if (!$val) return null;
    $col = is_numeric($val) ? 'id' : 'slug';
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE {$col} = ? LIMIT 1");
    $stmt->execute([$val]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_top_rankings(PDO $pdo, string $type, int $limit = 10): array {
    global $config;
    try {
        $rankingsPdo = ConnectionFactory::named($config, 'rankings2025');
        // Find latest window with substantial data
        $wid = $rankingsPdo->query("SELECT window_id FROM ranking_items GROUP BY window_id HAVING COUNT(*) > 10 ORDER BY window_id DESC LIMIT 1")->fetchColumn();
        if (!$wid) $wid = $rankingsPdo->query("SELECT MAX(window_id) FROM ranking_items")->fetchColumn();
        if (!$wid) return [];

        $stmt = $rankingsPdo->prepare("SELECT score as Score, entity_id as id, prev_rank, deltas, flags FROM ranking_items WHERE window_id = :wid AND entity_type = :type ORDER BY score DESC LIMIT :lim");
        $stmt->bindValue(':wid', $wid, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return [];

        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $table = ($type === 'label') ? 'labels' : 'artists';
        $meta = $pdo->prepare("SELECT id, name as Name, slug, image_url FROM `{$table}` WHERE id IN ($placeholders)");
        $meta->execute($ids);
        $map = []; foreach($meta->fetchAll(PDO::FETCH_ASSOC) as $m) $map[$m['id']] = $m;
        $res = []; foreach($rows as $r) if(isset($map[$r['id']])) $res[] = array_merge($r, $map[$r['id']]);
        return $res;
    } catch (\Throwable $e) { return []; }
}

function get_trending_artists(PDO $pdo, int $limit = 5): array {
    $stmt = $pdo->prepare("SELECT a.id, a.name, a.slug, a.image_url, COUNT(ce.id) as engagement_count FROM `artists` a JOIN `cdm_engagements` ce ON a.id = ce.artist_id WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY a.id, a.name, a.slug, a.image_url ORDER BY engagement_count DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_ngn_posts(PDO $pdo, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = "WHERE p.status = 'published'";
    if ($search !== '') $where .= " AND p.title LIKE :search";
    $stmt = $pdo->prepare("SELECT p.*, a.name as author_name FROM `posts` p LEFT JOIN `artists` a ON p.author_id = a.id {$where} ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset");
    if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_ngn_posts_count(PDO $pdo, string $search): int {
    $where = "WHERE status = 'published'";
    if ($search !== '') $where .= " AND title LIKE :search";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `posts` {$where}");
    if ($search !== '') $stmt->execute(['%'.$search.'%']); else $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_releases_list(PDO $pdo, string $search, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $where = $search !== '' ? "WHERE r.title LIKE :search" : '';
    $stmt = $pdo->prepare("SELECT r.*, a.name as artist_name, a.slug as artist_slug FROM `releases` r LEFT JOIN `artists` a ON r.artist_id = a.id {$where} ORDER BY r.release_date DESC LIMIT :limit OFFSET :offset");
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
        $date = $smrPdo->query("SELECT MAX(window_date) FROM smr_chart")->fetchColumn();
        if (!$date) return ['items' => [], 'date' => null];
        $stmt = $smrPdo->prepare("SELECT * FROM smr_chart WHERE window_date = ? ORDER BY tws DESC LIMIT 100");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return ['items' => [], 'date' => $date];

        // Enrichment
        $ids = array_unique(array_column($rows, 'ArtistId'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $meta = $pdo->prepare("SELECT id, name, slug, image_url FROM artists WHERE id IN ($placeholders)");
        $meta->execute($ids);
        $map = []; foreach($meta->fetchAll(PDO::FETCH_ASSOC) as $m) $map[$m['id']] = $m;
        foreach($rows as &$r) {
            $r['artist'] = $map[$r['ArtistId']] ?? ['name' => 'Unknown Artist', 'slug' => '', 'image_url' => ''];
        }
        return ['items' => $rows, 'date' => $date];
    } catch (\Throwable $e) { return ['items' => [], 'date' => null]; }
}

$seoTitle = "NextGenNoise // The Financial Engine for Independent Music";
$seoDesc = "Invest in the future of sound. NextGenNoise provides fixed-return investment opportunities backed by real-time music data and high-tech production labs.";
$seoImage = "https://nextgennoise.com/lib/images/site/og-image-2026.jpg";
$seoUrl = "https://nextgennoise.com" . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $seoTitle ?></title>
  <?php include $root . 'lib/partials/head-sovereign.php'; ?>
  <?php include $root . 'lib/partials/app-styles.php'; ?>
</head>
<body class="h-full selection:bg-brand/30 dark bg-black text-white">
  <?php include $root . 'lib/partials/pwa-mobilizer.php'; ?>
  <div class="app-frame flex flex-col">
    <?php include $root . 'lib/partials/sovereign-menu.php'; ?>
    <main class="flex-1 content-container flex flex-col">
      <header class="flex items-center justify-between px-6 h-20 sticky top-0 z-40 bg-black/80 backdrop-blur-2xl border-b border-white/5 w-full">
        <div class="flex items-center gap-8">
            <a href="/" class="flex-shrink-0">
                <img src="/lib/images/site/2026/NGN-Emblem-Light.png" class="h-10 w-auto md:hidden" alt="NGN">
                <img src="/lib/images/site/2026/NGN-Logo-Full-Light.png" class="h-8 w-auto hidden md:block" alt="NGN">
            </a>
            <nav class="hidden md:flex items-center gap-6 text-[10px] font-black uppercase tracking-widest text-zinc-500">
                <a href="/charts" class="hover:text-brand transition-colors">Charts</a>
                <a href="/releases" class="hover:text-brand transition-colors">Music</a>
                <a href="/posts" class="hover:text-brand transition-colors">Market Reports</a>
                <a href="/partners" class="hover:text-brand transition-colors">Partners</a>
            </nav>
        </div>
        <div class="flex items-center gap-3">
          <form method="get" action="/"><input type="text" name="q" placeholder="Search..." class="w-32 focus:w-48 h-9 px-4 rounded-full bg-zinc-800 border-none text-[11px] text-white transition-all"></form>
          <button onclick="toggleSovereignMenu()" class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center text-zinc-400 hover:text-white"><i class="bi bi-three-dots-vertical text-xl"></i></button>
        </div>
      </header>
      <div class="px-4 py-6">
        <?php 
        if ($view === 'home') include $root . 'lib/partials/view-home.php';
        elseif (in_array($view, ['artists', 'partners', 'labels', 'stations', 'venues'])) include $root . 'lib/partials/view-listings.php';
        elseif (in_array($view, ['posts', 'market-reports'])) include $root . 'lib/partials/view-posts.php';
        elseif ($view === 'post') {
            if (!empty($data['post'])) include $root . 'lib/partials/view-post-single.php';
            else include $root . 'lib/partials/view-404.php';
        }
        elseif ($view === 'releases') include $root . 'lib/partials/view-releases.php';
        elseif ($view === 'release') {
            if (!empty($data['release'])) include $root . 'lib/partials/view-release-single.php';
            else include $root . 'lib/partials/view-404.php';
        }
        elseif ($view === 'charts') include $root . 'lib/partials/view-charts.php';
        elseif ($view === 'smr-charts') include $root . 'lib/partials/view-smr-charts.php';
        elseif ($view === 'advertisers') include $root . 'lib/partials/view-advertiser.php';
        elseif ($view === 'pricing') include $root . 'lib/partials/view-pricing.php';
        elseif (in_array($view, ['artist', 'label', 'station', 'venue'])) {
            $entity = $data['entity'] ?? null;
            if ($entity) include $root . "lib/partials/profiles/{$view}.php";
            else include $root . 'lib/partials/view-404.php';
        }
        else include $root . 'lib/partials/view-404.php';
        ?>
      </div>
    </main>
  </div>
  <?php require $root . 'lib/partials/footer.php'; ?>
</body>
</html>
