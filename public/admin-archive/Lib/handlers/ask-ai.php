<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
$_POST = json_decode(file_get_contents("php://input"), true);

// Necessary includes/restorations
require $root . 'lib/definitions/site-settings.php';
require $root . 'lib/controllers/ResponseController.php';

$r = makeResponse();

$instructions = !isset($_POST['instructions']) ? killWithMessage('No instructions provided', $r) : $_POST['instructions'];
$prompt = !isset($_POST['prompt']) ? killWithMessage('No prompt provided', $r) : $_POST['prompt'];

$url = "https://ai.nextgennoise.com/automation/tester";

// Prepare payload for the POST request
$data = [
    'instructions' => $instructions,
    'prompt' => $prompt
];

// Initialize the CurlHandler

try {
    $curl = new CurlHandler($url);

    // Set CURL options for the POST request
    $curl->setOption(CURLOPT_RETURNTRANSFER, true);
    $curl->setOption(CURLOPT_POST, true);
    $curl->setOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $curl->setOption(CURLOPT_POSTFIELDS, json_encode($data));

    // Execute the request
    $response = $curl->execute();

    // Close the CURL connection
    $curl->close();

    // Process and output the response
    $r['content'] = $response;
    echo json_encode($r);

} catch (RuntimeException $e) {
    // Handle CURL errors
    killWithMessage("Error: " . $e->getMessage(), $r);
}