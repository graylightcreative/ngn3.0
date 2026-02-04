<?php

include 'SMRController.php';
include 'NGNController.php';

/// Include necessary controller files (SMRController and NGNController).

function getTopContent($limit=3){
    // Step 1: Retrieve aggregated views data.
    $views = aggregateViews();

    // Step 2: Process the aggregated views data to generate scores.
    $processedData = processViewDataToScores($views);

    // Step 3: Retrieve artist rankings data.
    $artists = browse('NGNArtistRankings');

    // Step 4: Retrieve label rankings data.
    $labels = browse('NGNLabelRankings');

    // Step 5: Retrieve SMR data.
    $smrData = getDecayedSMRData();

    // Step 6: Create a mapping of artist names to their IDs. (Assume a function or a database query to get these mappings.)
    // Step 7: Create a mapping of label names to their IDs. (Assume a function or a database query to get these mappings.)
    $artistTitleToIdMap = [];
    $LabelTitleToIdMap = [];
    foreach($smrData as $smrEntry){
        $smrArtists = handleSMRArtists($smrEntry['Artists']);
        $smrLabels = handleSMRLabels($smrEntry['Label']);
        foreach($smrArtists as $smrArtist){
            $a = read('users','Title', ucwords($smrArtist));
            if($a){
                $artistTitleToIdMap[$a['Id']]['Title'] = $smrArtist;
                $artistTitleToIdMap[$a['Id']]['SMR_Score'] = $smrEntry['TWS'];
            }
        }
        foreach($smrLabels as $smrLabel){
            $l = read('users','Title', ucwords($smrLabel));
            if($l){
                $labelTitleToIdMap[$l['Id']]['Title'] = $smrLabel;
                $labelTitleToIdMap[$l['Id']]['SMR_Score'] = $smrEntry['TWS'];
            }
        }
    }

    // Step 8: Sort the processed data by score in descending order (already sorted in processViewDataToScores).
    $topPosts = array_slice($processedData, 0, $limit);

    // Step 9: Initialize an array to hold top artists data.
    $topArtists = [];

    // Step 10: Loop through artist mappings and compute combined scores.
    foreach ($artistTitleToIdMap as $id => $artist) {

        foreach ($processedData as $data) {
            if ($data['item']['ItemId'] == $id) {
                $artistTitleToIdMap[$id]['Combined_Score'] += $data['score'];
                $artistTitleToIdMap[$id]['Original_Score'] = $data['score']; // Store original score data
            } else {
                $artistTitleToIdMap[$id]['Combined_Score'] = $artist['SMR_Score'];
                $artistTitleToIdMap[$id]['Original_SMR_Score'] = $artist['SMR_Score']; // Store original SMR score
            }
        }
        $topArtists[] = $artistTitleToIdMap[$id];
    }

    // Step 11: Initialize an array to hold top labels data.
    $topLabels = [];
    foreach ($labelTitleToIdMap as $id => $label) {
        $labelTitleToIdMap[$id]['Combined_Score'] = $label['SMR_Score'];
        foreach ($processedData as $data) {
            if ($data['item']['ItemId'] == $id) {
                $labelTitleToIdMap[$id]['Combined_Score'] += $data['score'];
                $labeltTitleToIdMap[$id]['Original_Score'] = $data['score']; // Store original score data

            }
        }
        $topLabels[] = $labelTitleToIdMap[$id];
    }

    // Step 16: Return an associative array containing top posts, top artists, and top labels.
    return [
        'topPosts' => array_values($topPosts),
        'topArtists' => $topArtists,
        'topLabels' => $topLabels
    ];
}