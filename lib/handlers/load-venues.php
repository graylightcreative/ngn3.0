<?php

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response; // Assuming NGN\Lib\Http\Response is the correct namespace
// Assuming sortByColumnIndex and createUserListItem will be adapted or moved

// Include necessary NGN bootstrap for Config and ConnectionFactory
require_once __DIR__ . '/../../../lib/bootstrap.php'; 

$_POST = json_decode(file_get_contents('php://input'), true);

$res = new Response();

$roleId = $_POST['role_id'] ?? 11; // Default to venue role
$state = $_POST['state'] ?? 'All';

$config = new Config();
$pdo = ConnectionFactory::read($config);

// Load users from ngn_2025.users
$stmt = $pdo->prepare("SELECT id, display_name AS Title, username AS Slug, role_id AS RoleId, avatar_url AS Image, address AS Address FROM `ngn_2025`.`users` WHERE role_id = :roleId AND status = 'active'");
$stmt->execute([':roleId' => $roleId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if(!$users) {
    $res->killWithMessage('Could not load users');
}

$users = sortByColumnIndex($users, 'Title'); // Assuming sortByColumnIndex handles 'Title' (now display_name mapping)

$items = [];
if($state != 'All'){

    // Load venues by $user['Address'] (State is in string)
    foreach($users as $user)  {
        $address = $user['Address'] ?? false;
        if ($address) {
            $addressParts = explode(',', $address);
            $addressParts = array_map('trim', $addressParts);
            $ourState = end($addressParts);

            if (strlen($ourState) == 2 && ctype_alpha($ourState)) {
                // Likely a US state code
                if ($state == $ourState) {
                    $items[] = createUserListItem($user);
                }
            } else {
                // Check if state matches for non-US addresses or full state names
                if (stripos($address, $state) !== false) {
                    $items[] = createUserListItem($user);
                }
            }
        }
    }
} else {
    foreach($users as $user)  $items[] = createUserListItem($user);
}

$res->content = $items;
$res->success = true;
$res->code = 200;
echo json_encode($res);