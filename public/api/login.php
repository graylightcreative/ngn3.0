<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Env;

require_once __DIR__ . '/../../lib/bootstrap.php'; // Use bootstrap for environment loading

header('Content-Type: application/json');

Env::load(dirname(__DIR__, 3)); // Load environment variables from project root
$config = new Config();
$pdo = ConnectionFactory::write($config); // Use write connection for authentication and token storage

// 1. Authenticate User
function authenticateUser(PDO $pdo, $email, $password)
{
    $stmt = $pdo->prepare("SELECT id, password_hash FROM `ngn_2025`.`users` WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        return $user['id'];
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
$userId = authenticateUser($pdo, $email, $password);

if ($userId === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// Generate JWT
$token = generateJWTToken($userId, $email);

// Store JWT in the database
$stmt = $pdo->prepare("INSERT INTO `ngn_2025`.`jwt_tokens` (user_id, token, expiration) VALUES (:user_id, :token, :expiration)");
$success = $stmt->execute([
    ':user_id' => $userId,
    ':token' => $token,
    ':expiration' => date('Y-m-d H:i:s', time() + 3600),
]);
if (!$success) {
    http_response_code(500);
    echo json_encode(['error' => 'There was an issue adding the token to the database.']);
    exit;
}

// Retrieve user data
$stmt = $pdo->prepare("SELECT id, email, display_name FROM `ngn_2025`.`users` WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userData) {
    echo json_encode(['token' => $token, 'user' => $userData]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}