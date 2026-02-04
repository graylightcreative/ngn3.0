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

$spinCheck = readMany('station_spins', 'StationId', $userId);
if ($spinCheck) {
    foreach ($spinCheck as $spin) {
        $spinDate = Carbon::parse($spin['Timestamp']); // Assuming 'Timestamp' is the field for the spin's date
        if ($spinDate->between(Carbon::parse($weekStart), Carbon::parse($weekEnd))) {
            die('This user has already submitted spins for this week.');
        }
    }
}

if (isset($_FILES['spin_file']) && $_FILES['spin_file']['type'] === 'text/csv') {
    if (($handle = fopen($_FILES['spin_file']['tmp_name'], 'r')) !== FALSE) {
        $uploadedSpins = [];
        $headings = fgetcsv($handle, 1000, ','); // Skip first row (headings)
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            list($artist, $song, $spins, $program) = array_pad($data, 4, null);

            // Add validated data to the database
            $add = add('station_spins', [
                'Artist' => $artist,
                'Song' => $song,
                'TWS' => $spins,
                'StationId' => $check['Id'],
                'Program' => $program,
                'Hotlist' => 0
            ]);

            if ($add) {
                $uploadedSpins[] = "{$artist}, {$song}, Spins: {$spins}, Program: {$program}";
            }
        }
        fclose($handle);

        // Prepare and send email to the user
        $emailClass = new Email();
        $emailContent = "<h1>Thank You for Submitting Your Spins</h1>";
        $emailContent .= "<p>We have successfully received the following spins:</p><ul>";
        foreach ($uploadedSpins as $spin) {
            $emailContent .= "<li>" . htmlspecialchars($spin) . "</li>";
        }
        $emailContent .= "</ul>";
        $emailContent .= "<p>We appreciate your participation in NextGen Noise and look forward to seeing your amazing contributions to the community!</p>";
        $emailContent .= "<p>Rock on,</p><p>The NextGen Noise Team</p>";

        $subject = "Your Spins Submission Confirmation";
        $wrappedMail = $emailClass->wrapEmail($subject, $emailContent);

        $e = new Email();
        $e->emailAddress = $check['Email'];
        $e->subject = $subject;
        $e->content = $wrappedMail;
        $userEmail = $e->sendNGNEmail();

        echo 'Thank you for submitting your spins!';
    }
} else {
    echo 'There was an issue with the file you uploaded. Consult the admin.';
}
