<?php
// admin/testing/lib/mailchimp_test.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/bootstrap.php';

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    // This is a placeholder for Mailchimp testing logic.
    // You would typically instantiate a Mailchimp client and make a simple API call,
    // e.g., get a list of audiences, to verify connectivity and API key validity.

    $mailchimpApiKey = MAILCHIMP_API_KEY ?? null;
    $mailchimpServer = MAILCHIMP_SERVER_PREFIX ?? null; // e.g., 'us1', 'us2'

    if (!$mailchimpApiKey || !$mailchimpServer) {
        throw new Exception("Mailchimp API key or Server Prefix is not configured. Define MAILCHIMP_API_KEY and MAILCHIMP_SERVER_PREFIX.");
    }

    // Example of a basic Mailchimp API call (using cURL directly for simplicity)
    // In a real application, use a dedicated Mailchimp PHP library.
    $ch = curl_init();
    $url = "https://{$mailchimpServer}.api.mailchimp.com/3.0/ping";

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "anystring:{$mailchimpApiKey}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }

    $data = json_decode($result, true);

    // Attempt a basic API call to verify functionality, e.g., get a list of audiences
    $chAudiences = curl_init();
    $urlAudiences = "https://{$mailchimpServer}.api.mailchimp.com/3.0/lists?count=1"; // Get one list to verify
    curl_setopt($chAudiences, CURLOPT_URL, $urlAudiences);
    curl_setopt($chAudiences, CURLOPT_USERPWD, "anystring:{$mailchimpApiKey}");
    curl_setopt($chAudiences, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($chAudiences, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chAudiences, CURLOPT_TIMEOUT, 10);
    $resultAudiences = curl_exec($chAudiences);
    $httpCodeAudiences = curl_getinfo($chAudiences, CURLINFO_HTTP_CODE);
    $curlErrorAudiences = curl_error($chAudiences);
    curl_close($chAudiences);

    if ($curlErrorAudiences) {
        throw new Exception("Mailchimp Audiences cURL Error: " . $curlErrorAudiences);
    }

    $dataAudiences = json_decode($resultAudiences, true);

    if ($httpCode === 200 && isset($data['health_status']) && $data['health_status'] === "Everything's Chimpy!" && $httpCodeAudiences === 200 && isset($dataAudiences['lists'])) {
        $response['success'] = true;
        $response['message'] = "Successfully connected to Mailchimp API (Health: " . htmlspecialchars($data['health_status']) . ", Audiences retrieved: " . count($dataAudiences['lists']) . ").";
    } elseif ($httpCode === 200 && isset($data['health_status']) && $data['health_status'] === "Everything's Chimpy!") {
        $response['success'] = false;
        $response['message'] = "Successfully connected to Mailchimp API (Health: " . htmlspecialchars($data['health_status']) . "), but failed to retrieve audiences.";
    } elseif (isset($data['title']) && isset($data['detail'])) {
        throw new Exception("Mailchimp API Health Error: " . htmlspecialchars($data['title']) . " - " . htmlspecialchars($data['detail']));
    } elseif (isset($dataAudiences['title']) && isset($dataAudiences['detail'])) {
        throw new Exception("Mailchimp API Audiences Error: " . htmlspecialchars($dataAudiences['title']) . " - " . htmlspecialchars($dataAudiences['detail']));
    } else {
        throw new Exception("Unknown response from Mailchimp API (Health HTTP " . $httpCode . ", Audiences HTTP " . $httpCodeAudiences . "): " . $result . " | " . $resultAudiences);
    }

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>