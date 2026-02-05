<?php

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response; // Assuming NGN\Lib\Http\Response is the correct namespace

// Configure session cookie to work across entire site
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Include necessary NGN bootstrap
require_once __DIR__ . '/../../../lib/bootstrap.php';

$_POST = json_decode(file_get_contents('php://input'), true);

$config = new Config();
$response = ['success' => false, 'message' => '', 'redirect' => ''];

// Helper to kill with message
$killWithMessage = function($message, $code = 400) use (&$response) {
    http_response_code($code);
    $response['message'] = $message;
    echo json_encode($response);
    exit;
};

$redirect = $_POST['redirect'] ?? '';
$email = $_POST['email'] ?? $killWithMessage('email is required');
$password = $_POST['password'] ?? $killWithMessage('password is required');

$pdo = null;
try {
    $pdo = ConnectionFactory::read($config);
} catch (\Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    $killWithMessage('Database connection error. Please contact support.', 500);
}

// Fetch user from ngn_2025.users
$user = null;
try {
    $stmt = $pdo->prepare("SELECT id, display_name, email, username, role_id, password_hash FROM `ngn_2025`.`users` WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $killWithMessage('An error occurred while fetching user data.', 500);
}


if (!$user) {
    $killWithMessage('User not found', 404);
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    $killWithMessage('Password is incorrect', 401);
}

// Log in the user (inline logic from old loginUser function)
$sessionUser = [];
$sessionUser['id'] = $user['id'];
$sessionUser['Id'] = $user['id'];
$sessionUser['display_name'] = $user['display_name'];
$sessionUser['Title'] = $user['display_name'];
$sessionUser['email'] = $user['email'];
$sessionUser['Email'] = $user['email'];
$sessionUser['username'] = $user['username'];
$sessionUser['Slug'] = $user['username'];
$sessionUser['role_id'] = $user['role_id'];
$sessionUser['RoleId'] = $user['role_id'];
$sessionUser['logged_in_at'] = date('Y-m-d H:i:s');
$_SESSION['User'] = $sessionUser;
$_SESSION['LoggedIn'] = 1;

// Smart redirect
$maintenance = false;
try {
    $mm = getenv('MAINTENANCE_MODE');
    if ($mm !== false && $mm !== null) {
        $v = strtolower((string)$mm);
        $maintenance = in_array($v, ['1','true','on','yes'], true);
    }
} catch (Throwable $e) { $maintenance = false; }

if (empty($redirect)) {
    $isAdmin = (string)($user['role_id'] ?? '') === '1'; // Check against role_id
    if ($isAdmin || $maintenance) {
        $redirect = $config->baseUrl() . '/admin/index.php';
    } else {
        // Determine redirect based on role ID
        $roleId = (int)($user['role_id'] ?? 0);
        $baseUrl = $config->baseUrl();
        $redirect = match($roleId) {
            3 => $baseUrl . '/dashboard/artist/',
            7 => $baseUrl . '/dashboard/label/',
            4, 15 => $baseUrl . '/dashboard/station/',
            5, 17 => $baseUrl . '/dashboard/venue/',
            default => $baseUrl . '/'
        };
    }
}
$response['success'] = true;
$response['message'] = 'You have been logged in';
$response['redirect'] = $redirect;
echo json_encode($response);