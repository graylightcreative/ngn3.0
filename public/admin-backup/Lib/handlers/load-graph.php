<?php

$root = $_SERVER['DOCUMENT_ROOT'] . '/';

require $root.'lib/definitions/site-settings.php';
require $root.'lib/controllers/ResponseController.php';
require $root.'admin/lib/definitions/admin-settings.php';

$response = makeResponse();

$url = "https://api.nextgennoise.com/rankings/load_graph";

try {
    // Initialize the CurlHandler with the URL
    $curlHandler = new CurlHandler($url);

    // Set options for the cURL request
    // Set return transfer as a string of the return value of curl_exec() instead of outputting it out directly
    $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true);

    // Execute the request and get the response
    $items = $curlHandler->execute();

    // Check if the response is false
    if ($items === false) {
        throw new Exception('Curl error: ' . curl_error($curlHandler->getHandle()));
    }


    // Output or process the response as needed
    $response['content'] = json_decode($items, true);
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

