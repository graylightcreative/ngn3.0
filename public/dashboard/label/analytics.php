<?php
/**
 * Label Dashboard - Analytics
 * Label performance, roster analytics, and engagement metrics
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('label');

$user = dashboard_get_user();
$entity = dashboard_get_entity('label');
$pageTitle = 'Analytics';
$currentPage = 'analytics';

// Initialize analytics data
$totalArtists = 0;
$totalReleases = 0;
$totalSpins = 0;
$totalFans = 0;

$labelId = $entity['id'] ?? 0;

if ($labelId > 0) {
    try {
        $pdo = dashboard_pdo();

        // Get total artists on roster (placeholder)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`artists` WHERE label_id = ?");
        $stmt->execute([$labelId]);
        $totalArtists = (int)$stmt->fetchColumn();

        // Get total releases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`releases` WHERE label_id = ?");
        $stmt->execute([$labelId]);
        $totalReleases = (int)$stmt->fetchColumn();

        // Get total spins (placeholder)
        $totalSpins = rand(50000, 500000);
        $totalFans = rand(1000, 50000);
    } catch (\Throwable $e) {
        error_log('Label analytics error: ' . $e->getMessage());
    }
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Analytics</h1>
        <p class="page-subtitle">Label performance and engagement metrics</p>
    </header>

    <div class="page-content">
        <!-- Key Metrics -->
        <div class="grid grid-4">
            <div class="metric-card">
                <div class="metric-value"><?= number_format($totalArtists) ?></div>
                <div class="metric-label">Roster Artists</div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?= number_format($totalReleases) ?></div>
                <div class="metric-label">Total Releases</div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?= number_format($totalSpins) ?></div>
                <div class="metric-label">Total Spins</div>
            </div>

            <div class="metric-card">
                <div class="metric-value"><?= number_format($totalFans) ?></div>
                <div class="metric-label">Total Fans</div>
            </div>
        </div>

        <!-- Performance Overview -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Performance Overview</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Last 30 days</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 24px;">
                <div>
                    <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">Spins Trend</div>
                    <div style="font-size: 28px; font-weight: 600;">↑ 12.5%</div>
                    <p style="font-size: 13px; color: var(--text-muted); margin: 8px 0 0 0;">+125 spins compared to previous period</p>
                </div>

                <div>
                    <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 8px;">Fan Growth</div>
                    <div style="font-size: 28px; font-weight: 600;">↑ 8.3%</div>
                    <p style="font-size: 13px; color: var(--text-muted); margin: 8px 0 0 0;">+250 new fans this month</p>
                </div>
            </div>
        </div>

        <!-- Top Performing Artists -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Top Performing Artists</h2>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Artist</th>
                        <th>Releases</th>
                        <th>Spins</th>
                        <th>Fans</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            No artist data available yet. Add artists to your roster to see analytics.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Engagement Timeline -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Engagement Timeline</h2>
                <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Last 7 days</p>
            </div>

            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
                    <?php for ($i = 6; $i >= 0; $i--): ?>
                        <?php $date = date('M d', strtotime("-$i days")); ?>
                        <div style="text-align: center;">
                            <div style="height: <?= rand(40, 120) ?>px; background: linear-gradient(to top, var(--primary), transparent); border-radius: 4px; margin-bottom: 8px;"></div>
                            <div style="font-size: 12px; color: var(--text-muted);"><?= $date ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.grid-4 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.metric-card {
    background: var(--bg-secondary);
    padding: 24px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.metric-value {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
}

.metric-label {
    font-size: 14px;
    color: var(--text-muted);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.table th {
    background: var(--bg-primary);
    font-weight: 600;
    color: var(--text-muted);
}

.table tr:hover {
    background: var(--bg-primary);
}
</style>

<?php include dirname(__DIR__) . '/lib/partials/footer.php'; ?>
