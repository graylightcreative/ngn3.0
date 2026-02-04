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
$labelId = $_GET['lid'] ?? null;
$roleId = $_GET['rid'] ?? null;

$type = 'users';
if ($id) {
    // get user by id
    $item = read($type, 'id', $id);
    unset($item['Password']);
    $item = array_filter($item, function ($key) {
        return !is_numeric($key);
    }, ARRAY_FILTER_USE_KEY);
    echo json_encode([$item], true);
} else if ($labelId) {

    $artists = array_map(function ($artist) {
        unset($artist['Password']);
        return array_filter($artist, function ($key) {
            return !str_contains(strtolower($key), 'password') && !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);
    }, readMany('users', 'label_id', $labelId));
    echo json_encode($artists, true);
} else if ($roleId) {
    // get all users by role
    $items = array_map(function ($item) {
        unset($item['Password']);
        return array_filter($item, function ($key) {
            return !str_contains(strtolower($key), 'password') && !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);
    }, readMany($type, 'role_id', $roleId));
    echo json_encode($items, true);

} else if ($slug) {
    $item = read($type, 'slug', $slug);
    unset($item['Password']);
    echo json_encode($item, true);
} else {
    $items = array_map(function ($item) {
        unset($item['Password']);
        return $item;
    }, browse($type));
    echo json_encode($items, true);
}



