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
$slug = $_GET['slug'] ?? null;
$artistId = $_GET['aid'] ?? null;
$labelId = $_GET['lid'] ?? null;
$stationId = $_GET['sid'] ?? null;
$venueId = $_GET['vid'] ?? null;
$type = $_GET['type'] ?? null;

if ($type) {
    // get posts filtered by 'TypeId' field matching $type
    $posts = readMany('posts', 'type_id', $type);
    echo json_encode($posts, true);
} else if ($id) {
    // get posts by id
    $item = read('posts', 'id', $id);
    $item = array_filter($item, function ($key) {
        return !is_numeric($key);
    }, ARRAY_FILTER_USE_KEY);
    echo json_encode($item, true);
} else if ($slug) {
    // get single post by slug
    $post = read('posts', 'slug', $slug);
    echo json_encode($post, true);
} else if ($artistId || $labelId || $stationId || $venueId) {
    // get posts by corresponding author type id
    $authorId = $artistId ?? $labelId ?? $stationId ?? $venueId;
    $posts = readMany('posts', 'author', $authorId);
    echo json_encode($posts, true);
} else {
    // get all posts if no filters are set
    $items = browse('posts');
    echo json_encode($items, true);
}



