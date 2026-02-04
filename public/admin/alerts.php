<?php
/**
 * Alert Management Dashboard
 * Comprehensive alert history, resolution, and notification configuration
 *
 * Bible Reference: Ch. 12 - System Integrity & Monitoring
 */

require_once dirname(__DIR__, 1) . '/_guard.php';
$root = dirname(__DIR__, 1);

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Services\AlertService;

if (!class_exists('NGN\Lib\Env')) { if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($root, false); } exit; }
Env::load($root);
$cfg = new Config();

// Handle alert resolution action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve') {
    $alertId = (int)$_POST['alert_id'];
    if ($alertId > 0 && isset($pdo)) {
        try {
            $alertService = new AlertService($pdo);
            $alertService->resolveAlert($alertId);
            header('Location: alerts.php?resolved=1');
            exit;
        } catch (\Throwable $e) {
            error_log("Failed to resolve alert: " . $e->getMessage());
        }
    }
}

// Get filter parameters
$filterSeverity = isset($_GET['severity']) ? $_GET['severity'] : null;
$filterType = isset($_GET['type']) ? $_GET['type'] : null;
$filterResolved = isset($_GET['resolved']) ? $_GET['resolved'] : 'unresolved'; // 'all', 'unresolved', 'resolved'

// Initialize data
$alerts = [];
$alertStats = [
    'total' => 0,
    'unresolved' => 0,
    'p0_count' => 0,
    'p1_count' => 0,
    'p2_count' => 0,
    'recent_24h' => 0
];

$alertTypes = [];

try {
    if (isset($pdo) && $pdo instanceof \PDO) {
        $alertService = new AlertService($pdo);

        // Get alert statistics
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN resolved_at IS NULL THEN 1 END) as unresolved,
                COUNT(CASE WHEN severity = 'p0' AND resolved_at IS NULL THEN 1 END) as p0_count,
                COUNT(CASE WHEN severity = 'p1' AND resolved_at IS NULL THEN 1 END) as p1_count,
                COUNT(CASE WHEN severity = 'p2' AND resolved_at IS NULL THEN 1 END) as p2_count,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_24h
            FROM ngn_2025.alert_history
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
            $alertStats = [
                'total' => (int)$stats['total'],
                'unresolved' => (int)$stats['unresolved'],
                'p0_count' => (int)$stats['p0_count'],
                'p1_count' => (int)$stats['p1_count'],
                'p2_count' => (int)$stats['p2_count'],
                'recent_24h' => (int)$stats['recent_24h']
            ];
        }

        // Get unique alert types
        $stmt = $pdo->prepare("
            SELECT DISTINCT alert_type
            FROM ngn_2025.alert_history
            ORDER BY alert_type ASC
        ");
        $stmt->execute();
        $alertTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Build filtered query
        $sql = "SELECT * FROM ngn_2025.alert_history WHERE 1=1";
        $params = [];

        if ($filterSeverity) {
            $sql .= " AND severity = :severity";
            $params['severity'] = $filterSeverity;
        }

        if ($filterType) {
            $sql .= " AND alert_type = :alert_type";
            $params['alert_type'] = $filterType;
        }

        if ($filterResolved === 'unresolved') {
            $sql .= " AND resolved_at IS NULL";
        } elseif ($filterResolved === 'resolved') {
            $sql .= " AND resolved_at IS NOT NULL";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    error_log("Alerts Dashboard Error: " . $e->getMessage());
}

$pageTitle = 'Alert Management';
$currentPage = 'alerts';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<style>
.stat-card {
    @apply rounded-lg border border-gray-200 dark:border-white/10 p-5 bg-white/70 dark:bg-white/5 shadow-sm;
}

.alert-card {
    @apply rounded-lg border border-gray-200 dark:border-white/10 p-4 bg-white/50 dark:bg-white/5;
}

.alert-unresolved {
    @apply border-l-4 border-red-500;
}

.severity-badge {
    @apply inline-flex items-center px-2 py-1 rounded text-xs font-bold uppercase;
}

.severity-p0 {
    @apply bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300;
}

.severity-p1 {
    @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300;
}

.severity-p2 {
    @apply bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300;
}
</style>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold sk-text-primary">Alert Management</h1>
        <p class="text-sm text-gray-500 mt-1">System alerts, notifications, and resolution tracking</p>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh
        </button>
    </div>
</div>

<!-- Alert Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
    <!-- Total Alerts -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Total Alerts
        </div>
        <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">
            <?php echo number_format($alertStats['total']); ?>
        </div>
    </div>

    <!-- Unresolved -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Unresolved
        </div>
        <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">
            <?php echo number_format($alertStats['unresolved']); ?>
        </div>
    </div>

    <!-- P0 Critical -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            P0 (Critical)
        </div>
        <div class="text-3xl font-bold text-red-600 dark:text-red-400">
            <?php echo number_format($alertStats['p0_count']); ?>
        </div>
    </div>

    <!-- P1 High -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            P1 (High)
        </div>
        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
            <?php echo number_format($alertStats['p1_count']); ?>
        </div>
    </div>

    <!-- P2 Normal -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            P2 (Normal)
        </div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
            <?php echo number_format($alertStats['p2_count']); ?>
        </div>
    </div>

    <!-- Last 24h -->
    <div class="stat-card">
        <div class="text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
            Last 24 Hours
        </div>
        <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
            <?php echo number_format($alertStats['recent_24h']); ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="stat-card mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Severity</label>
            <select name="severity" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">All Severities</option>
                <option value="p0" <?php echo $filterSeverity === 'p0' ? 'selected' : ''; ?>>P0 (Critical)</option>
                <option value="p1" <?php echo $filterSeverity === 'p1' ? 'selected' : ''; ?>>P1 (High)</option>
                <option value="p2" <?php echo $filterSeverity === 'p2' ? 'selected' : ''; ?>>P2 (Normal)</option>
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Alert Type</label>
            <select name="type" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">All Types</option>
                <?php foreach ($alertTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterType === $type ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Resolution Status</label>
            <select name="resolved" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="all" <?php echo $filterResolved === 'all' ? 'selected' : ''; ?>>All Alerts</option>
                <option value="unresolved" <?php echo $filterResolved === 'unresolved' ? 'selected' : ''; ?>>Unresolved Only</option>
                <option value="resolved" <?php echo $filterResolved === 'resolved' ? 'selected' : ''; ?>>Resolved Only</option>
            </select>
        </div>

        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Apply Filters
        </button>
        <a href="?" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            Clear
        </a>
    </form>
</div>

<!-- Alert List -->
<div class="stat-card">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold sk-text-primary">
            Alert History
            <?php if ($filterResolved === 'unresolved'): ?>
                (Unresolved)
            <?php elseif ($filterResolved === 'resolved'): ?>
                (Resolved)
            <?php endif; ?>
        </h2>
        <span class="text-sm text-gray-500">Showing <?php echo count($alerts); ?> alerts</span>
    </div>

    <?php if (!empty($alerts)): ?>
        <div class="space-y-4">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert-card <?php echo empty($alert['resolved_at']) ? 'alert-unresolved' : ''; ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Header -->
                        <div class="flex items-center gap-2 mb-2">
                            <!-- Severity Badge -->
                            <span class="severity-badge severity-<?php echo $alert['severity']; ?>">
                                <?php echo strtoupper($alert['severity']); ?>
                            </span>

                            <!-- Alert Type -->
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <?php echo htmlspecialchars($alert['alert_type']); ?>
                            </span>

                            <!-- Resolution Status -->
                            <?php if (empty($alert['resolved_at'])): ?>
                                <span class="px-2 py-1 rounded text-xs font-bold bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                                    UNRESOLVED
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    ✓ RESOLVED
                                </span>
                            <?php endif; ?>

                            <!-- Alert ID -->
                            <span class="text-xs text-gray-400">
                                #<?php echo $alert['id']; ?>
                            </span>
                        </div>

                        <!-- Message -->
                        <p class="text-sm text-gray-700 dark:text-gray-200 mb-2">
                            <?php echo htmlspecialchars($alert['message']); ?>
                        </p>

                        <!-- Details (Expandable) -->
                        <?php if (!empty($alert['details'])): ?>
                        <details class="text-xs mt-2">
                            <summary class="cursor-pointer text-blue-600 dark:text-blue-400 hover:underline">
                                View Details
                            </summary>
                            <pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-x-auto"><?php echo htmlspecialchars(json_encode(json_decode($alert['details']), JSON_PRETTY_PRINT)); ?></pre>
                        </details>
                        <?php endif; ?>

                        <!-- Timestamps -->
                        <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
                            <span>
                                <strong>Created:</strong> <?php echo date('M j, Y g:ia', strtotime($alert['created_at'])); ?>
                            </span>
                            <?php if (!empty($alert['notified_at'])): ?>
                                <span>
                                    <strong>Notified:</strong> <?php echo date('M j, Y g:ia', strtotime($alert['notified_at'])); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($alert['resolved_at'])): ?>
                                <span class="text-green-600 dark:text-green-400">
                                    <strong>Resolved:</strong> <?php echo date('M j, Y g:ia', strtotime($alert['resolved_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <?php if (empty($alert['resolved_at'])): ?>
                    <div class="ml-4">
                        <form method="POST" onsubmit="return confirm('Mark this alert as resolved?')">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                Mark Resolved
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-12 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <p class="font-semibold">No alerts found</p>
            <p class="text-sm mt-1">No alerts match your current filters</p>
        </div>
    <?php endif; ?>
</div>

<!-- Alert Tier Reference -->
<div class="stat-card mt-8 bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-500/30">
    <div class="flex items-start gap-3">
        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <h3 class="font-bold text-blue-800 dark:text-blue-200 mb-2">Alert Tier System (Bible Ch. 12)</h3>
            <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                <p><strong>• P0 (Critical):</strong> System failures - Chart calculation failure, Payment gateway down, Origin unreachable</p>
                <p><strong>• P1 (High):</strong> Service degradation - SMR ingestion error, AI hallucination detected, Webhook latency > 5s, P95 latency > 250ms</p>
                <p><strong>• P2 (Normal):</strong> Informational - Successful weekly backup, New artist claim, Daily health report</p>
                <p class="mt-3"><strong>Notification Channels:</strong> P0 → SMS/PagerDuty/Phone | P1 → Slack/Discord | P2 → Email/Weekly digest</p>
            </div>
        </div>
    </div>
</div>

</section>
</main>

<?php include __DIR__.'/_footer.php'; ?>

<script>
// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>
