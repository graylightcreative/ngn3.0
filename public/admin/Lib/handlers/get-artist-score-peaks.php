<?php

//$root = $_SERVER['DOCUMENT_ROOT'] . '/';

//require $root.'lib/definitions/site-settings.php';
//require $root.'lib/controllers/ResponseController.php';
//require $root.'admin/lib/definitions/admin-settings.php';

//$_POST = json_decode(file_get_contents("php://input"), true);

//$startDate = !isset($_REQUEST['start_date']) ? '' : $_REQUEST['start_date'];
//$endDate = !isset($_REQUEST['end_date']) ? 'Today' : $_REQUEST['end_date'];
//$id = !isset($_REQUEST['id']) ? 1286 : $_REQUEST['id'];
//
//$response = makeResponse();

//function getRankingsData($table,$intervalStart,$intervalEnd,$artistId)
//{
//    $query = "SELECT Score, Timestamp FROM $table WHERE ArtistId = ? AND Timestamp BETWEEN ? AND ? ORDER BY Timestamp ASC";
//    return query($query,[$artistId,$intervalStart,$intervalEnd]);
//}

function analyzeArtistScorePeaks($artistId, $startDate, $endDate) {
    /*
     * Tracks the peak scores an artist reaches on different charts within a date interval
     * and across various time intervals such as daily, weekly, monthly, yearly.
     */

    // Convert the start and end dates using the existing convertDate function
    $startDateFormatted = convertDate($startDate);
    $endDateFormatted = convertDate($endDate);

    // Retrieve rankings from different interval tables
    $current = getRankingsData('Artists', $startDateFormatted, $endDateFormatted, $artistId);
    $daily = getRankingsData('ArtistsDaily', $startDateFormatted, $endDateFormatted, $artistId);
    $weekly = getRankingsData('ArtistsWeekly', $startDateFormatted, $endDateFormatted, $artistId);
    $monthly = getRankingsData('ArtistsMonthly', $startDateFormatted, $endDateFormatted, $artistId);
    $yearly = getRankingsData('ArtistsYearly', $startDateFormatted, $endDateFormatted, $artistId);

    // Initialize the chart peaks structure
    $chartPeaks = [];

    // Calculate peaks for current interval (NGN Chart)
    if ($current !== false && count($current) > 0) {
        $ngnScores = array_column($current, 'score'); // Extract all scores
        $chartPeaks['NGN'] = [
            'highest_score' => max($ngnScores), // Maximum score
            'score_count' => count($ngnScores)  // Total number of scores
        ];
    }

    // Calculate peaks for each historical interval (daily, weekly, monthly, yearly)
    $intervals = [
        'Daily' => $daily,
        'Weekly' => $weekly,
        'Monthly' => $monthly,
        'Yearly' => $yearly
    ];

    foreach ($intervals as $intervalName => $data) {
        if ($data !== false && count($data) > 0) {
            $scores = array_column($data, 'score'); // Extract all scores
            $chartPeaks[$intervalName] = [
                'highest_score' => max($scores), // Maximum score
                'score_count' => count($scores)  // Total number of scores
            ];
        }
    }

    return $chartPeaks;
}

//echo json_encode(analyzeArtistScorePeaks($id, $startDate, $endDate));