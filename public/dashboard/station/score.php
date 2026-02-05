<?php
/**
 * Station Dashboard - NGN Score
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

dashboard_require_auth();
dashboard_require_entity_type('station');

$user = dashboard_get_user();
$entity = dashboard_get_entity('station');
$pageTitle = 'NGN Score';
$currentPage = 'score';

// Fetch score data
$scoreData = ['score' => 0, 'rank' => '-', 'prev_rank' => '-'];
$scoreBreakdown = [];

$stationId = $entity['id'] ?? 0;

if ($stationId > 0) {
    try {
        $pdo = dashboard_pdo();

        // Get station score and rank
        $stmt = $pdo->prepare("SELECT score, ranking as rank FROM entity_scores WHERE entity_type = 'station' AND entity_id = ?");
        $stmt->execute([$stationId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $scoreData['score'] = (int)$data['score'];
            $scoreData['rank'] = $data['rank'] ?: '-';
        }

        // Get content counts for breakdown
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `ngn_2025`.`station_spins` WHERE `StationId` = ?");
        $stmt->execute([$stationId]);
        $totalSpins = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT `ArtistName`) FROM `ngn_2025`.`station_spins` WHERE `StationId` = ?");
        $stmt->execute([$stationId]);
        $uniqueArtists = (int)$stmt->fetchColumn();

        $scoreBreakdown = [
            ['factor' => 'Radio Spins', 'points' => min($totalSpins, 1000), 'max' => 1000],
            ['factor' => 'Unique Artists', 'points' => min($uniqueArtists, 500), 'max' => 500],
            ['factor' => 'Profile Complete', 'points' => 100, 'max' => 100],
            ['factor' => 'Activity', 'points' => 50, 'max' => 100],
            ['factor' => 'Engagement', 'points' => 25, 'max' => 50],
        ];

    } catch (\Throwable $e) {
        error_log("Station Score Error: " . $e->getMessage());
        $scoreData = ['score' => 0, 'rank' => '-', 'prev_rank' => '-'];
    }
}

if (empty($scoreBreakdown)) {
    $scoreBreakdown = [
        ['factor' => 'Radio Spins', 'points' => 0, 'max' => 1000],
        ['factor' => 'Unique Artists', 'points' => 0, 'max' => 500],
        ['factor' => 'Profile Complete', 'points' => 0, 'max' => 100],
        ['factor' => 'Activity', 'points' => 0, 'max' => 100],
        ['factor' => 'Engagement', 'points' => 0, 'max' => 50],
    ];
}

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">NGN Score</h1>
        <p class="page-subtitle">Your station ranking and performance breakdown</p>
    </header>

    <div class="page-content">
        <!-- Main Score Card -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="card-title">Station Ranking</h2>
            </div>
            <div class="card-content">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="text-5xl font-bold text-primary"><?php echo number_format($scoreData['score']); ?></div>
                        <p class="text-sm text-muted mt-2">NGN Score</p>
                    </div>
                    <div class="text-center">
                        <div class="text-5xl font-bold text-primary">#<?php echo htmlspecialchars($scoreData['rank']); ?></div>
                        <p class="text-sm text-muted mt-2">Current Rank</p>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary"><?php echo htmlspecialchars($scoreData['prev_rank']); ?></div>
                        <p class="text-sm text-muted mt-2">Previous Rank</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score Breakdown -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Score Breakdown</h2>
            </div>
            <div class="card-content">
                <div class="space-y-6">
                    <?php foreach ($scoreBreakdown as $item): ?>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="font-medium"><?php echo htmlspecialchars($item['factor']); ?></span>
                            <span class="text-sm text-muted"><?php echo number_format($item['points']); ?> / <?php echo number_format($item['max']); ?></span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-gradient-to-r from-cyan-400 to-magenta-500 h-2 rounded-full" style="width: <?php echo ($item['points'] / $item['max'] * 100); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Score Factors Explanation -->
        <div class="card mt-8">
            <div class="card-header">
                <h3 class="card-title">How Your Score is Calculated</h3>
            </div>
            <div class="card-content">
                <div class="space-y-4 text-sm">
                    <p>
                        <strong>Radio Spins:</strong> The number of songs your station has spun. More spins increase your score.
                    </p>
                    <p>
                        <strong>Unique Artists:</strong> The diversity of artists you play. Playing a wider variety of artists boosts your score.
                    </p>
                    <p>
                        <strong>Profile Complete:</strong> How completely filled out your station profile is.
                    </p>
                    <p>
                        <strong>Activity:</strong> Recent activity and engagement on your station.
                    </p>
                    <p>
                        <strong>Engagement:</strong> Interaction with listeners and the NGN community.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
