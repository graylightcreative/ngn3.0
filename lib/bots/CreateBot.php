<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root . 'lib/definitions/site-settings.php';

// Include required controllers and classes
require $root . 'lib/controllers/BotController.php';
require $root . 'lib/controllers/TaskController.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestBody = file_get_contents('php://input');
$params = json_decode($requestBody, true);
$response = null;

header('Content-Type: application/json');

// Log request details for debugging
error_log("Request URI: " . $requestUri);
error_log("Request Method: " . $requestMethod);
error_log("Request Body: " . $requestBody);

try {
    // Handle routing
    switch ($requestMethod) {
        case 'POST':
            if (preg_match('/\/lib\/bots\/create-bot/', $requestUri)) {
                // Call the createBot function
                $response = createBot($params);
            } elseif (preg_match('/\/lib\/bots\/assign-task\/([a-zA-Z0-9_-]+)/', $requestUri, $matches)) {
                // Call the assignTask function
                $botId = $matches[1];
                $response = assignTask($params, $botId);
            } else {
                throw new Exception('Invalid endpoint for POST request');
            }
            break;
        case 'GET':
            if (preg_match('/\/lib\/bots\/get-bot\/([a-zA-Z0-9_-]+)/', $requestUri, $matches)) {
                // Call the getBot function
                $botId = $matches[1];
                $response = getBot($botId);
            } else {
                throw new Exception('Invalid endpoint for GET request');
            }
            break;
        default:
            throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    // Log the exception for debugging
    error_log("Error: " . $e->getMessage());
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
exit();