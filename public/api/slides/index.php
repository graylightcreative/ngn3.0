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
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$limit = $_GET['limit'] ?? null;


$type = 'Slides';
if ($id) {
    // get posts by id
    $item = read($type, 'id', $id);
    $item = array_filter($item, function ($key) {
        return !is_numeric($key);
    }, ARRAY_FILTER_USE_KEY);
    echo json_encode($item, true);
} else {
    $items = browse($type);
    echo json_encode($items, true);
}



