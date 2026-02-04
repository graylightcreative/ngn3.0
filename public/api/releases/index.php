<?php
$root = $_SERVER['DOCUMENT_ROOT'].'/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

$id = $_GET['id'] ?? null;
$slug = $_GET['slug'] ?? null;
$artistId = $_GET['aid'] ?? null;
$labelId = $_GET['lid'] ?? null;


$type = 'releases';
if ($id) {
    // get posts by id
    $item = read($type, 'id', $id);
    $item = array_filter($item, function ($key) {
        return !is_numeric($key);
    }, ARRAY_FILTER_USE_KEY);
    echo json_encode($item, true);
} else if ($artistId || $labelId) {
    if ($artistId) {
        $items = readMany($type, 'artist_id', $artistId);
        echo json_encode($items, true);
    } else if ($labelId) {
        $artists = readMany('users','label_id', $labelId);
        $collection = [];
        if($artists){
            foreach($artists as $artist){
                $items = readMany($type, 'artist_id', $artist['id']);
                if($items) $collection = array_merge($collection, $items);
            }
        }
        echo json_encode($collection, true);
    }
} else if($slug){
    $item = read($type, 'slug', $slug);
    echo json_encode($item, true);
} else {
    $items = browse($type);
    echo json_encode($items, true);
}



