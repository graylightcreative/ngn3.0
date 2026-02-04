<?php

$root = $_SERVER['DOCUMENT_ROOT'];
require $root . '/lib/definitions/site-settings.php';

$userId = isset($_POST['user_id']) ? $_POST['user_id'] : die('No user ID provided.');
// is this a radio station?
$check = read('users', 'Id', $userId);
if (!$check and !$check['RoleId'] === 9) {
    die('This is not a radio station');
}

if (isset($_FILES['spin_file']) && $_FILES['spin_file']['type'] === 'text/csv') {
    if (($handle = fopen($_FILES['spin_file']['tmp_name'], 'r')) !== FALSE) {
        echo 'You have uploaded the following data:<br><ul>';
        // Skip the first row (headings)
        $headings = fgetcsv($handle, 1000, ',');
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            list($artist, $song, $spins) = $data;
            // Add code to validate and insert $artist, $song, $spins into the database
            echo "<li>{$artist}, {$song}, {$spins}</li>";
            // we must find our artist id
            $a = read('users', 'Title', ucwords($artist));
            if ($a) {
                $add = add('station_spins', [
                    'ArtistId' => $a['Id'],
                    'SongTitle' => $song,
                    'TWS' => $spins,
                    'StationId' => $check['Id']
                ]);
                if($add){
                    echo '<li>'.$artist. ', ' . $song . ', ' . $spins . '</li>';
                }
            } else {
                die('Could not find artist ' . $artist);
            }

        }
        echo '</ul>';
        fclose($handle);
    }
} else {
    echo 'there was an issue with the file you uploaded. Consult the admin';
}
