<?php

/**
 * admin/templates/artist/score-breakdown-template.php
 * Template for displaying an artist's core score factors.
 * Assumes $artistData is passed, containing:
 *  - artist: { Id, Name }
 *  - smr_score: Artist's SMR Score
 *  - social_score: Artist's Social Score
 *  - views_score: Artist's Views Score
 *  - spins: Artist's Spins Factor (as per prompt request)
 *  - plays: Artist's Plays Factor (as per prompt request)
 *  - views: Artist's Views Factor (as per prompt request)
 *  - posts: Artist's Posts Factor (as per prompt request)
 */

// Placeholder data if $artistData is not provided externally
if (!isset($artistData)) {
    $artistData = [
        'artist' => ['Id' => 1, 'Name' => 'Test Artist'],
        'smr_score' => 85.5,
        'social_score' => 78.2,
        'views_score' => 91.0,
        'spins' => 1500,
        'plays' => 2000,
        'views' => 50000,
        'posts' => 15
    ];
}

$artistName = $artistData['artist']['Name'] ?? 'Unknown Artist';
$smrScore = $artistData['smr_score'] ?? '--';
$socialScore = $artistData['social_score'] ?? '--';
$viewsScore = $artistData['views_score'] ?? '--';

// Factors as requested: spins, plays, views, posts
$spinsFactor = $artistData['spins'] ?? '--';
$playsFactor = $artistData['plays'] ?? '--';
$viewsFactor = $artistData['views'] ?? '--';
$postsFactor = $artistData['posts'] ?? '--';

?>

<div class="admin-widget">
    <div class="widget-header">
        <h3 class="widget-title">Core Factors & Scores</h3>
    </div>
    <div class="widget-body p-4">
        <div class="mb-4 pb-4 border-b border-gray-200 dark:border-white/10">
            <h4 class="text-base font-semibold mb-2">Key Scores</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">SMR Score</span>
                    <span class="font-medium sk-text-primary"><?= htmlspecialchars($smrScore) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Social Score</span>
                    <span class="font-medium sk-text-primary"><?= htmlspecialchars($socialScore) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Views Score</span>
                    <span class="font-medium sk-text-primary"><?= htmlspecialchars($viewsScore) ?></span>
                </div>
            </div>
        </div>

        <div>
            <h4 class="text-base font-semibold mb-2">Input Factors</h4>
            <div class="overflow-x-auto">
                <table class="table-auto w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Factor</th>
                            <th class="px-4 py-2 text-center">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-4 py-2 border-b">Spins</td>
                            <td class="px-4 py-2 border-b text-center"><?= htmlspecialchars($spinsFactor) ?></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 border-b">Plays</td>
                            <td class="px-4 py-2 border-b text-center"><?= htmlspecialchars($playsFactor) ?></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 border-b">Views</td>
                            <td class="px-4 py-2 border-b text-center"><?= htmlspecialchars($viewsFactor) ?></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 border-b">Posts</td>
                            <td class="px-4 py-2 border-b text-center"><?= htmlspecialchars($postsFactor) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
