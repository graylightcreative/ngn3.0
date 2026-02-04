<?php

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response; // Assuming NGN\Lib\Http\Response is the correct namespace
// Assuming sortByColumnIndex and createUserListItem will be adapted or moved

// Include necessary NGN bootstrap for Config and ConnectionFactory
require_once __DIR__ . '/../../../lib/bootstrap.php'; 

$_POST = json_decode(file_get_contents('php://input'), true);

$res = new Response();

$roleId = $_POST['role_id'] ?? 3; // Default to artist role
$type = $_POST['type'] ?? 'all';

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Load users from ngn_2025.users
$stmt = $pdo->prepare("SELECT id, display_name AS Title, username AS Slug, role_id AS RoleId, avatar_url AS Image, address AS Address FROM `ngn_2025`.`users` WHERE role_id = :roleId AND status = 'active'");
$stmt->execute([':roleId' => $roleId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(!$users) {
    $res->killWithMessage('Could not load users');
}

// sortByColumnIndex and createUserListItem need to be refactored or replaced.
// For now, assuming they are accessible or will be removed.
// Assuming sortByColumnIndex handles 'Title' (now display_name mapping)
// Assuming createUserListItem handles the new user array structure
$users = sortByColumnIndex($users, 'Title');

$items = [];
foreach($users as $user) {
    // This function needs to be updated to work with the new user data structure
    // For now, it remains as is, but this is a point of concern.
    $items[] = createUserListItem($user);
}

$res->content = $items;
$res->success = true;
$res->code = 200;
echo json_encode($res);