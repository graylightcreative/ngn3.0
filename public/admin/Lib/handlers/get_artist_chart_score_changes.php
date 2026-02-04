<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ResponseController.php';
require $root.'admin/lib/definitions/admin-settings.php';

$_POST = json_decode(file_get_contents("php://input"), true);

$response = makeResponse();

if(!$_POST) killWithMessage('No data sent', $response);
$id = !isset($_POST['id']) ? killWithMessage('No ID', $response) : $_POST['id'];


$url = "https://api.nextgennoise.com/rankings/artist_chart_score_changes?id=$id";

try {
    // Initialize the CurlHandler with the URL
    $curlHandler = new CurlHandler($url);

    // Set options for the cURL request
    // Set return transfer as a string of the return value of curl_exec() instead of outputting it out directly
    $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);

    // Execute the request and get the response
    $trends = $curlHandler->execute();

    // Check if the response is false
    if ($trends === false) {
        throw new Exception('Curl error: ' . curl_error($curlHandler->getHandle()));
    }


    // Output or process the response as needed
    $response['content'] = json_decode($trends, true);
    $response['code'] = 200;
    $response['success'] = true;
    $response['message'] = 'Content Loaded Successfully!';
    echo json_encode($response);
} catch (Exception $e) {
    killWithMessage("Error: " . $e->getMessage(), $response);
} finally {
    // Always close the cURL handle
    if (isset($curlHandler)) {
        $curlHandler->close();
    }
}

