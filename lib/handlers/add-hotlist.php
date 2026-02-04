<?php

$root = $_SERVER['DOCUMENT_ROOT'];
require $root . '/lib/definitions/site-settings.php';
use Carbon\Carbon;
$userId = isset($_POST['user_id']) ? $_POST['user_id'] : die('No user ID provided.');
// is this a radio station?
$check = read('users', 'Id', $userId);
if (!$check and !$check['RoleId'] === 9) {
    die('This is not a radio station');
}

$weekStart = Carbon::now()->startOfWeek()->toDateString();
$weekEnd = Carbon::now()->endOfWeek()->toDateString();

$spinCheck = read('station_spins', 'StationId', $userId);
if ($spinCheck) {
    foreach ($spinCheck as $spin) {
        if($spin['Hotlist'] === 1){
            $spinDate = Carbon::parse($spin['Timestamp']); // Assuming 'Timestamp' is the field for the spin's date
            if ($spinDate->between(Carbon::parse($weekStart), Carbon::parse($weekEnd))) {
                die('This user has already submitted spins for this week.');
            }
        }

    }
}


if (isset($_FILES['spin_file']) && $_FILES['spin_file']['type'] === 'text/csv') {
    if (($handle = fopen($_FILES['spin_file']['tmp_name'], 'r')) !== FALSE) {
        echo 'You have uploaded the following data:<br><ul>';
        // Skip the first row (headings)
        $headings = fgetcsv($handle, 1000, ',');
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            list($artist, $song, $spins, $program) = $data;

            // Add code to validate and insert $artist, $song, $spins into the database

            $add = add('station_spins', [
                'Artist' => $artist,
                'Song' => $song,
                'TWS' => $spins,
                'StationId' => $check['Id'],
                'Program' => $program,
                'Hotlist' => 1
            ]);
            if($add){
                echo '<li>'.$artist. ', ' . $song . ', ' . $spins . '</li>';
            }

        }
        echo '</ul>';
        fclose($handle);
    }
} else {
    echo 'there was an issue with the file you uploaded. Consult the admin';
}
