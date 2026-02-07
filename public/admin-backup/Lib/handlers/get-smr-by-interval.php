<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
$_POST = json_decode(file_get_contents("php://input"), true);

// Necessary includes/restorations
require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/ResponseController.php';

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::named($config, 'SMR2025');

// Default date handling
$startDate = $_POST['start_date'];
$endDate = $_POST['end_date'];

$query = 'SELECT * FROM smr_chart WHERE Timestamp BETWEEN :start AND :end';
$smr = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);


// Filter the top 100 per Timestamp based on TWS and LWS
$filteredResults = [];
foreach ($smr as $entry) {
    $timestamp = $entry['Timestamp']; // Extract timestamp from each entry

    if (!isset($filteredResults[$timestamp])) {
        $filteredResults[$timestamp] = [];
    }

    $filteredResults[$timestamp][] = $entry;
}

// Sort and filter for each timestamp
foreach ($filteredResults as $timestamp => &$entries) {
    // Sort entries by TWS and then LWS in descending order
    usort($entries, function ($a, $b) {
        return ($b['TWS'] <=> $a['TWS']) ?: ($b['LWS'] <=> $a['LWS']);
    });

    // Take the top 100 entries for the current timestamp
    $entries = array_slice($entries, 0, 10);
}
unset($entries); // Unset reference to avoid accidental modification

echo json_encode($filteredResults);
