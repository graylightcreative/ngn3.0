<?php

/**
 * admin/templates/artist/ranking-trend-template.php
 * Template for displaying an artist's ranking trend.
 * Assumes $artistData is passed, containing:
 *  - artist: { Id, Name }
 *  - rankingHistory: [ { Timestamp, Rank, Score }, ... ] sorted by Timestamp for daily interval.
 */

// Placeholder data if $artistData is not provided externally
if (!isset($artistData)) {
    $artistData = [
        'artist' => ['Id' => 1, 'Name' => 'Test Artist'],
        'rankingHistory' => [
            ['Timestamp' => '2023-01-01', 'Rank' => 50, 'Score' => 1200],
            ['Timestamp' => '2023-01-02', 'Rank' => 48, 'Score' => 1250],
            ['Timestamp' => '2023-01-03', 'Rank' => 55, 'Score' => 1180],
            ['Timestamp' => '2023-01-04', 'Rank' => 45, 'Score' => 1300],
            ['Timestamp' => '2023-01-05', 'Rank' => 46, 'Score' => 1290],
        ]
    ];
}

$artistName = $artistData['artist']['Name'] ?? 'Unknown Artist';
$rankingHistory = $artistData['rankingHistory'] ?? [];
$artistId = $artistData['artist']['Id'] ?? 'unknown';

// NOTE: The actual rendering of a chart would typically involve JavaScript (e.g., Chart.js).
// This PHP template provides the HTML structure and placeholders for that JS.
?>

<div class="admin-widget">
    <div class="widget-header">
        <h3 class="widget-title">Ranking Trend (Daily)</h3>
    </div>
    <div class="widget-body p-4">
        <?php if (!empty($rankingHistory)):
            // For basic display, a table is used. A real dashboard would use a chart library.
        ?>
            <div class="overflow-x-auto mb-4">
                <table class="table-auto w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-center">Rank</th>
                            <th class="px-4 py-2 text-center">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankingHistory as $entry):
                            // Escape output to prevent XSS
                            $timestamp = htmlspecialchars($entry['Timestamp'] ?? 'N/A');
                            $rank = htmlspecialchars($entry['Rank'] ?? 'N/A');
                            $score = htmlspecialchars($entry['Score'] ?? 'N/A');
                        ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?= $timestamp ?></td>
                                <td class="px-4 py-2 border-b text-center"><?= $rank ?></td>
                                <td class="px-4 py-2 border-b text-center"><?= $score ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php /* 
            // Placeholder for Chart.js integration:
            // This section demonstrates where a JS chart would be initialized.
            // It assumes Chart.js is available globally and the component's data is passed correctly.
            */ ?>
            <div class="h-64 w-full bg-gray-200 dark:bg-white/10 rounded flex items-center justify-center text-gray-500 dark:text-gray-400">
                <canvas id="artistRankingChart-<?= htmlspecialchars($artistId) ?>"></canvas>
            </div>
            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400 flex justify-center gap-4">
                <span>Daily</span>
                <span>Weekly</span>
                <span>Yearly</span>
            </div>
            <?php /*
            <script>
                // Ensure the DOM is fully loaded before initializing the chart
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('artistRankingChart-<?= htmlspecialchars($artistId) ?>');
                    if (ctx) {
                        const chartData = {
                            labels: <?= json_encode(array_column($rankingHistory, 'timestamp')) ?>,
                            datasets: [{
                                label: 'Ranking',
                                data: <?= json_encode(array_column($rankingHistory, 'rank')) ?>,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1,
                                fill: false
                            }]
                        };
                        new Chart(ctx, {
                            type: 'line',
                            data: chartData,
                            options: {
                                scales: {
                                    y: { reverse: true } // Rank is usually higher when number is lower
                                },
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                    }
                });
            </script>
            */ ?>
        <?php else:
            // Message if no data is available
        ?>
            <p class="text-center text-gray-500">No ranking trend data available for <?= htmlspecialchars($artistName) ?>.</p>
        <?php endif; ?>
    </div>
</div>
