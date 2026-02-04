<?php
$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// Check for Bearer Token
$headers = getallheaders();
if (!isset($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authorization token is required']);
    exit;
}

$token = $matches[1];
// Validate the token (replace this with actual validation logic)
$check = read('jwt_tokens','token',$token);
if (!$check) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid token']);
    exit;
}
$expiration = $check['expiration'];
if(strtotime($expiration) < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Token expired']);
    exit;
}

$id = $_GET['id'] ?? null;



if ($id) {
    // get post by id
    $post = read('releases', 'id', $id);
    if ($post) {
        if (!delete('releases', $id)) {
            http_response_code(400); // Not Found
            echo json_encode(['error' => 'Unknown error occurred while attempting to delete item']);
            exit;
        }
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Id parameter is required']);
}



