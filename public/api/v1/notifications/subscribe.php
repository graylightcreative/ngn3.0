<?php
/**
 * NGN Web Push Subscription API v1
 * Registers user devices for mobile PWA push notifications.
 */

require_once __DIR__ . '/../../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Notifications\PushNotificationService;

header('Content-Type: application/json');

// Check authentication
@session_start();
$userId = $_SESSION['user_id'] ?? 0;

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$config = new Config();
$pushService = new PushNotificationService($config);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['endpoint']) || empty($input['keys'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid subscription payload']);
            exit;
        }

        $pushService->saveSubscription($userId, $input);
        echo json_encode([
            'status' => 'success',
            'message' => 'Subscription registered for push notifications'
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
