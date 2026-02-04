<?php

/**
 * API Endpoint: GET /api/v1/retention/xp
 *
 * Returns user XP and level data
 * Requires authentication
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../lib/bootstrap.php';

    use NGN\Lib\Retention\RetentionService;
    use NGN\Lib\Http\ConnectionFactory;
    use NGN\Lib\Config;

    // Check authentication
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Initialize service
    $config = Config::getInstance();
    $pdo = ConnectionFactory::write($config);
    $retentionService = new RetentionService($pdo);

    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Get user's XP/level data
            $xpData = $retentionService->getUserLevel($userId);

            http_response_code(200);
            echo json_encode($xpData);
            break;

        case 'POST':
            // Award XP (admin/system use only)
            // TODO: Verify admin privileges

            $data = json_decode(file_get_contents('php://input'), true);
            $amount = $data['amount'] ?? null;
            $source = $data['source'] ?? 'other';

            if (!$amount || $amount <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid amount']);
                exit;
            }

            $retentionService->awardXP($userId, (int)$amount, $source);

            // Get updated data
            $xpData = $retentionService->getUserLevel($userId);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'XP awarded',
                'xp_data' => $xpData
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Error in /api/v1/retention/xp: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
