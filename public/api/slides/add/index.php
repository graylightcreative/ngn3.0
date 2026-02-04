<?php
$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/ImageController.php';
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
$authorId = $check['user_id']; // author is owner of token that was used for auth


// Retrieve incoming POST data
$postData = json_decode(file_get_contents('php://input'), true);

// Validate the decoded data
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON in request body']);
    exit;
}

$title = $_GET['title'] ?? null;
$url = $_GET['url'] ?? null;
$location = $_GET['location'] ?? 'home';
$start = $_GET['start'] ?? date('Y-m-d H:i:s');
$end = $_GET['end'] ?? date('Y-m-d H:i:s', strtotime('+1 year', strtotime($start)));
$desktopImage = $_FILES['desktop_image'] ?? throw new Exception('Desktop image is required');
$mobileImage = $_FILES['mobile_image'] ?? throw new Exception('Mobile image is required');

$user = read('users', 'id', $authorId);
if(!$user){
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$desktopImage = uploadImages($_FILES['desktop_image'], $root . 'lib/images/slides');
$mobileImage = uploadImages($_FILES['mobile_image'], $root . 'lib/images/slides');

$data = [
    'Title' => $title,
    'Url' => $url,
    'Location' => $location,
    'StartDate' => date('Y-m-d H:i:s', strtotime($start)),
    'EndDate' => date('Y-m-d H:i:s', strtotime($end)),
    'DesktopImage' => $desktopImage,
    'MobileImage' => $mobileImage
];

if(!add('Songs', $data)){
    http_response_code(500);
    echo json_encode(['error' => 'Item could not be added']);
    exit;
}

http_response_code(201);
echo json_encode(['success' => true]);


