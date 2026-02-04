<?php
use Carbon\Carbon;
function handleArtistsandSong($artistAndSong){
	$artistSongSplit = explode(' / ',$artistAndSong);
	$array = false;
	if(!isset($artistSongSplit[1])){
		echo '<h1 style="color:red;">There was an issue handling ' . $artistAndSong .'</h1>';
	} else {
		$array = [];
		$array['Artists'] = $artistSongSplit[0];
		$array['Song'] = $artistSongSplit[1];
	}
	return $array;
}
function getWeekStartEndDates($filename) {
    // Extract year and week number from filename
    echo '<h2>File: ' .$filename.' Becomes:</h2>';
    preg_match('/Week_(\d+)-(\d+)-Top200\.csv/', $filename, $matches);
    $week = $matches[1];
    $year = $matches[2];
    echo "Week {$week} Year {$year}<br>";
    $date = Carbon::now(); // or $date = new Carbon();
    $date->setISODate($year,$week); // 2016-10-17 23:59:59.000000
    return $date;
}
function handleSMRArtists($artists) {
	$artists = strtolower($artists); // Convert to lowercase for easier comparison

	// Split the string by comma first
	$artistList = explode(',', $artists);

	$finalArtistList = [];
    $knownArtists = ['hearts & hand grenades','of mice & men'];

	foreach ($artistList as $artist) {
		$artist = trim($artist); // Trim whitespace

        if(strpos($artist, ' ft. ') !== false){
            // we have artist ft artist
            $subArtists = explode(' ft. ', $artist);
            foreach($subArtists as $subArtist){
                $finalArtistList[] = trim($subArtist);
            }
        } else {
            // If there's an ampersand, check if it's part of a single artist's name
            if (strpos($artist, '&') !== false) {
                // Hypothetical example: Assume you have a list of artist names in an array $knownArtists

                if (in_array($artist, $knownArtists)) {
                    // It's a single artist with '&' in their name
                    $finalArtistList[] = $artist;
                } else {
                    // It's multiple artists separated by '&'
                    $subArtists = explode('&', $artist);
                    foreach ($subArtists as $subArtist) {
                        $finalArtistList[] = trim($subArtist);
                    }
                }
            } else {
                $finalArtistList[] = $artist;
            }
        }

	}


	return $finalArtistList;
}
function handleSMRLabels($labels){
	// labels is a string
	// LABEL/LABEL
	// OR LABEL
	$fullList = [];
	$labels = strtolower($labels);
	if(str_contains($labels, '/')){
		// multiple labels
		$labels = explode('/',$labels);
		foreach($labels as $label){
			$fullList[] = $label;
		}
	} else {
		// single label
		$fullList[] = $labels;
	}

	return $fullList;
}

/**
 * Fetch raw SMR data and apply decay factor to TWS values.
 *
 * @return array Returns the decayed SMR data.
 */
function getDecayedSMRData() {
    global $pdo;

    // Fetch raw data using the browse function from DataController
    $data = browse('smr_chart');

    // Define decay constant (example value, adjust as needed)
    $decayConstant = 0.1; // Decay rate per week

    // Apply decay factor to each entry
    foreach ($data as &$entry) {
        if (isset($entry['TWS']) && isset($entry['WeeksOnChart'])) {
            $weeksOnChart = $entry['WeeksOnChart'];
            $originalTWS = $entry['TWS'];

            // Apply exponential decay
            $decayedTWS = $originalTWS * exp(-$decayConstant * $weeksOnChart);
            $entry['TWS'] = $decayedTWS;
        }
    }

    return $data;
}