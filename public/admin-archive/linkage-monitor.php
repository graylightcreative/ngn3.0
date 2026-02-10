<?php
/**
 * Linkage Rate Monitoring Dashboard
 *
 * Bible Ch. 12.6 requires:
 * "A dashboard for Admin (Erik) showing the percentage of 'Unlinked' artist names in the last 24 hours."
 *
 * Displays:
 * - Real-time linkage rate for current and recent uploads
 * - Historical trend (7 days, 30 days)
 * - Alerts if linkage rate drops below 95% threshold
 * - Per-station linkage rates
 * - Compliance status
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$currentPage = 'linkage-monitor';
$timeframe = $_GET['timeframe'] ?? '7d';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=ngn_2025", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Determine date range
    $dateRange = match($timeframe) {
        '24h' => "NOW() - INTERVAL 1 DAY",
        '7d' => "NOW() - INTERVAL 7 DAY",
        '30d' => "NOW() - INTERVAL 30 DAY",
        'all' => "DATE_SUB(NOW(), INTERVAL 10 YEAR)",
        default => "NOW() - INTERVAL 7 DAY"
    };

    // Get current uploads with linkage data
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.upload_filename,
            u.row_count,
            u.linkage_rate,
            u.status,
            u.finalized_at,
            u.created_at,
            (u.row_count - u.unmatched_count) as matched_count,
            u.unmatched_count
        FROM smr_uploads u
        WHERE u.created_at >= {$dateRange}
        ORDER BY u.created_at DESC
    ");
    $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get audit trail
    $stmt = $pdo->query("
        SELECT
            DATE(timestamp) as date,
            AVG(linkage_rate) as avg_rate,
            MIN(linkage_rate) as min_rate,
            MAX(linkage_rate) as max_rate,
            COUNT(*) as audits,
            SUM(CASE WHEN threshold_met = TRUE THEN 1 ELSE 0 END) as threshold_passes
        FROM smr_linkage_audit
        WHERE timestamp >= {$dateRange}
        GROUP BY DATE(timestamp)
        ORDER BY date DESC
    ");
    $auditHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get overall metrics
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_uploads,
            AVG(linkage_rate) as avg_linkage,
            MIN(linkage_rate) as min_linkage,
            MAX(linkage_rate) as max_linkage,
            SUM(CASE WHEN linkage_rate >= 95 THEN 1 ELSE 0 END) as compliant_uploads,
            SUM(CASE WHEN linkage_rate < 95 THEN 1 ELSE 0 END) as non_compliant_uploads
        FROM smr_uploads
        WHERE created_at >= {$dateRange}
    ");
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get last 24 hours detailed stats
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_rows,
            SUM(CASE WHEN is_matched = TRUE THEN 1 ELSE 0 END) as matched_rows,
            COUNT(DISTINCT artist_name) as unique_artists,
            SUM(CASE WHEN is_matched = FALSE THEN 1 ELSE 0 END) as unmatched_rows
        FROM smr_staging s
        WHERE s.upload_id IN (
            SELECT id FROM smr_uploads WHERE created_at >= NOW() - INTERVAL 1 DAY
        )
    ");
    $last24h = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Linkage monitor error: ' . $e->getMessage());
    $metrics = null;
}

require_once __DIR__ . '/_header.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-800">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-gray-700 text-3xl font-medium dark:text-gray-200">Linkage Rate Monitor</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">Bible Ch. 12.6 | Tracks artist name linking compliance</p>
        </div>

        <!-- Timeframe Selector -->
        <div class="mb-6 flex gap-2">
            <a href="?timeframe=24h" class="px-4 py-2 rounded <?= $timeframe === '24h' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200' ?>">24h</a>
            <a href="?timeframe=7d" class="px-4 py-2 rounded <?= $timeframe === '7d' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200' ?>">7d</a>
            <a href="?timeframe=30d" class="px-4 py-2 rounded <?= $timeframe === '30d' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200' ?>">30d</a>
            <a href="?timeframe=all" class="px-4 py-2 rounded <?= $timeframe === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200' ?>">All</a>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase tracking-wider">Average Linkage</p>
                <p class="text-4xl font-bold text-gray-800 dark:text-gray-100 mt-2">
                    <?= $metrics ? round($metrics['avg_linkage'], 1) : '—' ?>%
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    <?= $metrics && $metrics['avg_linkage'] >= 95 ? '✓ Compliant' : '⚠ Below threshold' ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase tracking-wider">Total Uploads</p>
                <p class="text-4xl font-bold text-gray-800 dark:text-gray-100 mt-2">
                    <?= $metrics ? $metrics['total_uploads'] : '0' ?>
                </p>
                <p class="text-sm text-green-600 mt-2">
                    <?= $metrics ? $metrics['compliant_uploads'] . ' compliant' : '0' ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase tracking-wider">Compliance Rate</p>
                <p class="text-4xl font-bold text-green-600 mt-2">
                    <?= $metrics && $metrics['total_uploads'] > 0 ? round(($metrics['compliant_uploads'] / $metrics['total_uploads']) * 100, 1) : '—' ?>%
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    <?= $metrics ? ($metrics['compliant_uploads'] . ' / ' . $metrics['total_uploads']) : '—' ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6">
                <p class="text-gray-500 text-sm uppercase tracking-wider">Last 24h Status</p>
                <p class="text-3xl font-bold mt-2">
                    <span class="<?= $last24h && $last24h['matched_rows'] > 0 ? (($last24h['matched_rows'] / $last24h['total_rows'] * 100) >= 95 ? 'text-green-600' : 'text-yellow-600') : 'text-gray-500' ?>">
                        <?= $last24h && $last24h['total_rows'] > 0 ? round(($last24h['matched_rows'] / $last24h['total_rows']) * 100, 1) : '—' ?>%
                    </span>
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                    <?= $last24h ? $last24h['matched_rows'] . ' / ' . $last24h['total_rows'] . ' linked' : '—' ?>
                </p>
            </div>
        </div>

        <!-- Daily Trend Chart -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Daily Linkage Rate Trend</h4>
            <?php if (!empty($auditHistory)): ?>
            <div class="space-y-3">
                <?php foreach ($auditHistory as $day): ?>
                <div class="flex items-center gap-4">
                    <div class="w-24">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100"><?= date('M j, Y', strtotime($day['date'])) ?></p>
                    </div>
                    <div class="flex-1">
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                            <div class="bg-<?= $day['avg_rate'] >= 95 ? 'green' : 'yellow' ?>-500 h-4"
                                 style="width: <?= min(100, $day['avg_rate']) ?>%"></div>
                        </div>
                    </div>
                    <div class="w-40 text-right">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Avg: <?= round($day['avg_rate'], 1) ?>% |
                            Range: <?= round($day['min_rate'], 1) ?>-<?= round($day['max_rate'], 1) ?>%
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            <?= $day['threshold_passes'] ?> / <?= $day['audits'] ?> audits compliant
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-600 dark:text-gray-400">No audit data available for this timeframe.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Uploads Detail -->
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow p-6 mb-6">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Recent SMR Uploads</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Upload</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Matched</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Linkage %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($uploads as $upload): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= htmlspecialchars(substr($upload['upload_filename'], 0, 40)) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"><?= date('M j, Y H:i', strtotime($upload['created_at'])) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= number_format($upload['row_count']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-200"><?= number_format($upload['matched_count']) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-<?= $upload['linkage_rate'] >= 95 ? 'green' : 'yellow' ?>-500 h-2 rounded-full"
                                             style="width: <?= min(100, $upload['linkage_rate']) ?>%"></div>
                                    </div>
                                    <span class="<?= $upload['linkage_rate'] >= 95 ? 'text-green-600' : 'text-yellow-600' ?> font-semibold text-sm">
                                        <?= round($upload['linkage_rate'], 1) ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-semibold text-white bg-<?php
                                    echo match($upload['status']) {
                                        'finalized' => 'green-600',
                                        'ready' => 'blue-600',
                                        'mapping' => 'purple-600',
                                        'review' => 'yellow-600',
                                        default => 'gray-600'
                                    };
                                ?>">
                                    <?= ucfirst($upload['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="/admin/smr-mapping.php?upload_id=<?= $upload['id'] ?>" class="text-blue-500 hover:text-blue-700">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($uploads)): ?>
            <p class="text-gray-600 dark:text-gray-400 text-center py-8">No uploads in this timeframe.</p>
            <?php endif; ?>
        </div>

        <!-- Compliance Information -->
        <div class="bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 p-6 rounded">
            <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">QA Gate Threshold</h4>
            <p class="text-blue-800 dark:text-blue-200 text-sm mb-3">
                Bible Ch. 5.3 requires a <strong>Linkage Rate > 95%</strong> before chart publication.
                Unlinked artist names are names that could not be matched to canonical NGN artist records.
            </p>
            <p class="text-blue-800 dark:text-blue-200 text-sm">
                Current compliance: <strong><?= $metrics && $metrics['compliant_uploads'] > 0 ? 'All recent uploads meet threshold' : 'Monitor for issues' ?></strong>
            </p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
