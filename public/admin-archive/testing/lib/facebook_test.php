<?php
// admin/testing/lib/facebook_test.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../lib/bootstrap.php';

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    // This is a placeholder. In a real scenario, you would fetch a valid access token
    // from your database or configuration, associated with an admin or test account.
    // The `dashboard/lib/oauth/facebook.php` might provide clues on how to obtain it.
    $accessToken = FACEBOOK_APP_TOKEN ?? 'YOUR_FACEBOOK_ACCESS_TOKEN'; // Replace with a valid, preferably long-lived, access token

    if ($accessToken === 'YOUR_FACEBOOK_ACCESS_TOKEN') {
        throw new Exception("Facebook access token is not configured. Please replace 'YOUR_FACEBOOK_ACCESS_TOKEN' with a valid token or ensure FACEBOOK_APP_TOKEN is defined.");
    }

    $appId = FACEBOOK_APP_ID ?? null;
    $appSecret = FACEBOOK_APP_SECRET ?? null;

    if (!$appId || !$appSecret) {
        throw new Exception("Facebook App ID or App Secret is not defined in configuration.");
    }

    // Using file_get_contents for a simple GET request
    // In a production environment, use a more robust HTTP client (e.g., Guzzle, cURL)
    $url = "https://graph.facebook.com/v19.0/me?access_token=" . $accessToken;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true, // Get content even if there's an error status
        ],
    ]);

    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        throw new Exception("Failed to connect to Facebook Graph API.");
    }

    $data = json_decode($result, true);

    if (isset($data['id']) && isset($data['name'])) {
        $response['success'] = true;
        $response['message'] = "Successfully connected to Facebook Graph API and retrieved user profile data for: " . htmlspecialchars($data['name']) . " (ID: " . htmlspecialchars($data['id']) . ")";
    } elseif (isset($data['error'])) {
        throw new Exception("Facebook API Error: " . htmlspecialchars($data['error']['message']) . " (Code: " . htmlspecialchars($data['error']['code']) . ")");
    } else {
        throw new Exception("Unknown response from Facebook Graph API: " . $result);
    }

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>