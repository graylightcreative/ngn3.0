<?php

/**
 * API Endpoint: POST /api/v1/retention/push-tokens
 *
 * Register FCM device token for push notifications
 * Requires authentication
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../lib/bootstrap.php';

    use NGN\Lib\Retention\PushNotificationService;
    use NGN\Lib\Http\ConnectionFactory;
    use NGN\Lib\Config;

    // Check authentication
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Initialize service
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);
    $pushService = new PushNotificationService($pdo);

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);

    $fcmToken = $data['fcm_token'] ?? null;
    $platform = $data['platform'] ?? null;
    $deviceName = $data['device_name'] ?? null;

    // Validate required fields
    if (!$fcmToken || !$platform) {
        http_response_code(400);
        echo json_encode(['error' => 'fcm_token and platform are required']);
        exit;
    }

    $validPlatforms = ['ios', 'android', 'web'];
    if (!in_array($platform, $validPlatforms)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid platform. Must be ios, android, or web']);
        exit;
    }

    // Register device token
    $pushService->registerDeviceToken($userId, $fcmToken, $platform);

    // Send test notification to verify
    try {
        $pushService->sendPush(
            $userId,
            'Notifications enabled',
            'You\'re all set! You\'ll now receive push notifications.',
            ['deep_link' => '/settings/notifications']
        );
    } catch (Exception $e) {
        error_log("Error sending test notification: " . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Device token registered',
        'platform' => $platform
    ]);

} catch (Exception $e) {
    error_log("Error in /api/v1/retention/push-tokens: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
