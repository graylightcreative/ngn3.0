<?php
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
$_POST = json_decode(file_get_contents("php://input"), true);

// Necessary includes/restorations
require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/ResponseController.php';

$r = makeResponse();

// Initialize database connection
$config = new Config();
$pdo = ConnectionFactory::write($config);

// Default date handling
$startDate = $_POST['start_date'];
$endDate = $_POST['end_date'];

$rankingsCollection = [];
$query = 'SELECT * FROM ranking_items WHERE type = "artist" AND timestamp BETWEEN :start AND :end';
$response = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);
if(!empty($response)) $rankingsCollection = array_merge($rankingsCollection,$response);

$query = 'SELECT * FROM ranking_items WHERE type = "artist" AND interval = "daily" AND timestamp BETWEEN :start AND :end';
$response = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);
if(!empty($response)) $rankingsCollection = array_merge($rankingsCollection,$response);

$query = 'SELECT * FROM ranking_items WHERE type = "artist" AND interval = "weekly" AND timestamp BETWEEN :start AND :end';
$response = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);
if(!empty($response)) $rankingsCollection = array_merge($rankingsCollection,$response);

$query = 'SELECT * FROM ranking_items WHERE type = "artist" AND interval = "monthly" AND timestamp BETWEEN :start AND :end';
$response = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);
if(!empty($response)) $rankingsCollection = array_merge($rankingsCollection,$response);

$query = 'SELECT * FROM ranking_items WHERE type = "artist" AND interval = "yearly" AND timestamp BETWEEN :start AND :end';
$response = queryByDB($pdo,$query,['start'=>$startDate,'end'=>$endDate]);
if(!empty($response)) $rankingsCollection = array_merge($rankingsCollection,$response);

foreach($rankingsCollection as $key=>$ranking) {
    $artist = read('users','id',$ranking['artist_id']);
    if($artist) $rankingsCollection[$key]['Artist'] = $artist['Title'];

}

$r['success'] = true;
$r['code'] = 200;
$r['message'] = 'Entries successfully retrieved';
$r['content'] = $rankingsCollection;
echo json_encode($r);