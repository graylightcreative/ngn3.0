<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root . 'lib/definitions/site-settings.php';
require $root . 'admin/lib/definitions/admin-settings.php';
require $root . 'lib/controllers/ResponseController.php';
require $root . 'lib/classes/CentralBot.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$config = new Config();
$pdo = ConnectionFactory::write($config);

// Use credentials path from .env (set via bootstrap)
$credPath = $_ENV['NGN_GOOGLE_APPLICATION_CREDENTIALS'] ?? getenv('NGN_GOOGLE_APPLICATION_CREDENTIALS') ?: '';
if (!empty($credPath)) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credPath);
}

$_POST = json_decode(file_get_contents('php://input'), true);
$pageResponse = makeResponse();
$message = $_POST['message'] ?? killWithMessage('Please provide a valid message', $pageResponse);

// Ensure the environment variable for credentials is set
if (!getenv('GOOGLE_APPLICATION_CREDENTIALS') || !file_exists(getenv('GOOGLE_APPLICATION_CREDENTIALS'))) {
    $pageResponse['error'] = 'Google Cloud credentials file not found. Set NGN_GOOGLE_APPLICATION_CREDENTIALS in .env';
    $pageResponse['code'] = 500;
    header('Content-Type: application/json');
    echo json_encode($pageResponse);
    exit;
}

try {
    // Fetch the list of available departments using the preferred method
    $departments = browse('Departments');

    // Fetch the list of available bots using the preferred method
    $bots = browse('Bots');

    // Instantiate CentralBot with departments and bots (no apiData needed)
    $centralBot = new CentralBot($departments, $bots);


    // Assuming the task is the message received
    $assignedBotResponse = $centralBot->analyzeAndDelegate($message);

    if (isset($assignedBotResponse['task']) && isset($assignedBotResponse['bot'])) {
        // Successful response and suitable bot found
        $assignedBot = $assignedBotResponse['bot'];
        $botTitle = $assignedBot['Title'] ?? 'Unknown Bot Title';

        if ($botTitle === 'Unknown Bot Title') {
            error_log("Error: Missing 'Title' key in bot array. Bot details: " . print_r($assignedBot, true));
        }

        $pageResponse['message'] = "Delegating message: '{$message}' to $botTitle.";
        $pageResponse['content'] = $assignedBotResponse;
        $pageResponse['code'] = 200;
    } else {
        // Handle no suitable bot found or an error scenario
        $pageResponse['message'] = $assignedBotResponse['message'] ?? 'No suitable bot found to handle the message.';
        $pageResponse['code'] = 500;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "<br>";
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    $pageResponse['error'] = 'An error occurred: ' . $e->getMessage();
    $pageResponse['code'] = 500;
}

header('Content-Type: application/json');
echo json_encode($pageResponse);