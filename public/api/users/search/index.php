<?php

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

require_once __DIR__ . '/../../../lib/bootstrap.php'; // Use bootstrap for environment loading

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}
$title = $_GET['title'] ?? die('no title');

$config = new Config();
$pdo = ConnectionFactory::read($config);

if ($title) {
    // get user by title from ngn_2025.users
    $stmt = $pdo->prepare("SELECT id, display_name AS Title, email AS Email, username AS Slug, role_id AS RoleId FROM `ngn_2025`.`users` WHERE LOWER(display_name) LIKE ? LIMIT 1");
    $stmt->execute(['%'.strtolower($title).'%']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // No need to unset password, as it's not selected
    if ($user) {
        echo json_encode([$user]);
    } else {
        echo json_encode([]);
    }
}



