<?php



function analyzeArtistScoreChanges($artistId, $startDate, $endDate) {
    /*
     * Analyzes how an artist's score changes over time within a given date interval.
     * Uses the convertDate function to ensure proper date format.
     *
     * Parameters:
     *  - $artistId: The ID of the artist
     *  - $startDate: Start date of the interval
     *  - $endDate: End date of the interval
     *  - $databaseConnection: The database connection
     *
     * Returns:
     *  - An array of score changes filtered by the given interval
     */

    // Convert dates to standardized formats using convertDate
    $startDateFormatted = convertDate($startDate);
    $endDateFormatted = convertDate($endDate);

    // Retrieve artist ranking data, filtered by the date range and sorted by timestamp

    $current = getRankingsData('Artists', $startDateFormatted, $endDateFormatted, $artistId);
    $daily = getRankingsData('ArtistsDaily', $startDateFormatted, $endDateFormatted, $artistId);
    $weekly = getRankingsData('ArtistsWeekly', $startDateFormatted, $endDateFormatted, $artistId);
    $monthly = getRankingsData('ArtistsMonthly', $startDateFormatted, $endDateFormatted, $artistId);
    $yearly = getRankingsData('ArtistsYearly', $startDateFormatted, $endDateFormatted, $artistId);


    $rankings = array_merge(
        $current !== false ? $current : [],
        $daily !== false ? $daily : [],
        $weekly !== false ? $weekly : [],
        $monthly !== false ? $monthly : [],
        $yearly !== false ? $yearly : []
    );


    // Initialize variables
    $scoreChanges = [];
    $previousScore = null;

    // Loop through each ranking to calculate score changes
    foreach ($rankings as $ranking) {
        $currentScore = $ranking['Score'];
        if ($previousScore !== null && $currentScore !== null) {
            // Calculate the change in score
            $scoreChange = $currentScore - $previousScore;
            $scoreChanges[] = [
                'timestamp' => $ranking['Timestamp'],  // Use original timestamp
                'score_change' => $scoreChange
            ];
        }
        $previousScore = $currentScore; // Update the previous score for the next iteration
    }

    return $scoreChanges;
}
