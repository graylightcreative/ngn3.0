<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'].'/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::named($config, 'SMR2025');

$date = $_GET['date'] ?? null;
if($date) $date = date('Y-m-d', strtotime($date)) . ' 00:00:00';

$latest = $_GET['latest'] ?? null;
if($latest) {
    // the goal of latest is to find the most recent timestamp in the smr database then get all results for that
    $stmt = "SELECT MAX(Timestamp) FROM smr_chart";
    $query = queryByDB($pdo, $stmt, []);
    $date = $query[0]['MAX(Timestamp)'];
}



if ($date) {
    $stmt = "SELECT * FROM smr_chart WHERE Timestamp = ?";
    $query = queryByDB($pdo, $stmt, [$date]);
    if(empty($stmt)) die('Empty');
    echo json_encode($query, true);
} else {
    $items = browseByDB($pdo,'smr_chart');
    echo json_encode($items, true);
}



