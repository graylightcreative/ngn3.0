<?php
/**
 * NGN 2.0 Admin Dashboard
 * Comprehensive command center for platform operations
 */

require_once __DIR__ . '/_guard.php';
$root = dirname(__DIR__, 1);

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Services\MetricsService;
use NGN\Lib\Services\AlertService;

if (!class_exists('NGN\Lib\Env')) { if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($root, false); } exit; }
Env::load($root);
$cfg = new Config();

// Handle view mode actions
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

// Auto-mint admin token
$mintedToken = null;
$mintedExpires = 0;

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

// === DATA FETCHING ===

// Initialize metrics
$totalCapitalRaised = 0;
$serviceRevenue = 0;
$activeFanSubs = 0;
$pendingOrders = 0;
$draftsToReview = 0;
$pendingTakedowns = 0;
$activeInvestors = 0;
$totalUsers = 0;
$apiP95Latency = null;
$recentAlerts = [];
$systemHealth = 'healthy';

try {
    if (isset($pdo) && $pdo instanceof \PDO) {
        // Financial Metrics
        $stmtCapital = $pdo->prepare("SELECT SUM(amount_cents) FROM ngn_2025.investments WHERE status = 'active'");
        $stmtCapital->execute();
        $totalCapitalRaised = (float)$stmtCapital->fetchColumn() / 100; // Convert cents to dollars

        $stmtRevenue = $pdo->prepare("SELECT SUM(price) FROM ngn_2025.service_orders WHERE status = 'completed'");
        $stmtRevenue->execute();
        $serviceRevenue = (float)$stmtRevenue->fetchColumn();

        // User Metrics
        $stmtSubs = $pdo->prepare("SELECT COUNT(*) FROM ngn_2025.user_fan_subscriptions WHERE status = 'active'");
        $stmtSubs->execute();
        $activeFanSubs = (int)$stmtSubs->fetchColumn();

        $stmtInvestors = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM ngn_2025.investments WHERE status = 'active'");
        $stmtInvestors->execute();
        $activeInvestors = (int)$stmtInvestors->fetchColumn();

        $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM ngn_2025.users WHERE status = 'active'");
        $stmtUsers->execute();
        $totalUsers = (int)$stmtUsers->fetchColumn();

        // Action Items
        $stmtPendingOrders = $pdo->prepare("SELECT COUNT(*) FROM ngn_2025.service_orders WHERE status = 'pending'");
        $stmtPendingOrders->execute();
        $pendingOrders = (int)$stmtPendingOrders->fetchColumn();

        $stmtDrafts = $pdo->prepare("SELECT COUNT(*) FROM ngn_2025.posts WHERE status = 'pending_review'");
        $stmtDrafts->execute();
        $draftsToReview = (int)$stmtDrafts->fetchColumn();

        $stmtTakedowns = $pdo->prepare("SELECT COUNT(*) FROM ngn_2025.takedown_requests WHERE status = 'open'");
        $stmtTakedowns->execute();
        $pendingTakedowns = (int)$stmtTakedowns->fetchColumn();

        // P95 Latency (last 5 minutes)
        try {
            $metricsService = new MetricsService($pdo);
            $latencyStats = $metricsService->getLatencyStats(5);
            $apiP95Latency = $latencyStats['p95_ms'];
        } catch (\Throwable $e) {
            // Metrics service might not be available yet
        }

        // Recent Alerts (last 24 hours)
        try {
            $alertService = new AlertService($pdo);
            $recentAlerts = $alertService->getRecentAlerts(10);

            // Determine overall system health based on recent P0/P1 alerts
            $criticalCount = count(array_filter($recentAlerts, function($alert) {
                return $alert['severity'] === 'p0' && empty($alert['resolved_at']);
            }));
            $highCount = count(array_filter($recentAlerts, function($alert) {
                return $alert['severity'] === 'p1' && empty($alert['resolved_at']);
            }));

            if ($criticalCount > 0) {
                $systemHealth = 'critical';
            } elseif ($highCount > 0) {
                $systemHealth = 'degraded';
            }
        } catch (\Throwable $e) {
            // Alert service might not be available yet
        }

    }
} catch (\Throwable $e) {
    error_log("Admin Dashboard Error: Data fetching failed - " . $e->getMessage());
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<style>
.metric-card {
    @apply rounded-lg border border-gray-200 dark:border-white/10 p-5 bg-white/70 dark:bg-white/5 shadow-sm hover:shadow-md transition-shadow;
}

.metric-icon {
    @apply w-5 h-5;
}

.metric-label {
    @apply text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide;
}

.metric-value {
    @apply text-3xl font-bold sk-text-primary;
}

.health-badge {
    @apply inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold;
}

.health-healthy {
    @apply bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300;
}

.health-degraded {
    @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300;
}

.health-critical {
    @apply bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300;
}

.quick-action {
    @apply flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5 hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-300 dark:hover:border-blue-500/30 transition-all cursor-pointer;
}

.alert-item {
    @apply flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5;
}
</style>

<!-- System Health Banner -->
<?php if ($systemHealth === 'critical'): ?>
<div class="mb-6 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 border-2 border-red-500">
    <div class="flex items-center gap-3">
        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h3 class="text-lg font-bold text-red-800 dark:text-red-200">Critical System Alert</h3>
            <p class="text-sm text-red-700 dark:text-red-300">One or more P0 critical alerts are active. Immediate attention required.</p>
        </div>
        <a href="/admin/alerts.php" class="ml-auto px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">View Alerts</a>
    </div>
</div>
<?php elseif ($systemHealth === 'degraded'): ?>
<div class="mb-6 p-4 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-500">
    <div class="flex items-center gap-3">
        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h3 class="text-lg font-bold text-yellow-800 dark:text-yellow-200">System Degraded</h3>
            <p class="text-sm text-yellow-700 dark:text-yellow-300">Service degradation detected. Please review P1 alerts.</p>
        </div>
        <a href="/admin/alerts.php" class="ml-auto px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">View Alerts</a>
    </div>
</div>
<?php endif; ?>

<!-- Key Metrics Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Total Capital Raised -->
    <div class="metric-card">
        <div class="flex items-center justify-between mb-3">
            <span class="metric-label">Capital Raised</span>
            <svg class="metric-icon text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.108 4.107 3 4.354m0-8.708C10.912 7.744 12 8.503 12 10c0 1.657-1.343 3-3 3s-3-1.343-3-3 1.108-4.107 3-4.354M12 17c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/>
            </svg>
        </div>
        <div class="metric-value text-green-600 dark:text-green-400">$<?php echo number_format($totalCapitalRaised, 2); ?></div>
        <div class="mt-2 text-xs text-gray-500">
            <span class="font-semibold"><?php echo $activeInvestors; ?></span> active investors
        </div>
    </div>

    <!-- Service Revenue -->
    <div class="metric-card">
        <div class="flex items-center justify-between mb-3">
            <span class="metric-label">Service Revenue</span>
            <svg class="metric-icon text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m-4-3h6m-6 3h6m0 6h-6m6-2a6 6 0 11-12 0 6 6 0 0112 0z"/>
            </svg>
        </div>
        <div class="metric-value text-blue-600 dark:text-blue-400">$<?php echo number_format($serviceRevenue, 2); ?></div>
        <div class="mt-2 text-xs text-gray-500"><?= $cfg->featureAiEnabled() ? 'From AI services' : 'AI Services (DISABLED)' ?></div>
    </div>

    <!-- Active Subscribers -->
    <div class="metric-card">
        <div class="flex items-center justify-between mb-3">
            <span class="metric-label">Fan Subscribers</span>
            <svg class="metric-icon text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </div>
        <div class="metric-value text-purple-600 dark:text-purple-400"><?php echo number_format($activeFanSubs); ?></div>
        <div class="mt-2 text-xs text-gray-500">Recurring revenue</div>
    </div>

    <!-- Total Platform Users -->
    <div class="metric-card">
        <div class="flex items-center justify-between mb-3">
            <span class="metric-label">Platform Users</span>
            <svg class="metric-icon text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <div class="metric-value text-indigo-600 dark:text-indigo-400"><?php echo number_format($totalUsers); ?></div>
        <div class="mt-2 text-xs text-gray-500">Active accounts</div>
    </div>
</div>

<!-- System Performance & Status -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- System Health Status -->
    <div class="metric-card">
        <h3 class="text-lg font-bold mb-4 sk-text-primary">System Health</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">Overall Status</span>
                <?php if ($systemHealth === 'healthy'): ?>
                    <span class="health-badge health-healthy">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Healthy
                    </span>
                <?php elseif ($systemHealth === 'degraded'): ?>
                    <span class="health-badge health-degraded">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Degraded
                    </span>
                <?php else: ?>
                    <span class="health-badge health-critical">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        Critical
                    </span>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">API P95 Latency</span>
                <?php if ($apiP95Latency !== null): ?>
                    <?php if ($apiP95Latency <= 250): ?>
                        <span class="text-sm font-semibold text-green-600 dark:text-green-400"><?php echo number_format($apiP95Latency, 0); ?>ms</span>
                    <?php else: ?>
                        <span class="text-sm font-semibold text-red-600 dark:text-red-400"><?php echo number_format($apiP95Latency, 0); ?>ms ⚠️</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-sm text-gray-400">N/A</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-300">Environment</span>
                <span class="text-sm font-semibold <?php echo $env === 'production' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'; ?>">
                    <?php echo strtoupper($env); ?>
                </span>
            </div>
        </div>
        <div class="mt-4">
            <a href="/admin/monitoring.php" class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                View Full Monitoring Dashboard
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Action Items -->
    <div class="metric-card">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h3 class="text-lg font-bold text-amber-800 dark:text-amber-200">Action Items</h3>
        </div>
        <div class="space-y-3">
            <?php if ($pendingOrders > 0): ?>
            <a href="/admin/orders.php" class="flex items-center justify-between text-sm p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition">
                <span class="text-gray-700 dark:text-gray-200">Pending Service Orders</span>
                <span class="px-2 py-1 bg-amber-500 text-white rounded-full text-xs font-bold"><?php echo $pendingOrders; ?></span>
            </a>
            <?php endif; ?>

            <?php if ($draftsToReview > 0): ?>
            <a href="/admin/editorial.php" class="flex items-center justify-between text-sm p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition">
                <span class="text-gray-700 dark:text-gray-200">Drafts to Review</span>
                <span class="px-2 py-1 bg-amber-500 text-white rounded-full text-xs font-bold"><?php echo $draftsToReview; ?></span>
            </a>
            <?php endif; ?>

            <?php if ($pendingTakedowns > 0): ?>
            <a href="/admin/takedown_requests.php" class="flex items-center justify-between text-sm p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition">
                <span class="text-gray-700 dark:text-gray-200">Takedown Requests</span>
                <span class="px-2 py-1 bg-amber-500 text-white rounded-full text-xs font-bold"><?php echo $pendingTakedowns; ?></span>
            </a>
            <?php endif; ?>

            <?php if ($pendingOrders == 0 && $draftsToReview == 0 && $pendingTakedowns == 0): ?>
            <div class="flex items-center justify-center p-4 text-green-600 dark:text-green-400">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-semibold">All caught up!</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Alerts -->
    <div class="metric-card">
        <h3 class="text-lg font-bold mb-4 sk-text-primary">Recent Alerts</h3>
        <div class="space-y-2 max-h-48 overflow-y-auto">
            <?php if (!empty($recentAlerts)): ?>
                <?php foreach (array_slice($recentAlerts, 0, 5) as $alert): ?>
                <div class="alert-item <?php echo empty($alert['resolved_at']) ? 'border-l-4 border-red-500' : ''; ?>">
                    <div class="flex-shrink-0">
                        <?php if ($alert['severity'] === 'p0'): ?>
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <?php elseif ($alert['severity'] === 'p1'): ?>
                            <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?php else: ?>
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold uppercase text-gray-500"><?php echo strtoupper($alert['severity']); ?> - <?php echo htmlspecialchars($alert['alert_type']); ?></p>
                        <p class="text-sm text-gray-700 dark:text-gray-200 truncate"><?php echo htmlspecialchars($alert['message']); ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?php echo date('M j, g:ia', strtotime($alert['created_at'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="mt-3 text-center">
                    <a href="/admin/alerts.php" class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        View All Alerts
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center py-4">No recent alerts</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions Grid -->
<div class="mb-8">
    <h2 class="text-2xl font-bold mb-4 sk-text-primary">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="/admin/royalties.php" class="quick-action">
            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 3 3 3 3 .895 3 2-1.343 3-3 3m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Royalties & Payouts</p>
                <p class="text-xs text-gray-500">Manage artist payments</p>
            </div>
        </a>

        <a href="/admin/pricing.php" class="quick-action">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Pricing & Commission</p>
                <p class="text-xs text-gray-500">Margin control (60% KPI)</p>
            </div>
        </a>

        <a href="/admin/cron-setup.php" class="quick-action">
            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Cron Jobs</p>
                <p class="text-xs text-gray-500">View scheduled tasks</p>
            </div>
        </a>

        <a href="/admin/governance.php" class="quick-action">
            <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Directorate Board</p>
                <p class="text-xs text-gray-500">Governance & SIR tracking</p>
            </div>
        </a>

        <a href="/admin/smr-ingestion.php" class="quick-action">
            <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">SMR Ingestion</p>
                <p class="text-xs text-gray-500">Upload radio data</p>
            </div>
        </a>

        <a href="/admin/api-health.php" class="quick-action">
            <svg class="w-6 h-6 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">API Health</p>
                <p class="text-xs text-gray-500">Service connections</p>
            </div>
        </a>

        <a href="/admin/charts-2025.php" class="quick-action">
            <svg class="w-6 h-6 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Chart Management</p>
                <p class="text-xs text-gray-500">Weekly rankings</p>
            </div>
        </a>

        <a href="/admin/users.php" class="quick-action">
            <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">User Management</p>
                <p class="text-xs text-gray-500">Roles, claims, bans</p>
            </div>
        </a>

        <a href="/admin/database.php" class="quick-action">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
            </svg>
            <div>
                <p class="font-semibold text-gray-800 dark:text-gray-100">Database Tools</p>
                <p class="text-xs text-gray-500">Explorer & backups</p>
            </div>
        </a>
    </div>
</div>

</section>
</main>

<?php include __DIR__.'/_footer.php'; ?>

<script>
// Auto-refresh metrics every 60 seconds
setInterval(function() {
    location.reload();
}, 60000);
</script>

</body>
</html>
