<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/ResponseController.php';
require $root . 'admin/lib/definitions/admin-settings.php';

$config = new Config();
$pdo = ConnectionFactory::write($config);

$response = makeResponse();

function getArtistPopularityTrend($artist)
{
    $artistId = $artist['Id'];

    // Function calls for specific trend calculation
    $trendScore = calculateCurrentTrend($artistId);
    $trendScoreDaily = calculateDailyTrend($artistId);
    $trendScoreWeekly = calculateWeeklyTrend($artistId);
    $trendScoreMonthly = calculateMonthlyTrend($artistId);
    $trendScoreYearly = calculateYearlyTrend($artistId);

    // Aggregate score with potential weightings
    $trendScore = ($trendScore * 0.5) +
        ($trendScoreDaily * 0.4) +
        ($trendScoreWeekly * 0.3) +
        ($trendScoreMonthly * 0.2) +
        ($trendScoreYearly * 0.1);

    return [
        'trend_score' => $trendScore,
        'metric_timestamps' => [
            'current' => getLatestTimestamp('ranking_items', $artistId, null),
            'daily' => getLatestTimestamp('ranking_items', $artistId, 'daily'),
            'weekly' => getLatestTimestamp('ranking_items', $artistId, 'weekly'),
            'monthly' => getLatestTimestamp('ranking_items', $artistId, 'monthly'),
            'yearly' => getLatestTimestamp('ranking_items', $artistId, 'yearly')
        ]
    ];
}

function calculateCurrentTrend($artistId)
{
    global $pdo;
    // Example SQL query for daily calculations
    $nodes = queryByDB($pdo,"SELECT score, timestamp FROM ranking_items WHERE artist_id = ? AND type = 'artist' ORDER BY timestamp DESC", [$artistId]);
    return calculateScoreDifference($nodes, .5);
}

function calculateDailyTrend($artistId)
{
    global $pdo;
    // Example SQL query for daily calculations
    $nodes = queryByDB($pdo,"SELECT score, timestamp FROM ranking_items WHERE artist_id = ? AND type = 'artist' AND interval = 'daily' ORDER BY timestamp DESC", [$artistId]);
    return calculateScoreDifference($nodes, .4);
}

function calculateWeeklyTrend($artistId)
{
    global $pdo;
    // Example SQL query for daily calculations
    $nodes = queryByDB($pdo,"SELECT score, timestamp FROM ranking_items WHERE artist_id = ? AND type = 'artist' AND interval = 'weekly' ORDER BY timestamp DESC", [$artistId]);
    return calculateScoreDifference($nodes, .3);
}

function calculateMonthlyTrend($artistId)
{
    global $pdo;
    // Example SQL query for daily calculations
    $nodes = queryByDB($pdo,"SELECT score, timestamp FROM ranking_items WHERE artist_id = ? AND type = 'artist' AND interval = 'monthly' ORDER BY timestamp DESC", [$artistId]);
    return calculateScoreDifference($nodes, .2);
}

function calculateYearlyTrend($artistId)
{
    global $pdo;
    // Example SQL query for daily calculations
    $nodes = queryByDB($pdo,"SELECT score, timestamp FROM ranking_items WHERE artist_id = ? AND type = 'artist' AND interval = 'yearly' ORDER BY timestamp DESC", [$artistId]);
    return calculateScoreDifference($nodes, .1);
}

function getLatestTimestamp($tableName, $artistId, $interval = null)
{
    global $pdo;
    // Example SQL query to get the latest timestamp
    if ($interval) {
        $result = queryByDB($pdo,"SELECT MAX(timestamp) as latest_timestamp FROM $tableName WHERE artist_id = ? AND interval = ? AND type = 'artist'", [$artistId, $interval]);
    } else {
        $result = queryByDB($pdo,"SELECT MAX(timestamp) as latest_timestamp FROM $tableName WHERE artist_id = ? AND type = 'artist'", [$artistId]);
    }

    // Check if the query returned any result
    if ($result && count($result) > 0) {
        return $result[0]['latest_timestamp'];
    }

    // Return null if no results were found
    return null;
}

function calculateScoreDifference($nodes, $weightFactor)
{
    if (count($nodes) > 1) {
        $latestScore = $nodes[0]['Score'];
        $previousScore = $nodes[1]['Score'];
        $scoreDifference = $latestScore - $previousScore;

        // Apply a weight factor if needed
        return $scoreDifference * $weightFactor;
    }
    return 0;
}

function getArtistsPopularityTrend($artists) {
    $artistsTrends = [];

    foreach ($artists as $artist) {
        $result = getArtistPopularityTrend($artist);
        $trendScore = $result['trend_score'];
        $metricTimestamps = $result['metric_timestamps'];
        $artistId = $artist['Id'];

        // Ensures calculation_timestamp is always set
        $currentCalculationTimestamp = date('Y-m-d H:i:s');

        $artistsTrends[$artistId] = [
            'name' => $artist['Title'],
            'trend_score' => $trendScore,
            'calculation_timestamp' => $currentCalculationTimestamp, // Add current timestamp as calculation timestamp
            'metric_timestamps' => $metricTimestamps
        ];
    }

    return $artistsTrends;
}
