<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/SMRController.php';

$config = new Config();
$smr_pdo = ConnectionFactory::named($config, 'SMR2025');

if (!isset($_FILES['smrdata'])) die('The file smrdata is not set.');
if (!isset($_POST['smr_date'])) die('no date set.');

$smrdata = $_FILES['smrdata'];
$date = date('Y-m-d H:i:s', strtotime($_POST['smr_date']));

if ($smrdata['error'] !== UPLOAD_ERR_OK || $smrdata['type'] !== 'text/csv') {
    die('Failed to upload. Make sure the file is a CSV and try again.');
}
$uploadedContent = file_get_contents($smrdata['tmp_name']);
$csvRows = str_getcsv($uploadedContent, "\n", '"', '\\');
$csvData = [];
foreach ($csvRows as $row) {
    $csvData[] = str_getcsv($row, ",", '"', '\\');
}
$check = readByDB($smr_pdo, 'smr_chart', 'Timestamp', $date);
if ($check) die('Data already exists for this date.');

if (count($csvData) > 0) {
    $headers = array_shift($csvData);

    foreach ($csvData as $row) {
        // Prepare variables to insert into the database
        $data = [
            'Artists' => ucwords(strtolower($row[array_search('ARTIST', $headers)])),
            'Song' => ucwords(strtolower($row[array_search('TITLE', $headers)])),
            'Label' => ucwords(strtolower($row[array_search('LABEL', $headers)])),
            'WOC' => is_numeric($row[array_search('WKS ON', $headers)]) ? $row[array_search('WKS ON', $headers)] : 0,
            'LWP' => is_numeric($row[array_search('LW POS', $headers)]) ? $row[array_search('LW POS', $headers)] : 0,
            'TWP' => is_numeric($row[array_search('TW POS', $headers)]) ? $row[array_search('TW POS', $headers)] : 0,
            'Timestamp' => $date,
            'Date' => $date,
            'Peak' => is_numeric($row[array_search('PEAK', $headers)]) ? $row[array_search('PEAK', $headers)] : 0,
            'TWS' => is_numeric($row[array_search('TW SPIN', $headers)]) ? $row[array_search('TW SPIN', $headers)] : 0,
            'LWS' => is_numeric($row[array_search('LW SPIN', $headers)]) ? $row[array_search('LW SPIN', $headers)] : 0,
            'Difference' => is_numeric($row[array_search('(+/-)', $headers)]) ? $row[array_search('(+/-)', $headers)] : 0,
            'Adds' => is_numeric($row[array_search('ADDS', $headers)]) ? $row[array_search('ADDS', $headers)] : 0,
            'StationsOn' => is_numeric($row[array_search('STATIONS ON', $headers)]) ? $row[array_search('STATIONS ON', $headers)] : 0
        ];

//    var_dump($data);
//    echo '<hr>';
        addByDB($smr_pdo, 'smr_chart', $data);

    }
} else {
    echo 'No data found in the CSV.';
}
