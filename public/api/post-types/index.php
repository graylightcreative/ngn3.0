<?php
$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

$id = $_GET['id'] ?? null;
$section = $_GET['section'] ?? null;


if ($section) {
    // get posts filtered by 'TypeId' field matching $type
    $types = readMany('PostTypes', 'section', $section);
    echo json_encode($types, true);
} else if ($id) {
    // get posts by id
    $item = read('PostTypes', 'id', $id);
    $item = array_filter($item, function ($key) {
        return !is_numeric($key);
    }, ARRAY_FILTER_USE_KEY);
    echo json_encode($item, true);
} else {
    // get all posts if no filters are set
    $items = browse('PostTypes');
    echo json_encode($items, true);
}



