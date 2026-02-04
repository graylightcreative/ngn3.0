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

$id = $_GET['id'] ?? null;
$title = $_GET['title'] ?? null;
$url = $_GET['url'] ?? null;
$location = $_GET['location'] ?? 'home';
$start = $_GET['start'] ?? date('Y-m-d H:i:s');
$end = $_GET['end'] ?? date('Y-m-d H:i:s', strtotime('+1 year', strtotime($start)));
$desktopImage = $_GET['desktopImage'] ?? throw new Exception('Desktop image is required');
$mobileImage = $_GET['mobileImage'] ?? throw new Exception('Mobile image is required');


$type = 'Slides';

if ($id) {
    // get post by id
    $item = read('videos', 'id', $id);
    if ($item) {
        $data = [];
        if ($title) $data['Title'] = $title;
        if ($url) $data['Url'] = $url;
        if ($location) $data['Location'] = $location;
        if ($start) $data['StartDate'] = date('Y-m-d H:i:s', strtotime($start));
        if ($end) $data['EndDate'] = date('Y-m-d H:i:s', strtotime($end));

        $desktop = uploadImages($desktopImage, $GLOBALS['baseurl'].'lib/images/slides');
        if(!$desktop) throw new Exception('Desktop image upload failed');
        $data['DesktopImage'] = $desktop;
        $mobile = uploadImages($mobileImage, $GLOBALS['baseurl'].'lib/images/slides');
        if(!$mobile) throw new Exception('Mobile image upload failed');
        $data['MobileImage'] = $mobile;

        if(empty($data)){
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            exit;
        }
        if (!edit('videos', $id, $data)) {
            http_response_code(400); // Not Found
            echo json_encode(['error' => 'There was an issue editing this item']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $id]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Id parameter is required']);
}



