<?php

require_once __DIR__ . '/../../../../lib/bootstrap.php'; // Bootstrap NGN environment

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response as NGNResponse; // Alias to avoid conflict

$config = new Config();
$pdo = ConnectionFactory::write($config); // Use ngn_2025 connection

$_POST = json_decode(file_get_contents("php://input"), true);
$response = new NGNResponse(); // Use aliased Response class

$title = $_POST['title'] ?? $response->kill('title is required');
$source = $_POST['source'] ?? $response->kill('source is required');
$type = $_POST['type'] ?? $response->kill('type is required');

// Search for user by title
$checkUserStmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`users` WHERE LOWER(display_name) = :title LIMIT 1");
$checkUserStmt->execute([':title' => strtolower($title)]);
$checkUser = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
if($checkUser) $response->kill('user with that name already exists');

$checkArtistStmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`artists` WHERE LOWER(name) = :title LIMIT 1");
$checkArtistStmt->execute([':title' => strtolower($title)]);
$checkArtist = $checkArtistStmt->fetch(PDO::FETCH_ASSOC);
if($checkArtist) $response->kill('artist with that name already exists');

$checkLabelStmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`labels` WHERE LOWER(name) = :title LIMIT 1");
$checkLabelStmt->execute([':title' => strtolower($title)]);
$checkLabel = $checkLabelStmt->fetch(PDO::FETCH_ASSOC);
if($checkLabel) $response->kill('label with that name already exists');

switch($type) {
    case 'artist':
        $roleId = 3;
        break;
    case 'label':
        $roleId = 7;
        break;
}
$data = [
    'display_name' => ucwords($title),
    'source' => $source,
    'role_id' => $roleId,
    'username' => createSlug($title),
    'email' => createSlug($title).'@nextgennoise.com',
    'password_hash' => password_hash('NextGen1!', PASSWORD_DEFAULT),
    'status' => 'active',
];

try {
    // Insert into ngn_2025.users
    $insertUserStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`users` (email, password_hash, display_name, username, role_id, status) VALUES (:email, :password_hash, :display_name, :username, :role_id, :status)");
    $insertUserStmt->execute([
        ':email' => $data['email'],
        ':password_hash' => $data['password_hash'],
        ':display_name' => $data['display_name'],
        ':username' => $data['username'],
        ':role_id' => $data['role_id'],
        ':status' => $data['status'],
    ]);
    $newUserId = $pdo->lastInsertId();
    if (!$newUserId) $response->kill('error adding user to central users table');

    // Insert into specific entity table (artists or labels)
    if ($type === 'artist') {
        $insertEntityStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`artists` (id, user_id, slug, name) VALUES (:id, :user_id, :slug, :name)");
        $insertEntityStmt->execute([
            ':id' => $newUserId,
            ':user_id' => $newUserId,
            ':slug' => $data['username'],
            ':name' => $data['display_name'],
        ]);
        if (!$pdo->lastInsertId()) $response->kill('error adding artist entity');
    } elseif ($type === 'label') {
        $insertEntityStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`labels` (id, user_id, slug, name) VALUES (:id, :user_id, :slug, :name)");
        $insertEntityStmt->execute([
            ':id' => $newUserId,
            ':user_id' => $newUserId,
            ':slug' => $data['username'],
            ':name' => $data['display_name'],
        ]);
        if (!$pdo->lastInsertId()) $response->kill('error adding label entity');
    } else {
        $response->kill('unsupported user type for entity creation');
    }

    $response->success = true;
    $response->message = 'User added successfully';
    echo json_encode($response);
    exit;
} catch (\Throwable $e) {
    $response->kill('database error: ' . $e->getMessage());
}