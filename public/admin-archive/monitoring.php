<?php
/**
 * P95 API Latency Monitoring Dashboard
 * Real-time performance monitoring and alert management
 */

require_once dirname(__DIR__, 1) . '/_guard.php';
$root = dirname(__DIR__, 1);

use NGN\Lib\Env;
use NGN\Lib\Config;
use NGN\Lib\Services\MetricsService;
use NGN\Lib\Services\AlertService;

if (!class_exists('NGN\Lib\Env')) { if (function_exists('ngn_autoload_diagnostics')) { ngn_autoload_diagnostics($root, false); } exit; }
Env::load($root);
$cfg = new Config();

// Get time window from query param (default: 60 minutes)
$windowMinutes = isset($_GET['window']) ? (int)$_GET['window'] : 60;
$windowMinutes = max(5, min($windowMinutes, 1440)); // Clamp between 5 min and 24 hours

// Initialize metrics
$overallStats = [
    'request_count' => 0,
    'p50_ms' => null,
    'p95_ms' => null,
    'p99_ms' => null,
    'avg_ms' => null,
    'min_ms' => null,
    'max_ms' => null
];
$endpointBreakdown = [];
$recentAlerts = [];
$alertStats = [
    'total' => 0,
    'p0_count' => 0,
    'p1_count' => 0,
    'p2_count' => 0,
    'unresolved' => 0
];

try {
    if (isset($pdo) && $pdo instanceof \PDO) {
        $metricsService = new MetricsService($pdo);
        $alertService = new AlertService($pdo);

        // Get overall latency statistics
        $overallStats = $metricsService->getLatencyStats($windowMinutes);

        // Get per-endpoint breakdown
        $endpointBreakdown = $metricsService->getEndpointBreakdown($windowMinutes, 20);

        // Get recent alerts (last 50)
        $recentAlerts = $alertService->getRecentAlerts(50);

        // Calculate alert statistics
        $alertStats['total'] = count($recentAlerts);
        foreach ($recentAlerts as $alert) {
            if ($alert['severity'] === 'p0') $alertStats['p0_count']++;
            if ($alert['severity'] === 'p1') $alertStats['p1_count']++;
            if ($alert['severity'] === 'p2') $alertStats['p2_count']++;
            if (empty($alert['resolved_at'])) $alertStats['unresolved']++;
        }
    }
} catch (\Throwable $e) {
    error_log("Monitoring Dashboard Error: " . $e->getMessage());
}

$pageTitle = 'P95 Latency Monitoring';
$currentPage = 'monitoring';
include __DIR__.'/_header.php';
include __DIR__.'/_topbar.php';
?>

<style>
.stat-card {
    @apply rounded-lg border border-gray-200 dark:border-white/10 p-5 bg-white/70 dark:bg-white/5 shadow-sm;
}

.stat-label {
    @apply text-sm font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2;
}

.stat-value {
    @apply text-3xl font-bold;
}

.endpoint-row {
    @apply flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5 hover:bg-gray-50 dark:hover:bg-white/10 transition;
}

.alert-row {
    @apply flex items-start gap-4 p-4 rounded-lg border border-gray-200 dark:border-white/10 bg-white/50 dark:bg-white/5;
}

.tab-button {
    @apply px-4 py-2 font-semibold rounded-t-lg transition;
}

.tab-active {
    @apply bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 border-b-2 border-blue-600;
}

.tab-inactive {
    @apply text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50;
}
</style>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold sk-text-primary">P95 Latency Monitoring</h1>
        <p class="text-sm text-gray-500 mt-1">Real-time API performance metrics and alerts</p>
    </div>

    <!-- Time Window Selector -->
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600 dark:text-gray-300">Time Window:</label>
        <select id="window-select" onchange="window.location.href='?window='+this.value" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            <option value="5" <?php echo $windowMinutes == 5 ? 'selected' : ''; ?>>5 minutes</option>
            <option value="15" <?php echo $windowMinutes == 15 ? 'selected' : ''; ?>>15 minutes</option>
            <option value="60" <?php echo $windowMinutes == 60 ? 'selected' : ''; ?>>1 hour</option>
            <option value="360" <?php echo $windowMinutes == 360 ? 'selected' : ''; ?>>6 hours</option>
            <option value="1440" <?php echo $windowMinutes == 1440 ? 'selected' : ''; ?>>24 hours</option>
        </select>
        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh
        </button>
    </div>
</div>

<!-- Overall Latency Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- P95 Latency -->
    <div class="stat-card">
        <div class="stat-label">P95 Latency</div>
        <?php if ($overallStats['p95_ms'] !== null): ?>
            <?php if ($overallStats['p95_ms'] <= 250): ?>
                <div class="stat-value text-green-600 dark:text-green-400"><?php echo number_format($overallStats['p95_ms'], 0); ?>ms</div>
                <div class="mt-2 text-xs text-green-600 dark:text-green-400 font-semibold">✓ Within threshold</div>
            <?php else: ?>
                <div class="stat-value text-red-600 dark:text-red-400"><?php echo number_format($overallStats['p95_ms'], 0); ?>ms</div>
                <div class="mt-2 text-xs text-red-600 dark:text-red-400 font-semibold">⚠ Above threshold (250ms)</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="stat-value text-gray-400">N/A</div>
            <div class="mt-2 text-xs text-gray-500">No data in window</div>
        <?php endif; ?>
    </div>

    <!-- Average Latency -->
    <div class="stat-card">
        <div class="stat-label">Average Latency</div>
        <?php if ($overallStats['avg_ms'] !== null): ?>
            <div class="stat-value text-blue-600 dark:text-blue-400"><?php echo number_format($overallStats['avg_ms'], 0); ?>ms</div>
            <div class="mt-2 text-xs text-gray-500">P50: <?php echo number_format($overallStats['p50_ms'] ?? 0, 0); ?>ms</div>
        <?php else: ?>
            <div class="stat-value text-gray-400">N/A</div>
        <?php endif; ?>
    </div>

    <!-- Request Volume -->
    <div class="stat-card">
        <div class="stat-label">Total Requests</div>
        <div class="stat-value text-indigo-600 dark:text-indigo-400"><?php echo number_format($overallStats['request_count']); ?></div>
        <div class="mt-2 text-xs text-gray-500">
            In last <?php echo $windowMinutes < 60 ? "{$windowMinutes} min" : ($windowMinutes / 60) . " hr"; ?>
        </div>
    </div>

    <!-- P99 Latency -->
    <div class="stat-card">
        <div class="stat-label">P99 Latency</div>
        <?php if ($overallStats['p99_ms'] !== null): ?>
            <div class="stat-value text-purple-600 dark:text-purple-400"><?php echo number_format($overallStats['p99_ms'], 0); ?>ms</div>
            <div class="mt-2 text-xs text-gray-500">Max: <?php echo number_format($overallStats['max_ms'] ?? 0, 0); ?>ms</div>
        <?php else: ?>
            <div class="stat-value text-gray-400">N/A</div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs -->
<div class="mb-6">
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button id="tab-endpoints" class="tab-button tab-active" onclick="switchTab('endpoints')">
            Endpoint Breakdown
        </button>
        <button id="tab-alerts" class="tab-button tab-inactive" onclick="switchTab('alerts')">
            Alert History (<?php echo $alertStats['unresolved']; ?> unresolved)
        </button>
    </div>
</div>

<!-- Tab Content: Endpoint Breakdown -->
<div id="content-endpoints" class="tab-content">
    <div class="stat-card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold sk-text-primary">Endpoint Performance Breakdown</h2>
            <span class="text-sm text-gray-500">Showing top 20 endpoints by request volume</span>
        </div>

        <?php if (!empty($endpointBreakdown)): ?>
            <div class="space-y-2">
                <?php foreach ($endpointBreakdown as $index => $endpoint): ?>
                <div class="endpoint-row">
                    <div class="flex-1">
                        <div class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-100">
                            <?php echo htmlspecialchars($endpoint['endpoint']); ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <?php echo number_format($endpoint['request_count']); ?> requests
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-sm">
                        <div class="text-right">
                            <div class="text-gray-500">P50</div>
                            <div class="font-semibold"><?php echo number_format($endpoint['p50_ms'] ?? 0, 0); ?>ms</div>
                        </div>
                        <div class="text-right">
                            <div class="text-gray-500">P95</div>
                            <?php if ($endpoint['p95_ms'] > 250): ?>
                                <div class="font-semibold text-red-600 dark:text-red-400"><?php echo number_format($endpoint['p95_ms'], 0); ?>ms ⚠</div>
                            <?php else: ?>
                                <div class="font-semibold text-green-600 dark:text-green-400"><?php echo number_format($endpoint['p95_ms'], 0); ?>ms</div>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <div class="text-gray-500">Avg</div>
                            <div class="font-semibold"><?php echo number_format($endpoint['avg_ms'], 0); ?>ms</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p>No endpoint data available for the selected time window</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Content: Alert History -->
<div id="content-alerts" class="tab-content hidden">
    <!-- Alert Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="stat-label">Total Alerts</div>
            <div class="stat-value text-gray-600 dark:text-gray-400"><?php echo $alertStats['total']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">P0 (Critical)</div>
            <div class="stat-value text-red-600 dark:text-red-400"><?php echo $alertStats['p0_count']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">P1 (High)</div>
            <div class="stat-value text-yellow-600 dark:text-yellow-400"><?php echo $alertStats['p1_count']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unresolved</div>
            <div class="stat-value text-orange-600 dark:text-orange-400"><?php echo $alertStats['unresolved']; ?></div>
        </div>
    </div>

    <div class="stat-card">
        <h2 class="text-xl font-bold sk-text-primary mb-6">Recent Alerts</h2>

        <?php if (!empty($recentAlerts)): ?>
            <div class="space-y-3">
                <?php foreach ($recentAlerts as $alert): ?>
                <div class="alert-row <?php echo empty($alert['resolved_at']) ? 'border-l-4 border-red-500' : ''; ?>">
                    <div class="flex-shrink-0 mt-1">
                        <?php if ($alert['severity'] === 'p0'): ?>
                            <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        <?php elseif ($alert['severity'] === 'p1'): ?>
                            <div class="w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase <?php
                                echo $alert['severity'] === 'p0' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' :
                                    ($alert['severity'] === 'p1' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300');
                            ?>">
                                <?php echo strtoupper($alert['severity']); ?>
                            </span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <?php echo htmlspecialchars($alert['alert_type']); ?>
                            </span>
                            <?php if (empty($alert['resolved_at'])): ?>
                                <span class="px-2 py-1 rounded text-xs font-bold bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                                    UNRESOLVED
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    RESOLVED
                                </span>
                            <?php endif; ?>
                        </div>

                        <p class="text-sm text-gray-700 dark:text-gray-200 mb-2">
                            <?php echo htmlspecialchars($alert['message']); ?>
                        </p>

                        <?php if (!empty($alert['details'])): ?>
                        <details class="text-xs">
                            <summary class="cursor-pointer text-blue-600 dark:text-blue-400 hover:underline">View Details</summary>
                            <pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-x-auto"><?php echo htmlspecialchars(json_encode(json_decode($alert['details']), JSON_PRETTY_PRINT)); ?></pre>
                        </details>
                        <?php endif; ?>

                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            <span>Created: <?php echo date('M j, Y g:ia', strtotime($alert['created_at'])); ?></span>
                            <?php if (!empty($alert['notified_at'])): ?>
                                <span>Notified: <?php echo date('M j, Y g:ia', strtotime($alert['notified_at'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($alert['resolved_at'])): ?>
                                <span>Resolved: <?php echo date('M j, Y g:ia', strtotime($alert['resolved_at'])); ?></span>
                            <?php endif; ?>
                        </div>
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
                <p class="text-sm mt-1">System is healthy with no recent alerts</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</section>
</main>

<?php include __DIR__.'/_footer.php'; ?>

<script>
function switchTab(tab) {
    // Update tab buttons
    const tabs = ['endpoints', 'alerts'];
    tabs.forEach(t => {
        const button = document.getElementById(`tab-${t}`);
        const content = document.getElementById(`content-${t}`);

        if (t === tab) {
            button.classList.remove('tab-inactive');
            button.classList.add('tab-active');
            content.classList.remove('hidden');
        } else {
            button.classList.remove('tab-active');
            button.classList.add('tab-inactive');
            content.classList.add('hidden');
        }
    });
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>
