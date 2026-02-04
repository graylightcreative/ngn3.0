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
$artistId = $postData['artist_id'] ?? throw new Exception('Artist Id is required');
$labelId = $postData['label_id'] ?? throw new Exception('Label Id is required');
$title = $postData['title'] ?? throw new Exception('Title is required');
$slug = createSlug($title);
$body = $postData['body'] ?? throw new Exception('Body is required');
$tags = $postData['tags'] ?? throw new Exception('Tags are required');
$summary = $postData['summary'] ?? throw new Exception('Summary is required');
$type = $postData['type'] ?? throw new Exception('Type is required');
$releaseDate = $postData['releaseDate'] ?? throw new Exception('Release Date is required');
$genre = $postData['genre'] ?? throw new Exception('Genre is required');
$image = $_FILES['image'] ?? throw new Exception('Image is required');


if ($id) {
    // get post by id
    $item = read('videos', 'id', $id);
    if ($item) {
        $data = [];
        if ($title) {
            $data['Title'] = $title;
            $data['Slug'] = $slug;
        }
        if ($body) $data['Body'] = $body;
        if ($tags) $data['Tags'] = $tags;
        if ($summary) $data['Summary'] = $summary;
        if ($type) $data['Type'] = $type;
        if ($releaseDate) $data['ReleaseDate'] = $releaseDate;
        if($genre) $data['Genre'] = $genre;

        if(empty($data)){
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            exit;
        }
        if (!edit('releases', $id, $data)) {
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



