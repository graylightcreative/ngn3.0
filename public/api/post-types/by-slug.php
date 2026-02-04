<?php
$root = $_SERVER['DOCUMENT_ROOT'].'/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

$slug = $_GET['slug'] ?? null;

if ($slug) {
    // get post by slug
    $post = read('posts', 'slug', $slug);
    if ($post) {
        $post = array_filter($post, function ($key) {
            return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);
        echo json_encode($post, true);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Post not found']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Slug parameter is required']);
}



