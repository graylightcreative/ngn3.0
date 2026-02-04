<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';

header('Content-Type: application/json');

// 1. Authenticate User
function authenticateUser($email, $password)
{
    $stmt = "SELECT Id, PasswordHash FROM users WHERE Email = ?";
    $result = query($stmt, [$email]);

    if ($result && password_verify($password, $result['PasswordHash'])) {
        return $result['Id'];
    }
    return false;
}

// 2. Generate JWT Token
function generateJWTToken($userId, $email)
{
    $secretKey = $_ENV['JWTKEY'];
    if (empty($secretKey)) {
        error_log("JWT_SECRET_KEY environment variable not set!");
        http_response_code(500);
        echo json_encode(['error' => 'JWT_SECRET_KEY environment variable not set']);
        exit;
    }

    $payload = [
        'uid' => $userId,
        'email' => $email,
        'exp' => time() + 3600,
        'iat' => time()
    ];

    try {
        return JWT::encode($payload, $secretKey, 'HS256');
    } catch (Exception $e) {
        error_log("JWT Encoding Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate token']);
        exit;
    }
}

// 3. Login Endpoint Logic

// Retrieve and decode the request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$email = $data['email'];
$password = $data['password'];

// Authenticate user
$userId = authenticateUser($email, $password);

if ($userId === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// Generate JWT
$token = generateJWTToken($userId, $email);

// Store JWT in the database
$storage = [
    'token' => $token,
    'user_id' => $userId,
    'expiration' => date('Y-m-d H:i:s', time() + 3600),
];
if (!add('jwt_tokens', $storage)) {
    http_response_code(500);
    echo json_encode(['error' => 'There was an issue adding the token to the database.']);
    exit;
}

// Retrieve user data
$userData = read('users', 'id', $userId);

if ($userData) {
    // Clean user data
    unset($userData['password']);
    $userData = array_values(array_filter($userData, function ($key) {
        return !is_int($key);
    }, ARRAY_FILTER_USE_KEY));

    echo json_encode(['token' => $token, 'user' => $userData]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}