<?php

require_once dirname(__DIR__, 2) . '/_guard.php';
// NGN Admin — Tailwind shell with dark mode and live dashboard
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;
use App\Lib\Commerce\ServiceOrderManager; // Assuming this is available

if (!class_exists('NGN\Lib\Env')) { if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($root, false); } exit; }
Env::load($root);
$cfg = new Config();

// Handle simple actions to set admin override cookie for view mode
$action = $_GET['action'] ?? null;
if ($action === 'set-view') {
    $mode = $_GET['mode'] ?? '';
    if (in_array($mode, ['legacy','next'], true)) {
        setcookie('NGN_VIEW_MODE', $mode, [
            'expires' => time() + 7*86400,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        header('Location: /admin/index.php?set=1');
        exit;
    }
}

$publicMode   = $cfg->publicViewMode();
$override     = $_COOKIE['NGN_VIEW_MODE'] ?? null;
$effective    = (in_array($override, ['legacy','next'], true)) ? $override : $publicMode;
$env          = $cfg->appEnv();
$featureAdmin = $cfg->featureAdmin();
$envLoadedFrom= getenv('NGN_ENV_LOADED_FROM') ?: 'none';
$searchPaths  = getenv('NGN_ENV_SEARCH_PATHS') ?: '';

// Auto-mint admin token - if we got past _guard.php, we're authorized
$mintedToken = null;
$mintedExpires = 0;

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

try {
    $svc = new TokenService($cfg);
    $sub = !empty($_SESSION['User']['Email']) ? (string)$_SESSION['User']['Email'] : 'admin@session';
    $issued = $svc->issueAccessToken(['sub' => $sub, 'role' => 'admin']);
    $mintedToken = $issued['token'] ?? null;
    $mintedExpires = (int)($issued['expires_in'] ?? 0);
} catch (\Throwable $e) {
    error_log('Token mint failed: ' . $e->getMessage());
}

// --- Metrics and Action Items Data Fetching ---
$totalCapitalRaised = 0;
$serviceRevenue = 0;
$activeFanSubs = 0;
$pendingOrders = 0;
$draftsToReview = 0;
$pendingTakedowns = 0;

try {
    // Assuming $pdo and $logger are available from bootstrap
    if (isset($pdo) && $pdo instanceof \PDO) {
        // Total Capital Raised
        $stmtCapital = $pdo->prepare("SELECT SUM(amount) FROM `ngn_2025`.`investments` WHERE status = 'active'");
        $stmtCapital->execute();
        $totalCapitalRaised = (float)$stmtCapital->fetchColumn();

        // Service Revenue
        $stmtRevenue = $pdo->prepare("SELECT SUM(price) FROM `ngn_2025`.`service_orders` WHERE status = 'completed'");
        $stmtRevenue->execute();
        $serviceRevenue = (float)$stmtRevenue->fetchColumn();

        // Active Fan Subscribers
        $stmtSubs = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`user_fan_subscriptions` WHERE status = 'active'");
        $stmtSubs->execute();
        $activeFanSubs = (int)$stmtSubs->fetchColumn();

        // Pending Service Orders
        $stmtPendingOrders = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`service_orders` WHERE status = 'pending'");
        $stmtPendingOrders->execute();
        $pendingOrders = (int)$stmtPendingOrders->fetchColumn();

        // Drafts to Review (assuming 'status' in 'posts' table)
        $stmtDrafts = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`posts` WHERE status = 'pending_review'");
        $stmtDrafts->execute();
        $draftsToReview = (int)$stmtDrafts->fetchColumn();

        // Pending Takedown Requests
        $stmtTakedowns = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`takedown_requests` WHERE status = 'open'");
        $stmtTakedowns->execute();
        $pendingTakedowns = (int)$stmtTakedowns->fetchColumn();

    } else {
        error_log("Admin Dashboard Error: PDO connection not available.");
        // Handle error if PDO is not available
    }
} catch (\Throwable $e) {
    // Log errors during data fetching
    error_log("Admin Dashboard Error: Data fetching failed - " . $e->getMessage());
    // Set to default/mock values or display an error
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
include __DIR__.'/_header.php';

include __DIR__.'/_topbar.php';
?>

        <!-- Admin Dashboard Metrics (Top Row Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <!-- Total Capital Raised -->
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">Total Capital Raised</div>
              <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.108 4.107 3 4.354m0-8.708C10.912 7.744 12 8.503 12 10c0 1.657-1.343 3-3 3s-3-1.343-3-3 1.108-4.107 3-4.354M12 17c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM15 10a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <div class="text-3xl font-bold sk-text-primary">$<?php echo number_format($totalCapitalRaised, 2); ?></div>
          </div>

          <!-- Service Revenue -->
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">Service Revenue</div>
              <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m-4-3h6m-6 3h6m0 6h-6m6-2a6 6 0 11-12 0 6 6 0 0112 0z"></path></svg>
            </div>
            <div class="text-3xl font-bold sk-text-primary">$<?php echo number_format($serviceRevenue, 2); ?></div>
          </div>

          <!-- Active Fan Subscribers -->
          <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/70 dark:bg-white/5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">Active Fan Subs</div>
              <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div class="text-3xl font-bold sk-text-primary"><?php echo number_format($activeFanSubs); ?></div>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-8">
          <section class="lg:col-span-2">
            <h2 class="text-2xl font-bold mb-4 sk-text-gradient-secondary">Recent Activity Overview</h2>
            <?php // Placeholder for recent activity feed or charts ?>
            <div class="sk-card sk-card-glow p-6 h-full">
              <p class="text-gray-500 dark:text-gray-400">Recent activity feed content would go here. This section is currently a placeholder.</p>
            </div>
          </section>

          <!-- Action Items Widget -->
          <section class="lg:col-span-1">
            <div class="rounded-lg border border-amber-200 dark:border-amber-500/30 p-4 bg-amber-50/50 dark:bg-amber-500/5 shadow-sm">
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h3 class="text-xl font-semibold text-amber-800 dark:text-amber-200">Action Items</h3>
              </div>
              <div class="space-y-3">
                <div class="flex items-center justify-between text-sm">
                  <span>Pending Service Orders</span>
                  <a href="/admin/orders.php" class="sk-badge sk-badge-pending"><?php echo $pendingOrders; ?></a>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span>Drafts to Review</span>
                  <a href="/admin/editorial.php" class="sk-badge sk-badge-pending"><?php echo $draftsToReview; ?></a>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <span>Takedown Requests</span>
                  <a href="/admin/takedown_requests.php" class="sk-badge sk-badge-pending"><?php echo $pendingTakedowns; ?></a>
                </div>
              </div>
            </div>
          </section>
        </div>

      </section>
    </main>

<?php include __DIR__.'/_footer.php'; ?>
<?php // End Admin Panel Layout ?>

<?php // JS for dynamic elements like chart rendering or data fetching would be here ?>
<?php // Currently, the existing JS for token management, refresh, etc. is included via _token_store.php ?>

</body>
</html>