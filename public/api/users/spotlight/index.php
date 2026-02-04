<?php

require_once __DIR__ . '/../../../config/bootstrap.php'; // Use bootstrap for environment loading

use NGN\Config;
use NGN\Lib\DB\ConnectionFactory;

header('Content-Type: application/json');

$config = Config::getInstance();
$pdo = ConnectionFactory::read($config);

$roleId = $_GET['rid'] ?? null;
$users = [];
$query = "SELECT id, display_name AS title FROM `ngn_2025`.`users` WHERE spotlight = 1";
$params = [];

if ($roleId && is_numeric($roleId)) {
    $query .= " AND role_id = ?";
    $params[] = (int)$roleId;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No need to unset password, as it's not selected
echo json_encode($users);



