<?php

function getRisingScore($artist,$startDate,$endDate){
    $artistId = $artist['Id'];
    $artistName = $artist['Title'];
    $risingScore = 0; // Initialize the rising score for the artist

// 1. Ranking Score Increase Rate
    $rankingData = getRankingsData('Artists', $startDate, $endDate, $artistId); // Replace with the appropriate function for fetching ranking data
    if (count($rankingData) > 2) { // Need at least 3 data points to calculate rates
        usort($rankingData, function ($a, $b) {
            return strtotime($a['Timestamp']) - strtotime($b['Timestamp']);
        });

        $latestRanking = $rankingData[count($rankingData) - 1];
        $previousRanking = $rankingData[count($rankingData) - 2];
        $olderRanking = $rankingData[count($rankingData) - 3];

        $scoreIncreaseRecent = $latestRanking['Score'] - $previousRanking['Score'];
        $scoreIncreasePrevious = $previousRanking['Score'] - $olderRanking['Score'];

        if ($scoreIncreaseRecent > 0 && $scoreIncreasePrevious > 0) {
            $increaseRate = $scoreIncreaseRecent / max($scoreIncreasePrevious, 1); // Prevent divide by zero
            if ($increaseRate > 1.5) { // Example threshold
                $risingScore += $increaseRate * 5; // Example weight
            }
        }
    }

// 2. Views Increase Rate
    $viewsData = getViewsData($artistId, $startDate, $endDate); // Replace with your function for fetching views data
    if (count($viewsData) > 2) {
        usort($viewsData, function ($a, $b) {
            return strtotime($a['Timestamp']) - strtotime($b['Timestamp']);
        });

        $currentPeriodViews = sumViews($viewsData, strtotime('-30 days'), time());
        $previousPeriodViews = sumViews($viewsData, strtotime('-60 days'), strtotime('-30 days'));
        $olderPeriodViews = sumViews($viewsData, strtotime('-90 days'), strtotime('-60 days'));

        $viewsIncreaseRecent = $currentPeriodViews - $previousPeriodViews;
        $viewsIncreasePrevious = $previousPeriodViews - $olderPeriodViews;

        if ($viewsIncreaseRecent > 0 && $viewsIncreasePrevious > 0) {
            $viewsIncreaseRate = $viewsIncreaseRecent / max($viewsIncreasePrevious, 1); // Prevent divide by zero
            if ($viewsIncreaseRate > 1.2) { // Example threshold
                $risingScore += $viewsIncreaseRate * 2; // Example weight
            }
        }
    }

// 3. Spins Increase Rate
    $spinsData = getSpinsData($artistName, $startDate, $endDate); // Replace with your function for fetching spins data
    if (count($spinsData) > 2) {
        usort($spinsData, function ($a, $b) {
            return strtotime($a['Timestamp']) - strtotime($b['Timestamp']);
        });

        $currentPeriodSpins = sumSpins($spinsData, strtotime('-30 days'), time());
        $previousPeriodSpins = sumSpins($spinsData, strtotime('-60 days'), strtotime('-30 days'));
        $olderPeriodSpins = sumSpins($spinsData, strtotime('-90 days'), strtotime('-60 days'));

        $spinsIncreaseRecent = $currentPeriodSpins - $previousPeriodSpins;
        $spinsIncreasePrevious = $previousPeriodSpins - $olderPeriodSpins;

        if ($spinsIncreaseRecent > 0 && $spinsIncreasePrevious > 0) {
            $spinsIncreaseRate = $spinsIncreaseRecent / max($spinsIncreasePrevious, 1); // Prevent divide by zero
            if ($spinsIncreaseRate > 1.3) { // Example threshold
                $risingScore += $spinsIncreaseRate * 5; // Example weight
            }
        }
    }

// 4. Post Mentions Increase Rate
    $mentionsData = getMentionsData($artistId, $startDate, $endDate); // Replace with your function for fetching mentions data
    if (count($mentionsData) > 2) {
        usort($mentionsData, function ($a, $b) {
            return strtotime($a['PublishedDate']) - strtotime($b['PublishedDate']);
        });

        $currentPeriodMentions = countMentions($mentionsData, strtotime('-30 days'), time());
        $previousPeriodMentions = countMentions($mentionsData, strtotime('-60 days'), strtotime('-30 days'));
        $olderPeriodMentions = countMentions($mentionsData, strtotime('-90 days'), strtotime('-60 days'));

        $mentionsIncreaseRecent = $currentPeriodMentions - $previousPeriodMentions;
        $mentionsIncreasePrevious = $previousPeriodMentions - $olderPeriodMentions;

        if ($mentionsIncreaseRecent > 0 && $mentionsIncreasePrevious > 0) {
            $mentionsIncreaseRate = $mentionsIncreaseRecent / max($mentionsIncreasePrevious, 1); // Prevent divide by zero
            if ($mentionsIncreaseRate > 1.1) { // Example threshold
                $risingScore += $mentionsIncreaseRate * 2; // Example weight
            }
        }
    }

    return $risingScore;
}



function sumViews($viewsData, $startTime, $endTime)
{
    return array_sum(array_column(array_filter($viewsData, function ($view) use ($startTime, $endTime) {
        $timestamp = strtotime($view['Timestamp']);
        return $timestamp >= $startTime && $timestamp <= $endTime;
    }), 'ViewCount'));
}

function sumSpins($spinsData, $startTime, $endTime)
{
    return array_sum(array_column(array_filter($spinsData, function ($spin) use ($startTime, $endTime) {
        $timestamp = strtotime($spin['Timestamp']);
        return $timestamp >= $startTime && $timestamp <= $endTime;
    }), 'TWS'));
}

function countMentions($mentionsData, $startTime, $endTime)
{
    return count(array_filter($mentionsData, function ($mention) use ($startTime, $endTime) {
        $timestamp = strtotime($mention['PublishedDate']);
        return $timestamp >= $startTime && $timestamp <= $endTime;
    }));
}

