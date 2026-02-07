<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
// require_once $root.'/lib/bootstrap.php'; // Handled by _guard.php
use NGN\Lib\Env; use NGN\Lib\Config; use NGN\Lib\Auth\TokenService; use NGN\Lib\DB\ConnectionFactory;
Env::load($root);
$cfg = new Config();
$env = $cfg->appEnv();
$featureAdmin = $cfg->featureAdmin();

// Auto-mint admin token - if we got past _guard.php, we're authorized
$mintedToken = null;
try {
    $svc = new TokenService($cfg);
    $sub = !empty($_SESSION['User']['Email']) ? (string)$_SESSION['User']['Email'] : 'admin@session';
    $issued = $svc->issueAccessToken(['sub' => $sub, 'role' => 'admin']);
    $mintedToken = $issued['token'] ?? null;
} catch (\Throwable $e) {
    error_log('Token mint failed: ' . $e->getMessage());
}

// ---- Live roles/status (server-side, fast) ----
function _pdo_role(string $role): ?PDO {
  try {
    $config = new Config();
    switch (strtolower($role)) {
      case 'primary':
        return ConnectionFactory::write($config);
      case 'rankings':
        return ConnectionFactory::named($config, 'ngnrankings');
      case 'smr':
        return ConnectionFactory::named($config, 'smrrankings');
      case 'spins':
        return ConnectionFactory::named($config, 'ngnspins');
      default: return null;
    }
  } catch (\Throwable $e) { return null; }
}
function _table_exists(PDO $pdo, string $schema, string $table): bool {
  try { $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?'); $st->execute([$schema,$table]); return ((int)$st->fetchColumn())>0; } catch(\Throwable $e){ return false; }
}
function _file_has(string $path, string $needle): bool { return (is_file($path) && strpos((string)@file_get_contents($path), $needle)!==false); }

$landingPath = $root.'/frontend/landing/index.php';
$apiPath = $root.'/api/v1/index.php';
$headPartial = $root.'/lib/partials/head.php';
$hasArtistsTab = _file_has($landingPath, '?view=artists');
$hasLabelsTab  = _file_has($landingPath, '?view=labels');
$hasStationsTab= _file_has($landingPath, '?view=stations');
$hasChartsTab  = _file_has($landingPath, '?view=charts');
$hasSMRTab     = _file_has($landingPath, '?view=smr-charts');

$pdoPrimary = _pdo_role('primary');
$pdoRank    = _pdo_role('rankings');
$pdoSmr     = _pdo_role('smr');
$pdoSpins   = _pdo_role('spins');

$hasTable = function(?PDO $pdo, string $schema, string $table) {
  return $pdo ? _table_exists($pdo, $schema, $table) : false;
};

$countRows = function(?PDO $pdo, string $schema, string $table): ?int {
  if (!$pdo) return null;
  try {
    $st = $pdo->query("SELECT COUNT(*) FROM `{$schema}`.`{$table}`");
    return (int)$st->fetchColumn();
  } catch (\Throwable $e) { return null; }
};

$exists = [
  'ngn_2025.artists'  => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','artists') : false,
  'ngn_2025.labels'   => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','labels') : false,
  'ngn_2025.stations' => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','stations') : false,
  'ngn_2025.venues'   => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','venues') : false,
  'ngn_2025.writers'  => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','writers') : false,
  'ngn_2025.managers' => $pdoPrimary ? _table_exists($pdoPrimary,'ngn_2025','managers') : false,
  // Editorial & media
  'ngn_2025.posts'    => $hasTable($pdoPrimary,'ngn_2025','posts'),
  'ngn_2025.videos'   => $hasTable($pdoPrimary,'ngn_2025','videos'),
  'ngn_2025.media_assets' => $hasTable($pdoPrimary,'ngn_2025','media_assets'),
  // Library & playback
  'ngn_2025.favorites' => $hasTable($pdoPrimary,'ngn_2025','favorites'),
  'ngn_2025.follows'   => $hasTable($pdoPrimary,'ngn_2025','follows'),
  'ngn_2025.history'   => $hasTable($pdoPrimary,'ngn_2025','history'),
  'ngn_2025.playlists' => $hasTable($pdoPrimary,'ngn_2025','playlists'),
  'ngn_2025.playlist_items' => $hasTable($pdoPrimary,'ngn_2025','playlist_items'),
  'ngn_2025.playback_events' => $hasTable($pdoPrimary,'ngn_2025','playback_events'),
  'rankings_2025.ranking_windows' => $pdoRank ? _table_exists($pdoRank,'ngn_rankings_2025','ranking_windows') : false,
  'rankings_2025.ranking_items'   => $pdoRank ? _table_exists($pdoRank,'ngn_rankings_2025','ranking_items') : false,
  'smr_2025.smr_chart' => $pdoSmr ? _table_exists($pdoSmr,'ngn_smr_2025','smr_chart') : false,
  'spins_2025.station_spins' => $pdoSpins ? _table_exists($pdoSpins,'ngn_spins_2025','station_spins') : false,
];

// Column checks for rankings additions
$hasRankingPrevRank = false; $hasRankingFlags = false;
if ($pdoRank && $exists['rankings_2025.ranking_items']) {
  try {
    $st = $pdoRank->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?');
    $st->execute(['ngn_rankings_2025','ranking_items','prev_rank']);
    $hasRankingPrevRank = ((int)$st->fetchColumn())>0;
    $st->execute(['ngn_rankings_2025','ranking_items','flags']);
    $hasRankingFlags = ((int)$st->fetchColumn())>0;
  } catch (\Throwable $e) {}
}

// Simple counts
$counts2025 = [
  'artists' => $exists['ngn_2025.artists'] ? $countRows($pdoPrimary,'ngn_2025','artists') : null,
  'labels' => $exists['ngn_2025.labels'] ? $countRows($pdoPrimary,'ngn_2025','labels') : null,
  'venues' => $exists['ngn_2025.venues'] ? $countRows($pdoPrimary,'ngn_2025','venues') : null,
  'stations' => $exists['ngn_2025.stations'] ? $countRows($pdoPrimary,'ngn_2025','stations') : null,
  'posts' => $exists['ngn_2025.posts'] ? $countRows($pdoPrimary,'ngn_2025','posts') : null,
  'videos'=> $exists['ngn_2025.videos'] ? $countRows($pdoPrimary,'ngn_2025','videos') : null,
  'media_assets'=> $exists['ngn_2025.media_assets'] ? $countRows($pdoPrimary,'ngn_2025','media_assets') : null,
  'playback_events'=> $exists['ngn_2025.playback_events'] ? $countRows($pdoPrimary,'ngn_2025','playback_events') : null,
];

// Rankings counts
$rankingWindowsCount = null;
$rankingItemsCount = null;
if ($pdoRank) {
  try {
    $rankingWindowsCount = $countRows($pdoRank, 'ngn_rankings_2025', 'ranking_windows');
    $rankingItemsCount = $countRows($pdoRank, 'ngn_rankings_2025', 'ranking_items');
  } catch (\Throwable $e) {}
}

// SMR counts
$smrChartDataCount = null;
if ($pdoSmr) {
  try {
    $st = $pdoSmr->query("SELECT COUNT(*) FROM smr_chart");
    $smrChartDataCount = (int)$st->fetchColumn();
  } catch (\Throwable $e) {}
}

// Legacy user counts by role
$legacyUserCounts = ['artists'=>0,'labels'=>0,'venues'=>0,'stations'=>0];
if ($pdoPrimary) {
  try {
    $st = $pdoPrimary->query("SELECT role_id, COUNT(*) as cnt FROM users GROUP BY role_id");
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $rid = (int)($r['role_id'] ?? 0);
      $cnt = (int)($r['cnt'] ?? 0);
      if ($rid === 3) $legacyUserCounts['artists'] = $cnt;
      if ($rid === 7) $legacyUserCounts['labels'] = $cnt;
      if (in_array($rid, [5,17])) $legacyUserCounts['venues'] += $cnt;
      if (in_array($rid, [4,15])) $legacyUserCounts['stations'] += $cnt;
    }
  } catch (\Throwable $e) {}
}

// Load current workflow from progress.json
$progressJson = null;
$currentWorkflow = null;
$progressPath = $root.'/storage/plan/progress.json';
if (is_file($progressPath)) {
  try {
    $progressJson = json_decode(file_get_contents($progressPath), true);
    $currentWorkflow = $progressJson['current_workflow'] ?? null;
  } catch (\Throwable $e) {}
}

// Analytics & pixels
$gaId = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixel = getenv('META_PIXEL_ID') ?: '';
$tiktokPixel = getenv('TIKTOK_PIXEL_ID') ?: '';
$hasGA4 = ($gaId !== '') || _file_has($headPartial, 'gtag(') || _file_has($headPartial, 'googletagmanager');
$hasMetaPixel = ($metaPixel !== '') || _file_has($headPartial, 'fbq(');
$hasTikTok = ($tiktokPixel !== '') || _file_has($headPartial, 'ttq(');

// Platform analytics integrations
$spotifyClientId = getenv('SPOTIFY_CLIENT_ID') ?: '';
$metaAppId = getenv('META_APP_ID') ?: '';
$tiktokClientKey = getenv('TIKTOK_CLIENT_KEY') ?: '';
$hasSpotifyApi = !empty($spotifyClientId);
$hasMetaApi = !empty($metaAppId);
$hasTikTokApi = !empty($tiktokClientKey);

// Subscription/Commerce
$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: '';
$hasStripe = !empty($stripeSecretKey);
$hasSubscriptionTiers = $hasTable($pdoPrimary, 'ngn_2025', 'subscription_tiers');
$hasUserSubscriptions = $hasTable($pdoPrimary, 'ngn_2025', 'user_subscriptions');
$hasOAuthTokens = $hasTable($pdoPrimary, 'ngn_2025', 'oauth_tokens');
$hasAnalyticsSnapshots = $hasTable($pdoPrimary, 'ngn_2025', 'analytics_snapshots');

// Rollout status
$publicRollout = $cfg->featurePublicRollout();
$rolloutPct = $cfg->rolloutPercentage();
$maintenanceMode = $cfg->maintenanceMode();
$publicViewMode = $cfg->publicViewMode();

// API endpoint presence probes (string search)
$apiHasPosts = _file_has($apiPath, "/posts");
$apiHasVideos = _file_has($apiPath, "/videos");
$apiHasLibrary = (_file_has($apiPath, "/me/favorites") || _file_has($apiPath, "/me/follows") || _file_has($apiPath, "/me/playlists") || _file_has($apiPath, "/me/history"));
$apiHasSmrCharts = _file_has($apiPath, "/smr");
$apiHasRankingsLabels = _file_has($apiPath, "/rankings/labels");
$apiHasSubscriptions = _file_has($apiPath, "/subscription");
$apiHasCheckout = _file_has($apiPath, "/checkout");

// UI view presence
$uiHasPosts = _file_has($landingPath, '?view=posts');
$uiHasVideos = _file_has($landingPath, '?view=videos');

$rolesList = [
  ['slug'=>'admin','title'=>'Administrator'],
  ['slug'=>'artist','title'=>'Artist'],
  ['slug'=>'label','title'=>'Label'],
  ['slug'=>'station','title'=>'Station'],
  ['slug'=>'venue','title'=>'Venue'],
  ['slug'=>'writer','title'=>'Writer'],
  ['slug'=>'editor','title'=>'Editor'],
  ['slug'=>'contributor','title'=>'Contributor'],
  ['slug'=>'moderator','title'=>'Moderator'],
  ['slug'=>'advertiser','title'=>'Advertiser'],
];

// Minimal role checks (presence-based)
$roleChecks = [
  'artist' => [ ['Landing tab', $hasArtistsTab], ['DB table', $exists['ngn_2025.artists']], ['API route', _file_has($apiPath,'/artists')], ['Data count', ($counts2025['artists']??0) > 0] ],
  'label'  => [ ['Landing tab', $hasLabelsTab],  ['DB table', $exists['ngn_2025.labels']],  ['API route', _file_has($apiPath,'/labels')], ['Data count', ($counts2025['labels']??0) > 0] ],
  'station'=> [ ['Landing tab', $hasStationsTab],['DB table', $exists['ngn_2025.stations']],['API route', _file_has($apiPath,'/stations')], ['Data count', ($counts2025['stations']??0) > 0] ],
  'venue'  => [ ['DB table', $exists['ngn_2025.venues']], ['API route', _file_has($apiPath,'/venues')], ['Data count', ($counts2025['venues']??0) > 0] ],
  'writer' => [ ['DB table', $exists['ngn_2025.writers']] ],
  'admin'  => [ ['Update‑DB console', true], ['Progress page', true] ],
];

$pageTitle = 'Live Progress';
$currentPage = 'progress';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

    <main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
      <!-- Status Row -->
      <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3 bg-white/70 dark:bg-white/5"><div class="text-xs text-gray-500">API Status</div><pre id="apiStatus" class="text-xs">Loading…</pre></div>
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3 bg-white/70 dark:bg-white/5"><div class="text-xs text-gray-500">Counters</div><pre id="counters" class="text-xs">—</pre></div>
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3 bg-white/70 dark:bg-white/5"><div class="text-xs text-gray-500">Environment</div><pre id="envBlock" class="text-xs">—</pre></div>
      </section>

      <!-- Current Workflow Tasks (from progress.json) - PRIMARY TASK LIST -->
      <?php if ($currentWorkflow):
        $tasks = $currentWorkflow['tasks'] ?? [];
        $doneCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'done'));
        $totalCount = count($tasks);
        $pendingHigh = array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done' && ($t['priority'] ?? '') === 'high');
        $pendingMed = array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done' && ($t['priority'] ?? '') === 'medium');
        $pendingLow = array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done' && ($t['priority'] ?? 'low') === 'low' && ($t['status'] ?? '') !== 'done');

        // Group by category
        $categories = [];
        foreach ($tasks as $t) {
          $cat = $t['category'] ?? 'other';
          if (!isset($categories[$cat])) $categories[$cat] = ['done'=>0,'pending'=>0,'tasks'=>[]];
          $categories[$cat]['tasks'][] = $t;
          if (($t['status'] ?? '') === 'done') $categories[$cat]['done']++;
          else $categories[$cat]['pending']++;
        }
        $catLabels = [
          'schema' => '🗄️ Schema/DB',
          'etl' => '🔄 ETL Migrations',
          'verify' => '✅ Verification',
          'rankings' => '📊 Rankings',
          'smr' => '📻 SMR Data',
          'api' => '🔌 API Endpoints',
          'frontend' => '🖥️ Frontend/Landing',
          'feature' => '⭐ Features',
          'analytics' => '📈 Analytics',
          'admin' => '⚙️ Admin',
          'other' => '📦 Other'
        ];
      ?>
      <section class="rounded-lg border-2 border-amber-400 dark:border-amber-500/50 p-4 bg-amber-50/80 dark:bg-amber-500/10">
        <div class="flex items-center justify-between mb-4">
          <div>
            <div class="text-lg font-bold text-amber-900 dark:text-amber-100"><?= htmlspecialchars($currentWorkflow['title'] ?? 'Current Workflow') ?></div>
            <?php if (!empty($currentWorkflow['description'])): ?>
            <div class="text-sm text-amber-700 dark:text-amber-300 mt-1"><?= htmlspecialchars($currentWorkflow['description']) ?></div>
            <?php endif; ?>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold text-amber-800 dark:text-amber-200"><?= $doneCount ?>/<?= $totalCount ?></div>
            <div class="text-xs text-amber-600 dark:text-amber-400">tasks complete (<?= $totalCount > 0 ? round(($doneCount/$totalCount)*100) : 0 ?>%)</div>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="w-full bg-amber-200 dark:bg-amber-900/50 rounded h-3 mb-4">
          <div class="h-3 bg-emerald-500 rounded transition-all" style="width:<?= $totalCount > 0 ? round(($doneCount/$totalCount)*100) : 0 ?>%"></div>
        </div>

        <!-- Category summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-4">
          <?php foreach ($categories as $cat => $data):
            $pct = ($data['done'] + $data['pending']) > 0 ? round(($data['done'] / ($data['done'] + $data['pending'])) * 100) : 0;
            $color = $pct >= 100 ? 'emerald' : ($pct > 0 ? 'amber' : 'rose');
          ?>
          <div class="rounded border border-gray-200 dark:border-white/10 p-2 text-center bg-white/50 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400"><?= $catLabels[$cat] ?? $cat ?></div>
            <div class="text-sm font-bold text-<?= $color ?>-600 dark:text-<?= $color ?>-400"><?= $data['done'] ?>/<?= $data['done'] + $data['pending'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Priority sections -->
        <?php if (count($pendingHigh) > 0): ?>
        <div class="mb-4">
          <div class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wide mb-2">🔴 High Priority (<?= count($pendingHigh) ?> remaining)</div>
          <div class="space-y-2">
            <?php foreach ($pendingHigh as $task): ?>
            <div class="flex items-start gap-3 p-2 rounded bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
              <span class="text-rose-500 mt-0.5">○</span>
              <div class="flex-1">
                <div class="font-medium text-rose-900 dark:text-rose-100">
                  <?= htmlspecialchars($task['title'] ?? '') ?>
                  <span class="text-xs text-rose-400 ml-2">[<?= $task['category'] ?? 'other' ?>]</span>
                </div>
                <?php if (!empty($task['notes'])): ?><div class="text-xs text-rose-600 dark:text-rose-300 mt-1"><?= htmlspecialchars($task['notes']) ?></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (count($pendingMed) > 0): ?>
        <details class="mb-4" open>
          <summary class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wide mb-2 cursor-pointer">🟡 Medium Priority (<?= count($pendingMed) ?> remaining)</summary>
          <div class="space-y-2 mt-2">
            <?php foreach ($pendingMed as $task): ?>
            <div class="flex items-start gap-3 p-2 rounded bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30">
              <span class="text-amber-500 mt-0.5">○</span>
              <div class="flex-1">
                <div class="font-medium text-amber-900 dark:text-amber-100">
                  <?= htmlspecialchars($task['title'] ?? '') ?>
                  <span class="text-xs text-amber-400 ml-2">[<?= $task['category'] ?? 'other' ?>]</span>
                </div>
                <?php if (!empty($task['notes'])): ?><div class="text-xs text-amber-600 dark:text-amber-300 mt-1"><?= htmlspecialchars($task['notes']) ?></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <?php
        $pendingOther = array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done' && !in_array($t['priority'] ?? '', ['high','medium']));
        if (count($pendingOther) > 0): ?>
        <details class="mb-4">
          <summary class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide cursor-pointer">⚪ Low Priority (<?= count($pendingOther) ?> remaining)</summary>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
            <?php foreach ($pendingOther as $task): ?>
            <div class="flex items-start gap-2 p-2 rounded bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10">
              <span class="text-gray-400 mt-0.5">○</span>
              <div class="text-sm">
                <?= htmlspecialchars($task['title'] ?? '') ?>
                <span class="text-xs text-gray-400 ml-1">[<?= $task['category'] ?? 'other' ?>]</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <!-- Completed tasks (collapsed) -->
        <?php $doneTasks = array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'done'); ?>
        <?php if (count($doneTasks) > 0): ?>
        <details class="mt-4">
          <summary class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide cursor-pointer">✅ Completed (<?= count($doneTasks) ?>)</summary>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-1 mt-2">
            <?php foreach ($doneTasks as $task): ?>
            <div class="flex items-center gap-2 py-1 text-sm text-gray-500 dark:text-gray-400 line-through opacity-70">
              <span class="text-emerald-500">✓</span>
              <span><?= htmlspecialchars($task['title'] ?? '') ?></span>
              <?php if (!empty($task['completed_at'])): ?><span class="text-xs">(<?= $task['completed_at'] ?>)</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

        <div class="mt-4 pt-3 border-t border-amber-200 dark:border-amber-500/30 flex items-center justify-between text-xs text-amber-600 dark:text-amber-400">
          <span>Source: storage/plan/progress.json</span>
          <span>Version <?= $progressJson['version'] ?? '?' ?> • Updated: <?= $progressJson['updated_at'] ?? 'unknown' ?></span>
        </div>
      </section>
      <?php endif; ?>

      <!-- Entity Migration Status -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-3">Entity Migration Status</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
          <?php
          $entities = ['artists','labels','venues','stations'];
          foreach ($entities as $ent):
            $legacy = $legacyUserCounts[$ent] ?? 0;
            $migrated = $counts2025[$ent] ?? 0;
            $pct = $legacy > 0 ? round(($migrated / $legacy) * 100) : 0;
            $color = $pct >= 100 ? 'emerald' : ($pct > 0 ? 'amber' : 'rose');
          ?>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-xs text-gray-500 uppercase"><?= ucfirst($ent) ?></div>
            <div class="text-lg font-semibold"><?= $migrated ?> / <?= $legacy ?></div>
            <div class="w-full bg-gray-200 dark:bg-white/10 rounded h-1.5 mt-1">
              <div class="h-1.5 bg-<?= $color ?>-500 rounded" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="text-xs text-<?= $color ?>-600 dark:text-<?= $color ?>-400 mt-1"><?= $pct ?>% migrated</div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Rankings & SMR Status -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-3">Rankings & SMR Data</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">Ranking Windows</td>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">
                    <?php if ($rankingWindowsCount === null): ?><span class="text-rose-400">DB unavailable</span>
                    <?php elseif ($rankingWindowsCount === 0): ?><span class="text-rose-400">0 (no rankings computed)</span>
                    <?php else: ?><span class="text-emerald-500"><?= $rankingWindowsCount ?> windows</span><?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">Ranking Items</td>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">
                    <?php if ($rankingItemsCount === null): ?><span class="text-rose-400">DB unavailable</span>
                    <?php elseif ($rankingItemsCount === 0): ?><span class="text-rose-400">0 (no rankings)</span>
                    <?php else: ?><span class="text-emerald-500"><?= number_format($rankingItemsCount) ?> items</span><?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">SMR ChartData</td>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">
                    <?php if ($smrChartDataCount === null): ?><span class="text-rose-400">DB unavailable</span>
                    <?php elseif ($smrChartDataCount === 0): ?><span class="text-rose-400">0 (no SMR data)</span>
                    <?php else: ?><span class="text-emerald-500"><?= number_format($smrChartDataCount) ?> rows</span><?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5">SMR Upload</td>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5"><a href="/admin/smr/" class="text-brand hover:underline">Open SMR Admin →</a></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Progress + Milestones -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-3">
          <div class="text-sm font-semibold flex items-center gap-2">
            <span>Roadmap Progress</span>
            <span id="fairnessBadge" class="hidden text-[11px] px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30">Fairness ✓</span>
            <span id="coverageBadge" class="hidden text-[11px] px-2 py-1 rounded-full border">Coverage</span>
            <span id="integrityBadge" class="hidden text-[11px] px-2 py-1 rounded-full border">Integrity</span>
          </div>
          <div id="progressPct" class="text-sm text-gray-500 dark:text-gray-400">0%</div>
        </div>
        <div class="w-full bg-gray-200/70 dark:bg-white/10 rounded h-2 overflow-hidden"><div id="progressBar" class="h-2 bg-brand" style="width:0%"></div></div>
        <div id="milestonesList" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-sm"></div>
      </section>

      <!-- Roles Checklist (auto from code/DB) -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="flex items-center justify-between mb-3">
          <div class="text-sm font-semibold">Roles Checklist</div>
          <a href="/docs/roadmap/ROADMAP.md" class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-white/10 hover:bg-gray-100 dark:hover:bg-white/10">Open ROADMAP.md</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <table class="w-full border-collapse">
              <thead><tr><th class="text-left border-b border-gray-200 dark:border-white/10 py-1">Role</th><th class="text-left border-b border-gray-200 dark:border-white/10 py-1">Done</th></tr></thead>
              <tbody>
              <?php foreach ($rolesList as $r): $slug=$r['slug']; $title=$r['title']; $checks=$roleChecks[$slug] ?? []; $done=0; $total=count($checks); foreach ($checks as $c){ if(!empty($c[1])) $done++; } ?>
                <tr>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5"><?=htmlspecialchars($title)?> <span class="text-xs text-gray-500">(<?=$slug?>)</span></td>
                  <td class="py-1 border-b border-gray-100 dark:border-white/5"><strong><?=$done?>/<?=$total?></strong></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <table class="w-full border-collapse">
              <thead><tr><th class="text-left border-b border-gray-200 dark:border-white/10 py-1">Item</th><th class="text-left border-b border-gray-200 dark:border-white/10 py-1">State</th></tr></thead>
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Artists</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasArtistsTab ? '<span class="text-emerald-500">ok</span>' : '<span class="text-rose-400">missing</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Labels</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasLabelsTab ? '<span class="text-emerald-500">ok</span>' : '<span class="text-rose-400">missing</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Stations</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasStationsTab ? '<span class="text-emerald-500">ok</span>' : '<span class="text-rose-400">missing</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Charts</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasChartsTab ? '<span class="text-emerald-500">ok</span>' : '<span class="text-rose-400">missing</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: SMR</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasSMRTab ? '<span class="text-emerald-500">present</span>' : '<span class="text-rose-400">missing</span>' ?></td></tr>
                <?php foreach ($exists as $k=>$v): ?>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5"><?=htmlspecialchars($k)?></td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $v ? '<span class="text-emerald-500">exists</span>' : '<span class="text-rose-400">absent</span>' ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Analytics & Insights -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Analytics & Insights</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">GA4</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $hasGA4?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Meta Pixel</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $hasMetaPixel?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">TikTok Pixel</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $hasTikTok?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Playback Events table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.playback_events']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">History table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.history']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Recent playback events</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo (is_int($counts2025['playback_events']??null) && ($counts2025['playback_events']>0))?'<span class="text-emerald-500">'.(int)$counts2025['playback_events'].'</span>':'<span class="text-rose-400">0</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Platform Integrations & Subscriptions -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Platform Integrations & Subscriptions</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
          <div>
            <div class="text-xs text-gray-500 uppercase mb-2">Analytics APIs</div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Spotify API</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasSpotifyApi?'<span class="text-emerald-500">configured</span>':'<span class="text-rose-400">not set</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Meta/FB API</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasMetaApi?'<span class="text-emerald-500">configured</span>':'<span class="text-rose-400">not set</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">TikTok API</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasTikTokApi?'<span class="text-emerald-500">configured</span>':'<span class="text-rose-400">not set</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">OAuth Tokens table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasOAuthTokens?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Analytics Snapshots</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasAnalyticsSnapshots?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>' ?></td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <div class="text-xs text-gray-500 uppercase mb-2">Commerce</div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Stripe</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasStripe?'<span class="text-emerald-500">configured</span>':'<span class="text-rose-400">not set</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Subscription Tiers</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasSubscriptionTiers?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">User Subscriptions</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $hasUserSubscriptions?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /subscription</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $apiHasSubscriptions?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /checkout</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $apiHasCheckout?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>' ?></td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <div class="text-xs text-gray-500 uppercase mb-2">Rollout Status</div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Maintenance Mode</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $maintenanceMode?'<span class="text-amber-500">ON</span>':'<span class="text-emerald-500">OFF</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Public View Mode</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><span class="text-brand"><?= htmlspecialchars($publicViewMode) ?></span></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Public Rollout</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?= $publicRollout?'<span class="text-emerald-500">enabled</span>':'<span class="text-gray-400">disabled</span>' ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Rollout %</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><span class="font-mono"><?= $rolloutPct ?>%</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Editorial & Ads readiness -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Editorial & Ads</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Posts table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.posts']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Videos table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.videos']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Media assets table</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.media_assets']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /posts</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $apiHasPosts?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /videos</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $apiHasVideos?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Posts view</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $uiHasPosts?'<span class="text-emerald-500">ok</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Landing: Videos view</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $uiHasVideos?'<span class="text-emerald-500">ok</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Library & Playback readiness -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Library & Playback</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Favorites</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.favorites']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Follows</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.follows']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">History</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.history']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Playlists</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.playlists']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">Playlist items</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $exists['ngn_2025.playlist_items']?'<span class="text-emerald-500">exists</span>':'<span class="text-rose-400">absent</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
          <div>
            <table class="w-full border-collapse">
              <tbody>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /me/* (library)</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $apiHasLibrary?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">API: /rankings/labels</td><td class="py-1 border-b border-gray-100 dark:border-white/5"><?php echo $apiHasRankingsLabels?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark:border-white/5">SMR Charts API/Bridge</td><td class="py-1 border-b border-gray-100 dark-border-white/5"><?php echo $apiHasSmrCharts?'<span class="text-emerald-500">present</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
                <tr><td class="py-1 border-b border-gray-100 dark-border-white/5">Rankings columns (prev_rank/flags)</td><td class="py-1 border-b border-gray-100 dark-border-white/5"><?php echo ($hasRankingPrevRank&&$hasRankingFlags)?'<span class="text-emerald-500">ok</span>':'<span class="text-rose-400">missing</span>'; ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Feature Flags -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Feature Flags</div>
        <div id="flagsList" class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm"></div>
      </section>

      <!-- Quick Actions -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Quick Actions</div>
        <div class="flex flex-wrap gap-2 mb-3">
          <button id="qaSync24h" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Sync 24h</button>
          <button id="qaLink7d" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Link last 7 days</button>
          <button id="qaComputeLastWeek" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Compute last full week</button>
          <button id="qaVerify8w" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Verify completeness 8w</button>
          <button id="qaOneClickWeekly" class="px-3 h-9 rounded bg-brand text-white text-sm">One‑Click Weekly</button>
          <button id="qaOneClickHistory" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">OCW History</button>
        </div>
        <pre id="qaOut" class="text-xs">—</pre>
      </section>

      <!-- ETL: Spins Backfill -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Spins Backfill</div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2 text-sm">
          <input id="spinsSince" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="spinsUntil" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="spinsBatch" type="number" value="1000" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="spinsConn" type="text" value="ngnspins" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="spinsTable" type="text" value="spins" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <label class="inline-flex items-center gap-2 text-xs"><input id="spinsDryRun" type="checkbox" class="scale-90" /> Dry run</label>
        </div>
        <div class="flex gap-2 mb-2">
          <button id="btnSpinsDry" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Dry‑run</button>
          <button id="btnSpinsRun" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Run</button>
        </div>
        <pre id="spinsOut" class="text-xs">—</pre>
      </section>

      <!-- Weekly Chart Run -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Weekly Chart Run</div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2 text-sm">
          <input id="chartSlug" type="text" value="ngn:artists:weekly" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartIso" type="text" placeholder="ISO week (e.g., 2025-W45)" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartStart" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartEnd" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <label class="inline-flex items-center gap-2 text-xs"><input id="chartDryRun" type="checkbox" checked class="scale-90" /> Dry run</label>
          <label class="inline-flex items-center gap-2 text-xs"><input id="chartCompleteEmpty" type="checkbox" class="scale-90" /> Complete empty</label>
        </div>
        <div class="flex gap-2 mb-2">
          <button id="btnChartDry" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Dry‑run</button>
          <button id="btnChartRun" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Run</button>
        </div>
        <pre id="chartOut" class="text-xs">—</pre>
      </section>

      <!-- Compute Weekly Chart (entries) -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Compute Weekly Chart (entries)</div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-2 text-sm">
          <input id="chartComputeSlug" type="text" value="ngn:artists:weekly" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartComputeIso" type="text" placeholder="ISO week" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartComputeStart" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="chartComputeEnd" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <button id="btnChartCompute" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Compute</button>
        </div>
        <pre id="chartComputeOut" class="text-xs">—</pre>
      </section>

      <!-- Latest Chart Run -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Latest Chart Run</div>
        <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
          <input id="latestRunSlug" type="text" value="ngn:artists:weekly" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <button id="btnLatestRunRefresh" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Refresh</button>
          <button id="btnLatestRunVerify" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Verify</button>
          <button id="btnLatestRunRebuild" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Rebuild</button>
          <button id="btnFairnessJson" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Fairness JSON</button>
          <button id="btnFixLatest" class="px-3 h-9 rounded bg-brand text-white text-sm">Fix latest (Link + Recompute)</button>
        </div>
        <div id="fairnessExcerpt" class="text-xs text-gray-600 dark:text-gray-300 mb-2 hidden"></div>
        <pre id="fairnessJson" class="text-xs hidden"></pre>
        <pre id="latestRunOut" class="text-xs">—</pre>
      </section>

      <!-- Link Spins by Name -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Link Spins by Name</div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-2 text-sm">
          <input id="linkSince" type="datetime-local" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="linkUntil" type="datetime-local" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="linkLimit" type="number" value="5000" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <label class="inline-flex items-center gap-2 text-xs"><input id="linkDryRun" type="checkbox" class="scale-90" /> Dry run</label>
          <div class="flex gap-2">
            <button id="btnLinkDry" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Dry‑run</button>
            <button id="btnLinkRun" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Run</button>
          </div>
        </div>
        <pre id="linkOut" class="text-xs">—</pre>
      </section>

      <!-- Spins Incremental Sync -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Spins Incremental Sync</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2 text-sm">
          <input id="incSince" type="datetime-local" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="incLimit" type="number" value="2000" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <button id="btnIncSync" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Run Incremental</button>
        </div>
        <pre id="incOut" class="text-xs">—</pre>
      </section>

      <!-- Verification Suite -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Verification Suite</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Scoring Coverage</div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
              <button id="btnVerifyScoringCoverage" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Check coverage</button>
            </div>
            <pre id="verifyScoringCoverageOut" class="text-xs">—</pre>
          </div>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Factor Integrity</div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
              <button id="btnVerifyFactorIntegrity" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Check integrity</button>
            </div>
            <pre id="verifyFactorIntegrityOut" class="text-xs">—</pre>
          </div>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Charts Completeness</div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
              <input id="compChart" type="text" value="ngn:artists:weekly" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <input id="compFrom" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <input id="compTo" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <button id="btnVerifyCompleteness" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Verify</button>
            </div>
            <pre id="verifyCompletenessOut" class="text-xs">—</pre>
          </div>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Backups</div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-sm">
              <input id="bVerifyWorld" type="text" value="primary" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <button id="btnBackupVerifyLatest" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Verify latest</button>
              <button id="btnBackupRefresh" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Refresh list</button>
              <button id="btnVerifyBackupsAll" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Verify all (latest)</button>
            </div>
            <div id="backupList" class="text-xs mb-2">—</div>
            <pre id="backupVerifyOut" class="text-xs">—</pre>
            <pre id="verifyBackupsAllOut" class="text-xs">—</pre>
          </div>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Spins Parity</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-2 text-sm">
              <input id="vSpinsSince" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <input id="vSpinsUntil" type="date" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <input id="vSpinsConn" type="text" value="ngnspins" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
              <input id="vSpinsTable" type="text" value="spins" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
            </div>
            <button id="btnVerifySpins" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Verify</button>
            <pre id="verifySpinsOut" class="text-xs mt-2">—</pre>
          </div>
          <div class="rounded border border-gray-200 dark:border-white/10 p-3">
            <div class="text-sm font-semibold mb-2">Migrations · Schemas · Identity · CDM</div>
            <div class="flex flex-wrap gap-2 mb-2">
              <button id="btnVerifyMigrations" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Migrations</button>
              <button id="btnVerifySchemas" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Schemas</button>
              <button id="btnVerifyIdentityEmail" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Identity Email</button>
              <button id="btnVerifyCdm" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">CDM</button>
            </div>
            <pre id="verifyMigrationsOut" class="text-xs">—</pre>
            <pre id="verifySchemasOut" class="text-xs">—</pre>
            <pre id="verifyIdentityEmailOut" class="text-xs">—</pre>
            <pre id="verifyCdmOut" class="text-xs">—</pre>
          </div>
        </div>
      </section>

      <!-- Backups: Create (optional) -->
      <section class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5">
        <div class="text-sm font-semibold mb-2">Create Backups</div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-2 text-sm">
          <input id="bWorlds" type="text" value="primary" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <label class="inline-flex items-center gap-2 text-xs"><input id="bSchemaOnly" type="checkbox" class="scale-90" /> Schema only</label>
          <input id="bBatch" type="number" value="1000" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <input id="bCap" type="number" value="0" class="rounded border px-2 py-1 bg-white dark:bg-transparent dark:border-white/10" />
          <label class="inline-flex items-center gap-2 text-xs"><input id="bDryRun" type="checkbox" class="scale-90" /> Dry run</label>
          <div class="flex gap-2">
            <button id="btnBackupDry" class="px-3 h-9 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-sm">Dry‑run</button>
            <button id="btnBackupRun" class="px-3 h-9 rounded bg-gray-900 text-white dark:bg-white dark:text-gray-900 text-sm">Run</button>
          </div>
        </div>
        <pre id="backupOut" class="text-xs">—</pre>
      </section>

    </main>

    <!-- Milestone Details Modal (read-only, a11y-friendly) -->
    <div id="milestoneModal" class="hidden opacity-0 fixed inset-0 z-50 transition-opacity duration-150" aria-hidden="true">
      <div class="absolute inset-0 bg-black/40" onclick="(function(){ if (typeof closeMilestoneModal==='function') closeMilestoneModal(); })()" aria-hidden="true"></div>
      <div class="absolute inset-0 flex items-center justify-center p-4">
        <div id="milestoneModalDialog" role="dialog" aria-modal="true" aria-labelledby="milestoneModalTitle" tabindex="-1" class="w-full max-w-lg rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-[#0b1020] shadow-lg focus:outline-none">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-white/10">
            <div id="milestoneModalTitle" class="flex items-center gap-2 text-sm font-semibold">Milestone Details</div>
            <button class="px-2 h-8 rounded bg-gray-200 text-gray-900 dark:bg-white/10 dark:text-gray-100 text-xs" onclick="(function(){ if (typeof closeMilestoneModal==='function') closeMilestoneModal(); })()" aria-label="Close details">Close</button>
          </div>
          <div id="milestoneModalBody" class="p-4 text-sm"></div>
        </div>
      </div>
    </div>
  </div>

<?php include __DIR__.'/_token_store.php'; ?>
  <script>
    // Small icon helpers (Heroicons inline)
    const icons = {
      flag: (cls='w-4 h-4')=>`<svg class="${cls}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.75 3A.75.75 0 0 0 3 3.75v16.5a.75.75 0 1 0 1.5 0V14.5c1.239-.826 2.73-1.25 4.25-1.25 1.52 0 3.011.424 4.25 1.25 1.239-.826 2.73-1.25 4.25-1.25.837 0 1.665.118 2.45.35a.75.75 0 0 0 .95-.72V4.75a.75.75 0 0 0-.95-.72A10.52 10.52 0 0 1 17.25 4c-1.52 0-3.011.424-4.25 1.25C11.761 4.424 10.27 4 8.75 4c-1.52 0-3.011.424-4.25 1.25V3.75A.75.75 0 0 0 3.75 3z"/></svg>`,
      check: (cls='w-4 h-4')=>`<svg class="${cls}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 12.75 6.75 10.5a.75.75 0 1 0-1.06 1.06l3 3a.75.75 0 0 0 1.06 0l7.5-7.5a.75.75 0 0 0-1.06-1.06L9 12.75z"/></svg>`
    };

    // Global verification badge cache
    window.__badge = { fairness: null, coverage: null, integrity: null };

    // API helpers
    const api = p => `/api/v1${p}`;
    const token = () => localStorage.getItem('ngn_admin_token') || localStorage.getItem('admin_token') || '';
    async function http(path, opts={}){
      const res = await fetch(api(path), Object.assign({ headers: { 'Authorization': token()?('Bearer '+token()):'' } }, opts));
      let text = '';
      try { text = await res.text(); } catch(e) { text = ''; }
      let json = null;
      try { if (text) { const tmp = JSON.parse(text); if (tmp && typeof tmp === 'object') json = tmp; } } catch(e) { json = null; }
      return { status: res.status, json, text, res };
    }
    async function post(path, body){
      return http(path, { method:'POST', headers: { 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify(body||{}) });
    }

    // Date helpers for completeness windows (use last FULL week end to avoid partial week false negatives)
    function fmtDateUTC(d){ return d.toISOString().slice(0,10); }
    function todayUTC(){ const n=new Date(); return new Date(Date.UTC(n.getUTCFullYear(), n.getUTCMonth(), n.getUTCDate())); }
    function lastSundayUTC(){ const d=todayUTC(); const dow=d.getUTCDay(); // 0=Sun
      const out=new Date(d.getTime()); out.setUTCDate(out.getUTCDate() - dow); return out; }
    function lastFullWeekEndUTC(){ // last Sunday
      return lastSundayUTC();
    }

    // Progress poller with backoff
    let pollMs = 15000; let backoffUntil = 0; let backoffMs = 60000; const backoffMax = 300000;
    async function refreshProgress(){
      const now = Date.now();
      if (now < backoffUntil) return;
      const r = await http('/admin/progress');
      if (r.status === 429) {
        // Exponential backoff with jitter
        backoffMs = Math.min(backoffMs * 2, backoffMax);
        const jitter = Math.floor(backoffMs * (0.5 + Math.random()));
        backoffUntil = now + jitter;
        const secs = Math.ceil(jitter/1000);
        document.getElementById('ts').textContent = `Polling paused (rate limited)… resuming in ~${secs}s`;
        return;
      }
      // Reset backoff on success or non-429
      backoffMs = 60000; backoffUntil = 0;
      if (r.status === 200) {
        const d = r.json?.data || {};
        // API status + env
        document.getElementById('apiStatus').textContent = JSON.stringify({ version:d.version, time:d.time }, null, 2);
        document.getElementById('envBlock').textContent = JSON.stringify({ env_path:d.env_path, env_mtime:d.env_mtime }, null, 2);
        document.getElementById('counters').textContent = JSON.stringify({ posts_count:d.posts_count }, null, 2);
        // Progress bar
        const pct = Math.max(0, Math.min(100, parseInt(d.percent_complete||0,10)));
        document.getElementById('progressPct').textContent = pct+'%';
        document.getElementById('progressBar').style.width = pct+'%';
        // Public view mode (canary helper)
        try {
          const vmb = document.getElementById('viewModeBlock');
          const vmt = document.getElementById('vmText');
          if (d.public_view_mode || d.view_mode_effective) {
            if (vmb) vmb.classList.remove('hidden');
            if (vmt) vmt.textContent = 'View: '+(d.view_mode_effective||d.public_view_mode);
          }
        } catch(e) { /* ignore */ }
        // Fairness badge
        const fb = document.getElementById('fairnessBadge');
        if (d.fairness_latest) { 
          fb.classList.remove('hidden'); 
          fb.textContent = 'Fairness '+(d.fairness_latest.ok? '✓':'!'); 
          try { window.__badge.fairness = !!d.fairness_latest.ok; } catch(e){}
        }
        // Flags
        const flags = d.flags||{}; const fl = document.getElementById('flagsList'); fl.innerHTML='';
        Object.keys(flags).sort().forEach(k=>{
          const on = !!flags[k];
          const chip = document.createElement('span');
          chip.className = 'inline-flex items-center px-2 py-1 rounded-full text-[11px] border ' + (on ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30' : 'bg-gray-200 text-gray-700 border-gray-300 dark:bg-white/10 dark:text-gray-300 dark:border-white/10');
          chip.innerHTML = (on? icons.check('w-3 h-3 mr-1'):'') + k;
          fl.appendChild(chip);
        });
        // Milestones
        const list = document.getElementById('milestonesList'); list.innerHTML='';
        const b = (function(){ try { return window.__badge || {}; } catch(e){ return {}; } })();
        const badgeHtml = (ok,label)=>{
          const clsOk = 'inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] border bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30';
          const clsWarn = 'inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] border bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30';
          return `<span class="${ok?clsOk:clsWarn}" title="${label}">${label} ${ok?'✓':'!'}</span>`;
        };
        (d.milestones||[]).forEach(m=>{
          const row = document.createElement('div');
          const badgeBase = 'inline-flex items-center px-2 py-1 rounded-full text-[11px] border ';
          const badge = m.status==='done' ? badgeBase+'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30' : (m.status==='in_progress' ? badgeBase+'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30' : badgeBase+'bg-gray-200 text-gray-700 border-gray-300 dark:bg-white/10 dark:text-gray-300 dark:border-white/10');
          let extra = '';
          const titleLc = (m.title||'').toLowerCase();
          if (titleLc.includes('scoring') || titleLc.includes('fairness')) {
            if (b.coverage!=null) extra += ' '+badgeHtml(!!b.coverage, 'Coverage');
            if (b.integrity!=null) extra += ' '+badgeHtml(!!b.integrity, 'Integrity');
            if (b.fairness!=null) extra += ' '+badgeHtml(!!b.fairness, 'Fairness');
          }
          const subt = (m.meta && m.meta.present!=null && m.meta.total!=null) 
            ? `<div class="text-[11px] text-gray-500 dark:text-gray-400">${m.meta.present}/${m.meta.total} tasks · ${m.meta.epic||''}${extra? ' · '+extra: ''}</div>` 
            : `<div class="text-[11px] text-gray-500 dark:text-gray-400">${m.meta?.epic||''}${extra? ' · '+extra: ''}</div>`;
          row.className='p-2 rounded border border-gray-200 dark:border-white/10 bg-white/70 dark:bg-white/5 flex items-center justify-between gap-3';
          row.innerHTML = `<div class="flex items-center gap-2"><span class="text-brand">${icons.flag()}</span><div><div class="text-sm font-medium">${m.title||m.key}</div>${subt}</div></div><span class="${badge}">${(m.status||'pending').replace('_',' ')}</span>`;
          list.appendChild(row);
        });
      }
      document.getElementById('ts').textContent = new Date().toLocaleString();
    }
    setInterval(refreshProgress, pollMs);
    refreshProgress();

    // Governance docs links (pull once from /admin/roadmap)
    (async function(){
      try {
        const r = await http('/admin/roadmap');
        if (r.status === 200) {
          const docs = r.json?.data?.docs || {};
          const items = [];
          if (docs.fairness) items.push(`<a href="${docs.fairness}" class="underline" target="_blank">Fairness</a>`);
          if (docs.scoring) items.push(`<a href="${docs.scoring}" class="underline" target="_blank">Scoring</a>`);
          if (docs.acceptance) items.push(`<a href="${docs.acceptance}" class="underline" target="_blank">Acceptance</a>`);
          if (docs.factors) items.push(`<a href="${docs.factors}" class="underline" target="_blank">Factors.json</a>`);
          if (docs.cron) items.push(`<a href="${docs.cron}" class="underline" target="_blank">Cron</a>`);
          const el = document.getElementById('docsLinksLP');
          if (el && items.length) { el.innerHTML = items.join(' · '); el.classList.remove('hidden'); }
          // Show 2.0 Preview button only when effective mode is next or admin (always admin here)
          try {
            const pv = document.getElementById('preview20');
            if (pv) pv.classList.remove('hidden');
          } catch(e) {}
        }
      } catch (e) { /* ignore */ }
    })();

    // Quick Actions
    function printQa(obj){ const out = document.getElementById('qaOut'); out.textContent = (typeof obj==='string')? obj : JSON.stringify(obj, null, 2); try { out.scrollTop = out.scrollHeight; } catch(e){} }
    document.getElementById('qaSync24h')?.addEventListener('click', async ()=>{
      printQa('Syncing last 24h…');
      const since = new Date(Date.now()-24*3600*1000).toISOString().slice(0,19).replace('T',' ');
      const r = await post('/admin/etl/spins/sync', { since, limit: 5000, dry_run: false });
      printQa(r.status===200 ? (r.json?.data||r.json) : { error:r.status, body:r.json });
    });
    document.getElementById('qaLink7d')?.addEventListener('click', async ()=>{
      printQa('Linking last 7 days…');
      const since = new Date(Date.now()-7*24*3600*1000).toISOString().slice(0,10)+' 00:00:00';
      const until = new Date().toISOString().slice(0,10)+' 23:59:59';
      const r = await post('/admin/etl/spins/link-names', { since, until, limit: 10000, dry_run: false });
      printQa(r.status===200 ? (r.json?.data||r.json) : { error:r.status, body:r.json });
    });
    document.getElementById('qaComputeLastWeek')?.addEventListener('click', async ()=>{
      printQa('Computing last full ISO week…');
      const r = await post('/admin/etl/charts/compute-week', { chart:'ngn:artists:weekly' });
      printQa(r.status===200 ? (r.json?.data||r.json) : { error:r.status, body:r.json });
    });
    document.getElementById('qaVerify8w')?.addEventListener('click', async ()=>{
      printQa('Verifying completeness (last 8 weeks)…');
      const from = new Date(Date.now()-8*7*24*3600*1000).toISOString().slice(0,10);
      const to = fmtDateUTC(lastFullWeekEndUTC());
      const r = await http(`/admin/verify/charts-completeness?chart=${encodeURIComponent('ngn:artists:weekly')}&from=${from}&to=${to}`);
      printQa(r.status===200 ? (r.json?.data||r.json) : { error:r.status, body:r.json });
    });
    // Removed duplicate basic handler for One‑Click Weekly (see enhanced handler below)

    // Incremental sync (simple helper bound to Spins Incremental Sync card)
    document.getElementById('btnIncSync')?.addEventListener('click', async ()=>{
      const out = document.getElementById('incOut'); if (out) out.textContent = 'Running incremental sync...';
      const since = (document.getElementById('incSince')?.value || '').replace('T',' ');
      const limit = parseInt(document.getElementById('incLimit')?.value || '2000', 10);
      try {
        const r = await fetch(api('/admin/etl/spins/sync'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token() ? ('Bearer '+token()) : '' }, body: JSON.stringify({ since, limit, dry_run: false }) });
        const ct = r.headers.get('content-type')||''; const json = ct.includes('application/json') ? await r.json() : null;
        if (out) out.textContent = r.status===200 ? JSON.stringify(json?.data || json, null, 2) : `Error ${r.status}: `+JSON.stringify(json,null,2);
      } catch(e){ if (out) out.textContent = 'Request failed: '+e.message; }
    });

    // ETL controls
    async function runSpins(dry){
      const since = document.getElementById('spinsSince')?.value;
      const until = document.getElementById('spinsUntil')?.value;
      const batch = parseInt(document.getElementById('spinsBatch')?.value||'1000', 10);
      const source_conn = document.getElementById('spinsConn')?.value || 'ngnspins';
      const source_table = document.getElementById('spinsTable')?.value || 'spins';
      const body = { since, until, batch, source_conn, source_table, dry_run: !!dry };
      const out = document.getElementById('spinsOut');
      out.textContent = 'Running...';
      try {
        const r = await post('/admin/etl/spins/backfill', body);
        if (r.status===200) {
          out.textContent = JSON.stringify(r.json?.data || r.json, null, 2);
        } else if (r.status===401 || r.status===403) {
          out.textContent = 'Authorization required. Open Settings once to mint an admin token.';
        } else {
          out.textContent = `Error ${r.status}: ` + JSON.stringify(r.json, null, 2);
        }
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    }
    document.getElementById('btnSpinsDry')?.addEventListener('click', ()=>{ runSpins(true); });
    document.getElementById('btnSpinsRun')?.addEventListener('click', ()=>{
      const manual = document.getElementById('spinsDryRun');
      const dry = manual ? manual.checked : false;
      runSpins(dry);
    });

    async function runChart(){
      const chart = document.getElementById('chartSlug')?.value || 'ngn:artists:weekly';
      const iso = document.getElementById('chartIso')?.value || '';
      const start = document.getElementById('chartStart')?.value;
      const end = document.getElementById('chartEnd')?.value;
      const dry_run = document.getElementById('chartDryRun')?.checked ?? true;
      const complete_empty = document.getElementById('chartCompleteEmpty')?.checked ?? false;
      const body = { chart, dry_run, complete_empty };
      if (iso) body.iso_week = iso; else { body.start = start; body.end = end; }
      const out = document.getElementById('chartOut');
      out.textContent = 'Running...';
      try {
        const r = await post('/admin/etl/charts/run-week', body);
        if (r.status===200) {
          out.textContent = JSON.stringify(r.json?.data || r.json, null, 2);
        } else if (r.status===401 || r.status===403) {
          out.textContent = 'Authorization required. Open Settings once to mint an admin token.';
        } else {
          out.textContent = `Error ${r.status}: ` + JSON.stringify(r.json, null, 2);
        }
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    }
    document.getElementById('btnChartDry')?.addEventListener('click', ()=>{ const chk=document.getElementById('chartDryRun'); if (chk) chk.checked=true; runChart(); });
    document.getElementById('btnChartRun')?.addEventListener('click', ()=>{ runChart(); });

    // Compute Weekly Chart (entries)
    async function runChartCompute(){
      const slug = document.getElementById('chartComputeSlug')?.value || 'ngn:artists:weekly';
      const iso = document.getElementById('chartComputeIso')?.value || '';
      const start = document.getElementById('chartComputeStart')?.value;
      const end = document.getElementById('chartComputeEnd')?.value;
      const body = { chart: slug };
      if (iso) body.iso_week = iso; else { body.start = start; body.end = end; }
      const out = document.getElementById('chartComputeOut'); out.textContent = 'Running...';
      try {
        const res = await fetch(api('/admin/etl/charts/compute-week'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': token() ? ('Bearer ' + token()) : '' }, body: JSON.stringify(body) });
        const ct = res.headers.get('content-type')||''; const json = ct.includes('application/json') ? await res.json() : null;
        if (res.status===200) { out.textContent = JSON.stringify(json?.data || json, null, 2); }
        else if (res.status===401 || res.status===403) { out.textContent = 'Authorization required. Open Settings once to mint an admin token.'; }
        else { out.textContent = `Error ${res.status}: ` + JSON.stringify(json, null, 2); }
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    }
    document.getElementById('btnChartCompute')?.addEventListener('click', runChartCompute);

    // Latest Chart Run widget
    async function refreshLatestRun(){
      const slug = document.getElementById('latestRunSlug')?.value || 'ngn:artists:weekly';
      const out = document.getElementById('latestRunOut'); out.textContent = 'Loading...';
      try {
        const res = await fetch(api('/admin/charts/latest-run?chart=') + encodeURIComponent(slug), { headers: { 'Authorization': token() ? ('Bearer ' + token()) : '' } });
        const ct = res.headers.get('content-type')||''; const json = ct.includes('application/json') ? await res.json() : null;
        if (res.status===200) {
          // Render raw latest run payload
          out.textContent = JSON.stringify(json?.data || json, null, 2);
          // Fairness excerpt (if available)
          try {
            const fx = document.getElementById('fairnessExcerpt');
            const fair = json?.data?.fairness_latest || null;
            if (fx) {
              if (fair) {
                const covS = (fair.coverage && fair.coverage.stations!=null) ? Math.round((fair.coverage.stations*100))+'%' : 'n/a';
                const covL = (fair.coverage && fair.coverage.linkage!=null) ? Math.round((fair.coverage.linkage*100))+'%' : 'n/a';
                const ok = fair.ok === true;
                const mix = fair.mix ? Object.entries(fair.mix).map(([k,v])=>`${k}:${(v*100).toFixed(0)}%`).join(' ') : '';
                fx.innerHTML = `Fairness — ${ok? 'OK':'Check'} · stations ${covS}, linkage ${covL}${mix? ' · mix '+mix:''}`;
                fx.classList.remove('hidden');
              } else {
                fx.classList.add('hidden');
                fx.textContent = '';
              }
            }
            // Attach full JSON for the optional viewer
            try { window.__fairnessLatest = json?.data?.fairness_latest || null; } catch(e) {}
          } catch(e) { /* ignore */ }
        }
        else if (res.status===401 || res.status===403) { out.textContent = 'Authorization required. Open Settings once to mint an admin token.'; }
        else { out.textContent = `Error ${res.status}: ` + JSON.stringify(json, null, 2); }
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    }
    document.getElementById('btnLatestRunRefresh')?.addEventListener('click', refreshLatestRun);
    document.getElementById('btnLatestRunVerify')?.addEventListener('click', async ()=>{
      const out = document.getElementById('latestRunOut'); out.textContent = 'Verifying latest run...';
      try {
        const slug = document.getElementById('latestRunSlug')?.value || 'ngn:artists:weekly';
        const lr = await fetch(api('/admin/charts/latest-run?chart=') + encodeURIComponent(slug), { headers: { 'Authorization': token() ? ('Bearer ' + token()) : '' } });
        const json = await lr.json();
        const run = json?.data?.run; const entries = json?.data?.entries || 0;
        if (!run) { out.textContent = 'No run found to verify.'; return; }
        const ok = (run.status === 'completed' && entries > 0);
        out.textContent = JSON.stringify({ ok, reason: ok? 'completed with entries' : 'status or entries check failed', run, entries }, null, 2);
      } catch(e){ out.textContent = 'Verify failed: ' + e.message; }
    });
    document.getElementById('btnLatestRunRebuild')?.addEventListener('click', async ()=>{
      const out = document.getElementById('latestRunOut'); out.textContent = 'Rebuilding latest run...';
      try {
        const slug = document.getElementById('latestRunSlug')?.value || 'ngn:artists:weekly';
        const r = await fetch(api('/admin/etl/charts/rebuild-latest-week'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token() ? ('Bearer '+token()) : '' }, body: JSON.stringify({ chart: slug }) });
        const ct = r.headers.get('content-type')||''; const json = ct.includes('application/json') ? await r.json() : null;
        if (r.status===200) out.textContent = JSON.stringify(json?.data || json, null, 2);
        else if (r.status===401 || r.status===403) out.textContent = 'Authorization required. Open Settings once to mint an admin token.';
        else out.textContent = `Error ${r.status}: ` + JSON.stringify(json, null, 2);
      } catch(e){ out.textContent = 'Rebuild failed: ' + e.message; }
    });

    // Show Fairness JSON
    document.getElementById('btnFairnessJson')?.addEventListener('click', async ()=>{
      const el = document.getElementById('fairnessJson');
      if (!el) return;
      let data = (function(){ try { return window.__fairnessLatest || null; } catch(e){ return null; } })();
      if (!data) {
        // Auto-refresh latest run to populate fairness cache
        try { await refreshLatestRun(); data = (function(){ try { return window.__fairnessLatest || null; } catch(e){ return null; } })(); } catch(e) {}
      }
      if (data) {
        el.textContent = JSON.stringify(data, null, 2);
        el.classList.remove('hidden');
      } else {
        el.textContent = 'No fairness summary available. Click Refresh first.';
        el.classList.remove('hidden');
      }
    });

    // Fix latest: Link names for last 7d, then rebuild latest week, then refresh widget
    document.getElementById('btnFixLatest')?.addEventListener('click', async ()=>{
      const out = document.getElementById('latestRunOut');
      if (out) out.textContent = 'Linking last 7 days, then rebuilding…';
      try {
        const since = new Date(Date.now()-7*24*3600*1000).toISOString().slice(0,10)+' 00:00:00';
        const until = new Date().toISOString().slice(0,10)+' 23:59:59';
        // Link names
        await fetch(api('/admin/etl/spins/link-names'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify({ since, until, limit: 20000, dry_run: false }) });
        // Rebuild
        const slug = document.getElementById('latestRunSlug')?.value || 'ngn:artists:weekly';
        await fetch(api('/admin/etl/charts/rebuild-latest-week'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify({ chart: slug }) });
        // Refresh display and fairness
        await refreshLatestRun();
      } catch(e) {
        if (out) out.textContent = 'Fix failed: '+(e?.message||e);
      }
    });

    // Backups helpers & handlers (manual refresh only)
    async function postAuth(path, body){ const r = await fetch(api(path), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify(body||{}) }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; return { status:r.status, json }; }
    async function getAuth(path){ const r = await fetch(api(path), { headers:{ 'Authorization': token()?('Bearer '+token()):'' }}); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; return { status:r.status, json }; }
    function renderBackupsList(container, data){ if (!container) return; container.innerHTML=''; const groups = data?.data?.db || []; if (!groups.length) { container.textContent='No backups found.'; return; } groups.forEach(g=>{ const wrap=document.createElement('div'); wrap.className='rounded border border-gray-200 dark:border-white/10 p-2'; const title=document.createElement('div'); title.className='font-semibold mb-1'; title.textContent=g.world; wrap.appendChild(title); (g.items||[]).forEach(it=>{ const row=document.createElement('div'); row.className='flex items-center justify-between gap-2 py-0.5'; const left=document.createElement('div'); left.textContent=`${it.file} · ${it.bytes||0} bytes`; const right=document.createElement('div'); const ts = it.mtime ? new Date(it.mtime*1000).toLocaleString() : ''; right.className='text-gray-500'; right.textContent=ts; row.appendChild(left); row.appendChild(right); wrap.appendChild(row); }); container.appendChild(wrap); }); }
    async function refreshBackups(){ const out=document.getElementById('backupList'); out.textContent='Loading...'; try { const r=await getAuth('/admin/backups/status'); if (r.status===200) renderBackupsList(out, r.json); else if (r.status===401||r.status===403) out.textContent='Authorization required. Open Settings once to mint an admin token.'; else out.textContent='Error '+r.status; } catch(e){ out.textContent='Request failed: '+e.message; } }
    document.getElementById('btnBackupRefresh')?.addEventListener('click', refreshBackups);
    document.getElementById('btnBackupVerifyLatest')?.addEventListener('click', async ()=>{ const world=(document.getElementById('bVerifyWorld')?.value||'').trim()||'primary'; const out=document.getElementById('backupVerifyOut'); out.textContent='Verifying '+world+'...'; try { const r = await fetch(api('/admin/backups/verify'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify({ world }) }); const json = await r.json(); out.textContent = JSON.stringify(json?.data || json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifyBackupsAll')?.addEventListener('click', async ()=>{ const out=document.getElementById('verifyBackupsAllOut'); out.textContent='Verifying all latest backups...'; try { const r = await fetch(api('/admin/backups/verify-all'), { method:'POST', headers:{ 'Authorization': token()?('Bearer '+token()):'' } }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = r.status===200 ? JSON.stringify(json?.data||json, null, 2) : `Error ${r.status}: `+JSON.stringify(json,null,2); } catch(e){ out.textContent='Verify failed: '+e.message; } });

    // Verify suite handlers (parity, migrations, schemas, identity, CDM, completeness)
    document.getElementById('btnVerifySpins')?.addEventListener('click', async ()=>{ const since=document.getElementById('vSpinsSince')?.value; const until=document.getElementById('vSpinsUntil')?.value; const source_conn=document.getElementById('vSpinsConn')?.value||'ngnspins'; const source_table=document.getElementById('vSpinsTable')?.value||'spins'; const out=document.getElementById('verifySpinsOut'); out.textContent='Verifying spins parity...'; try { const r = await fetch(api('/admin/verify/spins-parity'), { method:'POST', headers:{ 'Content-Type':'application/json', 'Authorization': token()?('Bearer '+token()):'' }, body: JSON.stringify({ since, until, source_conn, source_table }) }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = JSON.stringify(json?.data||json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifyMigrations')?.addEventListener('click', async ()=>{ const out=document.getElementById('verifyMigrationsOut'); out.textContent='Checking migrations...'; try { const r = await fetch(api('/admin/verify/migrations'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = JSON.stringify(json?.data||json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifySchemas')?.addEventListener('click', async ()=>{ const out=document.getElementById('verifySchemasOut'); out.textContent='Verifying schema dumps...'; try { const r = await fetch(api('/admin/verify/schemas'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = JSON.stringify(json?.data||json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifyIdentityEmail')?.addEventListener('click', async ()=>{ const out=document.getElementById('verifyIdentityEmailOut'); out.textContent='Verifying identity email...'; try { const r = await fetch(api('/admin/verify/identity-email'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = JSON.stringify(json?.data||json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifyCdm')?.addEventListener('click', async ()=>{ const out=document.getElementById('verifyCdmOut'); out.textContent='Verifying CDM tables...'; try { const r = await fetch(api('/admin/verify/cdm'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } }); const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null; out.textContent = JSON.stringify(json?.data||json, null, 2); } catch(e){ out.textContent='Verify failed: '+e.message; } });
    document.getElementById('btnVerifyCompleteness')?.addEventListener('click', async ()=>{
      const slug=document.getElementById('compChart')?.value||'ngn:artists:weekly';
      let from=document.getElementById('compFrom')?.value||'';
      let to=document.getElementById('compTo')?.value||'';
      if (!to) to = fmtDateUTC(lastFullWeekEndUTC());
      if (!from) from = new Date(Date.now()-8*7*24*3600*1000).toISOString().slice(0,10);
      const out=document.getElementById('verifyCompletenessOut'); out.textContent='Verifying...';
      try {
        const qs=new URLSearchParams({ chart: slug, from, to }).toString();
        const r = await fetch(api('/admin/verify/charts-completeness?'+qs), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } });
        const ct=r.headers.get('content-type')||''; const json = ct.includes('application/json')? await r.json():null;
        out.textContent = r.status===200 ? JSON.stringify(json?.data||json, null, 2) : `Error ${r.status}: `+JSON.stringify(json,null,2);
      } catch(e){ out.textContent='Request failed: '+e.message; }
    });

    // Prefill Completeness inputs with sane defaults on load
    (function(){ try {
      const f=document.getElementById('compFrom'); const t=document.getElementById('compTo');
      if (f && !f.value) f.value = new Date(Date.now()-8*7*24*3600*1000).toISOString().slice(0,10);
      if (t && !t.value) t.value = fmtDateUTC(lastFullWeekEndUTC());
    } catch(e){} })();

    // Scoring Coverage (new card)
    document.getElementById('btnVerifyScoringCoverage')?.addEventListener('click', async ()=>{
      const out = document.getElementById('verifyScoringCoverageOut');
      out.textContent = 'Checking scoring coverage...';
      try {
        const r = await fetch(api('/admin/verify/scoring-coverage'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } });
        const ct = r.headers.get('content-type')||''; const json = ct.includes('application/json') ? await r.json() : null;
        out.textContent = r.status===200 ? JSON.stringify(json?.data||json, null, 2) : `Error ${r.status}: ` + JSON.stringify(json, null, 2);
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    });

    // Factor Integrity (new card)
    document.getElementById('btnVerifyFactorIntegrity')?.addEventListener('click', async ()=>{
      const out = document.getElementById('verifyFactorIntegrityOut');
      out.textContent = 'Checking factor integrity...';
      try {
        const r = await fetch(api('/admin/verify/factor-integrity'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' } });
        const ct = r.headers.get('content-type')||''; const json = ct.includes('application/json') ? await r.json() : null;
        out.textContent = r.status===200 ? JSON.stringify(json?.data||json, null, 2) : `Error ${r.status}: ` + JSON.stringify(json, null, 2);
      } catch(e){ out.textContent = 'Request failed: ' + e.message; }
    });

    // Enhance One‑Click Weekly: include idempotency key and show timings/retries if present
    document.getElementById('qaOneClickWeekly')?.addEventListener('click', async ()=>{
      const key = 'ocw-' + Date.now();
      printQa('Starting One‑Click Weekly…');
      try {
        const r = await post('/admin/etl/charts/chain-one-click', { idempotency_key: key });
        if (r.status === 200) {
          const data = (r.json?.data ?? r.json) ?? (r.text ? (()=>{ try { const tmp=JSON.parse(r.text); return tmp.data ?? tmp; } catch(e){ return r.text; } })() : null);
          // Pretty print with per-step timings if available
          if (data && Array.isArray(data.steps)) {
            const view = {
              ok: !!data.ok,
              idempotency_key: data.idempotency_key || key,
              steps: data.steps.map(s=>({ key: s.key, status: s.status, http: s.http_status, ms: s.duration_ms, retries: s.retries }))
            };
            printQa(view);
            try { await refreshLatestRun(); } catch(e) {}
            try { await refreshProgress(); } catch(e) {}
          } else if (data != null) {
            printQa(data);
            try { await refreshLatestRun(); } catch(e) {}
            try { await refreshProgress(); } catch(e) {}
          } else {
            printQa({ warn: 'empty_response', note: 'Server returned 200 but no JSON body. See raw:', raw: r.text?.slice(0,512) || '' });
          }
        } else if (r.status===401 || r.status===403) {
          printQa('Authorization required. Open Settings once to mint an admin token.');
        } else {
          printQa({ error: r.status, body: r.json ?? (r.text || null) });
        }
      } catch (e) {
        printQa({ error: 'request_failed', message: e?.message || String(e) });
      }
    });

    // One‑Click Weekly history viewer
    document.getElementById('qaOneClickHistory')?.addEventListener('click', async ()=>{
      printQa('Loading One‑Click Weekly history…');
      try {
        const r = await http('/admin/roadmap');
        if (r.status === 200) {
          const runs = r.json?.data?.verify?.chain_runs || [];
          if (!runs.length) { printQa('No recent One‑Click Weekly runs found.'); return; }
          const view = runs.map(x=>({
            at: x.ended_at || x.started_at || x.at || null,
            ok: !!x.ok,
            idempotency_key: x.idempotency_key || null,
            steps: (x.steps||[]).map(s=>({ key:s.key, status:s.status, http:s.http_status, ms:s.duration_ms, retries:s.retries }))
          }));
          printQa(view);
        } else if (r.status===401 || r.status===403) {
          printQa('Authorization required. Open Settings once to mint an admin token.');
        } else {
          printQa({ error:r.status, body:r.json ?? r.text });
        }
      } catch(e) {
        printQa({ error:'request_failed', message:e?.message||String(e) });
      }
    });

    // Lightweight verification chips — function we can call after actions or refresh
    async function updateVerificationBadges(){
      try {
        // Scoring coverage
        const r = await fetch(api('/admin/verify/scoring-coverage'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' }});
        const ct = r.headers.get('content-type')||''; const j = ct.includes('application/json') ? await r.json() : null;
        const ok = !!(j?.data?.ok ?? false);
        const cEl = document.getElementById('coverageBadge');
        if (cEl) {
          cEl.textContent = ok? 'Coverage ✓' : 'Coverage !';
          cEl.className = 'text-[11px] px-2 py-1 rounded-full border ' + (ok
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30'
            : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30');
          cEl.classList.remove('hidden');
        }
        try { window.__badge.coverage = ok; } catch(e){}
      } catch(e) { /* ignore */ }
      try {
        // Factor integrity
        const r2 = await fetch(api('/admin/verify/factor-integrity'), { headers:{ 'Authorization': token()?('Bearer '+token()):'' }});
        const ct2 = r2.headers.get('content-type')||''; const j2 = ct2.includes('application/json') ? await r2.json() : null;
        const ok2 = !!(j2?.data?.ok ?? (r2.status===200));
        const iEl = document.getElementById('integrityBadge');
        if (iEl) {
          iEl.textContent = ok2? 'Integrity ✓' : 'Integrity !';
          iEl.className = 'text-[11px] px-2 py-1 rounded-full border ' + (ok2
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30'
            : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30');
          iEl.classList.remove('hidden');
        }
        try { window.__badge.integrity = ok2; } catch(e){}
      } catch(e) { /* ignore */ }
    }

    // Run once on load
    (async function(){ try { await updateVerificationBadges(); } catch(e){} })();
  </script>
</body>
</html>
