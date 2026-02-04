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
$title = $postData['title'] ?? throw new Exception('Title is required');
$summary = $postData['summary'] ?? throw new Exception('Summary is required');
$tags = $postData['tags'] ?? throw new Exception('Tags are required');
$body = $postData['body'] ?? throw new Exception('Body is required');
$published = $postData['published'] ?? throw new Exception('Published is required');
$featured = $postData['featured'] ?? throw new Exception('Featured is required');
$type = $postData['type'] ?? throw new Exception('Type is required');
$isUser = $postData['isUser'] ?? throw new Exception('IsUser is required');
$publishedDate = $postData['publishedDate'] ?? throw new Exception('PublishedDate is required');

$user = read('users', 'id', $authorId);
if(!$user){
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}



// Check if 'image' file exists in the uploaded files
if (empty($_FILES) || !isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Image is required and must be successfully uploaded']);
    exit;
}

if((bool)$isUser){
    // this is a user's post so we have to put the image in the user's (slug) folder
    $path = $root . 'lib/images/posts/'.$user['Slug'];
    $image = uploadImages($_FILES['image'], $path);
    if(!$image) {
        http_response_code(500);
        echo json_encode(['error' => 'Image could not be uploaded']);
        exit;
    }
}
$image = $_FILES['image'];
$data = [
    'Title' => $title,
    'Slug' => createSlug($title),
    'Summary' => $summary,
    'Tags' => $tags,
    'Body' => $body,
    'TypeId' => $type,
    'Published' => $published,
    'Featured' => $featured,
    'Author' => $authorId,
    'Image'  => $image,
    'PublishedDate' => $publishedDate,
    'IsUser' => $isUser,
];

if(!add('posts', $data)){
    http_response_code(500);
    echo json_encode(['error' => 'Post could not be added']);
    exit;
}

http_response_code(201);
echo json_encode(['success' => true]);


