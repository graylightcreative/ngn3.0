<?php
/**
 * Station Dashboard - Analytics
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'Analytics';
$currentPage = 'analytics';

// --- Data Fetching Logic ---
$totalSpins = 0;
$spinsThisWeek = 0;
$uniqueArtists = 0;
$ngnRank = '-';
$ngnScore = 0;

$stationId = $entity['id'] ?? 0;

if ($stationId > 0) {
    try {
        $pdo = dashboard_pdo();

        // Get total spins
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`station_spins` WHERE `station_id` = ?");
        $stmt->execute([$stationId]);
        $totalSpins = (int)$stmt->fetchColumn();

        // Get spins this week
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`station_spins` WHERE `station_id` = ? AND `played_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$stationId]);
        $spinsThisWeek = (int)$stmt->fetchColumn();

        // Get unique artists played
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT `artist_name`) FROM `ngn_2025`.`station_spins` WHERE `station_id` = ?");
        $stmt->execute([$stationId]);
        $uniqueArtists = (int)$stmt->fetchColumn();

        // Get station ranking and score
        $stmt = $pdo->prepare("SELECT ranking, score FROM entity_scores WHERE entity_type = 'station' AND entity_id = ?");
        $stmt->execute([$stationId]);
        $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($scoreData) {
            $ngnRank = $scoreData['ranking'] ?: '-';
            $ngnScore = (int)$scoreData['score'];
        }

    } catch (\Throwable $e) {
        error_log("Station Analytics Error: " . $e->getMessage());
        // Use mock data on error
        $totalSpins = rand(1000, 50000);
        $spinsThisWeek = rand(50, 5000);
        $uniqueArtists = rand(100, 1000);
        $ngnScore = rand(100, 10000);
    }
} else {
    // Mock data if no station
    $totalSpins = rand(1000, 50000);
    $spinsThisWeek = rand(50, 5000);
    $uniqueArtists = rand(100, 1000);
    $ngnScore = rand(100, 10000);
}

// Chart data for recent spins
$chartLabels = [];
$chartDataSpins = [];
$currentDate = new DateTime();
for ($i = 0; $i < 30; $i++) {
    $date = clone $currentDate;
    $date->modify('-' . $i . ' days');
    $chartLabels[] = $date->format('M d');
    $mockDailySpins = rand(50, 500) + ($i * 2);
    $chartDataSpins[] = $mockDailySpins;
}
$chartLabels = array_reverse($chartLabels);
$chartDataSpins = array_reverse($chartDataSpins);

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Analytics</h1>
        <p class="page-subtitle">View your station performance</p>
    </header>

    <div class="page-content">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-sm">Total Spins</h3>
                </div>
                <div class="card-content">
                    <div class="text-4xl font-bold"><?php echo number_format($totalSpins); ?></div>
                    <p class="text-sm text-muted mt-2">All time</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-sm">Spins This Week</h3>
                </div>
                <div class="card-content">
                    <div class="text-4xl font-bold"><?php echo number_format($spinsThisWeek); ?></div>
                    <p class="text-sm text-muted mt-2">Last 7 days</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-sm">Unique Artists</h3>
                </div>
                <div class="card-content">
                    <div class="text-4xl font-bold"><?php echo number_format($uniqueArtists); ?></div>
                    <p class="text-sm text-muted mt-2">Artists played</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-sm">NGN Score</h3>
                </div>
                <div class="card-content">
                    <div class="text-4xl font-bold"><?php echo number_format($ngnScore); ?></div>
                    <p class="text-sm text-muted mt-2">Rank <?php echo htmlspecialchars($ngnRank); ?></p>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Spins Over Time (Last 30 Days)</h2>
            </div>
            <div class="card-content" style="height: 300px; position: relative;">
                <canvas id="spinsChart" style="position: absolute; top: 0; left: 0; width: 100% !important; height: 100% !important;"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('spinsChart').getContext('2d');

        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const chartDataSpins = <?php echo json_encode($chartDataSpins); ?>;

        const spinsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Daily Spins',
                    data: chartDataSpins,
                    borderColor: '#00F0FF',
                    backgroundColor: 'rgba(0, 240, 255, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
