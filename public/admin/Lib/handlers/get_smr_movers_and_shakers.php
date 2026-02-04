<?php

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\Response; // Assuming NGN\Lib\Http\Response is the correct namespace

// Include necessary NGN bootstrap for Config and ConnectionFactory
require_once __DIR__ . '/../../../../lib/bootstrap.php';

$_POST = json_decode(file_get_contents("php://input"), true);

// Create a new Response object if makeResponse is no longer a global helper
$response = new Response();

$startDate = '2024-11-01'; // Use YYYY-MM-DD format for DATE type
$endDate = '2024-11-22';   // Use YYYY-MM-DD format for DATE type

$config = new Config();
$pdoSmr = ConnectionFactory::named($config, 'smr2025'); // Get the smr2025 connection

$query = "SELECT * FROM `ngn_smr_2025`.`smr_chart` WHERE window_date BETWEEN :startDate AND :endDate ORDER BY window_date DESC";
$stmt = $pdoSmr->prepare($query);
$stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

var_dump($results);