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
$title = $_GET['title'] ?? null;
$videoId = $_GET['video_id'] ?? null;
$summary = $_GET['summary'] ?? null;
$tags = $_GET['tags'] ?? null;
$body = $_GET['body'] ?? null;
$published = $_GET['published'] ?? null;
$featured = $_GET['featured'] ?? null;
$author = $_GET['author'] ?? null;
$releaseDate = $_GET['releaseDate'] ?? null;
$platform = $_GET['platform'] ?? null;


if ($id) {
    // get post by id
    $item = read('videos', 'id', $id);
    if ($item) {
        $data = [];
        if ($title) $data['Title'] = $title;
        if ($summary) $data['Summary'] = $summary;
        if ($tags) $data['Tags'] = $tags;
        if ($body) $data['Body'] = $body;
        if ($published) $data['Published'] = $published;
        if ($featured) $data['Featured'] = $featured;
        if ($author) $data['Author'] = $author;
        if ($videoId) $data['VideoId'] = $videoId;
        if ($platform) $data['Platform'] = $platform;
        if ($releaseDate) $data['ReleaseDate'] = $releaseDate;
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



